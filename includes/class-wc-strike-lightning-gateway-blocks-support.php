<?php
// File: includes/class-wc-strike-lightning-gateway-blocks-support.php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Strike_Lightning_Gateway_Blocks_Support extends AbstractPaymentMethodType {
    private $gateway;
    protected $name = 'strike_lightning';

    public function initialize() {
        $this->settings = get_option("woocommerce_{$this->name}_settings", []);
        $this->gateway = new WC_Gateway_Strike_Lightning();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'wc-strike-lightning-blocks-integration',
            STRIKE_LIGHTNING_PLUGIN_URL . 'assets/js/blocks.js',  // Match Coinos pattern
            array(
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ),
            STRIKE_LIGHTNING_VERSION,
            true
        );
        
        return array('wc-strike-lightning-blocks-integration');
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->get_title(),
            'description' => $this->gateway->get_description(),
            'supports' => $this->gateway->supports,
            'icon' => plugin_dir_url(__DIR__) . 'assets/images/lightning-icon.svg',
        ];
    }
}
