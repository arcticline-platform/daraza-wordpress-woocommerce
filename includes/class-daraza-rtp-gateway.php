<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Daraza_RTP_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'daraza_rtp';
        $this->method_title = __('Daraza Request to Pay', 'daraza-payments');
        $this->method_description = __('Accept payments using Daraza Request to Pay.', 'daraza-payments');

        $this->has_fields = true;

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        // Save settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'daraza-payments'),
                'type' => 'checkbox',
                'label' => __('Enable Daraza Request to Pay', 'daraza-payments'),
                'default' => 'yes',
            ],
            'title' => [
                'title' => __('Title', 'daraza-payments'),
                'type' => 'text',
                'default' => __('Request to Pay', 'daraza-payments'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'daraza-payments'),
                'type' => 'textarea',
                'default' => __('Securely pay using Daraza Request to Pay.', 'daraza-payments'),
            ],
        ];
    }

    public function payment_fields() {
        ?>
        <p><?php echo esc_html($this->description); ?></p>
        <label for="daraza_phone"><?php _e('Phone Number', 'daraza-payments'); ?>:</label>
        <input type="text" id="daraza_phone" name="daraza_phone" required>
        <?php
    }

    public function validate_fields() {
        if (empty($_POST['daraza_phone'])) {
            wc_add_notice(__('Phone number is required for payment.', 'daraza-payments'), 'error');
            return false;
        }
        return true;
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $phone = sanitize_text_field($_POST['daraza_phone']);
        $amount = $order->get_total();
        $reference = 'Order-' . $order_id;

        // Get API Key
        $api_key = get_option('daraza_api_key');
        $api = new Daraza_API($api_key);

        // Send Request to Pay
        $response = $api->request_to_pay($amount, $phone, $reference);

        if (!empty($response['status']) && $response['status'] === 'success') {
            // Mark order as on-hold until payment confirmation
            $order->update_status('on-hold', __('Waiting for payment confirmation.', 'daraza-payments'));

            // Reduce stock levels
            wc_reduce_stock_levels($order_id);

            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        } else {
            wc_add_notice(__('Payment request failed: ', 'daraza-payments') . esc_html($response['message'] ?? 'Unknown error'), 'error');
            return ['result' => 'fail'];
        }
    }
}
