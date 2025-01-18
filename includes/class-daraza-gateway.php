<?php

class Daraza_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'daraza';
        $this->method_title = __('Daraza Payments', 'daraza-payments');
        $this->method_description = __('Pay securely using Daraza Payments.', 'daraza-payments');
        $this->supports = ['products'];

        // Initialize settings
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->api_key = $this->get_option('api_key');
        $this->logging_enabled = $this->get_option('logging') === 'yes';

        // Save settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'daraza-payments'),
                'type' => 'checkbox',
                'label' => __('Enable Daraza Payments', 'daraza-payments'),
                'default' => 'yes',
            ],
            'title' => [
                'title' => __('Title', 'daraza-payments'),
                'type' => 'text',
                'description' => __('This controls the title displayed during checkout.', 'daraza-payments'),
                'desc_tip' => true,
                'default' => __('Daraza Payments', 'daraza-payments'),
            ],
            'description' => [
                'title' => __('Description', 'daraza-payments'),
                'type' => 'textarea',
                'description' => __('Details about the payment method displayed during checkout.', 'daraza-payments'),
                'desc_tip' => true,
                'default' => __('Pay securely using Daraza.', 'daraza-payments'),
            ],
            'api_key' => [
                'title' => __('API Key', 'daraza-payments'),
                'type' => 'password',
                'description' => __('Your Daraza API key.', 'daraza-payments'),
                'desc_tip' => true,
            ],
            'logging' => [
                'title' => __('Enable Logging', 'daraza-payments'),
                'type' => 'checkbox',
                'label' => __('Log API requests and responses for debugging purposes.', 'daraza-payments'),
                'default' => 'no',
            ],
        ];
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        // Validate API key
        if (empty($this->api_key)) {
            wc_add_notice(__('Payment error: Missing API key.', 'daraza-payments'), 'error');
            return ['result' => 'failure'];
        }

        // Get order details
        $amount = $order->get_total();
        $phone = $order->get_billing_phone();
        $note = sprintf(__('Order #%s via Daraza Payments', 'daraza-payments'), $order_id);

        // Log request details (optional)
        if ($this->logging_enabled) {
            $this->log_info("Initiating payment for Order #{$order_id}. Amount: {$amount}, Phone: {$phone}");
        }

        // Process payment via API
        $api = new Daraza_API($this->api_key);
        $response = $api->request_to_pay($amount, $phone, $note);

        // Handle successful response
        if (isset($response['status']) && $response['status'] === 'success') {
            $order->payment_complete();
            $order->add_order_note(__('Payment successful via Daraza.', 'daraza-payments'));

            // Log success
            if ($this->logging_enabled) {
                $this->log_info("Payment successful for Order #{$order_id}.");
            }

            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        }

        // Handle failure response
        $error_message = $response['message'] ?? __('Payment failed. Please try again.', 'daraza-payments');
        wc_add_notice(__('Payment error: ', 'daraza-payments') . $error_message, 'error');

        // Log error
        if ($this->logging_enabled) {
            $this->log_error("Payment failed for Order #{$order_id}. Error: {$error_message}");
        }

        return ['result' => 'failure'];
    }

    /**
     * Log informational messages for debugging.
     *
     * @param string $message Log message.
     */
    private function log_info($message) {
        if ($this->logging_enabled) {
            $logger = wc_get_logger();
            $logger->info("Daraza Gateway: {$message}", ['source' => $this->id]);
        }
    }

    /**
     * Log error messages for debugging.
     *
     * @param string $message Error message.
     */
    private function log_error($message) {
        if ($this->logging_enabled) {
            $logger = wc_get_logger();
            $logger->error("Daraza Gateway: {$message}", ['source' => $this->id]);
        }
    }
}
