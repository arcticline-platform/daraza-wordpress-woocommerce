<?php

class Daraza_API {
    private $api_key;
    private $logger;

    // Define API endpoints
    private $endpoints = [
        'remit' => 'https://daraza.net/api/remit/',
        'request_to_pay' => 'https://daraza.net/api/request_to_pay/',
        'balance' => 'https://daraza.net/api/app_wallet/balance/',
        'transfer' => 'https://daraza.net/api/app_wallet/transfer/',
    ];

    public function __construct($api_key) {
        $this->api_key = $api_key;

        // Check if WooCommerce logger is available
        if (class_exists('WooCommerce') && function_exists('wc_get_logger')) {
            $this->logger = wc_get_logger();
        } else {
            $this->logger = null; // No logger available, fallback to error_log
        }
    }

    /**
     * Retrieve wallet balance.
     *
     * @return array API response.
     */
    public function get_wallet_balance() {
        if (empty($this->api_key)) {
            return $this->handle_missing_api_key('balance');
        }

        $response = wp_remote_get($this->endpoints['balance'], [
            'headers' => [
                'Authorization' => 'Api-Key ' . $this->api_key,
            ],
            'timeout' => 45,
        ]);

        return $this->process_response($response, 'balance');
    }

    /**
     * Transfer a percentage of the wallet balance.
     *
     * @param float $percentage Percentage to transfer.
     * @return array API response.
     */
    public function transfer_balance($percentage) {
        if (empty($this->api_key)) {
            return $this->handle_missing_api_key('transfer');
        }

        $response = wp_remote_post($this->endpoints['transfer'], [
            'headers' => [
                'Authorization' => 'Api-Key ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode(['percentage' => $percentage]),
            'timeout' => 45,
        ]);

        return $this->process_response($response, 'transfer');
    }

    /**
     * Process request to pay.
     *
     * @param float $amount Amount for request_to_pay.
     * @param string $phone Recipient's phone number.
     * @param string $note Payment note.
     * @return array API response.
     */
    public function request_to_pay($amount, $phone, $note) {
        if (empty($this->api_key)) {
            return $this->handle_missing_api_key('request_to_pay');
        }

        $response = wp_remote_post($this->endpoints['request_to_pay'], [
            'headers' => [
                'Authorization' => 'Api-Key ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'method' => 1,
                'amount' => $amount,
                'phone' => $phone,
                'note' => $note,
            ]),
            'timeout' => 180, // Increased timeout to 3 minutes
        ]);

        return $this->process_response($response, 'request_to_pay');
    }

    /**
     * Process remittance payment.
     *
     * @param float $amount Amount to remit.
     * @param string $phone Recipient's phone number.
     * @param string $note Payment note.
     * @return array API response.
     */
    public function remit_payment($amount, $phone, $note) {
        if (empty($this->api_key)) {
            return $this->handle_missing_api_key('remit');
        }

        $response = wp_remote_post($this->endpoints['remit'], [
            'headers' => [
                'Authorization' => 'Api-Key ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'method' => 1,
                'amount' => $amount,
                'phone' => $phone,
                'note' => $note,
            ]),
            'timeout' => 180, // Increased timeout to 3 minutes
        ]);

        return $this->process_response($response, 'remit');
    }

    /**
     * Process API responses.
     *
     * @param WP_HTTP_Response $response API response.
     * @param string $context Context for the request.
     * @return array Parsed response or error message.
    */
    private function process_response($response, $context) {
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_error("API Request Error: {$error_message}", $context);
            return ['status' => 'error', 'message' => $error_message];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_error('Invalid JSON response from the API.', $context);
            return ['status' => 'error', 'message' => __('Invalid JSON response.', 'daraza-payments')];
        }

        // If the API returned an error status, log details if available.
        if (isset($body['status']) && strtolower($body['status']) === 'error') {
            $error_message = $body['message'];
            if (isset($body['details']) && !empty($body['details'])) {
                $this->log_error("API Error Details: " . $body['details'], $context);
                $error_message .= ' - ' . $body['details'];
            }
            $this->log_error("API Error: " . $error_message, $context);
            return [
                'status'  => 'error',
                'message' => $error_message,
                'details' => $body['details'] ?? ''
            ];
        }

        $this->log_info("API Response Success: {$context}", $body);
        return $body;
    }

    /**
     * Handle missing API key scenario.
     *
     * @param string $context Context for the request.
     * @return array Error response.
     */
    private function handle_missing_api_key($context) {
        $message = __('API Key is missing.', 'daraza-payments');
        $this->log_error($message, $context);
        return ['status' => 'error', 'message' => $message];
    }

    /**
     * Log errors to WooCommerce logs or fallback to error_log.
     *
     * @param string $message Error message.
     * @param string $context Context for the log.
     */
    private function log_error($message, $context) {
        if ($this->logger) {
            $this->logger->error("Daraza API Error ({$context}): {$message}", ['source' => 'daraza-payments']);
        } else {
            error_log("Daraza API Error ({$context}): {$message}");
        }
    }

    /**
     * Log informational messages to WooCommerce logs or fallback to error_log.
     *
     * @param string $message Log message.
     * @param array $data Additional data to log.
     */
    private function log_info($message, $data = []) {
        if ($this->logger) {
            $this->logger->info("Daraza API Info: {$message}", ['source' => 'daraza-payments', 'data' => $data]);
        } else {
            error_log("Daraza API Info: {$message}");
        }
    }
}
