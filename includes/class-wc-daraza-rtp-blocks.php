<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Daraza_RTP_Blocks extends AbstractPaymentMethodType {
    /**
     * Payment method name/id
     *
     * @var string
     */
    protected $name = 'daraza_rtp';

    /**
     * Initialize settings from WooCommerce.
     */
    public function initialize() {
        $this->settings = get_option( 'woocommerce_daraza_rtp_settings', [] );
    }

    /**
     * Check if payment method is active
     *
     * @return bool
     */
    public function is_active() {
        $is_enabled = isset( $this->settings['enabled'] ) ? 
            $this->settings['enabled'] === 'yes' : false;
        
        return $is_enabled && ! empty( $this->settings['api_key'] );
    }

    /**
     * Return an array of any payment method scripts handles.
     */
    public function get_payment_method_script_handles() {
        wp_register_script(
            'daraza_gateway-blocks-integration',
            plugins_url( '../assets/js/checkout.js', __FILE__ ),
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'daraza_gateway-blocks-integration' );
        }
        return [ 'daraza_gateway-blocks-integration' ];
    }

    /**
     * Get payment method supported features
     *
     * @return string[]
     */
    public function get_supported_features() {
        return [
            'products',
            'refunds',
        ];
    }

    /**
     * Provide data for the block-based checkout.
     * Get payment method data for frontend
     *
     * @return array
     */
    public function get_payment_method_data() {
        return [
            'title'       => $this->settings['title'] ?? __( 'Pay with Daraza', 'daraza-payments' ),
            'description' => $this->settings['description'] ?? __( 'Securely pay using Daraza Pay.', 'daraza-payments' ),
            'supports'    => $this->get_supported_features(),
            'icons'       => $this->get_payment_method_icons(),
            // Add custom field definitions to be used by the block UI.
            'fields'      => [
                [
                    'id'          => 'daraza_phone',
                    'name'        => 'daraza_phone',
                    'type'        => 'tel',
                    'label'       => __( 'Phone Number', 'daraza-payments' ),
                    'placeholder' => __( 'Enter your phone number', 'daraza-payments' ),
                    'pattern'     => '^[0-9]{10,14}$',
                    'required'    => true,
                ],
            ],
        ];
    }    

    /**
     * Get payment method icons
     *
     * @return array
     */
    private function get_payment_method_icons() {
        return [
            'logo' => plugin_dir_url( __FILE__ ) . 'assets/images/daraza-icon.png',
        ];
    }
}