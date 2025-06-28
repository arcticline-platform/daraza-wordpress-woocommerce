<?php

/**
 * Plugin Name: Daraza Payments Gateway
 * Plugin URI: https://daraza.net
 * Description: A plugin to integrate Daraza Payments with WordPress and WooCommerce.
 * Version: 1.2
 * Author: Daraza, AJr.Allan
 * Author URI: https://daraza.net
 * Text Domain: daraza-payments
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Prevent direct file access
if (!defined('WPINC')) {
    die;
}

// Define constants
define('DARAZA_PAYMENTS_VERSION', '1.2');
define('DARAZA_PAYMENTS_DIR', plugin_dir_path(__FILE__));
define('DARAZA_PAYMENTS_URL', plugin_dir_url(__FILE__));
define('DARAZA_PAYMENTS_MIN_WP_VERSION', '5.0');
define('DARAZA_PAYMENTS_MIN_PHP_VERSION', '7.4');

// Include API class
require_once DARAZA_PAYMENTS_DIR . 'includes/class-daraza-api.php';
require_once DARAZA_PAYMENTS_DIR . 'includes/class-daraza-api-key-manager.php';

// Load text domain for translations
add_action('plugins_loaded', 'daraza_payments_load_textdomain');
function daraza_payments_load_textdomain()
{
    load_plugin_textdomain('daraza-payments', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Enqueue plugin assets with proper security
add_action('wp_enqueue_scripts', 'daraza_enqueue_assets');
function daraza_enqueue_assets()
{
    // Only enqueue on pages that need it - simplified check
    if (class_exists('WooCommerce')) {
        // Check if we're on a WooCommerce page
        global $post;
        if (!$post || !in_array($post->post_type, ['page', 'post'])) {
            return;
        }
    }

    wp_enqueue_style('daraza-styles', DARAZA_PAYMENTS_URL . 'assets/css/daraza.css', [], DARAZA_PAYMENTS_VERSION);
    wp_enqueue_script('daraza-scripts', DARAZA_PAYMENTS_URL . 'assets/js/daraza.js', ['jquery'], DARAZA_PAYMENTS_VERSION, true);

    wp_localize_script('daraza-scripts', 'daraza_params', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('daraza_nonce'),
        'security' => wp_create_nonce('daraza_payment_nonce'),
    ]);
}

// Initialize plugin functionality
add_action('plugins_loaded', 'daraza_initialize_plugin');
function daraza_initialize_plugin()
{
    if (class_exists('WooCommerce')) {
        // WooCommerce-specific functionality
        require_once DARAZA_PAYMENTS_DIR . 'includes/class-wc-daraza-rtp-gateway.php';
        require_once DARAZA_PAYMENTS_DIR . 'includes/class-wc-daraza-rtp-blocks.php';

        function daraza_add_woocommerce_gateway($gateways)
        {
            $gateways[] = 'WC_Daraza_RTP_Gateway'; // Add the Request to Pay gateway
            return $gateways;
        }

        /**
         * Custom function to declare compatibility with cart_checkout_blocks feature 
         */
        function declare_checkout_blocks_compatibility()
        {
            // Check if the required class exists
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                // Declare compatibility for 'cart_checkout_blocks'
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
            }
        }

        // Hook the custom function to the 'before_woocommerce_init' action
        add_action('before_woocommerce_init', 'declare_checkout_blocks_compatibility');

        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                // Register an instance of My_Custom_Gateway_Blocks
                $payment_method_registry->register(new WC_Daraza_RTP_Blocks);
            }
        );

        // Additional initialization to ensure gateway is loaded
        function daraza_ensure_gateway_loaded()
        {
            if (class_exists('WC_Payment_Gateway') && !class_exists('WC_Daraza_RTP_Gateway')) {
                require_once plugin_dir_path(__FILE__) . 'includes/class-wc-daraza-rtp-gateway.php';
            }
        }
        add_action('woocommerce_init', 'daraza_ensure_gateway_loaded');

        add_action('woocommerce_checkout_update_order_meta', function ($order_id) {
            if (isset($_POST['daraza_phone']) && wp_verify_nonce($_POST['daraza_phone_nonce'], 'daraza_phone_nonce')) {
                update_post_meta($order_id, '_daraza_phone', sanitize_text_field($_POST['daraza_phone']));
            }
        });

        // ********** Filters **********
        add_filter('woocommerce_checkout_fields', function ($fields) {
            $fields['billing']['daraza_phone'] = [
                'type'        => 'tel',
                'label'       => __('Phone Number', 'daraza-payments'),
                'placeholder' => __('Enter your phone number', 'daraza-payments'),
                'required'    => true,
            ];
            return $fields;
        });

        add_filter('woocommerce_payment_gateways', 'daraza_add_woocommerce_gateway');
    } else {
        // General WordPress-specific functionality
        add_action('admin_menu', 'daraza_add_admin_menu');
    }
}

// Add admin menu for non-WooCommerce users
function daraza_add_admin_menu()
{
    add_menu_page(
        __('Daraza Payments', 'daraza-payments'),
        __('Daraza Payments', 'daraza-payments'),
        'manage_options',
        'daraza-payments',
        'daraza_admin_dashboard',
        'dashicons-money-alt',
        56
    );

    add_submenu_page(
        'daraza-payments',
        __('Remittance', 'daraza-payments'),
        __('Remittance', 'daraza-payments'),
        'manage_options',
        'daraza-remittance',
        'daraza_remittance_page'
    );

    add_submenu_page(
        'daraza-payments',
        __('Wallet Balance', 'daraza-payments'),
        __('Wallet Balance', 'daraza-payments'),
        'manage_options',
        'daraza-wallet-balance',
        'daraza_wallet_balance_page'
    );

    add_submenu_page(
        'daraza-payments', // Parent slug (matches 'menu slug' of parent menu)
        __('API Key Settings', 'daraza-payments'), // Page title
        __('API Key Settings', 'daraza-payments'), // Menu title
        'manage_options', // Capability required to access
        'daraza-api-settings', // Menu slug
        'daraza_api_key_settings_page' // Callback function
    );
}

function daraza_admin_dashboard()
{
    // Ensure only authorized users can access this page
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'daraza-payments'));
    }

    // Verify nonce for security
    if (isset($_GET['_wpnonce']) && !wp_verify_nonce($_GET['_wpnonce'], 'daraza_dashboard_nonce')) {
        wp_die(__('Security check failed.', 'daraza-payments'));
    }

    // Retrieve the API key and wallet balance
    $api_key = Daraza_API_Key_Manager::get_api_key();
    $wallet_balance = false;

    // Show wallet balance only on the first load
    if (!isset($_SESSION['daraza_dashboard_loaded'])) {
        $_SESSION['daraza_dashboard_loaded'] = true;

        if (!empty($api_key)) {
            try {
                $api = new Daraza_API();
                $response = $api->get_wallet_balance();

                if (!empty($response['code']) && $response['code'] === 'Success' && !empty($response['details']['balance'])) {
                    $wallet_balance = $response['details']['balance'];
                } else {
                    $error_message = $response['message'] ?? __('Unknown error occurred.', 'daraza-payments');
                    $wallet_balance = __('Unable to fetch wallet balance: ', 'daraza-payments') . esc_html($error_message);
                }
            } catch (Exception $e) {
                $wallet_balance = __('Error fetching wallet balance: ', 'daraza-payments') . esc_html($e->getMessage());
            }
        } else {
            $wallet_balance = __('API Key is not configured.', 'daraza-payments');
        }
    }

    // Render the dashboard
    echo '<div class="wrap">';
    echo '<h1 style="margin-bottom: 20px;">' . esc_html__('Daraza Payments Dashboard', 'daraza-payments') . '</h1>';
    echo '<p>' . esc_html__('Welcome to Daraza Payments! Manage your payment settings and learn how to get started.', 'daraza-payments') . '</p>';

    // API Key Section
    echo '<div style="background: #f9f9f9; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 6px;">';
    echo '<h2>' . esc_html__('Your API Key', 'daraza-payments') . '</h2>';
    if (!empty($api_key)) {
        echo '<div style="position: relative; display: inline-block; margin-bottom: 10px;">';
        echo '<input type="password" id="daraza_api_key_display" value="' . esc_attr(substr($api_key, 0, 8) . '...') . '" readonly class="regular-text" style="margin-right: 10px;">';
        echo '<button type="button" id="toggle_api_key_visibility" class="button button-secondary">' . esc_html__('Show', 'daraza-payments') . '</button>';
        echo '</div>';
        echo '<p>' . esc_html__('This is your current API key. Keep it secure.', 'daraza-payments') . '</p>';
    } else {
        echo '<p>' . esc_html__('API Key is not configured. Please set it in the settings.', 'daraza-payments') . '</p>';
    }
    echo '</div>';

    // Wallet Balance Section
    if ($wallet_balance !== false) {
        echo '<div style="background: #f9f9f9; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 6px;">';
        echo '<h2>' . esc_html__('Current Wallet Balance', 'daraza-payments') . '</h2>';
        echo '<p><strong>' . esc_html__('Balance:', 'daraza-payments') . '</strong> ' . esc_html($wallet_balance) . '</p>';
        echo '</div>';
    }

    // Getting Started Section
    echo '<div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 6px;">';
    echo '<h2>' . esc_html__('Getting Started with Daraza Payments', 'daraza-payments') . '</h2>';
    echo '<ol>';
    echo '<li>' . esc_html__('Create an account on Daraza to access the payment gateway.', 'daraza-payments') . '</li>';
    echo '<li>' . esc_html__('Navigate to the API section in your Daraza account to generate your first API key.', 'daraza-payments') . '</li>';
    echo '<li>' . esc_html__('Copy the API key and paste it into the Daraza Payments settings page.', 'daraza-payments') . '</li>';
    echo '<li>' . esc_html__('Test your integration with sandbox credentials before going live.', 'daraza-payments') . '</li>';
    echo '</ol>';
    echo '<p>' . sprintf(
        esc_html__('Need help? Check the %s or contact support.', 'daraza-payments'),
        '<a href="https://daraza.net/docs/get_started/" target="_blank" rel="noopener noreferrer">' . esc_html__('Daraza Documentation', 'daraza-payments') . '</a>'
    ) . '</p>';
    echo '</div>';

    // Inline JavaScript for Toggle Functionality
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const apiKeyField = document.getElementById("daraza_api_key_display");
            const toggleButton = document.getElementById("toggle_api_key_visibility");

            toggleButton.addEventListener("click", function() {
                if (apiKeyField.type === "password") {
                    apiKeyField.type = "text";
                    toggleButton.textContent = "' . esc_js(__('Hide', 'daraza-payments')) . '";
                } else {
                    apiKeyField.type = "password";
                    toggleButton.textContent = "' . esc_js(__('Show', 'daraza-payments')) . '";
                }
            });
        });
    </script>';

    echo '</div>';
}

// Wallet Balance Admin Page
function daraza_wallet_balance_page()
{
    // Ensure only authorized users can access this page
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'daraza-payments'));
    }

    // Verify nonce for security
    if (isset($_GET['_wpnonce']) && !wp_verify_nonce($_GET['_wpnonce'], 'daraza_wallet_nonce')) {
        wp_die(__('Security check failed.', 'daraza-payments'));
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Wallet Balance', 'daraza-payments') . '</h1>';

    // Retrieve the API key
    $api_key = Daraza_API_Key_Manager::get_api_key();

    if (empty($api_key)) {
        // Display error if API key is missing
        echo '<div class="notice notice-error">';
        echo '<p>' . esc_html__('API Key is not configured. Please set your API key in the settings.', 'daraza-payments') . '</p>';
        echo '</div>';
        return;
    }

    // Attempt to fetch the wallet balance
    try {
        $api = new Daraza_API();
        $response = $api->get_wallet_balance();

        if (!empty($response['code']) && $response['code'] === 'Success' && !empty($response['details']['balance'])) {
            // Display balance if API call is successful
            echo '<div class="notice notice-success">';
            echo '<p><strong>' . esc_html__('Current Balance:', 'daraza-payments') . '</strong> ' . esc_html($response['details']['balance']) . '</p>';
            echo '</div>';
        } else {
            // Handle API errors or unsuccessful response
            $error_message = !empty($response['message']) ? $response['message'] : __('Unknown error occurred.', 'daraza-payments');
            echo '<div class="notice notice-error">';
            echo '<p>' . esc_html__('Unable to fetch wallet balance. Error:', 'daraza-payments') . ' ' . esc_html($error_message) . '</p>';
            echo '</div>';
        }
    } catch (Exception $e) {
        // Handle exceptions gracefully
        echo '<div class="notice notice-error">';
        echo '<p>' . esc_html__('An error occurred while fetching the wallet balance:', 'daraza-payments') . ' ' . esc_html($e->getMessage()) . '</p>';
        echo '</div>';
    }

    echo '</div>';
}

// Remittance Admin Page
function daraza_remittance_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'daraza-payments'));
    }

    // Rate limiting for remittance requests
    $user_id = get_current_user_id();
    $rate_limit_key = 'daraza_remit_rate_limit_' . $user_id;
    $rate_limit = get_transient($rate_limit_key);

    if ($rate_limit && $rate_limit >= 5) {
        add_settings_error('daraza_remit_messages', 'rate_limit', __('Too many requests. Please wait a few minutes before trying again.', 'daraza-payments'), 'error');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['daraza_remit_submit'])) {
        // Verify nonce
        if (!isset($_POST['daraza_remit_nonce']) || !wp_verify_nonce($_POST['daraza_remit_nonce'], 'daraza_remit_action')) {
            wp_die(__('Nonce verification failed.', 'daraza-payments'));
        }

        // Check rate limiting
        if ($rate_limit && $rate_limit >= 5) {
            add_settings_error('daraza_remit_messages', 'rate_limit', __('Too many requests. Please wait a few minutes before trying again.', 'daraza-payments'), 'error');
        } else {
            // Increment rate limit
            set_transient($rate_limit_key, ($rate_limit ? $rate_limit + 1 : 1), 300); // 5 minutes

            // Sanitize and validate inputs
            $phone = sanitize_text_field($_POST['daraza_remit_phone']);
            $amount = floatval($_POST['daraza_remit_amount']);
            $note = sanitize_textarea_field($_POST['daraza_remit_note']);

            // Enhanced validation
            if (empty($phone) || !preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
                add_settings_error('daraza_remit_messages', 'invalid_phone', __('Invalid phone number format. Please use a valid phone number.', 'daraza-payments'), 'error');
            } elseif ($amount <= 0 || $amount > 1000000) { // Add maximum amount limit
                add_settings_error('daraza_remit_messages', 'invalid_amount', __('Amount must be between 1 and 1,000,000.', 'daraza-payments'), 'error');
            } elseif (strlen($note) > 255) { // Limit note length
                add_settings_error('daraza_remit_messages', 'invalid_note', __('Note is too long. Maximum 255 characters allowed.', 'daraza-payments'), 'error');
            } else {
                // Process the payment
                $api_key = Daraza_API_Key_Manager::get_api_key();
                if (empty($api_key)) {
                    add_settings_error('daraza_remit_messages', 'missing_api_key', __('API Key is not configured.', 'daraza-payments'), 'error');
                } else {
                    try {
                        $api = new Daraza_API();
                        $response = $api->remit_payment($amount, $phone, $note);

                        if (!empty($response['code']) && $response['code'] === 'Success') {
                            // Handle successful remittance
                            $success_message = !empty($response['response_details'])
                                ? $response['response_details']
                                : __('Remittance Successful.', 'daraza-payments');

                            add_settings_error(
                                'daraza_remit_messages',
                                'success',
                                esc_html($success_message),
                                'updated'
                            );
                        } else {
                            // Handle remittance failure
                            $error_message = !empty($response['message'])
                                ? $response['message']
                                : __('Unknown error', 'daraza-payments');

                            add_settings_error(
                                'daraza_remit_messages',
                                'remit_failed',
                                __('Remittance failed: ', 'daraza-payments') . esc_html($error_message),
                                'error'
                            );
                        }
                    } catch (Exception $e) {
                        add_settings_error(
                            'daraza_remit_messages',
                            'exception',
                            __('An error occurred: ', 'daraza-payments') . esc_html($e->getMessage()),
                            'error'
                        );
                    }
                }
            }
        }
    }

    // Display messages
    settings_errors('daraza_remit_messages');

    // Render the form
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Remittance', 'daraza-payments') . '</h1>';
    echo '<form method="post" action="">';
    wp_nonce_field('daraza_remit_action', 'daraza_remit_nonce');
    echo '<p>';
    echo '<label for="daraza_remit_phone">' . esc_html__('Phone Number:', 'daraza-payments') . '</label><br>';
    echo '<input type="text" id="daraza_remit_phone" name="daraza_remit_phone" class="regular-text" pattern="^\+?[0-9]{10,15}$" title="' . esc_attr__('Please enter a valid phone number', 'daraza-payments') . '" required>';
    echo '</p>';
    echo '<p>';
    echo '<label for="daraza_remit_amount">' . esc_html__('Amount:', 'daraza-payments') . '</label><br>';
    echo '<input type="number" id="daraza_remit_amount" name="daraza_remit_amount" min="1" max="1000000" step="0.01" class="regular-text" required>';
    echo '</p>';
    echo '<p>';
    echo '<label for="daraza_remit_note">' . esc_html__('Note:', 'daraza-payments') . '</label><br>';
    echo '<textarea id="daraza_remit_note" name="daraza_remit_note" class="large-text" maxlength="255" required></textarea>';
    echo '</p>';
    echo '<p><button type="submit" name="daraza_remit_submit" class="button-primary">' . esc_html__('Submit', 'daraza-payments') . '</button></p>';
    echo '</form>';
    echo '</div>';
}

// Add settings for API key
add_action('admin_init', 'daraza_register_settings');

function daraza_api_key_settings_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'daraza-payments'));
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['daraza_save_api_key'])) {
        if (!isset($_POST['daraza_api_key_nonce']) || !wp_verify_nonce($_POST['daraza_api_key_nonce'], 'daraza_api_key_save')) {
            wp_die(__('Security check failed.', 'daraza-payments'));
        }

        $api_key = sanitize_text_field($_POST['daraza_api_key']);
        $result = Daraza_API_Key_Manager::save_api_key($api_key);

        if (is_wp_error($result)) {
            add_settings_error(
                'daraza_api_key_messages',
                'daraza_api_key_error',
                $result->get_error_message(),
                'error'
            );
        } else {
            add_settings_error(
                'daraza_api_key_messages',
                'daraza_api_key_saved',
                __('API key saved successfully.', 'daraza-payments'),
                'success'
            );
        }
    }

    // Get key metadata
    $metadata = Daraza_API_Key_Manager::get_key_metadata();
    $needs_rotation = $metadata['needs_rotation'];
    $days_until_expiry = $metadata['days_until_expiry'];

    // Display the form
?>
    <div class="wrap">
        <h1><?php echo esc_html__('Daraza API Key Settings', 'daraza-payments'); ?></h1>

        <?php settings_errors('daraza_api_key_messages'); ?>

        <?php if ($needs_rotation): ?>
            <div class="notice notice-warning">
                <p>
                    <?php
                    if ($days_until_expiry === 0) {
                        echo esc_html__('Your API key has expired. Please update it immediately.', 'daraza-payments');
                    } else {
                        printf(
                            esc_html__('Your API key will expire in %d days. Please update it soon.', 'daraza-payments'),
                            $days_until_expiry
                        );
                    }
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field('daraza_api_key_save', 'daraza_api_key_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="daraza_api_key"><?php echo esc_html__('API Key', 'daraza-payments'); ?></label>
                    </th>
                    <td>
                        <input type="password"
                            id="daraza_api_key"
                            name="daraza_api_key"
                            class="regular-text"
                            required>
                        <p class="description">
                            <?php echo esc_html__('Enter your Daraza API key. The key will be encrypted before storage.', 'daraza-payments'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Key Information', 'daraza-payments'); ?></th>
                    <td>
                        <p>
                            <?php
                            printf(
                                esc_html__('Current key version: %d', 'daraza-payments'),
                                $metadata['version']
                            );
                            ?>
                        </p>
                        <p>
                            <?php
                            printf(
                                esc_html__('Days until expiry: %d', 'daraza-payments'),
                                $days_until_expiry
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit"
                    name="daraza_save_api_key"
                    class="button-primary">
                    <?php echo esc_html__('Save API Key', 'daraza-payments'); ?>
                </button>
            </p>
        </form>
    </div>
<?php
}

function daraza_register_settings()
{
    register_setting('general', 'daraza_api_key', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ]);

    add_settings_field(
        'daraza_api_key',
        __('Daraza API Key', 'daraza-payments'),
        'daraza_api_key_field',
        'general'
    );
}

function daraza_api_key_field()
{
    $value = get_option('daraza_api_key', '');
    echo '<input type="text" id="daraza_api_key" name="daraza_api_key" value="' . esc_attr($value) . '" class="regular-text">';
}

/* *********** Short Codes ****************** */
// Shortcode to display a "Request to Pay" button/form
function daraza_request_to_pay_shortcode($atts)
{
    // Extract attributes (amount and reference can be passed in the shortcode)
    $atts = shortcode_atts([
        'amount' => '10', // Default amount
        'reference' => 'PaymentRef-' . uniqid(), // Default unique reference
    ], $atts, 'daraza_request_to_pay');

    // Validate and sanitize attributes
    $amount = floatval($atts['amount']);
    $reference = sanitize_text_field($atts['reference']);

    // Validate amount
    if ($amount <= 0 || $amount > 1000000) {
        return '<p style="color: red;">' . esc_html__('Invalid amount specified.', 'daraza-payments') . '</p>';
    }

    // Nonce for security
    $nonce = wp_create_nonce('daraza_rtp_nonce');

    // Generate form
    ob_start();
?>
    <style>
        .daraza-payment-container {
            max-width: 400px;
            margin: 20px auto;
            padding: 20px;
            border-radius: 8px;
            background-color: #ffffff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            font-family: Arial, sans-serif;
        }

        .daraza-input {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        .daraza-button {
            display: inline-block;
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            font-size: 16px;
            font-weight: bold;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .daraza-button:hover {
            background: #0056b3;
        }

        .daraza-loading {
            display: none;
            margin-top: 10px;
            font-size: 14px;
            color: #555;
        }

        .daraza-payment-status {
            margin-top: 15px;
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }
    </style>

    <div class="daraza-payment-container">
        <h3><?php echo esc_html__('Complete Your Payment', 'daraza-payments'); ?></h3>
        <p><?php echo esc_html__('Amount:', 'daraza-payments'); ?> <strong><?php echo esc_html(number_format($amount, 2)); ?> UGX</strong></p>
        <p><?php echo esc_html__('Reference:', 'daraza-payments'); ?> <strong><?php echo esc_html($reference); ?></strong></p>

        <form id="daraza-rtp-form">
            <input type="hidden" id="daraza_rtp_amount" value="<?php echo esc_attr($amount); ?>">
            <input type="hidden" id="daraza_rtp_reference" value="<?php echo esc_attr($reference); ?>">
            <input type="hidden" id="daraza_rtp_nonce" value="<?php echo esc_attr($nonce); ?>">

            <input type="tel" id="daraza_rtp_phone" class="daraza-input" placeholder="<?php echo esc_attr__('Enter your phone number', 'daraza-payments'); ?>" pattern="^[0-9]{10,14}$" title="<?php echo esc_attr__('Please enter a valid phone number', 'daraza-payments'); ?>" required>

            <button type="button" id="daraza-request-pay" class="daraza-button">
                <?php echo esc_html__('Pay Now', 'daraza-payments'); ?>
            </button>
        </form>

        <div id="daraza-loading" class="daraza-loading"><?php echo esc_html__('Processing your payment...', 'daraza-payments'); ?></div>
        <div id="daraza-payment-status" class="daraza-payment-status"></div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            $('#daraza-request-pay').on('click', function() {
                let button = $(this);
                let loading = $('#daraza-loading');
                let statusDiv = $('#daraza-payment-status');
                let phone = $('#daraza_rtp_phone').val().trim();

                // Validate phone number
                if (!phone.match(/^\d{10,14}$/)) {
                    statusDiv.css('color', 'red').text('<?php echo esc_js(__('Please enter a valid phone number.', 'daraza-payments')); ?>');
                    return;
                }

                let data = {
                    action: 'daraza_request_to_pay',
                    amount: $('#daraza_rtp_amount').val(),
                    reference: $('#daraza_rtp_reference').val(),
                    phone: phone,
                    nonce: $('#daraza_rtp_nonce').val(),
                };

                // Disable button & show loading
                button.prop('disabled', true).text('<?php echo esc_js(__('Processing...', 'daraza-payments')); ?>');
                loading.show();
                statusDiv.text('');

                $.post('<?php echo admin_url("admin-ajax.php"); ?>', data, function(response) {
                    if (response.success) {
                        statusDiv.css('color', 'green').html(response.message);
                    } else {
                        statusDiv.css('color', 'red').html(response.message || '<?php echo esc_js(__('Payment failed. Please try again.', 'daraza-payments')); ?>');
                    }
                }).fail(function() {
                    statusDiv.css('color', 'red').html('<?php echo esc_js(__('Error processing payment. Please check your connection.', 'daraza-payments')); ?>');
                }).always(function() {
                    button.prop('disabled', false).text('<?php echo esc_js(__('Pay Now', 'daraza-payments')); ?>');
                    loading.hide();
                });
            });
        });
    </script>
<?php
    return ob_get_clean();
}

// Shortcode to display a Daraza Request to Pay checkout form
function daraza_checkout_shortcode($atts)
{
    // Set up a nonce for security
    $nonce = wp_create_nonce('daraza_checkout_nonce');

    ob_start(); ?>
    <style>
        .daraza-payment-container {
            max-width: 400px;
            margin: 20px auto;
            padding: 20px;
            border-radius: 8px;
            background-color: #ffffff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            font-family: Arial, sans-serif;
        }

        .daraza-input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        .daraza-label {
            display: block;
            text-align: left;
            font-weight: bold;
            margin-top: 15px;
        }

        .daraza-button {
            display: inline-block;
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            font-size: 16px;
            font-weight: bold;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .daraza-button:hover {
            background: #0056b3;
        }

        .daraza-loading {
            display: none;
            margin-top: 10px;
            font-size: 14px;
            color: #555;
        }

        .daraza-response {
            margin-top: 15px;
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }
    </style>

    <div class="daraza-payment-container">
        <h3><?php echo esc_html__('Complete Your Payment', 'daraza-payments'); ?></h3>
        <form id="daraza-checkout-form" method="post">
            <label for="daraza_checkout_phone" class="daraza-label"><?php echo esc_html__('Phone Number:', 'daraza-payments'); ?></label>
            <input type="text" name="daraza_checkout_phone" id="daraza_checkout_phone" class="daraza-input" pattern="^[0-9]{10,14}$" title="<?php echo esc_attr__('Please enter a valid phone number', 'daraza-payments'); ?>" placeholder="<?php echo esc_attr__('Enter your phone number', 'daraza-payments'); ?>" required>

            <label for="daraza_checkout_amount" class="daraza-label"><?php echo esc_html__('Amount:', 'daraza-payments'); ?></label>
            <input type="number" name="daraza_checkout_amount" id="daraza_checkout_amount" class="daraza-input" min="1" max="1000000" step="0.01" placeholder="<?php echo esc_attr__('Enter amount', 'daraza-payments'); ?>" required>

            <label for="daraza_checkout_reference" class="daraza-label"><?php echo esc_html__('Reference:', 'daraza-payments'); ?></label>
            <input type="text" name="daraza_checkout_reference" id="daraza_checkout_reference" class="daraza-input" maxlength="100" placeholder="<?php echo esc_attr__('Enter reference', 'daraza-payments'); ?>" required>

            <input type="hidden" name="daraza_checkout_nonce" value="<?php echo esc_attr($nonce); ?>">

            <button type="submit" id="daraza-checkout-submit" class="daraza-button"><?php echo esc_html__('Pay Now', 'daraza-payments'); ?></button>
        </form>
        <div id="daraza-loading" class="daraza-loading"><?php echo esc_html__('Processing your payment...', 'daraza-payments'); ?></div>
        <div id="daraza-checkout-response" class="daraza-response"></div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            $('#daraza-checkout-form').on('submit', function(e) {
                e.preventDefault();
                var data = {
                    action: 'daraza_checkout_payment',
                    nonce: $('input[name="daraza_checkout_nonce"]').val(),
                    phone: $('#daraza_checkout_phone').val(),
                    amount: $('#daraza_checkout_amount').val(),
                    reference: $('#daraza_checkout_reference').val()
                };

                $('#daraza-loading').show();
                $('#daraza-checkout-response').html('');
                $.post('<?php echo admin_url("admin-ajax.php"); ?>', data, function(response) {
                    if (response.status === 'success') {
                        $('#daraza-checkout-response').html('<p style="color: green;">' + response.message + '</p>');
                    } else {
                        $('#daraza-checkout-response').html('<p style="color: red;">' + response.message + '</p>');
                    }
                }).fail(function() {
                    $('#daraza-checkout-response').html('<p style="color: red;"><?php echo esc_js(__('An error occurred. Please try again.', 'daraza-payments')); ?></p>');
                }).always(function() {
                    $('#daraza-loading').hide();
                });
            });
        });
    </script>
<?php
    return ob_get_clean();
}

// Handle AJAX Request to Pay
function daraza_handle_request_to_pay()
{
    // Verify nonce
    check_ajax_referer('daraza_rtp_nonce', 'nonce');

    // Rate limiting
    $ip = $_SERVER['REMOTE_ADDR'];
    $rate_limit_key = 'daraza_rtp_rate_limit_' . md5($ip);
    $rate_limit = get_transient($rate_limit_key);

    if ($rate_limit && $rate_limit >= 10) {
        wp_send_json_error(['message' => __('Too many requests. Please wait a few minutes.', 'daraza-payments')]);
        return;
    }

    // Increment rate limit
    set_transient($rate_limit_key, ($rate_limit ? $rate_limit + 1 : 1), 300); // 5 minutes

    // Validate and sanitize inputs
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $reference = isset($_POST['reference']) ? sanitize_text_field($_POST['reference']) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';

    // Enhanced validation
    if ($amount <= 0 || $amount > 1000000) {
        wp_send_json_error(['message' => __('Invalid amount. Must be between 1 and 1,000,000.', 'daraza-payments')]);
        return;
    }

    if (empty($reference) || strlen($reference) > 100) {
        wp_send_json_error(['message' => __('Invalid reference. Must be between 1 and 100 characters.', 'daraza-payments')]);
        return;
    }

    if (empty($phone) || !preg_match('/^[0-9]{10,14}$/', $phone)) {
        wp_send_json_error(['message' => __('Invalid phone number format.', 'daraza-payments')]);
        return;
    }

    try {
        // Get API key
        $api_key = Daraza_API_Key_Manager::get_api_key();
        if (empty($api_key)) {
            wp_send_json_error(['message' => __('API Key is not configured.', 'daraza-payments')]);
            return;
        }

        $api = new Daraza_API();
        $response = $api->request_to_pay($amount, $phone, $reference);

        if (!empty($response['status']) && $response['status'] === 'Success') {
            wp_send_json_success(['message' => __('Payment request sent successfully.', 'daraza-payments')]);
        } else {
            $error_message = $response['message'] ?? __('Payment request failed.', 'daraza-payments');
            wp_send_json_error(['message' => $error_message]);
        }
    } catch (Exception $e) {
        wp_send_json_error(['message' => __('An error occurred while processing your request.', 'daraza-payments')]);
    }
}

add_action('wp_ajax_daraza_request_to_pay', 'daraza_handle_request_to_pay');
add_action('wp_ajax_nopriv_daraza_request_to_pay', 'daraza_handle_request_to_pay');

function daraza_handle_checkout_payment()
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'daraza_checkout_nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'daraza-payments')]);
        return;
    }

    // Rate limiting
    $ip = $_SERVER['REMOTE_ADDR'];
    $rate_limit_key = 'daraza_checkout_rate_limit_' . md5($ip);
    $rate_limit = get_transient($rate_limit_key);

    if ($rate_limit && $rate_limit >= 10) {
        wp_send_json_error(['message' => __('Too many requests. Please wait a few minutes.', 'daraza-payments')]);
        return;
    }

    // Increment rate limit
    set_transient($rate_limit_key, ($rate_limit ? $rate_limit + 1 : 1), 300); // 5 minutes

    // Validate and sanitize inputs
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $reference = isset($_POST['reference']) ? sanitize_text_field($_POST['reference']) : '';

    // Enhanced validation
    if (empty($phone) || !preg_match('/^[0-9]{10,14}$/', $phone)) {
        wp_send_json_error(['message' => __('Please enter a valid phone number.', 'daraza-payments')]);
        return;
    }

    if ($amount <= 0 || $amount > 1000000) {
        wp_send_json_error(['message' => __('Please enter a valid amount between 1 and 1,000,000.', 'daraza-payments')]);
        return;
    }

    if (empty($reference) || strlen($reference) > 100) {
        wp_send_json_error(['message' => __('Please enter a valid reference (1-100 characters).', 'daraza-payments')]);
        return;
    }

    try {
        // Retrieve the API key saved in settings
        $api_key = Daraza_API_Key_Manager::get_api_key();
        if (empty($api_key)) {
            wp_send_json_error(['message' => __('API Key not configured.', 'daraza-payments')]);
            return;
        }

        $api = new Daraza_API();
        $response = $api->request_to_pay($amount, $phone, $reference);

        // Return full response details
        if (!empty($response['status']) && strtolower($response['status']) === 'success') {
            wp_send_json_success(array_merge(['status' => 'success'], $response));
        } else {
            $error_message = $response['message'] ?? __('Payment failed. Please try again.', 'daraza-payments');
            wp_send_json_error(array_merge(['status' => 'error', 'message' => $error_message], $response));
        }
    } catch (Exception $e) {
        wp_send_json_error(['message' => __('An error occurred while processing your payment.', 'daraza-payments')]);
    }
}

add_action('wp_ajax_daraza_checkout_payment', 'daraza_handle_checkout_payment');
add_action('wp_ajax_nopriv_daraza_checkout_payment', 'daraza_handle_checkout_payment');

// Shortcode Registration
add_shortcode('daraza_request_to_pay', 'daraza_request_to_pay_shortcode');
add_shortcode('daraza_checkout', 'daraza_checkout_shortcode');
add_shortcode('daraza_payments', 'daraza_checkout_shortcode');
