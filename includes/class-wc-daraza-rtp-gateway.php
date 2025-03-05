<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly to prevent direct script access
}

/**
 * WooCommerce Daraza Request to Pay Payment Gateway
 *
 * Provides a secure payment gateway integration for Daraza Request to Pay
 *
 * @class WC_Daraza_RTP_Gateway
 * @extends WC_Payment_Gateway
 */
class WC_Daraza_RTP_Gateway extends WC_Payment_Gateway {
    /**
     * Constructor for the gateway
     */
    public function __construct() {
        $this->id                 = 'daraza_rtp';
        $this->method_title       = __( 'Daraza Pay', 'daraza-payments' );
        $this->method_description = __( 'Accept secure payments using Daraza Request to Pay service.', 'daraza-payments' );
        $this->has_fields         = true; // Enable custom checkout fields
        $this->supports           = [
            'products',
            'refunds',
        ];

        // Initialize settings
        $this->init_form_fields();
        $this->init_settings();

        // Load gateway settings
        $this->configure_gateway_settings();

        // Add hooks
        $this->add_gateway_hooks();
    }

    /**
     * Configure gateway settings from options
     */
    private function configure_gateway_settings() {
        $this->enabled     = $this->get_option( 'enabled', 'no' );
        $this->title       = $this->get_option( 'title', __( 'Pay with Daraza', 'daraza-payments' ) );
        $this->description = $this->get_option( 'description', __( 'Securely pay using Daraza Request to Pay.', 'daraza-payments' ) );
        $this->api_key     = $this->get_option( 'api_key' );
        $this->logging_enabled = $this->get_option( 'logging', 'no' ) === 'yes';
    }

    /**
     * Add necessary WordPress/WooCommerce hooks
     */
    private function add_gateway_hooks() {
        // Save admin options
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
    }

    /**
     * Define settings fields for the gateway
     */
    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => __( 'Enable/Disable', 'daraza-payments' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Daraza Request to Pay', 'daraza-payments' ),
                'default' => 'no',
            ],
            'title' => [
                'title'       => __( 'Payment Method Title', 'daraza-payments' ),
                'type'        => 'text',
                'description' => __( 'Title displayed to customers during checkout.', 'daraza-payments' ),
                'default'     => __( 'Pay with Daraza', 'daraza-payments' ),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __( 'Payment Method Description', 'daraza-payments' ),
                'type'        => 'textarea',
                'description' => __( 'Description shown to customers during checkout.', 'daraza-payments' ),
                'default'     => __( 'Securely pay using Daraza Request to Pay.', 'daraza-payments' ),
            ],
            'api_key' => [
                'title'       => __( 'API Key', 'daraza-payments' ),
                'type'        => 'password',
                'description' => __( 'Enter your Daraza API key. Keep this confidential.', 'daraza-payments' ),
                'desc_tip'    => true,
            ],
            'logging' => [
                'title'       => __( 'Enable Logging', 'daraza-payments' ),
                'type'        => 'checkbox',
                'label'       => __( 'Log API interactions for troubleshooting', 'daraza-payments' ),
                'default'     => 'no',
                'description' => __( 'Logs will be stored in WooCommerce system logs.', 'daraza-payments' ),
            ],
        ];
    }

    /**
     * Render custom payment fields at checkout
     */
    public function payment_fields() {
        // Display gateway description
        if ( $this->description ) {
            echo wp_kses_post( wpautop( $this->description ) );
        }

        // Phone number input field
        woocommerce_form_field( 'daraza_phone', [
            'type'        => 'tel',
            'label'       => __( 'Phone Number', 'daraza-payments' ),
            'placeholder' => __( 'Enter your phone number', 'daraza-payments' ),
            'required'    => true,
            'input_class' => [ 'form-control' ],
            'custom_attributes' => [
                'pattern'     => '^[0-9]{10,14}$', // Basic phone number validation
                'title'       => __( 'Please enter a valid phone number', 'daraza-payments' ),
            ],
        ] );
    }

    /**
     * Validate checkout fields
     *
     * @return bool
     */
    public function validate_fields() {
        $phone = isset( $_POST['daraza_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['daraza_phone'] ) ) : '';

        if ( empty( $phone ) ) {
            wc_add_notice( __( 'Phone number is required to complete the payment.', 'daraza-payments' ), 'error' );
            return false;
        }

        // Additional phone number validation
        if ( ! preg_match( '/^[0-9]{10,14}$/', $phone ) ) {
            wc_add_notice( __( 'Invalid phone number format.', 'daraza-payments' ), 'error' );
            return false;
        }

        return true;
    }

    /**
     * Process the payment
     *
     * @param int $order_id WooCommerce order ID
     * @return array
     */
    public function process_payment( $order_id ) {
        // Retrieve order and phone details
        $order = wc_get_order( $order_id );
        $phone = sanitize_text_field( wp_unslash( $_POST['daraza_phone'] ) );
        $amount = $order->get_total();
        $reference = 'Order-' . $order_id;

        // Validate API key
        if ( empty( $this->api_key ) ) {
            $this->log_error( 'Payment failed: API key is not configured.' );
            wc_add_notice( __( 'Payment setup error. Please contact site administrator.', 'daraza-payments' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        try {
            // Use dependency injection for better testability
            $api = $this->get_daraza_api();
            $response = $api->request_to_pay( $amount, $phone, $reference );

            // Process API response
            return $this->handle_payment_response( $order, $response );
        } catch ( Exception $e ) {
            $this->log_error( 'Payment process exception: ' . $e->getMessage() );
            wc_add_notice( __( 'Payment processing failed. Please try again.', 'daraza-payments' ), 'error' );
            return [ 'result' => 'failure' ];
        }
    }

    /**
     * Handle payment gateway API response
     *
     * @param WC_Order $order
     * @param array $response
     * @return array
     */
    private function handle_payment_response( $order, $response ) {
        if ( ! empty( $response['status'] ) && $response['status'] === 'success' ) {
            $order->update_status( 'on-hold', __( 'Awaiting Daraza payment confirmation.', 'daraza-payments' ) );
            wc_reduce_stock_levels( $order->get_id() );

            $this->log_info( 'Payment request successful for Order #' . $order->get_id() );

            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            ];
        }

        $error_message = $response['message'] ?? __( 'Payment request failed.', 'daraza-payments' );
        $this->log_error( 'Payment request failed: ' . $error_message );
        wc_add_notice( __( 'Payment error: ', 'daraza-payments' ) . esc_html( $error_message ), 'error' );

        return [ 'result' => 'failure' ];
    }

    /**
     * Dependency injection method for Daraza API
     * 
     * @return Daraza_API
     */
    protected function get_daraza_api() {
        return new Daraza_API( $this->api_key );
    }

    /**
     * Log informational messages
     *
     * @param string $message Log message
     */
    private function log_info( $message ) {
        if ( $this->logging_enabled ) {
            $logger = wc_get_logger();
            $logger->info( "Daraza Gateway: {$message}", [ 'source' => $this->id ] );
        }
    }

    /**
     * Log error messages
     *
     * @param string $message Error message
     */
    private function log_error( $message ) {
        if ( $this->logging_enabled ) {
            $logger = wc_get_logger();
            $logger->error( "Daraza Gateway: {$message}", [ 'source' => $this->id ] );
        }
    }
}