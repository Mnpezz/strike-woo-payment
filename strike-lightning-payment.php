<?php
/**
 * Plugin Name: Strike Lightning Payment Gateway
 * Plugin URI: https://github.com/mnpezz/strike-woo-payment
 * Description: Accept Bitcoin Lightning Network payments via Strike API for WooCommerce
 * Version: 2.2.0
 * Author: mnpezz
 * License: GPL v2 or later
 * Text Domain: strike-lightning-payment
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('STRIKE_LIGHTNING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('STRIKE_LIGHTNING_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('STRIKE_LIGHTNING_VERSION', '1.0.0');

add_filter('woocommerce_payment_gateways', 'add_strike_lightning_gateway');
function add_strike_lightning_gateway($gateways) {
    $gateways[] = 'WC_Gateway_Strike_Lightning';
    return $gateways;
}

add_action('plugins_loaded', 'init_strike_lightning_gateway', 11);
function init_strike_lightning_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>WooCommerce is not active. The Strike Lightning Gateway plugin requires WooCommerce to be active.</p></div>';
        });
        return;
    }

    // Include required files
    require_once STRIKE_LIGHTNING_PLUGIN_PATH . 'includes/class-strike-api.php';
    require_once STRIKE_LIGHTNING_PLUGIN_PATH . 'includes/class-strike-payment-gateway.php';
    require_once STRIKE_LIGHTNING_PLUGIN_PATH . 'includes/class-strike-admin.php';
}

// Block support
add_action('woocommerce_blocks_loaded', 'strike_lightning_register_payment_method_type');

function strike_lightning_register_payment_method_type() {
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-strike-lightning-gateway-blocks-support.php';

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new WC_Strike_Lightning_Gateway_Blocks_Support());
        }
    );
}

// Declare compatibility
add_action('before_woocommerce_init', 'strike_lightning_cart_checkout_blocks_compatibility');
function strike_lightning_cart_checkout_blocks_compatibility() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}

// AJAX handler for checking payment status
add_action('wp_ajax_strike_check_payment', 'strike_lightning_ajax_check_payment');
add_action('wp_ajax_nopriv_strike_check_payment', 'strike_lightning_ajax_check_payment');

// Debug AJAX handler to check settings
add_action('wp_ajax_strike_debug_settings', 'strike_lightning_debug_settings');

function strike_lightning_debug_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    // Check various possible option names
    $possible_options = array(
        'woocommerce_strike_lightning_settings',
        'woocommerce_gateway_strike_lightning_settings', 
        'woocommerce_strike_lightning_gateway_settings',
    );
    
    echo '<h3>Strike Lightning Settings Debug</h3>';
    
    foreach ($possible_options as $option_name) {
        $settings = get_option($option_name, array());
        echo '<h4>' . esc_html($option_name) . ':</h4>';
        echo '<pre>' . print_r($settings, true) . '</pre>';
    }
    
    // Check if gateway is instantiated and what its settings are
    $gateways = WC()->payment_gateways->payment_gateways();
    if (isset($gateways['strike_lightning'])) {
        echo '<h4>Gateway Instance Settings:</h4>';
        echo '<pre>' . print_r($gateways['strike_lightning']->settings, true) . '</pre>';
        echo '<h4>Gateway API Key:</h4>';
        echo '<pre>API Key: ' . (empty($gateways['strike_lightning']->api_key) ? 'NOT SET' : 'SET (length: ' . strlen($gateways['strike_lightning']->api_key) . ')') . '</pre>';
    }
    
    wp_die();
}

function strike_lightning_ajax_check_payment() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'strike_check_payment')) {
        wp_send_json_error('Invalid security token');
        return;
    }
    
    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);
    
    if (!$order) {
        wp_send_json_error('Invalid order');
        return;
    }
    
    // Check if order is already paid
    if ($order->is_paid()) {
        wp_send_json_success(array('paid' => true));
        return;
    }
    
    $payment_data = get_post_meta($order_id, '_strike_payment_data', true);
    
    if (!$payment_data || empty($payment_data['receive_request_id'])) {
        wp_send_json_error('No payment data found');
        return;
    }
    
    // Get gateway settings from WooCommerce
    $gateway_settings = get_option('woocommerce_strike_lightning_settings', array());
    $api_key = isset($gateway_settings['api_key']) ? $gateway_settings['api_key'] : '';
    $environment = isset($gateway_settings['environment']) ? $gateway_settings['environment'] : 'production';
    
    // Debug logging
    error_log('Strike Payment Check - Gateway settings: ' . print_r($gateway_settings, true));
    error_log('Strike Payment Check - API Key present: ' . (!empty($api_key) ? 'Yes' : 'No'));
    error_log('Strike Payment Check - API Key length: ' . strlen($api_key));
    error_log('Strike Payment Check - Environment: ' . $environment);
    error_log('Strike Payment Check - Receive Request ID: ' . $payment_data['receive_request_id']);
    
    // Also check if maybe the settings are stored differently
    $all_wc_settings = wp_list_pluck(get_option('woocommerce_gateway_order', array()), 'settings');
    error_log('Strike Payment Check - All WC gateway settings keys: ' . print_r(array_keys($all_wc_settings), true));
    
    if (empty($api_key)) {
        wp_send_json_error('API key not configured');
        return;
    }
    
    $strike_api = new Strike_API($api_key, $environment);
    
    // Check receives for the request using the correct endpoint
    $receives_response = $strike_api->get_receives_for_request($payment_data['receive_request_id']);
    
    // Debug logging
    error_log('Strike API receives response: ' . print_r($receives_response, true));
    
    if ($receives_response['success'] && !empty($receives_response['data'])) {
        // Handle paginated response structure from Strike API
        $receives_data = $receives_response['data'];
        if (isset($receives_data['items']) && is_array($receives_data['items'])) {
            // Strike API returns paginated response: {items: [...], count: X, isCountUnknown: false}
            $receives_list = $receives_data['items'];
            error_log('Strike Payment - Found paginated response with ' . count($receives_list) . ' receives in items array');
        } else if (is_array($receives_data)) {
            // Direct array response (fallback)
            $receives_list = $receives_data;
            error_log('Strike Payment - Found direct array response with ' . count($receives_list) . ' receives');
        } else {
            error_log('Strike Payment - ERROR: Unexpected receives response structure: ' . print_r($receives_data, true));
            $receives_list = array();
        }
        
        // Check for both completed and settled payment states
        $completed_receives = array_filter($receives_list, function($receive) {
            if (!isset($receive['state'])) return false;
            // Accept various completion states that might indicate payment success
            $accepted_states = ['COMPLETED', 'SETTLED', 'CONFIRMED', 'SUCCESS', 'PAID'];
            return in_array(strtoupper($receive['state']), $accepted_states);
        });
        
        if (!empty($completed_receives)) {
            // Payment received and completed! Mark order as paid
            error_log('Strike Payment - Payment confirmed and completed for order: ' . $order_id . ', completed receives: ' . count($completed_receives));
            $order->payment_complete();
            $order->add_order_note(__('Lightning payment completed via Strike', 'strike-lightning-payment'));
            
            // Clear the cart
            if (WC()->cart) {
                WC()->cart->empty_cart();
            }
            
            wp_send_json_success(array('paid' => true));
        } else {
            // Payment detected but not yet completed - log the actual states we received
            $states = array();
            if (is_array($receives_list)) {
                $states = array_map(function($receive) {
                    return isset($receive['state']) ? $receive['state'] : 'UNKNOWN';
                }, $receives_list);
            } else {
                error_log('Strike Payment - receives_list is not an array: ' . print_r($receives_list, true));
                $states = array('API_ERROR');
            }
            error_log('Strike Payment - Payment detected but not completed for order: ' . $order_id . ', states received: ' . implode(', ', $states));
            
            // Add detailed logging of each receive for debugging
            if (is_array($receives_list)) {
                foreach ($receives_list as $index => $receive) {
                    error_log("Strike Payment - Receive $index details: " . print_r($receive, true));
                }
            }
            
            
            // Create debug info for the frontend
            $debug_info = array();
            if (is_array($receives_list)) {
                foreach ($receives_list as $index => $receive) {
                    $debug_info[] = array(
                        'index' => $index,
                        'state' => isset($receive['state']) ? $receive['state'] : 'UNKNOWN',
                        'type' => isset($receive['type']) ? $receive['type'] : 'UNKNOWN',
                        'amountReceived' => isset($receive['amountReceived']) ? $receive['amountReceived'] : null,
                        'amountCredited' => isset($receive['amountCredited']) ? $receive['amountCredited'] : null,
                        'created' => isset($receive['created']) ? $receive['created'] : null,
                        'completed' => isset($receive['completed']) ? $receive['completed'] : null
                    );
                }
            }
            
            // Determine the appropriate status message
            $status_message = 'waiting';
            if (count($debug_info) > 0) {
                // We have receives, so payment was detected
                $status_message = 'pending';
            }
            
            wp_send_json_success(array(
                'paid' => false, 
                'status' => $status_message, 
                'receives_states' => $states,
                'debug_info' => $debug_info,
                'total_receives' => count($debug_info)
            ));
        }
    } else if (!$receives_response['success']) {
        // API error
        error_log('Strike API Error: ' . print_r($receives_response, true));
        $error_msg = isset($receives_response['error']) ? $receives_response['error'] : 'Unknown API error';
        wp_send_json_error('API error: ' . $error_msg);
        return;
    } else {
        // This shouldn't happen, but let's log it
        error_log('Strike Payment - Unexpected condition: receives_response success but no data');
        error_log('Strike Payment - Full response: ' . print_r($receives_response, true));
    }
    
    wp_send_json_success(array('paid' => false));
}

// Activation hook
register_activation_hook(__FILE__, 'strike_lightning_activate');

function strike_lightning_activate() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('This plugin requires WooCommerce to be installed and active.', 'strike-lightning-payment'));
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'strike_lightning_deactivate');

function strike_lightning_deactivate() {
    // Clean up any scheduled events or temporary data
}
