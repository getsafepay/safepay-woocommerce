<?php
/*
  Plugin Name:  Safepay for WooCommerce
  Plugin URI:   https://github.com/getsafepay/safepay-woocommerce
  Description:  Safepay Payment Gateway Integration for WooCommerce.
  Version:      1.0.6
  Author:       Team Safepay
  Author URI:   https://getsafepay.com
  License:      GPL-2.0+
  License URI:  http://www.gnu.org/licenses/gpl-2.0.txt
*/

if ( ! defined( 'ABSPATH' ) )
{
    exit; // Exit if accessed directly
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

add_action('plugins_loaded', 'woocommerce_safepay_init', 0);

function woocommerce_safepay_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Safepay extends WC_Payment_Gateway
    {
        const TRACKER_TOKEN                   = "safepay_wc_tracker_token";
        const SAFEPAY_TRANSACTION_TOKEN       = "safepay_transaction_token";
        const SAFEPAY_TRANSACTION_REFERENCE   = "safepay_reference_code";

        const WC_ORDER_ID                 = "woocommerce_order_id";

        const SANDBOX                     = "sandbox";
        const PRODUCTION                  = "production";

        const PRODUCTION_CHECKOUT_URL     = "https://www.getsafepay.com/components";
        const SANDBOX_CHECKOUT_URL        = "https://sandbox.api.getsafepay.com/components";

        const DEFAULT_UNSUPPORTED_MESSAGE = "Safepay currently does not support your store currency. Please choose from either USD ($) or PKR (Rs)";
        const DEFAULT_LABEL               = "Pay with your credit or debit card";
        const DEFAULT_DESCRIPTION         = "Securely pay with your Visa or MasterCard debit or credit card";
        const DEFAULT_SUCCESS_MESSAGE     = "Thank you for shopping with us. Your card has been charged and your transaction is successful. We will begin processing your order soon.";

        protected $visible_settings = array(
            "enabled",
            "title",
            "description",
            "sandbox_mode",
            "sandbox_key",
            "production_key",
            "production_webhook_secret",
            "sandbox_webhook_secret",
            "order_success_message"
        );

        public $form_fields = array();

        public $supports = array(
            'products'
        );

         /**
         * Set if the place order button should be renamed on selection.
         *
         * @var string
         */
        public $order_button_text = "Proceed to Safepay";

        /**
         * Can be set to true if you want payment fields
         * to show on the checkout (if doing a direct integration).
         * @var boolean
         */
        public $has_fields = false;

         /**
         * Unique ID for the gateway
         * @var string
         */
        public $id = 'safepay';

         /**
         * Title of the payment method shown on the admin page.
         * @var string
         */
        public $method_title = 'Safepay';

        /**
         * Description of the payment method shown on the admin page.
         * @var  string
         */
        public $method_description = "Safepay Checkout redirects customers to Safepay to complete their payment";

        /**
         * Icon URL, set in constructor
         * @var string
         */
        public $icon;

        /**
         * TODO: Remove usage of $this->msg
         */
        protected $msg = array(
            'message'   =>  '',
            'class'     =>  '',
        );

        /** @var WC_Logger Logger instance */
        public static $log = false;


        public function __construct()
        {
            $this->icon =  plugins_url('images/logo.png' , __FILE__);

            $this->init_form_fields();
            $this->init_settings();
            $this->init_hooks();       
            $this->title          = $this->get_option('title');
            $this->description    = $this->get_option('description');
            $this->enabled        = $this->get_option('enabled');
            $this->sandbox        = 'yes' === $this->get_option('sandbox_mode');

            $this->init_admin_options();
        }

        /**
         * Logging method.
         *
         * @param string $message Log message.
         * @param string $level   Optional. Default 'info'.
         *     emergency|alert|critical|error|warning|notice|info|debug
         */
        public static function log( $message, $level = 'info' ) 
        {
            if ( empty( self::$log ) ) {
                self::$log = wc_get_logger();
            }
            self::$log->log( $level, $message, array( 'source' => 'safepay' ) );
        }

        protected function init_hooks()
        {
            add_action('init', array($this, 'check_safepay_response'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'action_safepay_receipt_id'));

            add_action('woocommerce_api_' . $this->id, array($this, 'check_safepay_response'));
        }

        protected function init_admin_options()
        {
            $cb = array($this, 'process_admin_options');
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        protected function get_custom_order_creation_message()
        {
            $message =  $this->get_option('order_success_message');
            if (isset($message) === false)
            {
                $message = self::DEFAULT_SUCCESS_MESSAGE;
            }
            return $message;
        }

        public function init_form_fields()
        {   
            
            $default_form_fields = array(
                'enabled' => array(
                    'title'       => __('Enable/Disable', $this->id),
                    'label'       => __('Enable Safepay Checkout', $this->id),
                    'type'        => 'checkbox',
                    'default'     => 'yes'
                ),
                'title' => array(
                    'title'       => __('Title', $this->id),
                    'description' => __('This controls the title your user sees during checkout.', $this->id),
                    'type'        => 'text',
                    'default'     => __(self::DEFAULT_LABEL, $this->id)
                ),
                'description' => array(
                    'title'       => __('Description', $this->id),
                    'description' => __('This controls the description your user sees during checkout.', $this->id),
                    'type'        => 'textarea',
                    'default'     => __(self::DEFAULT_DESCRIPTION, $this->id)
                ),
                'sandbox_mode' => array(
                    'title'       => __('Sandbox mode', $this->id),
                    'label'       => __('Enable Sandbox Mode', $this->id),
                    'description' => __('Run test transactions in sandbox mode.', $this->id),
                    'type'        => 'checkbox',
                    'default'     => 'yes'
                ),
                'sandbox_key' => array(
                    'title'       => __('Sandbox key', $this->id),
                    'type'        => 'text',
                    'required'    => true
                ),
                'production_key' => array(
                    'title'       => __('Production key', $this->id),
                    'type'        => 'text',
                    'required'    => true
                ),
                'production_webhook_secret' => array(
                    'title'       => __( 'Production Shared Secret', $this->id),
                    'type'        => 'text',
                    'description' => 
                    // translators: Instructions for setting up 'webhook shared secrets' on settings page.
                    __( 'Using webhook secret keys allows Safepay to verify each payment. To get your live webhook key:', $this->id )
                    . '<br /><br />' .
                    // translators: Step 1 of the instructions for 'webhook shared secrets' on settings page.
                    __( '1. Navigate to your Live Safepay dashboard by clicking <a href="https://getsafepay.com/dashboard/api-settings">here</a>', $this->id )
                    . '<br />' .
                    // translators: Step 2 of the instructions for 'webhook shared secrets' on settings page. Includes webhook URL.
                    __( '2. Activate your Developer settings, copy the webhook secret key and paste into the box above.', $this->id ),
                ),
                'sandbox_webhook_secret' => array(
                    'title'       => __( 'Sandbox Shared Secret', $this->id),
                    'type'        => 'text',
                    'description' => 
                    // translators: Instructions for setting up 'webhook shared secrets' on settings page.
                    __( 'Using webhook secret keys allows Safepay to verify each payment. To get your sandbox webhook key:', $this->id )
                    . '<br /><br />' .
                    // translators: Step 1 of the instructions for 'webhook shared secrets' on settings page.
                    __( '1. Navigate to your Sandbox Safepay dashboard by clicking <a href="https://sandbox.api.getsafepay.com/dashboard/api-settings">here</a>', $this->id )
                    . '<br />' .
                    // translators: Step 2 of the instructions for 'webhook shared secrets' on settings page. Includes webhook URL.
                    __( '2. Activate your Developer settings, copy the webhook secret key and paste into the box above.', $this->id ),
                ),
                'order_success_message' => array(
                    'title'         => __('Order Completion Message', $this->id),
                    'type'          => 'textarea',
                    'description'   => __('Message to be displayed after a successful order', $this->id),
                    'default'       => __(self::DEFAULT_SUCCESS_MESSAGE, $this->id)
                ),
            );


            foreach ($default_form_fields as $key => $value)
            {
                if (in_array($key, $this->visible_settings, true))
                {
                    $this->form_fields[$key] = $value;
                }
            }
        }

        public function is_valid_for_use() 
        {
            return in_array(
                get_woocommerce_currency(),
                apply_filters(
                    'woocommerce_paypal_supported_currencies',
                    array('PKR', 'USD')
                ),
                true
            );
        }

        public function admin_options()
        {
            if ($this->is_valid_for_use()) {
                parent::admin_options();
            } else {
                ?>
                <div class="inline error">
                    <p>
                        <strong><?php esc_html_e('Gateway disabled', 'woocommerce'); ?></strong>: 
                        <?php esc_html_e(self::DEFAULT_UNSUPPORTED_MESSAGE, 'woocommerce'); ?>
                    </p>
                </div>
                <?php
            }
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function process_payment( $order_id )
        {   
            $order = wc_get_order($order_id);
            $env = $this->get_environment();
            $this->init_api();
            $result = Safepay_API_Handler::create_charge(
                $order->get_total(), get_woocommerce_currency(), $env
            );

            if (!$result[0]) {
                return array('result' => 'fail');
            }

            $charge = $result[1]['data'];

            $order->update_meta_data(self::SAFEPAY_TRANSACTION_TOKEN, $charge['token']);
            $order->save();

            $hosted_url = $this->construct_url($order, $charge['token']);
            return array(
                'result'   => 'success',
                'redirect' => $hosted_url,
            );
        }

        /**
         * Get the cancel url.
         *
         * @param WC_Order $order Order object.
         * @return string
         */
        public function get_cancel_url( $order ) 
        {
            $return_url = $order->get_cancel_order_url();
            if ( is_ssl() || get_option( 'woocommerce_force_ssl_checkout' ) == 'yes' ) {
                $return_url = str_replace( 'http:', 'https:', $return_url );
            }
            return apply_filters( 'woocommerce_get_cancel_url', $return_url, $order );
        }

        /**
         * Returns redirect URL post payment processing
         * @return string redirect URL
         */
        private function get_redirect_url()
        {
            return get_site_url() . '/wc-api/' . $this->id;
        }

        protected function get_environment()
        {
            return $this->sandbox ? self::SANDBOX : self::PRODUCTION;
        }

        protected function construct_url($order, $tracker="")
        {
            $baseURL = $this->sandbox ? self::SANDBOX_CHECKOUT_URL : self::PRODUCTION_CHECKOUT_URL;
            $order_id = $order->get_id();
            $params = array(
                "env"            => $this->sandbox ? Safepay_API_Handler::$SANDBOX : Safepay_API_Handler::$PRODUCTION,
                "beacon"         => $tracker,
                "source"         => 'woocommerce',
                "order_id"       => $order_id,
                "nonce"          => wp_create_nonce(self::WC_ORDER_ID . $order_id),
                "redirect_url"   => $this->get_redirect_url(),
                "cancel_url"     => $this->get_cancel_url( $order )
            );

            $baseURL = add_query_arg($params, $baseURL);

            return $baseURL;
        }

        /**
         * Check for valid safepay server callback
         */
        function check_safepay_response()
        {
            global $woocommerce;

            $order_id = sanitize_text_field($_POST["order_id"]);
            $signature = sanitize_text_field($_POST["sig"]);
            $reference_code = sanitize_text_field($_POST["reference"]);
            $tracker = sanitize_text_field($_POST["tracker"]);
            $nonce = $_POST["nonce"];
            $success = false;
            $error = "";

            if (empty($nonce))
            {
                $error = 'Required nonce not returned in request';
                self::log( 'Required nonce not returned in request' );
            }
            else if (!isset($order_id) || !isset($signature))
            {
                $error = 'Payment to Safepay Failed. No data received';
                self::log( 'Payment to Safepay Failed. No data received' );
            }
            else if ($this->validate_signature($tracker, $signature) === false)
            {
                $error = 'Payment is invalid. Failed security check.';
                self::log( 'Payment is invalid. Failed security check' );
            }
            else
            {
                $success = true;
            }

            $order = new WC_Order($order_id);
            // If the order has already been paid for
            // redirect user to success page
            if ($order->needs_payment() === false)
            {
                $this->redirect_user($order);
            }

            $this->update_order($order, $success, $error, $reference_code);
            $this->redirect_user($order);
        }

        protected function redirect_user($order)
        {
            $redirect_url = $this->get_return_url($order);
            wp_redirect($redirect_url);
            exit;
        }

        /**
         * Check Safepay webhook request is valid.
         * @param  string $tracker
         */
        public function validate_signature($tracker, $signature)
        {
            $secret = $this->get_shared_secret();
            $signature_2 = hash_hmac('sha256', $tracker, $secret);

            if ($signature_2 === $signature) {
                return true;
            }

            self::log( 'signature: ' . $signature );
            self::log( 'signature_2: ' . $signature_2 );
            return false;
        }

        private function get_shared_secret()
        {
            $key = $this->sandbox ? 'sandbox_webhook_secret' : 'production_webhook_secret';
            return $this->get_option($key);
        }

        /**
         * Modifies existing order and handles success case
         *
         * @param $success, & $order
         */
        public function update_order(& $order, $success, $error, $reference_code)
        {
            global $woocommerce;

            $order_id = $order->get_order_number();

            if (($success === true) and ($order->needs_payment() === true))
            {
                $this->msg['message'] = $this->get_custom_order_creation_message() . "&nbsp; Order Id: $order_id";
                $this->msg['class'] = 'success';

                $order->update_meta_data(self::SAFEPAY_TRANSACTION_REFERENCE, $reference_code);
                $order->payment_complete($reference_code);
                $order->add_order_note("Safepay payment successful <br/>Safepay Reference Code: $reference_code");

                if (isset($woocommerce->cart) === true)
                {
                    $woocommerce->cart->empty_cart();
                }
            }
            else
            {
                $this->msg['class'] = 'error';
                $this->msg['message'] = $error;
                $order->add_order_note("Transaction Failed: $error<br/>");
                $order->update_status('failed');
            }

            $this->add_notice($this->msg['message'], $this->msg['class']);
        }

        /**
         * Add a woocommerce notification message
         *
         * @param string $message Notification message
         * @param string $type Notification type, default = notice
         */
        protected function add_notice($message, $type = 'notice')
        {
            global $woocommerce;

            $type = in_array($type, array('notice','error','success'), true) ? $type : 'notice';
            wc_add_notice($message, $type);
        }

        /**
         * Init the API class and set the API key etc.
         */
        protected function init_api()
        {
            include_once dirname( __FILE__ ) . '/includes/safepay-api-handler.php';

            Safepay_API_Handler::$sandbox_api_key = $this->get_option('sandbox_key');
            Safepay_API_Handler::$production_api_key = $this->get_option('production_key');
        }
    }

     /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_safepay_gateway($methods)
    {
        $methods[] = 'WC_Safepay';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_safepay_gateway');
}
