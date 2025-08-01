<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * WooCommerce Daraza Request to Pay Payment Gateway
 *
 * Provides a secure payment gateway integration for Daraza Request to Pay.
 */
class WC_Daraza_RTP_Gateway extends WC_Payment_Gateway
{

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->id                 = 'daraza_rtp';
        $this->method_title       = __('Daraza Pay', 'daraza-payments');
        $this->method_description = __('Accept secure payments using the Daraza Request to Pay service. This gateway enables you to process payments securely by integrating with Daraza\'s API. To get started, log in to your Daraza account at <a href="https://daraza.net/accounts/user_account/" target="_blank">https://daraza.net/accounts/user_account</a>, create a business profile and then an app to get a new API key if you haven\'t done so already. Once you have your API key, enter it in the plugin settings here. This integration supports standard products, refunds, and block-based checkout, ensuring a seamless experience for both you and your customers.', 'daraza-payments');
        $this->icon               = ''; // URL to the gateway icon
        $this->has_fields         = true; // Enable custom checkout fields
        $this->supports           = [
            'products',
            'refunds',
            'block', // Declare support for block-based checkout
        ];

        // Initialize settings
        $this->init_form_fields();
        $this->init_settings();

        // Load gateway settings
        $this->configure_gateway_settings();

        // Add hooks for admin and processing.
        $this->add_gateway_hooks();

        // Always ensure this gateway is available.
        add_filter('woocommerce_payment_gateway_is_available', [$this, 'is_gateway_available'], 10, 2);

        // Register for block-based checkout.
        add_action('woocommerce_blocks_loaded', [$this, 'register_block_gateway']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        add_action('woocommerce_checkout_update_order_meta', [$this, 'daraza_save_phone_number_to_order_meta']);
    }

    /**
     * Register payment method for WooCommerce Blocks.
     */
    public function register_block_gateway()
    {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {

            // Ensure the blocks integration file is loaded
            $blocks_integration_file = plugin_dir_path(__FILE__) . 'includes/daraza-rtp-blocks.php';
            if (file_exists($blocks_integration_file)) {
                require_once $blocks_integration_file;
            }

            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function ($payment_method_registry) {
                    $payment_method_registry->register(
                        new WC_Daraza_RTP_Blocks()
                    );
                }
            );
        }
    }

    /**
     * Save phone number to order meta
     *
     * @param int $order_id WooCommerce order ID
     */
    public function daraza_save_phone_number_to_order_meta($order_id)
    {
        // Verify nonce for security
        if (!isset($_POST['daraza_phone_nonce']) || !wp_verify_nonce($_POST['daraza_phone_nonce'], 'daraza_phone_nonce')) {
            return;
        }

        if (isset($_POST['daraza_phone'])) {
            $phone = sanitize_text_field($_POST['daraza_phone']);
            // Validate phone number before saving
            if (preg_match('/^[0-9]{10,14}$/', $phone)) {
                update_post_meta($order_id, '_daraza_rtp_phone', $phone);
            }
        }
    }


    /**
     * Custom availability check.
     */
    public function is_gateway_available($is_available, $gateway)
    {
        if ($gateway->id === $this->id) {
            return true;
        }
        return $is_available;
    }

    /**
     * Override the is_available method to ensure it shows up.
     */
    public function is_available()
    {
        return true;
    }

    /**
     * Configure gateway settings from options.
     */
    private function configure_gateway_settings()
    {
        $this->enabled         = $this->get_option('enabled', 'no');
        $this->title           = $this->get_option('title', __('Pay with Daraza', 'daraza-payments'));
        $this->description     = $this->get_option('description', __('Securely pay using Daraza Request to Pay.', 'daraza-payments'));
        $this->api_key         = $this->get_option('api_key');
        $this->logging_enabled = $this->get_option('logging', 'no') === 'yes';
    }

    /**
     * Add necessary hooks.
     */
    private function add_gateway_hooks()
    {
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    /**
     * Define settings fields for the gateway.
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Enable/Disable', 'daraza-payments'),
                'type'    => 'checkbox',
                'label'   => __('Enable Daraza Request to Pay', 'daraza-payments'),
                'default' => 'no',
            ],
            'title' => [
                'title'       => __('Payment Method Title', 'daraza-payments'),
                'type'        => 'text',
                'description' => __('Title displayed to customers during checkout.', 'daraza-payments'),
                'default'     => __('Pay with Daraza', 'daraza-payments'),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __('Payment Method Description', 'daraza-payments'),
                'type'        => 'textarea',
                'description' => __('Description shown to customers during checkout.', 'daraza-payments'),
                'default'     => __('Securely pay using Daraza Request to Pay.', 'daraza-payments'),
            ],
            'api_key' => [
                'title'       => __('API Key', 'daraza-payments'),
                'type'        => 'password',
                'description' => __('Enter your Daraza API key. Keep this confidential. To obtain your API key, log into your Daraza account at <a href="https://dashboard.daraza.com" target="_blank">https://dashboard.daraza.com</a>. Once logged in, create a business profile and then an app to get a new API key if you haven\'t done so already', 'daraza-payments'),
                'default'     => '',
            ],
            'logging' => [
                'title'       => __('Enable Logging', 'daraza-payments'),
                'type'        => 'checkbox',
                'label'       => __('Log API interactions for troubleshooting', 'daraza-payments'),
                'default'     => 'no',
                'description' => __('Logs will be stored in WooCommerce system logs.', 'daraza-payments'),
            ],
        ];
    }

    /**
     * Render custom payment fields at checkout
     */
    public function payment_fields()
    {
        // Display gateway description
        if ($this->description) {
            echo wp_kses_post(wpautop($this->description));
        }

        // Phone number input field
        woocommerce_form_field('daraza_phone', [
            'type'        => 'tel',
            'label'       => __('Phone Number', 'daraza-payments'),
            'placeholder' => __('Enter your mobile money phone number', 'daraza-payments'),
            'required'    => true,
            'input_class' => ['form-control'],
            'custom_attributes' => [
                'pattern'     => '^[0-9]{10,14}$', // Basic phone number validation
                'title'       => __('Please enter a valid phone number', 'daraza-payments'),
            ],
        ]);
    }

    /**
     * Validate checkout fields
     *
     * @return bool
     */
    public function validate_fields()
    {
        // error_log('Payment Form Data: ' . print_r($_POST, true)); // Debugging
        // error_log('---- WooCommerce Checkout Debug ----');
        // error_log('POST Data: ' . print_r($_POST, true));
        // error_log('Payment Method Data: ' . print_r($_POST['payment_method_data'] ?? [], true));

        // Check for phone in payment_method_data (block-based checkout) first.
        $phone = '';
        if (isset($_POST['payment_method_data']['daraza_phone'])) {
            $phone = sanitize_text_field(wp_unslash($_POST['payment_method_data']['daraza_phone']));
        } elseif (isset($_POST['daraza_phone'])) {
            $phone = sanitize_text_field(wp_unslash($_POST['daraza_phone']));
        }

        if (empty($phone)) {
            wc_add_notice(__('Phone number is required to complete the payment.', 'daraza-payments'), 'error');
            return false;
        }

        if (! preg_match('/^[0-9]{10,14}$/', $phone)) {
            wc_add_notice(__('Invalid phone number format.', 'daraza-payments'), 'error');
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
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        // Retrieve phone from payment_method_data (block-based checkout) if available.
        $phone = '';
        if (isset($_POST['payment_method_data']['daraza_phone'])) {
            $phone = sanitize_text_field(wp_unslash($_POST['payment_method_data']['daraza_phone']));
        } elseif (isset($_POST['daraza_phone'])) {
            $phone = sanitize_text_field(wp_unslash($_POST['daraza_phone']));
        }

        $amount = $order->get_total();
        $reference = 'Order-' . $order_id;

        // Validate API key
        if (empty($this->api_key)) {
            $this->log_error('Payment failed: API key is not configured.');
            wc_add_notice(__('Payment setup error. Please contact site administrator.', 'daraza-payments'), 'error');
            return ['result' => 'failure'];
        }

        try {
            // Use dependency injection for better testability
            $api = $this->get_daraza_api();
            $response = $api->request_to_pay($amount, $phone, $reference);

            // Process API response
            return $this->handle_payment_response($order, $response);
        } catch (Exception $e) {
            $this->log_error('Payment process exception: ' . $e->getMessage());
            wc_add_notice(__('Payment processing failed. Please try again.', 'daraza-payments'), 'error');
            return ['result' => 'failure'];
        }
    }

    /**
     * Process refund for the given order.
     *
     * @param int    $order_id Order ID.
     * @param float  $amount   Refund amount.
     * @param string $reason   Reason for the refund.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);

        // Validate API key
        if (empty($this->api_key)) {
            $this->log_error('Refund failed: API key is not configured.');
            return new WP_Error('error', __('Refund setup error. Please contact site administrator.', 'daraza-payments'));
        }

        // Retrieve the phone number from order meta.
        $phone = get_post_meta($order_id, '_daraza_rtp_phone', true);
        if (empty($phone)) {
            $this->log_error('Refund failed: Phone number not found for Order #' . $order_id);
            return new WP_Error('refund_error', __('Refund error: Phone number not found for this order.', 'daraza-payments'));
        }

        try {
            // Use dependency injection to get the API instance
            $api = $this->get_daraza_api();
            // Call remit_payment for refund: pass the refund amount, phone, and note (reason)
            $response = $api->remit_payment($amount, $phone, $reason);

            if (isset($response['status']) && $response['status'] === 'success') {
                $this->log_info('Refund successful for Order #' . $order_id);
                return true;
            }

            $error_message = $response['message'] ?? __('Refund request failed.', 'daraza-payments');
            $this->log_error('Refund failed for Order #' . $order_id . ': ' . $error_message);
            return new WP_Error('refund_error', __('Refund error: ', 'daraza-payments') . esc_html($error_message));
        } catch (Exception $e) {
            $this->log_error('Refund process exception for Order #' . $order_id . ': ' . $e->getMessage());
            return new WP_Error('refund_exception', __('Refund processing failed. Please try again.', 'daraza-payments'));
        }
    }

    /**
     * Handle payment gateway API response
     *
     * @param WC_Order $order
     * @param array $response
     * @return array
     */
    private function handle_payment_response($order, $response)
    {
        if (! empty($response['status']) && $response['status'] === 'success') {
            $order->update_status('on-hold', __('Awaiting Daraza payment confirmation.', 'daraza-payments'));
            wc_reduce_stock_levels($order->get_id());

            $this->log_info('Payment request successful for Order #' . $order->get_id());

            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        }

        $error_message = $response['message'] ?? __('Payment request failed.', 'daraza-payments');
        $this->log_error('Payment request failed: ' . $error_message);
        wc_add_notice(__('Payment error: ', 'daraza-payments') . esc_html($error_message), 'error');

        return ['result' => 'failure'];
    }

    /**
     * Dependency injection method for Daraza API
     * 
     * @return Daraza_API
     */
    protected function get_daraza_api()
    {
        return new Daraza_API($this->api_key);
    }

    /**
     * Log informational messages
     *
     * @param string $message Log message
     */
    private function log_info($message)
    {
        if ($this->logging_enabled) {
            $logger = wc_get_logger();
            $logger->info("Daraza Gateway: {$message}", ['source' => $this->id]);
        }
    }

    /**
     * Log error messages
     *
     * @param string $message Error message
     */
    private function log_error($message)
    {
        if ($this->logging_enabled) {
            $logger = wc_get_logger();
            $logger->error("Daraza Gateway: {$message}", ['source' => $this->id]);
        }
    }
}
