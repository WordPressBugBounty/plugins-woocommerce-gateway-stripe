<?php

use Automattic\WooCommerce\Enums\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles Boleto payment method.
 *
 * @extends WC_Gateway_Stripe
 *
 * @since 5.8.0
 */
class WC_Gateway_Stripe_Boleto extends WC_Stripe_Payment_Gateway_Voucher {

	/**
	 * ID used by UPE
	 *
	 * @var string
	 */
	const ID = 'stripe_boleto';

	/**
	 * ID used by WooCommerce to identify the payment method
	 *
	 * @var string
	 */
	public $id = 'stripe_boleto';

	/**
	 * ID used by stripe
	 */
	protected $stripe_id = WC_Stripe_Payment_Methods::BOLETO;

	/**
	 * List of accepted currencies
	 *
	 * @var array
	 */
	protected $supported_currencies = [ WC_Stripe_Currency_Code::BRAZILIAN_REAL ];

	/**
	 * List of accepted countries
	 */
	protected $supported_countries = [ 'BR' ];

	/**
	 * Constructor
	 *
	 * @since 5.8.0
	 */
	public function __construct() {
		$this->method_title = __( 'Stripe Boleto', 'woocommerce-gateway-stripe' );
		parent::__construct();

		add_filter( 'wc_stripe_allowed_payment_processing_statuses', [ $this, 'add_allowed_payment_processing_statuses' ], 10, 2 );
	}

	/**
	 * Add payment gateway voucher expiration to API request body.
	 *
	 * @param array $body API request body.
	 * @return array
	 */
	protected function update_request_body_on_create_or_update_payment_intent( $body ) {
		$body['payment_method_options'] = [
			WC_Stripe_Payment_Methods::BOLETO => [
				'expires_after_days' => $this->get_option( 'expiration' ),
			],
		];
		return $body;
	}

	/**
	 * Add payment gateway voucher expiration.
	 *
	 * @param array $settings Settings array.
	 * @return array
	 */
	public function get_unique_settings( $settings = [] ) {
		$settings[ $this->id . '_expiration' ] = $this->get_option( 'expiration' );
		return $settings;
	}

	/**
	 * Updates payment gateway voucher expiration.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return void
	 *
	 * @deprecated 9.6.0 The customization of individual payment methods is now deprecated.
	 */
	public function update_unique_settings( WP_REST_Request $request ) {
		$field_name = $this->id . '_expiration';
		$expiration = $request->get_param( $field_name );

		if ( null === $expiration ) {
			return;
		}

		$value = absint( $expiration );
		$value = min( 60, $value );
		$value = max( 0, $value );
		$this->update_option( 'expiration', $value );
	}

	/**
	 * Adds on-hold as accepted status during webhook handling on orders paid with voucher
	 *
	 * @param $allowed_statuses
	 * @param $order
	 *
	 * @return mixed
	 */
	public function add_allowed_payment_processing_statuses( $allowed_statuses, $order ) {
		if ( $this->stripe_id === $order->get_meta( '_stripe_upe_payment_type' ) && ! in_array( OrderStatus::ON_HOLD, $allowed_statuses ) ) {
			$allowed_statuses[] = OrderStatus::ON_HOLD;
		}

		return $allowed_statuses;
	}

	/**
	 * Payment_scripts function.
	 *
	 * @since 5.8.0
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! parent::is_valid_pay_for_order_endpoint() && ! is_add_payment_method_page() ) {
			return;
		}

		parent::payment_scripts();
		wp_enqueue_script( 'jquery-mask', plugins_url( 'assets/js/jquery.mask.min.js', WC_STRIPE_MAIN_FILE ), [], WC_STRIPE_VERSION );
	}

	/**
	 * Payment form on checkout page
	 *
	 * @since 5.8.0
	 */
	public function payment_fields() {
		$description = $this->get_description();
		apply_filters( 'wc_stripe_description', wp_kses_post( $description ), $this->id )

		?>
		<label>CPF/CNPJ: <abbr class="required" title="required">*</abbr></label><br>
		<input id="stripe_boleto_tax_id" name="stripe_boleto_tax_id" type="text"><br><br>
		<div class="stripe-source-errors" role="alert"></div>

		<div id="stripe-boleto-payment-data"><?php echo wp_kses( wpautop( $description ), [ 'p' => [] ] ); ?></div>
		<?php
	}

	/**
	 * Validates the minimum and maximum amount. Throws exception when out of range value is added
	 *
	 * @since 5.8.0
	 *
	 * @param $amount
	 *
	 * @throws WC_Stripe_Exception
	 */
	protected function validate_amount_limits( $amount ) {

		if ( $amount < 5.00 ) {
			/* translators: 1) amount (including currency symbol) */
			throw new WC_Stripe_Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( 5.00 ) ) );
		} elseif ( $amount > 49999.99 ) {
			/* translators: 1) amount (including currency symbol) */
			throw new WC_Stripe_Exception( sprintf( __( 'Sorry, the maximum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( 49999.99 ) ) );
		}
	}

	/**
	 * Gather the data necessary to confirm the payment via javascript
	 * Override this when extending the class
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	protected function get_confirm_payment_data( $order ) {
		return [
			'payment_method' => [
				WC_Stripe_Payment_Methods::BOLETO => [
					'tax_id' => isset( $_POST['stripe_boleto_tax_id'] ) ? wc_clean( wp_unslash( $_POST['stripe_boleto_tax_id'] ) ) : null,
				],
				'billing_details'                 => [
					'name'    => $order->get_formatted_billing_full_name(),
					'email'   => $order->get_billing_email(),
					'address' => [
						'line1'       => $order->get_billing_address_1(),
						'city'        => $order->get_billing_city(),
						'state'       => $order->get_billing_state(),
						'postal_code' => $order->get_billing_postcode(),
						'country'     => $order->get_billing_country(),
					],
				],
			],
		];
	}
}
