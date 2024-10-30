<?php

namespace MAXELPAY\Api;

if ( !defined( 'ABSPATH' ) ) {
    exit();
}

/**
 * This file is used to handle Api Exception.
 * 
 * @package     api/MAXELPAY_Request_Builder_Exception
 */

if ( ! class_exists( 'MAXELPAY_Request_Builder_Exception' ) ) {
    final class MAXELPAY_Request_Builder_Exception extends \Exception {

        /**
         * @var string
         */
        private $method;

        /**
         * @var array
         */
        private $errors;

        /**
         * @param string $message
         * @param int $responseCode
         * @param string $uri
         * @param null|mixed $previous
         */
        public function __construct( $message, $responseCode, $uri, $errors = [], $previous = null ) {

            $this->method = $uri;
            $this->errors = $errors;

            parent::__construct( $message, $responseCode, $previous );
        }

        /**
         * @return string
         */
        public function getMethod() {

            return $this->method;
        }

        /**
         * @return array
         */
        public function getErrors() {
            
            return $this->errors;
        }
    }
}