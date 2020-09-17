<?php
/**
 * The PayPal API Exception.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Exception
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Exception;

/**
 * Class PayPalApiException
 */
class PayPalApiException extends RuntimeException {

	/**
	 * The JSON response object of PayPal.
	 *
	 * @var \stdClass
	 */
	private $response;

	/**
	 * The HTTP status code of the PayPal response.
	 *
	 * @var int
	 */
	private $status_code;

	/**
	 * PayPalApiException constructor.
	 *
	 * @param \stdClass|null $response The JSON object.
	 * @param int            $status_code The HTTP status code.
	 */
	public function __construct( \stdClass $response = null, int $status_code = 0 ) {
		if ( is_null( $response ) ) {
			$response = new \stdClass();
		}
		if ( ! isset( $response->message ) ) {
			$response->message = __(
				'Unknown error while connecting to PayPal.',
				'paypal-payments-for-woocommerce'
			);
		}
		if ( ! isset( $response->name ) ) {
			$response->name = __( 'Error', 'paypal-payments-for-woocommerce' );
		}
		if ( ! isset( $response->details ) ) {
			$response->details = array();
		}
		if ( ! isset( $response->links ) || ! is_array( $response->links ) ) {
			$response->links = array();
		}

		/**
		 * The JSON response object.
		 *
		 * @var \stdClass $response
		 */
		$this->response    = $response;
		$this->status_code = $status_code;
		$message           = $response->message;
		if ( $response->name ) {
			$message = '[' . $response->name . '] ' . $message;
		}
		foreach ( $response->links as $link ) {
			if ( isset( $link->rel ) && 'information_link' === $link->rel ) {
				$message .= ' ' . $link->href;
			}
		}
		parent::__construct( $message, $status_code );
	}

	/**
	 * The name of the exception.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->response->name;
	}

	/**
	 * The details of the Exception.
	 *
	 * @return array
	 */
	public function details(): array {
		return $this->response->details;
	}

	/**
	 * Whether a certain detail is part of the exception reason.
	 *
	 * @param string $issue The issue.
	 *
	 * @return bool
	 */
	public function has_detail( string $issue ): bool {
		foreach ( $this->details() as $detail ) {
			if ( isset( $detail->issue ) && $detail->issue === $issue ) {
				return true;
			}
		}
		return false;
	}
}
