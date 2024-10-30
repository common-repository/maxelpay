<?php

namespace MAXELPAY\Logger;
use MAXELPAY\Woo\MAXELPAY_Gateway as MAXELPAY_Gateway;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MAXELPAY_Logger
 * 
 * @package includes/MAXELPAY_Logger
 */
if ( ! class_exists( 'MAXELPAY_Logger' ) ) {
    final class MAXELPAY_Logger {
    
        /**
         * Log the provided message in the WC logs directory.
         *
         * @param int    $level
         * @param string $message
         */
        public static function log( $level, $message ) {

            if ( ! class_exists( 'WC_Logger' ) ) {
                return;
            }
            
            $gateway    = new MAXELPAY_Gateway();
            if ( $gateway->get_option('maxelpay_debug_log') =='yes' ) {
                $log = \wc_get_logger();
                $log->log( $level, $message, array( 'source' => MAXELPAY_PLUGIN_NAME ) );
            }
        }

        /**
         * Log an error message.
         *
         * @param string $message
         */
        public static function log_error( $message ) {
            self::log( \WC_Log_Levels::ERROR, $message );
        }

        /**
         * Log an informational message.
         *
         * @param string $message
         */
        public static function log_info( $message ) {
            self::log( \WC_Log_Levels::INFO, $message );
        }

        /**
         * Log an alert message.
         *
         * @param string $message
         */
        public static function log_alert( $message ) {
            self::log( \WC_Log_Levels::ALERT, $message );
        }
    }
}