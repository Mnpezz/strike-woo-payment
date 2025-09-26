<?php
/**
 * Strike Lightning Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Strike_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('Strike Lightning Settings', 'strike-lightning-payment'),
            __('Strike Lightning', 'strike-lightning-payment'),
            'manage_options',
            'strike-lightning-settings',
            array($this, 'admin_page')
        );
    }
    
    public function init_settings() {
        register_setting('strike_lightning_options', 'strike_lightning_options');
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Strike Lightning Settings', 'strike-lightning-payment'); ?></h1>
            <p><?php _e('Configure your Strike Lightning payment gateway settings.', 'strike-lightning-payment'); ?></p>
            
            <p><a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=strike_lightning'); ?>" class="button button-primary">
                <?php _e('Go to Payment Gateway Settings', 'strike-lightning-payment'); ?>
            </a></p>
        </div>
        <?php
    }
}
