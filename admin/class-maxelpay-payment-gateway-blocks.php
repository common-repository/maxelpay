<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( !defined( 'ABSPATH' ) ) {
    exit();
}

/**
 * This file is used to create payment gateway block.
 * 
 * @package     admin/MAXELPAY_Gateway_Blocks_Support
 */
final class MAXELPAY_Gateway_Blocks_Support extends AbstractPaymentMethodType {

    /**
     * The gateway instance.
     *
     * @var MAXELPAY_Gateway
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = "maxelpay";

    /**
     * Initializes the payment method type.
     */
    public function initialize () {

        $this->settings = get_option( 'woocommerce_maxelpay_settings', [] );
        $gateways       = WC()->payment_gateways->payment_gateways();
        $this->gateway  = $gateways[ $this->name ];
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {

        return $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        
        $script_url        =   MAXELPAY_ASSETS_PATH . 'maxelplay-woo/build/index.js';

        $script_asset_path =   MAXELPAY_ASSETS_PATH . 'maxelplay-woo/build/block/index.asset.php';
        
        $script_asset      =   file_exists( $script_asset_path) ? require $script_asset_path : [
            'dependencies' =>  [],
            'version'      =>  MAXELPAY_VERSION,
        ];
        
        wp_register_script(
            "wc-maxplay-blocks-integration",
            $script_url,
            $script_asset["dependencies"],
            $script_asset["version"],
            true
        );

        wp_enqueue_style(
            "maxelpay-checkout",
            MAXELPAY_ASSETS_PATH . "css/maxelpay-checkout.css",
            null,
            MAXELPAY_VERSION
        );
        
        return [ "wc-maxplay-blocks-integration" ];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
    
        return [

            "title" => !empty( $this->get_setting("maxelpay_title") )
                ? sanitize_text_field($this->get_setting("maxelpay_title"))
                : __("MaxelPay (Crypto)", "maxelpay"),

            "description" => !empty( $this->get_setting("maxelpay_method_description") )
                ? sanitize_text_field( $this->get_setting("maxelpay_method_description") )
                : __(
                    "Use MaxelPay for secure crypto payments.",
                    "maxelpay"
                ),

            "icon" => MAXELPAY_ASSETS_PATH . 'images/maxelpay.svg',

            "supports" => array_filter($this->gateway->supports, [
                $this->gateway,
                "supports",
            ]),

            "order_button_text" => !empty( $this->get_setting('maxelpay_order_button_txt') ) ? sanitize_text_field( $this->get_setting('maxelpay_order_button_txt') ) : __( "Pay With MaxelPay", "maxelpay")
        ];
    }
}
