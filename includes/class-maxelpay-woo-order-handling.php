<?php

namespace MAXELPAY\Woo;
use MAXELPAY\Woo\MAXELPAY_Payment_Status as MAXELPAY_Payment_Status;
use MAXELPAY\Woo\MAXELPAY_Gateway as MAXELPAY_Gateway;
use MAXELPAY\Logger\MAXELPAY_Logger as MAXELPAY_Logger; 

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MAXELPAY_Woo_Order_Handling
 */
if ( ! class_exists( 'MAXELPAY_Woo_Order_Handling ' ) ) {
    final class MAXELPAY_Woo_Order_Handling  {

        /**
         * Plugin instance.
         *
         * @var MAXELPAY_Woo_Order_Handling 
         * @access private
         */

        private static $instance = null;

       /**
        * Retrieves an instance of the plugin handler class.
        *
        * @return MAXELPAY_Woo_Order_Handling Returns an instance of MAXELPAY_Woo_Order_Handling.
        * @static
        */
        public static function get_instance() {

            if ( ! isset( self::$instance ) ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
        * Constructor method.
        *
        * @access private
        */
        private function __construct() {

            add_action( 'rest_api_init', array( $this, 'maxelpay_register_order_status_route' ) );
            add_action( 'woocommerce_thankyou', array($this, 'maxelpay_set_transaction_id' ),10,1);
            add_action( 'woocommerce_order_status_cancelled',array( $this,'maxelpay_process_order_cancellation' ) );
            
        }

       /**
        * Define routes for webhook handling.
        *
        * @access public
        */
        public function maxelpay_register_order_status_route() {
            
            register_rest_route( MAXELPAY_PLUGIN_NAME, MAXELPAY_API_SUB_PATH, array(

                'methods' => 'POST',
                'callback' => array( $this,'maxelpay_update_order_status' ),
                'permission_callback' => '__return_true',
                
            ));
        }

        /**
        * Update the status of an order based on the provided request.
        *
        * @param mixed $request The data containing the order status update.
        * @access public
        */
        public function maxelpay_update_order_status( $request ) {
            
            MAXELPAY_Logger::log_info( 'Webhook is triggered.' );

            $api_key    = !empty( $request->get_header( 'api-key' ) ) ? sanitize_text_field($request->get_header( 'api-key' )) : '';
            
            $gateway    = new MAXELPAY_Gateway();

            if( empty( $api_key ) || $api_key !== $gateway->payment_key ) {

                MAXELPAY_Logger::log_error( 'Api key is wrong or empty in the header.' );

                return new \WP_Error( 'invalid_api_key', 
                esc_html__( 'Api key is wrong or empty in the header.','maxelpay' ),
                array( 'status' => 401 ) );

            } else {
                
                    $data = !empty( $request->get_body() ) ? array_map('sanitize_text_field', json_decode(wp_unslash($request->get_body()), true)) : [];

                    MAXELPAY_Logger::log_info(
                        wc_print_r( $data, true )
                    );

                    if ( empty( $data ) ) { 
                        
                        MAXELPAY_Logger::log_error( 'There is no data found.' );

                        return new \WP_Error( 'empty_data', 
                        esc_html__( 'There is no data found.','maxelpay' ),
                        array( 'status' => 401 ) );

                    }
                
                    $order_id       = isset($data['order_id']) ? wc_sanitize_order_id($data['order_id']) : '';
                    $maxelpay_txn   = isset($data['maxelpay_txn']) ? sanitize_text_field($data['maxelpay_txn']) : '';
                    $payment_status = isset($data['payment_status']) ? sanitize_text_field($data['payment_status']) : '';
                    
                    if ( !( $order_id && $maxelpay_txn && $payment_status ) ) {

                        MAXELPAY_Logger::log_error( 'Unfortunately, we can`t update payment status. It seems the Order ID, Maxel ID, or payment status are unavailable.' );

                        return new \WP_Error( 'required_keys_missing', 
                        esc_html__( 'Unfortunately, we can`t update payment status. It seems the Order ID, Maxel ID, or payment status are unavailable.','maxelpay' ),
                        array( 'status' => 401 ) );
                        
                    }
                
                    $order  = wc_get_order( $order_id );
                    
                    if( !$order ) {

                        MAXELPAY_Logger::log_error('We couldn`t find any order with the order ID you provided.');

                        return new \WP_Error( 'order_not_found', 
                        esc_html__( 'We couldn`t find any order with the order ID you provided.','maxelpay' ),
                        array( 'status' => 401 ) );
                    }
                    
                    $transaction_id = isset( $order ) && !empty( $order->get_transaction_id() ) ? sanitize_text_field( $order->get_transaction_id() ) : '';
                    
                    if ( empty( $transaction_id ) ) {

                        $order->set_transaction_id($maxelpay_txn);
                        $order->save();
                        MAXELPAY_Logger::log_info('The transaction ID gets saved using a webhook.');
                    }
                    
                    $order->set_status( $payment_status  );
                    $order->save();

                    MAXELPAY_Logger::log_info('Payment status updated to '.$payment_status.'.');

                    if( $payment_status === 'completed' ){
                        /* translators: %s: payment */
                        $order->add_order_note( sprintf(esc_html__( '%1$s payment %2$s (payment ID: %3$s).', 'maxelpay' ),$order->get_payment_method_title(),esc_html( $payment_status ),esc_html( $maxelpay_txn )));
                        
                    }
                    
                    if ( MAXELPAY_Payment_Status::maxelpay_return_stocks( $payment_status ) ) {

                        MAXELPAY_Logger::log_info('Increased stock levels.');
                        wc_increase_stock_levels( $order );
                    }
                    
                    return new \WP_REST_Response(array(
                        'code' => 'payment_status_updated',
                        'message' => __('Payment status updated successfully.','maxelpay'),
                        'data' => array('status' => 200)
                    ));
                }
        }

        /**
        * Set transaction ID on the thank you page.
        *
        * @param mixed $order_id The ID of the order for which the transaction ID is being set.
        * @access public
        */
        public function maxelpay_set_transaction_id( $order_id ) {

            if ( ! $order_id ) {
                return;
            }
        
            $order          = wc_get_order($order_id); 

            $transaction_id = isset( $order ) && !empty( $order->get_transaction_id() ) ? sanitize_text_field( $order->get_transaction_id() ) : '';
        
            MAXELPAY_Logger::log_info( 'transaction id is '.$transaction_id );
            
            if ( isset( $order ) && empty( $transaction_id ) ) {

                $payment_method = !empty( $order->get_payment_method() ) ?  sanitize_text_field( $order->get_payment_method() ) : '' ;
            
                $mxl_id = filter_input(INPUT_GET, 'maxelpay_txn', FILTER_SANITIZE_STRING);
                
                $maxel_id = !empty($mxl_id) ? $mxl_id : '';
                
                MAXELPAY_Logger::log_info( 'Maxelpay transaction id is '.$maxel_id );
            
                if( !empty( $maxel_id ) && $payment_method == 'maxelpay' ) {

                    MAXELPAY_Logger::log_info( 'Order id '.$order_id.' Transaction id is => '.$maxel_id );
                
                    $order->set_transaction_id($maxel_id);
                    $order->save();
                }
            }
            
            return;
        }

        /**
        * Handle order cancellation based on the provided order ID.
        *
        * @param mixed $order_id The ID of the order to be cancelled.
        * @access public
        */
        public function maxelpay_process_order_cancellation( $order_id ) {
            
            MAXELPAY_Logger::log_info( 'Order number '.$order_id.' has been Cancelled.' );

            $order = wc_get_order( $order_id );
            
            if ( ! $order ) {

                MAXELPAY_Logger::log_info( 'No order found from the givn orderId '.$order_id.'' );
                return;
            }

            MAXELPAY_Logger::log_info('Increased stock levels.');

            wc_increase_stock_levels( $order );

            return;
        }
    }
}

function MAXELPAY_Woo_Order_Handling () {

	return MAXELPAY_Woo_Order_Handling ::get_instance();

}
MAXELPAY_Woo_Order_Handling ();
