<?php
/**
 * Strike Lightning Payment Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_Strike_Lightning extends WC_Payment_Gateway {
    
    private $strike_api;
    
    public function __construct() {
        $this->id = 'strike_lightning';
        $this->icon = STRIKE_LIGHTNING_PLUGIN_URL . 'assets/images/lightning-icon.svg';
        $this->has_fields = false;
        $this->method_title = __('Strike Lightning Payment', 'strike-lightning-payment');
        $this->method_description = __('Accept Bitcoin Lightning Network payments via Strike API', 'strike-lightning-payment');
        
        $this->supports = array('products');
        
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->api_key = $this->get_option('api_key');
        $this->environment = $this->get_option('environment');
        
        // Initialize Strike API
        if (!empty($this->api_key)) {
            $this->strike_api = new Strike_API($this->api_key, $this->environment);
        }
        
        // Save settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        
        // Handle webhooks
        add_action('woocommerce_api_strike_lightning_webhook', array($this, 'handle_webhook'));
    }
    
    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'strike-lightning-payment'),
                'type' => 'checkbox',
                'label' => __('Enable Strike Lightning Payment', 'strike-lightning-payment'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'strike-lightning-payment'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'strike-lightning-payment'),
                'default' => __('Bitcoin Lightning Payment', 'strike-lightning-payment'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'strike-lightning-payment'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'strike-lightning-payment'),
                'default' => __('Pay with Bitcoin Lightning Network via Strike', 'strike-lightning-payment'),
            ),
            'api_key' => array(
                'title' => __('Strike API Key', 'strike-lightning-payment'),
                'type' => 'password',
                'description' => __('Enter your Strike API key', 'strike-lightning-payment'),
                'default' => '',
                'desc_tip' => true,
            ),
            'environment' => array(
                'title' => __('Environment', 'strike-lightning-payment'),
                'type' => 'select',
                'description' => __('Choose the Strike API environment', 'strike-lightning-payment'),
                'default' => 'production',
                'options' => array(
                    'production' => __('Production', 'strike-lightning-payment'),
                    'sandbox' => __('Sandbox', 'strike-lightning-payment')
                ),
                'desc_tip' => true,
            ),
            'webhook_info' => array(
                'title' => __('Webhook URL', 'strike-lightning-payment'),
                'type' => 'title',
                'description' => sprintf(
                    __('Configure this webhook URL in your Strike dashboard:<br><code>%s</code><br>Subscribe to events: <strong>receive-request.receive-pending</strong> and <strong>receive-request.receive-completed</strong>', 'strike-lightning-payment'),
                    home_url('/wc-api/strike_lightning_webhook')
                ),
            ),
        );
    }
    
    /**
     * Enqueue payment scripts
     */
    public function payment_scripts() {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }

        // Use the same QR library as your NanoPay plugin
        wp_enqueue_script('qrcode', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', array(), '1.0.0', true);
        wp_enqueue_script('strike-lightning', STRIKE_LIGHTNING_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), STRIKE_LIGHTNING_VERSION, true);
        wp_enqueue_style('strike-lightning', STRIKE_LIGHTNING_PLUGIN_URL . 'assets/css/frontend.css', array(), STRIKE_LIGHTNING_VERSION);
        
        // Localize script for AJAX
        wp_localize_script('strike-lightning', 'strike_lightning_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('strike_check_payment')
        ));
    }
    
    /**
     * Process the payment and return the result
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return array(
                'result' => 'failure',
                'redirect' => wc_get_checkout_url()
            );
        }
        
        // Set order status to pending payment (crucial for Lightning payments)
        $order->update_status('pending', __('Awaiting Lightning payment', 'strike-lightning-payment'));
        
        // Clear any existing payment data to start fresh
        delete_post_meta($order_id, '_strike_payment_data');
        
        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }
    
    /**
     * Receipt page - shows payment form (like NanoPay)
     */
    public function receipt_page($order_id) {
        $order = wc_get_order($order_id);
        
        // Only skip rendering if order is already paid
        if ($order->is_paid()) {
            echo '<p>' . __('This order has already been paid.', 'strike-lightning-payment') . '</p>';
            return;
        }
        
        // Check if we've already rendered for this specific request to prevent duplicates
        static $rendered_orders = array();
        if (in_array($order_id, $rendered_orders)) {
            return;
        }
        $rendered_orders[] = $order_id;
        
        echo '<p>' . __('Please complete your payment using Bitcoin Lightning Network.', 'strike-lightning-payment') . '</p>';
        $this->generate_strike_lightning_form($order);
    }
    
    /**
     * Generate the Strike Lightning payment form (like NanoPay)
     */
    public function generate_strike_lightning_form($order) {
        $amount = $order->get_total();
        $currency = $order->get_currency();
        $order_id = $order->get_id();
        
        // Create fresh payment data using receive-requests
        $payment_data = $this->create_fresh_payment_data($order);
        
        if (!$payment_data) {
            echo '<p>' . __('Error creating payment. Please contact support.', 'strike-lightning-payment') . '</p>';
            return;
        }
        
        $btc_amount = $payment_data['btc_amount'];
        $usd_amount = $payment_data['usd_amount'];
        $ln_invoice = $payment_data['ln_invoice'];
        $expiration = $payment_data['expiration'];
        
        ?>
        <div id="strike-lightning-payment" class="strike-lightning-payment-container">
            <h3><?php _e('Pay with Bitcoin Lightning', 'strike-lightning-payment'); ?></h3>
            
            <div class="payment-amounts">
                <p><strong><?php _e('Amount to Pay:', 'strike-lightning-payment'); ?></strong></p>
                <p class="btc-amount"><?php echo $btc_amount; ?> BTC</p>
                <p class="usd-amount"><?php echo $usd_amount; ?> USD</p>
            </div>
            
            <div class="payment-methods">
                <div class="qr-code-section">
                    <h4><?php _e('Scan QR Code', 'strike-lightning-payment'); ?></h4>
                    <div id="lightning-qr-code" data-invoice="<?php echo esc_attr($ln_invoice); ?>"></div>
                    <p class="qr-instructions"><?php _e('Scan this QR code with your Lightning wallet', 'strike-lightning-payment'); ?></p>
                </div>
                
                <div class="lightning-address-section">
                    <h4><?php _e('Lightning Invoice', 'strike-lightning-payment'); ?></h4>
                    <div class="invoice-container">
                        <textarea id="lightning-invoice" readonly><?php echo esc_textarea($ln_invoice); ?></textarea>
                        <button id="copy-invoice" class="button"><?php _e('Copy Invoice', 'strike-lightning-payment'); ?></button>
                    </div>
                </div>
            </div>
            
            <div class="payment-status">
                <p id="payment-status-message"><?php _e('Waiting for payment...', 'strike-lightning-payment'); ?></p>
                <div id="payment-timer">
                    <p><?php _e('Payment expires in:', 'strike-lightning-payment'); ?> <span id="countdown" data-expiration="<?php echo esc_attr($expiration); ?>"></span></p>
                </div>
            </div>
            
            <div class="payment-actions">
                <a href="<?php echo wc_get_cart_url(); ?>" class="button"><?php _e('Cancel Order', 'strike-lightning-payment'); ?></a>
            </div>
            
            <!-- Hidden data for JavaScript -->
            <div data-order-id="<?php echo $order->get_id(); ?>" data-nonce="<?php echo wp_create_nonce('strike_check_payment'); ?>" style="display: none;"></div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Strike Lightning Payment form loaded');
            console.log('Order ID:', <?php echo $order->get_id(); ?>);
            console.log('AJAX URL:', strike_lightning_ajax.ajax_url);
            console.log('Nonce:', strike_lightning_ajax.nonce);
            
            // Generate QR code using the same library as NanoPay
            const lnInvoice = '<?php echo esc_js($ln_invoice); ?>';
            const qrContainer = document.getElementById('lightning-qr-code');
            
            if (typeof QRCode !== 'undefined') {
                new QRCode(qrContainer, {
                    text: lnInvoice,
                    width: 256,
                    height: 256,
                    colorDark: '#000000',
                    colorLight: '#ffffff'
                });
            }
            
            // Copy invoice functionality
            document.getElementById('copy-invoice').addEventListener('click', function() {
                const invoiceText = document.getElementById('lightning-invoice');
                invoiceText.select();
                document.execCommand('copy');
                this.textContent = '<?php _e('Copied!', 'strike-lightning-payment'); ?>';
                setTimeout(() => {
                    this.textContent = '<?php _e('Copy Invoice', 'strike-lightning-payment'); ?>';
                }, 2000);
            });
            
            // Auto-check payment status every 10 seconds
            let checkInterval = setInterval(function() {
                console.log('Checking payment status...');
                fetch(strike_lightning_ajax.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=strike_check_payment&order_id=<?php echo $order->get_id(); ?>&nonce=' + strike_lightning_ajax.nonce
                })
                .then(response => {
                    console.log('Payment check response:', response);
                    return response.json();
                })
                .then(data => {
                    console.log('Payment check data:', data);
                    console.log('data.success:', data.success);
                    console.log('data.data:', data.data);
                    if (data.data) {
                        console.log('data.data.paid:', data.data.paid);
                        console.log('data.data.status:', data.data.status);
                        console.log('data.data.receives_states:', data.data.receives_states);
                        if (data.data.debug_info) {
                            console.log('data.data.debug_info:', data.data.debug_info);
                        }
                        if (data.data.total_receives) {
                            console.log('data.data.total_receives:', data.data.total_receives);
                        }
                    }
                    
                    if (data.success && data.data && data.data.paid === true) {
                        document.getElementById('payment-status-message').textContent = '<?php _e('Payment received! Redirecting...', 'strike-lightning-payment'); ?>';
                        clearInterval(checkInterval);
                        setTimeout(() => {
                            window.location.href = '<?php echo $order->get_checkout_order_received_url(); ?>';
                        }, 2000);
                    } else if (data.data && data.data.status === 'pending') {
                        document.getElementById('payment-status-message').textContent = '<?php _e('Payment detected, waiting for confirmation...', 'strike-lightning-payment'); ?>';
                    } else if (data.data && data.data.status === 'waiting') {
                        document.getElementById('payment-status-message').textContent = '<?php _e('Waiting for payment...', 'strike-lightning-payment'); ?>';
                    }
                })
                .catch(error => {
                    console.error('Error checking payment:', error);
                });
            }, 10000); // Check every 10 seconds
            
            // Countdown timer
            const expirationTime = new Date('<?php echo $expiration; ?>').getTime();
            
            function updateCountdown() {
                const now = new Date().getTime();
                const distance = expirationTime - now;
                
                if (distance < 0) {
                    document.getElementById('countdown').textContent = '<?php _e('Expired', 'strike-lightning-payment'); ?>';
                    clearInterval(countdownInterval);
                    return;
                }
                
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                document.getElementById('countdown').textContent = minutes + 'm ' + seconds + 's';
            }
            
            const countdownInterval = setInterval(updateCountdown, 1000);
            updateCountdown();
        });
        </script>
        <?php
    }
    
    /**
     * Create fresh payment data using receive-requests (5 minute expiry)
     */
    private function create_fresh_payment_data($order) {
        // Always create a new receive request to avoid expired ones
        $amount = $order->get_total();
        $currency = $order->get_currency();
        $description = sprintf(__('Order #%s', 'strike-lightning-payment'), $order->get_order_number());
        
        $receive_response = $this->strike_api->create_receive_request($amount, $currency, $description, 'USD', 300); // 5 minutes
        
        if (!$receive_response['success']) {
            error_log('Strike API Error: ' . print_r($receive_response, true));
            return false;
        }
        
        $receive_data = $receive_response['data'];
        
        // Store payment data
        $payment_data = array(
            'receive_request_id' => $receive_data['receiveRequestId'],
            'ln_invoice' => $receive_data['bolt11']['invoice'],
            'btc_amount' => $receive_data['bolt11']['btcAmount'],
            'usd_amount' => $receive_data['bolt11']['requestedAmount']['amount'],
            'expiration' => $receive_data['bolt11']['expires'],
            'created_at' => time()
        );
        
        update_post_meta($order->get_id(), '_strike_payment_data', $payment_data);
        
        return $payment_data;
    }
    
    /**
     * Handle webhook from Strike
     */
    public function handle_webhook() {
        $raw_body = file_get_contents('php://input');
        $data = json_decode($raw_body, true);
        
        // Log webhook for debugging
        error_log('Strike Webhook received: ' . print_r($data, true));
        
        if (!$data || !isset($data['eventType']) || !isset($data['data']['entityId'])) {
            error_log('Strike Webhook: Invalid webhook data');
            status_header(400);
            exit;
        }
        
        $event_type = $data['eventType'];
        $entity_id = $data['data']['entityId'];
        
        // Handle different webhook events based on Strike API documentation
        if ($event_type === 'receive-request.receive-pending') {
            error_log('Strike Webhook: Payment detected (pending) for receive request: ' . $entity_id);
            // Payment detected but not yet confirmed
            $this->handle_payment_pending($entity_id);
        } elseif ($event_type === 'receive-request.receive-completed') {
            error_log('Strike Webhook: Payment completed for receive request: ' . $entity_id);
            // Payment confirmed and completed
            $this->handle_payment_completed($entity_id);
        }
        
        status_header(200);
        exit;
    }
    
    /**
     * Handle payment pending webhook
     */
    private function handle_payment_pending($receive_request_id) {
        $order = $this->find_order_by_receive_request($receive_request_id);
        if ($order) {
            $order->add_order_note(__('Lightning payment detected (pending confirmation)', 'strike-lightning-payment'));
        }
    }
    
    /**
     * Handle payment completed webhook
     */
    private function handle_payment_completed($receive_request_id) {
        $order = $this->find_order_by_receive_request($receive_request_id);
        if ($order && !$order->is_paid()) {
            // Double-check with API that payment is actually completed
            if (!empty($this->api_key) && $this->strike_api) {
                $receives_response = $this->strike_api->get_receives_for_request($receive_request_id);
                if ($receives_response['success'] && !empty($receives_response['data'])) {
                    $completed_receives = array_filter($receives_response['data'], function($receive) {
                        return isset($receive['state']) && $receive['state'] === 'COMPLETED';
                    });
                    
                    if (!empty($completed_receives)) {
                        $order->payment_complete();
                        $order->add_order_note(__('Lightning payment completed via Strike webhook', 'strike-lightning-payment'));
                        error_log('Strike Webhook: Order ' . $order->get_id() . ' marked as paid');
                    } else {
                        error_log('Strike Webhook: Payment for order ' . $order->get_id() . ' not yet completed');
                    }
                } else {
                    error_log('Strike Webhook: Could not verify payment completion for order ' . $order->get_id());
                }
            } else {
                // Fallback: mark as completed based on webhook alone
                $order->payment_complete();
                $order->add_order_note(__('Lightning payment completed via Strike webhook', 'strike-lightning-payment'));
                error_log('Strike Webhook: Order ' . $order->get_id() . ' marked as paid (fallback)');
            }
        }
    }
    
    /**
     * Find order by receive request ID
     */
    private function find_order_by_receive_request($receive_request_id) {
        $orders = get_posts(array(
            'post_type' => 'shop_order',
            'meta_query' => array(
                array(
                    'key' => '_strike_payment_data',
                    'value' => $receive_request_id,
                    'compare' => 'LIKE'
                )
            )
        ));
        
        if (!empty($orders)) {
            return wc_get_order($orders[0]->ID);
        }
        
        return null;
    }
}
