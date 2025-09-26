<?php
/**
 * Strike API Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Strike_API {
    
    private $api_key;
    private $environment;
    private $base_url;
    
    public function __construct($api_key, $environment = 'production') {
        $this->api_key = $api_key;
        $this->environment = $environment;
        $this->base_url = $environment === 'sandbox' 
            ? 'https://api.strike.me/sandbox' 
            : 'https://api.strike.me';
    }
    
    /**
     * Create a receive request (5 minute expiry)
     */
    public function create_receive_request($amount, $currency, $description = '', $target_currency = 'USD', $expiry_in_seconds = 300) {
        $data = array(
            'targetCurrency' => $target_currency,
            'bolt11' => array(
                'amount' => array(
                    'amount' => $amount,
                    'currency' => $currency
                ),
                'description' => $description,
                'expiryInSeconds' => $expiry_in_seconds
            )
        );
        
        return $this->make_request('/v1/receive-requests', 'POST', $data);
    }
    
    /**
     * Get receives for a specific receive request
     */
    public function get_receives_for_request($receive_request_id) {
        return $this->make_request('/v1/receive-requests/' . $receive_request_id . '/receives', 'GET');
    }
    
    /**
     * Get receive request details
     */
    public function get_receive_request($receive_request_id) {
        return $this->make_request('/v1/receive-requests/' . $receive_request_id, 'GET');
    }
    
    /**
     * Make API request
     */
    private function make_request($endpoint, $method = 'GET', $data = null) {
        $url = $this->base_url . $endpoint;
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        );
        
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30
        );
        
        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code >= 200 && $response_code < 300) {
            return array(
                'success' => true,
                'data' => json_decode($response_body, true)
            );
        } else {
            // Parse error response from Strike API
            $error_data = json_decode($response_body, true);
            $error_message = 'API Error: ' . $response_code;
            
            if ($error_data && isset($error_data['message'])) {
                $error_message = $error_data['message'];
            } elseif ($error_data && isset($error_data['error'])) {
                $error_message = $error_data['error'];
            }
            
            return array(
                'success' => false,
                'error' => $error_message,
                'response_code' => $response_code,
                'response' => $response_body
            );
        }
    }
}
