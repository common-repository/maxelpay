<?php

namespace MAXELPAY\Woo;

if ( !defined( 'ABSPATH' ) ) {
    exit();
}

/**
 * This file is used for keeping track of payment statuses and it's used together with a webhook.
 * 
 * @package     api/MAXELPAY_Payment_Status
 */

if ( ! class_exists( 'MAXELPAY_Payment_Status' ) ) {
    final class MAXELPAY_Payment_Status {

        const MAXELPAY_STATUS_REFUNDED     = 'refunded';
        const MAXELPAY_STATUS_PENDING      = 'pending';
        const MAXELPAY_STATUS_PROCESSING   = 'processing';
        const MAXELPAY_STATUS_COMPLETED    = 'completed';
        const MAXELPAY_STATUS_FAIL         = 'failed';
        const MAXELPAY_STATUS_CANCELED     = 'cancelled';
        const MAXELPAY_STATUS_HOLD         = 'on-hold';
        const MAXELPAY_STATUS_WRONG_AMOUNT = 'wrong-amount';
        
        public static function maxelpay_return_stocks($status) {
            
            if ( $status === self::MAXELPAY_STATUS_CANCELED || $status === self::MAXELPAY_STATUS_FAIL ) {

                return true;
            }

            return false;
        }
    }
}