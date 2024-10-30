<?php
/**
 * Plugin Name: MaxelPay
 * Description: Use MaxelPay for secure crypto payments.
 * Plugin URI:  https://maxelpay.com
 * Version:     1.0.0
 * Tested up to: 6.6.2
 * Author:      mndpsingh287
 * Author URI:  https://profiles.wordpress.org/mndpsingh287
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: maxelpay
 * Domain Path: /languages
 * 
 * @package     maxelpay
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'MAXELPAY_VERSION' ) ) {
	return;
}

define( 'MAXELPAY_VERSION', '1.0.0' );
define( 'MAXELPAY_PLUGIN_NAME', 'maxelpay' );
define( 'MAXELPAY_FILE', __FILE__ );
define( 'MAXELPAY_DIR_PATH', plugin_dir_path( MAXELPAY_FILE ) );
define( 'MAXELPAY_DIR_URL', plugin_dir_url( MAXELPAY_FILE ) );
define( 'MAXELPAY_ASSETS_PATH', MAXELPAY_DIR_URL . 'assets/' );
define( 'MAXELPAY_API_SUB_PATH', '/wc-api/order_status' );
define( 'MAXELPAY_WEBHOOK_URL',site_url().'/wp-json/'.MAXELPAY_PLUGIN_NAME.MAXELPAY_API_SUB_PATH );

/**
 * Class MaxelPay
 */
if ( ! class_exists( 'Maxelpay' ) ) {
    final class Maxelpay {

        /**
         * Plugin instance.
         *
         * @var Maxelpay
         * @access private
         */

        private static $instance = null;

        /**
         * Get plugin instance.
         *
         * @return Maxelpay
         * @static
         */
        public static function get_instance() {

            if ( ! isset( self::$instance ) ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Constructor.
         *
         * @access private
         */
        private function __construct() {

            register_activation_hook( MAXELPAY_FILE, array( $this, 'maxelpay_activate' ) );
            add_filter( 'plugin_action_links_' . plugin_basename( MAXELPAY_FILE ), array( $this, 'maxelpay_template_settings_page' ) );
            add_action( 'plugins_loaded', array( $this, 'maxelpay_plugins_loaded' ) );
            add_filter( 'woocommerce_payment_gateways', array( $this, 'maxelpay_add_gateway_class' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'maxelpay_admin_style' ) );
            add_action( 'woocommerce_blocks_loaded', array( $this,'maxelpay_gateway_block_support' ) );
        }

        /**
         * Run on activation hook.
         * @access public
         */
        public static function maxelpay_activate() {
            
            update_option( 'maxelpay_version', MAXELPAY_VERSION );
            update_option( 'maxelpay_installDate', gmdate( 'Y-m-d h:i:s' ) );
        }

        /**
        * Plugin settings.
        * @access public
        */
        public function maxelpay_template_settings_page( $links ) {

            $links[] = '<a style="font-weight:bold" href="' . esc_url( get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&section=maxelpay' ) ) . '">'. esc_html__('Settings','maxelpay') .'</a>';
            return $links;
        }
        
        /**
         * Require files.
         * @access public
         */
        public function maxelpay_plugins_loaded() {
            
            if ( ! class_exists( 'WooCommerce' ) ) {

                add_action( 'admin_notices', array( $this, 'maxelpay_missing_wc_notice' ) );
                return;
            }

            load_plugin_textdomain('maxelpay', false, basename(dirname(MAXELPAY_FILE)) . '/languages/');

            require_once MAXELPAY_DIR_PATH . 'admin/class-maxelpay-woo-payment-gateway.php';

            $files = array_merge(
                glob(MAXELPAY_DIR_PATH . 'api/*.php'),
                glob(MAXELPAY_DIR_PATH . 'includes/*.php')
            );

            foreach ( $files as $file ) {
                
                require_once $file;
            }
        }

        /**
         * Payment gatway class
         * @access public
         */
        function maxelpay_add_gateway_class( $gateways ) {

            $gateways[] = 'MAXELPAY\Woo\MAXELPAY_Gateway'; 
            return $gateways;
        }

        
        /**
         * Admin styles css/js.
         *
         * @access public
         */
        public function maxelpay_admin_style( $hook ) {
            
            wp_enqueue_style('maxelpay-admin-css', MAXELPAY_ASSETS_PATH . 'css/maxelpay-admin.css', array(), MAXELPAY_VERSION, null, 'all');
            wp_enqueue_script('maxelpay-custom-notice', MAXELPAY_ASSETS_PATH . 'js/maxelpay-admin-notice.js', array('jquery'), MAXELPAY_VERSION, true);
        
        }
        
         /**
         * Payment gateway block support.
         *
         * @access public
         */
        public function maxelpay_gateway_block_support() {
            
            if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
                
                require_once 'admin/class-maxelpay-payment-gateway-blocks.php';

                add_action(
                    'woocommerce_blocks_payment_method_type_registration',
                    function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                        $payment_method_registry->register( new MAXELPAY_Gateway_Blocks_Support() );
                    }
                );
            }
        }
        
        /**
         * Notice for missing required plugin.
         * @access public
         */
        public function maxelpay_missing_wc_notice() {

            $installurl = admin_url() . 'plugin-install.php?tab=plugin-information&plugin=woocommerce';

            if ( file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' )) {
               
                echo '<div class="error"><p>' . esc_html__('MaxelPay requires WooCommerce to be active.', 'maxelpay') . '</div>';
            
            } else {

                /* translators: %s: MaxelPay */
                echo '<div class="error"><p>' . sprintf(esc_html__('MaxelPay requires WooCommerce to be installed and active. Click here to %s WooCommerce plugin.', 'maxelpay'), '<button class="maxelpay_modal-toggle" >' . esc_html__('Install', 'maxelpay') . ' </button>') . '</p></div>';
                
                ?>
				<div class="maxelpay_modal">
					<div class="maxelpay_modal-overlay maxelpay_modal-toggle"></div>
					<div class="maxelpay_modal-wrapper maxelpay_modal-transition">
					<div class="maxelpay_modal-header">
						<button class="maxelpay_modal-close maxelpay_modal-toggle"><span class="dashicons dashicons-dismiss"></span></button>
						<h2 class="maxelpay_modal-heading"><?php esc_html__('Install WooCommerce', 'maxelpay');?></h2>
					</div>
					<div class="maxelpay_modal-body">
						<div class="maxelpay_modal-content">
						<iframe  src="<?php echo esc_url( $installurl ); ?>" width="600" height="400" id="maxelpay_custom_maxelpay_modal"> </iframe>
						</div>
					</div>
					</div>
				</div>
				<?php
            }
        }
    }
}
function Maxelpay() {

	return Maxelpay::get_instance();

}
Maxelpay();
