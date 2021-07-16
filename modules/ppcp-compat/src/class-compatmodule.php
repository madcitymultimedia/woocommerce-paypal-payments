<?php
/**
 * The compatibility module.
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Class CompatModule
 */
class CompatModule implements ModuleInterface {

	/**
	 * Setup the compatibility module.
	 *
	 * @return ServiceProviderInterface
	 */
	public function setup(): ServiceProviderInterface {
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	/**
	 * Run the compatibility module.
	 *
	 * @param ContainerInterface|null $container The Container.
	 */
	public function run( ContainerInterface $container ): void {

		if ( $this->is_ppec_available() ) {
			return;
		}

		// Process PPEC subscription renewals through PayPal Payments.
		if ( apply_filters( 'woocommerce_paypal_payments_process_legacy_subscriptions', true ) ) {
			$handler = $container->get( 'compat.ppec.subscriptions-handler' );
			$handler->maybe_hook();
		}

	}

	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}

	/**
	 * Checks whether the PayPal (Express) Checkout extension is active and configured.
	 *
	 * @return boolean
	 */
	private function is_ppec_available() {
		return is_callable( 'wc_gateway_ppec' ) && wc_gateway_ppec()->settings->get_active_api_credentials();
	}

}
