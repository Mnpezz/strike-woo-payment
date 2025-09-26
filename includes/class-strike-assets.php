<?php
/**
 * Strike Lightning Assets Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Strike_Assets {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on checkout and order pages
        if (is_checkout() || is_order_received_page()) {
            wp_enqueue_script('qrcode', 'https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js', array(), '1.5.3', true);
            wp_enqueue_script('strike-frontend', STRIKE_LIGHTNING_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), STRIKE_LIGHTNING_VERSION, true);
            wp_enqueue_style('strike-frontend', STRIKE_LIGHTNING_PLUGIN_URL . 'assets/css/frontend.css', array(), STRIKE_LIGHTNING_VERSION);
            
            // Localize script for AJAX
            wp_localize_script('strike-frontend', 'strike_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('strike_check_payment')
            ));
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook === 'woocommerce_page_strike-lightning-settings') {
            wp_enqueue_script('strike-admin', STRIKE_LIGHTNING_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), STRIKE_LIGHTNING_VERSION, true);
            wp_enqueue_style('strike-admin', STRIKE_LIGHTNING_PLUGIN_URL . 'assets/css/admin.css', array(), STRIKE_LIGHTNING_VERSION);
            
            // Localize script for AJAX
            wp_localize_script('strike-admin', 'strike_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('strike_admin')
            ));
        }
    }
}
