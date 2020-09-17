<?php
/**
 * Handles subscription renewals.
 *
 * @package WooCommerce\PayPalCommerce\Subscription
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Subscription;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Subscription\Repository\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Psr\Log\LoggerInterface;

/**
 * Class RenewalHandler
 */
class RenewalHandler {

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * The payment token repository.
	 *
	 * @var PaymentTokenRepository
	 */
	private $repository;

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $order_endpoint;

	/**
	 * The purchase unit factory.
	 *
	 * @var PurchaseUnitFactory
	 */
	private $purchase_unit_factory;

	/**
	 * The payer factory.
	 *
	 * @var PayerFactory
	 */
	private $payer_factory;

	/**
	 * RenewalHandler constructor.
	 *
	 * @param LoggerInterface        $logger The logger.
	 * @param PaymentTokenRepository $repository The payment token repository.
	 * @param OrderEndpoint          $order_endpoint The order endpoint.
	 * @param PurchaseUnitFactory    $purchase_unit_factory The purchase unit factory.
	 * @param PayerFactory           $payer_factory The payer factory.
	 */
	public function __construct(
		LoggerInterface $logger,
		PaymentTokenRepository $repository,
		OrderEndpoint $order_endpoint,
		PurchaseUnitFactory $purchase_unit_factory,
		PayerFactory $payer_factory
	) {

		$this->logger                = $logger;
		$this->repository            = $repository;
		$this->order_endpoint        = $order_endpoint;
		$this->purchase_unit_factory = $purchase_unit_factory;
		$this->payer_factory         = $payer_factory;
	}

	/**
	 * Renew an order.
	 *
	 * @param \WC_Order $wc_order The WooCommerce order.
	 */
	public function renew( \WC_Order $wc_order ) {

		$this->logger->log(
			'info',
			sprintf(
				// translators: %d is the id of the order.
				__( 'Start moneytransfer for order %d', 'paypal-payments-for-woocommerce' ),
				(int) $wc_order->get_id()
			),
			array(
				'order' => $wc_order,
			)
		);

		try {
			$this->process_order( $wc_order );
		} catch ( \Exception $error ) {
			$this->logger->log(
				'error',
				sprintf(
					// translators: %1$d is the order number, %2$s the error message.
					__(
						'An error occured while trying to renew the subscription for order %1$d: %2$s',
						'paypal-payments-for-woocommerce'
					),
					(int) $wc_order->get_id(),
					$error->getMessage()
				),
				array(
					'order' => $wc_order,
				)
			);
			\WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $wc_order );
			return;
		}
		$this->logger->log(
			'info',
			sprintf(
				// translators: %d is the order number.
				__(
					'Moneytransfer for order %d is completed.',
					'paypal-payments-for-woocommerce'
				),
				(int) $wc_order->get_id()
			),
			array(
				'order' => $wc_order,
			)
		);
	}

	/**
	 * Process a WooCommerce order.
	 *
	 * @param \WC_Order $wc_order The WooCommerce order.
	 *
	 * @throws \Exception If customer cannot be read/found.
	 */
	private function process_order( \WC_Order $wc_order ) {

		$user_id  = (int) $wc_order->get_customer_id();
		$customer = new \WC_Customer( $user_id );
		$token    = $this->get_token_for_customer( $customer, $wc_order );
		if ( ! $token ) {
			return;
		}
		$purchase_unit = $this->purchase_unit_factory->from_wc_order( $wc_order );
		$payer         = $this->payer_factory->from_customer( $customer );
		$order         = $this->order_endpoint->create(
			array( $purchase_unit ),
			$payer,
			$token,
			(string) $wc_order->get_id()
		);
		$this->capture_order( $order, $wc_order );
	}

	/**
	 * Returns a payment token for a customer.
	 *
	 * @param \WC_Customer $customer The customer.
	 * @param \WC_Order    $wc_order The current WooCommerce order we want to process.
	 *
	 * @return PaymentToken|null
	 */
	private function get_token_for_customer( \WC_Customer $customer, \WC_Order $wc_order ) {

		$token = $this->repository->for_user_id( (int) $customer->get_id() );
		if ( ! $token ) {
			$this->logger->log(
				'error',
				sprintf(
					// translators: %d is the customer id.
					__(
						'No payment token found for customer %d',
						'paypal-payments-for-woocommerce'
					),
					(int) $customer->get_id()
				),
				array(
					'customer' => $customer,
					'order'    => $wc_order,
				)
			);
			\WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $wc_order );
		}
		return $token;
	}

	/**
	 * If the PayPal order is captured/authorized the WooCommerce order gets updated accordingly.
	 *
	 * @param Order     $order The PayPal order.
	 * @param \WC_Order $wc_order The related WooCommerce order.
	 */
	private function capture_order( Order $order, \WC_Order $wc_order ) {

		if ( $order->intent() === 'CAPTURE' && $order->status()->is( OrderStatus::COMPLETED ) ) {
			$wc_order->update_status(
				'processing',
				__( 'Payment received.', 'paypal-payments-for-woocommerce' )
			);
			\WC_Subscriptions_Manager::process_subscription_payments_on_order( $wc_order );
		}

		if ( $order->intent() === 'AUTHORIZE' ) {
			$this->order_endpoint->authorize( $order );
			$wc_order->update_meta_data( PayPalGateway::CAPTURED_META_KEY, 'false' );
			\WC_Subscriptions_Manager::process_subscription_payments_on_order( $wc_order );
		}
	}
}
