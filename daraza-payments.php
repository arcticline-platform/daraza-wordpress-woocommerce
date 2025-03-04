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
        require_once DARAZA_PAYMENTS_DIR . 'includes/class-daraza-rtp-gateway.php';

        add_filter('woocommerce_payment_gateways', 'daraza_add_woocommerce_gateway');

        function daraza_add_woocommerce_gateway($gateways) {
            $gateways[] = 'Daraza_Gateway';
            $gateways[] = 'WC_Daraza_RTP_Gateway'; // Add the Request to Pay gateway
            
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



// Wallet Balance Admin Page
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

// Remittance Admin Page
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

/* *********** Short Codes ****************** */
// Shortcode to display a "Request to Pay" button/form
function daraza_request_to_pay_shortcode($atts) {
    // Extract attributes (amount and reference can be passed in the shortcode)
    $atts = shortcode_atts([
        'amount' => '10', // Default amount
        'reference' => 'PaymentRef-' . uniqid(), // Default unique reference
    ], $atts, 'daraza_request_to_pay');

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
        <h3>Complete Your Payment</h3>
        <p>Amount: <strong><?php echo esc_html($atts['amount']); ?> UGX</strong></p>
        <p>Reference: <strong><?php echo esc_html($atts['reference']); ?></strong></p>
        
        <form id="daraza-rtp-form">
            <input type="hidden" id="daraza_rtp_amount" value="<?php echo esc_attr($atts['amount']); ?>">
            <input type="hidden" id="daraza_rtp_reference" value="<?php echo esc_attr($atts['reference']); ?>">
            <input type="hidden" id="daraza_rtp_nonce" value="<?php echo esc_attr($nonce); ?>">

            <input type="tel" id="daraza_rtp_phone" class="daraza-input" placeholder="Enter your phone number" required>

            <button type="button" id="daraza-request-pay" class="daraza-button">
                Pay Now
            </button>
        </form>
        
        <div id="daraza-loading" class="daraza-loading">Processing your payment...</div>
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
            if (!phone.match(/^\d{10,12}$/)) {
                statusDiv.css('color', 'red').text('Please enter a valid phone number.');
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
            button.prop('disabled', true).text('Processing...');
            loading.show();
            statusDiv.text('');

            $.post('<?php echo admin_url("admin-ajax.php"); ?>', data, function(response) {
                if (response.success) {
                    statusDiv.css('color', 'green').html(response.message);
                } else {
                    statusDiv.css('color', 'red').html(response.message || 'Payment failed. Please try again.');
                }
            }).fail(function() {
                statusDiv.css('color', 'red').html('Error processing payment. Please check your connection.');
            }).always(function() {
                button.prop('disabled', false).text('Pay Now');
                loading.hide();
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// Shortcode to display a Daraza Request to Pay checkout form
// Shortcode to display a Daraza Request to Pay checkout form
function daraza_checkout_shortcode($atts) {
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
        <h3><?php _e('Complete Your Payment', 'daraza-payments'); ?></h3>
        <form id="daraza-checkout-form" method="post">
            <label for="daraza_checkout_phone" class="daraza-label"><?php _e('Phone Number:', 'daraza-payments'); ?></label>
            <input type="text" name="daraza_checkout_phone" id="daraza_checkout_phone" class="daraza-input" placeholder="<?php _e('Enter your phone number', 'daraza-payments'); ?>" required>

            <label for="daraza_checkout_amount" class="daraza-label"><?php _e('Amount:', 'daraza-payments'); ?></label>
            <input type="number" name="daraza_checkout_amount" id="daraza_checkout_amount" class="daraza-input" min="1" step="0.01" placeholder="<?php _e('Enter amount', 'daraza-payments'); ?>" required>

            <label for="daraza_checkout_reference" class="daraza-label"><?php _e('Reference:', 'daraza-payments'); ?></label>
            <input type="text" name="daraza_checkout_reference" id="daraza_checkout_reference" class="daraza-input" placeholder="<?php _e('Enter reference', 'daraza-payments'); ?>" required>

            <input type="hidden" name="daraza_checkout_nonce" value="<?php echo esc_attr($nonce); ?>">

            <button type="submit" id="daraza-checkout-submit" class="daraza-button"><?php _e('Pay Now', 'daraza-payments'); ?></button>
        </form>
        <div id="daraza-loading" class="daraza-loading"><?php _e('Processing your payment...', 'daraza-payments'); ?></div>
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
                if(response.status === 'success'){
                    $('#daraza-checkout-response').html('<p style="color: green;">' + response.message + '</p>');
                } else {
                    $('#daraza-checkout-response').html('<p style="color: red;">' + response.message + '</p>');
                }
            }).fail(function() {
                $('#daraza-checkout-response').html('<p style="color: red;"><?php _e('An error occurred. Please try again.', 'daraza-payments'); ?></p>');
            }).always(function() {
                $('#daraza-loading').hide();
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('daraza_payments', 'daraza_checkout_shortcode');

// Handle AJAX Request to Pay
function daraza_handle_request_to_pay() {
    // Verify nonce
    check_ajax_referer('daraza_rtp_nonce', 'nonce');

    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $reference = isset($_POST['reference']) ? sanitize_text_field($_POST['reference']) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';

    if ($amount <= 0 || empty($reference) || empty($phone)) {
        wp_send_json(['status' => 'error', 'message' => __('Invalid payment details.', 'daraza-payments')]);
    }

    // Get API key
    $api_key = get_option('daraza_api_key');
    $api = new Daraza_API($api_key);

    // Send Request to Pay including the phone number
    $response = $api->request_to_pay($amount, $phone, $reference);

    if (!empty($response['status']) && $response['status'] === 'Success') {
        wp_send_json(['status' => 'success', 'message' => __('Payment request sent successfully.', 'daraza-payments')]);
    } else {
        wp_send_json(['status' => 'error', 'message' => __('Payment request failed.', 'daraza-payments')]);
    }

    // Return the complete API response
    // if (!empty($response['status']) && $response['status'] === 'Success') {
    //     wp_send_json(array_merge(['status' => 'success'], $response));
    // } else {
    //     wp_send_json(array_merge(['status' => 'error'], $response));
    // }
}

add_action('wp_ajax_daraza_request_to_pay', 'daraza_handle_request_to_pay');
add_action('wp_ajax_nopriv_daraza_request_to_pay', 'daraza_handle_request_to_pay');


function daraza_handle_checkout_payment() {
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'daraza_checkout_nonce' ) ) {
        wp_send_json(['status' => 'error', 'message' => __('Security check failed.', 'daraza-payments')]);
    }

    $phone     = sanitize_text_field($_POST['phone']);
    $amount    = floatval($_POST['amount']);
    $reference = sanitize_text_field($_POST['reference']);

    if ( empty($phone) || $amount <= 0 || empty($reference) ) {
        wp_send_json(['status' => 'error', 'message' => __('Please fill in all required fields.', 'daraza-payments')]);
    }

    // Retrieve the API key saved in settings
    $api_key = get_option('daraza_api_key');
    if ( empty($api_key) ) {
        wp_send_json(['status' => 'error', 'message' => __('API Key not configured.', 'daraza-payments')]);
    }

    $api = new Daraza_API($api_key);
    $response = $api->request_to_pay($amount, $phone, $reference);

    // Return full response details
    if ( ! empty($response['status']) && strtolower($response['status']) === 'success' ) {
        wp_send_json(array_merge(['status' => 'success'], $response));
    } else {
        wp_send_json(array_merge(['status' => 'error'], $response));
    }
}
add_action('wp_ajax_daraza_checkout_payment', 'daraza_handle_checkout_payment');
add_action('wp_ajax_nopriv_daraza_checkout_payment', 'daraza_handle_checkout_payment');


// Shortcode Registration
add_shortcode('daraza_request_to_pay', 'daraza_request_to_pay_shortcode');
add_shortcode('daraza_checkout', 'daraza_checkout_shortcode');