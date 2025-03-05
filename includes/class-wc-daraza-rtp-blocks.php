<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Daraza_RTP_Blocks extends AbstractPaymentMethodType {

    protected $name = 'daraza_rtp';

    /**
     * Initialize settings from WooCommerce.
     */
    public function initialize() {
        $this->settings = get_option( 'woocommerce_daraza_rtp_settings', [] );
    }

    /**
     * Check if the payment method is active.
     */
    public function is_active() {
        return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
    }

    /**
     * Return an array of any payment method scripts handles.
     */
    public function get_payment_method_script_handles() {
        return [];
    }

    /**
     * Provide data for the block-based checkout.
     */
    public function get_payment_method_data() {
        return [
            'title'       => $this->settings['title'] ?? __( 'Pay with Daraza', 'daraza-payments' ),
            'description' => $this->settings['description'] ?? __( 'Securely pay using Daraza Request to Pay.', 'daraza-payments' ),
        ];
    }
}