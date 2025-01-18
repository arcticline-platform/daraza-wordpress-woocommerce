<?php
/**
 * Plugin Name: Daraza Payments Gateway
 * Plugin URI: https://daraza.net
 * Description: A plugin to integrate Daraza Payments with WordPress and WooCommerce.
 * Version: 1.0
 * Author: Daraza, AJr.Allan
 * Author URI: https://daraza.net
 * Text Domain: daraza-payments
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define constants
define('DARAZA_PAYMENTS_VERSION', '1.2');
define('DARAZA_PAYMENTS_DIR', plugin_dir_path(__FILE__));
define('DARAZA_PAYMENTS_URL', plugin_dir_url(__FILE__));

// Include API class
require_once DARAZA_PAYMENTS_DIR . 'includes/class-daraza-api.php';

// Load text domain for translations
add_action('plugins_loaded', 'daraza_payments_load_textdomain');
function daraza_payments_load_textdomain() {
    load_plugin_textdomain('daraza-payments', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Enqueue plugin assets
add_action('wp_enqueue_scripts', 'daraza_enqueue_assets');
function daraza_enqueue_assets() {
    wp_enqueue_style('daraza-styles', DARAZA_PAYMENTS_URL . 'assets/css/daraza.css', [], DARAZA_PAYMENTS_VERSION);
    wp_enqueue_script('daraza-scripts', DARAZA_PAYMENTS_URL . 'assets/js/daraza.js', ['jquery'], DARAZA_PAYMENTS_VERSION, true);

    wp_localize_script('daraza-scripts', 'daraza_params', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('daraza_nonce'),
    ]);
}

// Initialize plugin functionality
add_action('plugins_loaded', 'daraza_initialize_plugin');
function daraza_initialize_plugin() {
    if (class_exists('WooCommerce')) {
        // WooCommerce-specific functionality
        require_once DARAZA_PAYMENTS_DIR . 'includes/class-daraza-gateway.php';

        add_filter('woocommerce_payment_gateways', 'daraza_add_woocommerce_gateway');
        function daraza_add_woocommerce_gateway($gateways) {
            $gateways[] = 'Daraza_Gateway';
            return $gateways;
        }
    } else {
        // General WordPress-specific functionality
        add_action('admin_menu', 'daraza_add_admin_menu');
    }
}

// Add admin menu for non-WooCommerce users
function daraza_add_admin_menu() {
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

function daraza_admin_dashboard() {
    // Ensure only authorized users can access this page
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'daraza-payments'));
    }

    // Retrieve the API key and wallet balance
    $api_key = get_option('daraza_api_key', '');
    $wallet_balance = false;

    // Show wallet balance only on the first load
    if (!isset($_SESSION['daraza_dashboard_loaded'])) {
        $_SESSION['daraza_dashboard_loaded'] = true;

        if (!empty($api_key)) {
            try {
                $api = new Daraza_API($api_key);
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
        echo '<input type="password" id="daraza_api_key_display" value="' . esc_attr($api_key) . '" readonly class="regular-text" style="margin-right: 10px;">';
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
        '<a href="https://daraza.net/docs/get_started/" target="_blank">' . esc_html__('Daraza Documentation', 'daraza-payments') . '</a>'
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



// Wallet balance page
function daraza_wallet_balance_page() {
    // Ensure only authorized users can access this page
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'daraza-payments'));
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Wallet Balance', 'daraza-payments') . '</h1>';

    // Retrieve the API key
    $api_key = get_option('daraza_api_key');

    if (empty($api_key)) {
        // Display error if API key is missing
        echo '<div class="notice notice-error">';
        echo '<p>' . esc_html__('API Key is not configured. Please set your API key in the settings.', 'daraza-payments') . '</p>';
        echo '</div>';
        return;
    }

    // Attempt to fetch the wallet balance
    try {
        $api = new Daraza_API($api_key);
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

// Remittance page
function daraza_remittance_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'daraza-payments'));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['daraza_remit_submit'])) {
        // Verify nonce
        if (!isset($_POST['daraza_remit_nonce']) || !wp_verify_nonce($_POST['daraza_remit_nonce'], 'daraza_remit_action')) {
            wp_die(__('Nonce verification failed.', 'daraza-payments'));
        }

        // Sanitize and validate inputs
        $phone = sanitize_text_field($_POST['daraza_remit_phone']);
        $amount = floatval($_POST['daraza_remit_amount']);
        $note = sanitize_text_field($_POST['daraza_remit_note']);

        if (empty($phone) || !preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
            add_settings_error('daraza_remit_messages', 'invalid_phone', __('Invalid phone number.', 'daraza-payments'), 'error');
        } elseif ($amount <= 0) {
            add_settings_error('daraza_remit_messages', 'invalid_amount', __('Amount must be greater than zero.', 'daraza-payments'), 'error');
        } else {
            // Process the payment
            $api_key = get_option('daraza_api_key');
            if (empty($api_key)) {
                add_settings_error('daraza_remit_messages', 'missing_api_key', __('API Key is not configured.', 'daraza-payments'), 'error');
            } else {
                $api = new Daraza_API($api_key);
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
            }
        }
    }

    // Display messages
    settings_errors('daraza_remit_messages');

    // Render the form
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Remittance', 'daraza-payments') . '</h1>';
    echo '<form method="post">';
    wp_nonce_field('daraza_remit_action', 'daraza_remit_nonce');
    echo '<p>';
    echo '<label for="daraza_remit_phone">' . esc_html__('Phone Number:', 'daraza-payments') . '</label><br>';
    echo '<input type="text" id="daraza_remit_phone" name="daraza_remit_phone" class="regular-text" required>';
    echo '</p>';
    echo '<p>';
    echo '<label for="daraza_remit_amount">' . esc_html__('Amount:', 'daraza-payments') . '</label><br>';
    echo '<input type="number" id="daraza_remit_amount" name="daraza_remit_amount" min="1" step="0.01" class="regular-text" required>';
    echo '</p>';
    echo '<p>';
    echo '<label for="daraza_remit_note">' . esc_html__('Note:', 'daraza-payments') . '</label><br>';
    echo '<textarea id="daraza_remit_note" name="daraza_remit_note" class="large-text" required></textarea>';
    echo '</p>';
    echo '<p><button type="submit" name="daraza_remit_submit" class="button-primary">' . esc_html__('Submit', 'daraza-payments') . '</button></p>';
    echo '</form>';
    echo '</div>';
}


function daraza_api_key_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'daraza-payments'));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['daraza_save_api_key'])) {
        // Verify nonce
        if (!isset($_POST['daraza_api_key_nonce']) || !wp_verify_nonce($_POST['daraza_api_key_nonce'], 'daraza_api_key_save')) {
            wp_die(__('Nonce verification failed.', 'daraza-payments'));
        }

        // Save the API key
        $api_key = sanitize_text_field($_POST['daraza_api_key']);
        update_option('daraza_api_key', $api_key);
        add_settings_error(
            'daraza_api_key_messages',
            'daraza_api_key_saved',
            __('API Key saved successfully.', 'daraza-payments'),
            'updated'
        );
    }

    // Show settings errors
    settings_errors('daraza_api_key_messages');

    // Get the saved API key
    $api_key = get_option('daraza_api_key', '');

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Daraza API Key Settings', 'daraza-payments') . '</h1>';
    echo '<form method="post">';
    wp_nonce_field('daraza_api_key_save', 'daraza_api_key_nonce');
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="daraza_api_key">' . esc_html__('API Key', 'daraza-payments') . '</label></th>';
    echo '<td><input type="text" id="daraza_api_key" name="daraza_api_key" value="' . esc_attr($api_key) . '" class="regular-text" required></td>';
    echo '</tr>';
    echo '</table>';
    echo '<p class="submit"><button type="submit" name="daraza_save_api_key" class="button-primary">' . esc_html__('Save API Key', 'daraza-payments') . '</button></p>';
    echo '</form>';
    echo '</div>';
}


// Add settings for API key
add_action('admin_init', 'daraza_register_settings');
function daraza_register_settings() {
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

function daraza_api_key_field() {
    $value = get_option('daraza_api_key', '');
    echo '<input type="text" id="daraza_api_key" name="daraza_api_key" value="' . esc_attr($value) . '" class="regular-text">';
}