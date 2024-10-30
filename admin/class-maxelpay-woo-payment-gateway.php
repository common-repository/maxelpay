<?php

namespace MAXELPAY\Woo;
use MAXELPAY\Api\MAXELPAY_Request_Builder;
use WC_Payment_Gateway;
use MAXELPAY\Logger\MAXELPAY_Logger as MAXELPAY_Logger; 

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * This file helps set up how payments are handled and deals with the information for orders.
 * 
 * @package     admin/MAXELPAY_Gateway
 */
class MAXELPAY_Gateway extends WC_Payment_Gateway {

    /**
     * @var string
     */
    public $payment_key;

    /**
     * @var string
     */
    private $payment_secret_key;
    
    /**
     * @var string
     */
    private $payment;

    /**
     * @var string
     */
    private $environment;
    
    /**
    * Constructor.
    *
    * @access public
    */
    public function __construct() {
        
        $this->id                      =  'maxelpay';
        $this->icon                    =  MAXELPAY_ASSETS_PATH . 'images/maxelpay.svg';
        $this->has_fields              =  true;
        $this->method_title            =  __( 'MaxelPay (Crypto)', 'maxelpay' );
        $this->method_description      =  __( 'Use MaxelPay for secure crypto payments.', 'maxelpay' );
        $this->enabled                 =  $this->get_option('enabled');
        $this->title                   =  !empty( $this->get_option('maxelpay_title') ) ? sanitize_text_field( $this->get_option('maxelpay_title') ) : 'MaxelPay (Crypto)';
        $this->description             =  !empty( $this->get_option('maxelpay_method_description') ) ? sanitize_text_field( $this->get_option('maxelpay_method_description') ) : 'Use MaxelPay for secure crypto payments.';     
        $this->order_button_text       =  !empty( $this->get_option('maxelpay_order_button_txt') ) ? sanitize_text_field( $this->get_option('maxelpay_order_button_txt') ) : 'Pay With MaxelPay';
        $this->environment             =  !empty( $this->get_option('maxelpay_environment') ) ? sanitize_text_field ( $this->get_option('maxelpay_environment') ) : 'Staging';
        $this->payment_key             =  ($this->environment == 'prod') ? sanitize_text_field($this->get_option('maxelpay_payment_key')) : sanitize_text_field($this->get_option('maxelpay_stg_payment_key'));
        $this->payment_secret_key      =  ($this->environment == 'prod') ? sanitize_text_field($this->get_option('maxelpay_payment_secret_key')) : sanitize_text_field($this->get_option('maxelpay_stg_payment_secret_key'));
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->payment                 =  new MAXELPAY_Request_Builder( $this->payment_key, $this->payment_secret_key, $this->environment );
        
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        

        $this->supports = array(
            'products'
        );
    }
   

    /**
     * @param $order_id
     * 
     * @access public
     */
    public function process_payment( $order_id ) {
        
        global $woocommerce;
        
        $order = wc_get_order( $order_id );
        
        try {
            
            if ( ! $order ) {

                MAXELPAY_Logger::log_info( 'Invalid order ID');
		        throw new \Exception( __( 'Invalid order ID.', 'maxelpay' ) );

		    }

            $currency           = isset( $order ) && !empty( $order->get_currency() ) ? sanitize_text_field( $order->get_currency() ) : '';
            
            if (!$this->payment->maxelpay_supported_currency($currency)) {

                MAXELPAY_Logger::log_alert($currency.' is not a supported currency on MaxelPay. Please choose a different one.');
               
                /* translators: %s: currency */
				throw new \Exception( sprintf( esc_html__( '%s is not a supported currency on MaxelPay. Please choose a different one.', 'maxelpay' ), esc_html($currency) ) );
            } 
            
            $user_name          = isset( $order ) && !empty( $order->get_billing_first_name() ) ? sanitize_text_field( $order->get_billing_first_name() ) : '';
            $billing_email      = isset( $order ) && !empty( $order->get_billing_email() ) ? sanitize_email( $order->get_billing_email() ) : '';
            $amount             = isset( $order ) && !empty( $order->get_total() ) ? floatval( $order->get_total() ) : 0.0;
            $timestamp          = isset( $order ) && !empty( $order->get_date_created() ) ? $order->get_date_created()->getTimestamp() : time(); 
            $cancel_order_url   = isset( $order ) && !empty( $order->get_cancel_order_url( $order_id ) ) ? sanitize_url( $order->get_cancel_order_url($order_id) ) : '';
            $success_url        = isset( $order ) && !empty( $order->get_checkout_order_received_url( $order_id ) ) ? sanitize_url( $order->get_checkout_order_received_url($order_id) ) : '';
            $website_url        = isset( $order ) && !empty( get_site_url() ) ? sanitize_url( get_site_url() ) : '';
            
            $data               = [
            
                'orderID'       => $order_id,
                'amount'        => $amount,
                'currency'      => $currency,
                'timestamp'     => $timestamp,
                'userEmail'     => $billing_email,
                'userName'      => $user_name,
                'redirectUrl'   => $success_url,
                'websiteUrl'    => $website_url,
                'cancelUrl'     => $cancel_order_url,
                'siteName'      => sanitize_text_field(get_bloginfo( 'name' )),
                'webhookUrl'    => esc_url( MAXELPAY_WEBHOOK_URL )
            ];

            MAXELPAY_Logger::log_info(
                wc_print_r( $data, true )
            );
            
            $gateway_url        = $this->payment->maxelpay_create($data);
            return ['result' => 'success', 'redirect' => $gateway_url];
            
        } catch ( \Exception $e ) {
            
            $order->update_status(MAXELPAY_Payment_Status::MAXELPAY_STATUS_FAIL);

            $order->save();

            MAXELPAY_Logger::log_info( $e->getMessage() );

            throw new \Exception(esc_html($e->getMessage()));
        }

        MAXELPAY_Logger::log_alert( 'Payment could not be processed' );
        throw new \Exception(esc_html__('Payment could not be processed, please try again!', 'maxelpay'));
    }
    
    /**
     * Initialization form fields
     * 
     * @access public
     */
    public function init_form_fields() {
        
        $this->form_fields = array(

            'enabled' => array(
                'title' => 'Enable/Disable',
                'label' => 'Enable MaxelPay',
                'type' => 'checkbox',
                'description' => '',
                'default' => 'yes',
            ),
            
            'maxelpay_environment' => array(

				'type'        => 'select',
				'title'       => __( 'Environment', 'maxelpay' ),
				'default'     => 'stg',
				'options'     => [
					'stg'    => __( 'Staging', 'maxelpay' ),
					'prod' => __( 'Production', 'maxelpay' )
				],
				'desc_tip'    => true,
				'description' => __( 'This option determines whether you are processing real transactions or test transactions.', 'maxelpay' )
            ),

            'maxelpay_title' => array(
                'title' => __('Title', 'maxelpay'),
                'type' => 'text',
                'description' => __('This controls the title for the payment method the customer sees during checkout.', 'maxelpay'),
                'default' => __('MaxelPay (crypto)','maxelpay'),
                'desc_tip' => true,
            ),
            
            'maxelpay_method_description' => array(
                'title' => 'Description',
                'type' => 'text',
                'desc_tip' => true,
                'description' =>  __('This controls the description for the payment method the customer sees during checkout.', 'maxelpay'), 
                'default' => __('Use MaxelPay for secure crypto payments.','maxelpay'),               
            ),
            
            'maxelpay_order_button_txt' => array(
                'title' => 'Pay With MaxelPay Button',
                'type' => 'text',
                'desc_tip' => true,
                'description' =>  __('This controls the button for the payment method the customer sees during checkout.', 'maxelpay'), 
                'default' => __('Pay With MaxelPay','maxelpay'),               
            ),
            
            'maxelpay_payment_key' => array(
                'title' => 'API Key (Production)',
                'description' => __('You can locate the API Key by going to the settings in your MaxelPay account.','maxelpay'),
                'desc_tip' => true,
                'type' => 'text'
            ),
            
            'maxelpay_payment_secret_key' => array(
                'title' => 'API Secret (Production)',
                'description' => __('You can locate the API Secret by going to the settings in your MaxelPay account.','maxelpay'),
                'desc_tip' => true,
                'type' => 'password'
            ),

            'maxelpay_stg_payment_key' => array(
                'title' => 'API Key (Staging)',
                'description' => __('You can locate the API Key by going to the settings in your MaxelPay account.','maxelpay'),
                'desc_tip' => true,
                'type' => 'text'
            ),

            'maxelpay_stg_payment_secret_key' => array(
                'type' => 'password',
                'title' => 'API Secret (Staging)',
                'description' => __('You can locate the API Secret by going to the settings in your MaxelPay account.','maxelpay'),
                'desc_tip' => true,
                
            ),

            'maxelpay_webhook_url' => array(
                'title' => 'Webhook URL',
                'description' => __('Copy this url and add in <a href="https://dashboard.maxelpay.com/developers" target="_blank"><b>MaxelPay developers</b></a> area. This is required to add for communication between website and MaxelPay.','maxelpay'),
                'desc_tip' => false,
                'type' => 'url',
                'default'     => esc_url( MAXELPAY_WEBHOOK_URL ),
                'custom_attributes' => array(
                  'readonly' => 'readonly'
                )
            ),

            'maxelpay_debug_log'            => array(
                'title'       => __( 'Debug Log', 'maxelpay' ),
                'type'        => 'checkbox',
                'desc_tip'    => false,
                'default'     => 'yes',
                'description' => __( 'When you turn it on, the plugin keeps a record of important errors, information, and warnings that could help you figure out and fix problems.', 'maxelpay' ),
            ),
        );
    }

    /**
     * validate mexelpay fields
     * 
     * @return string
     * @access public
     */
    public function validate_fields() {
        
        $const_msg = $this->maxelpay_const_messages();
        
        if ( !(($this->payment_key) && ($this->payment_secret_key)) ) {

            $key = empty($this->payment_key) ? 'payment_key' : 'secret_key';
            MAXELPAY_Logger::log_info('Payment ' . ($key == 'payment_key' ? 'Api' : 'Secret') . ' key is empty');
            return $this->maxelpay_add_error_custom_notice($const_msg[$key]);
        }
        
        return true;
    }
   
    /**
     * @param $error_message
     * @param array $link
     * 
     * @return bool|mixed
     * @access protected
     * 
     */
    protected function maxelpay_add_error_custom_notice( $error_message, $link = true ) {
        
        if ( !empty( $error_message ) ) {

            $error_message = current_user_can('manage_options') 
            ? $error_message
            : __('Something went wrong!', 'maxelpay');

            $sanitized_message = wp_kses_post($error_message);

            wc_add_notice('<strong>' . $sanitized_message . '</strong>', 'error');
            
        }
        
        return true;
    }

    /**
     * @param $message
     * 
     * @return array
     */
    protected function maxelpay_const_messages() {

        $messages   =  "";
        $env        =  ($this->environment == 'prod') ? 'Production' : 'Staging';
        
        $messages = array(
            /* translators: %s: Api key*/
            'payment_key' => sprintf(esc_html__( 'Please Enter %s Api Key.', 'maxelpay' ),esc_html( $env )),
            /* translators: %s: Secret key*/
            'secret_key' => sprintf(esc_html__( 'Please Enter %s Secret Key.', 'maxelpay' ),esc_html( $env )),
        );
        
        return $messages;
    }
}
