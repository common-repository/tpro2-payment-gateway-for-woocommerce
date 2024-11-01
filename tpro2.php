<?php
/*
Plugin Name: WooCommerce TPro2 Payment Gateway
Plugin URI: http://www.2cpusa.com
Description: TPro2 Payment gateway for woocommerce
Version: 3.0.3
Author: 2C Processor USA
Author URI: http://www.2cpusa.com
 */

function woocommerce_tpro2_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) )
		return;

	class WC_tpro2 extends WC_Payment_Gateway {
        public $version = '3.0.3';
        public static $log = false;
        function __construct() {            
            $this->supports = array( 
                   'products', 
                   'subscriptions',
                   'subscription_cancellation', 
                   'subscription_suspension', 
                   'subscription_reactivation',
                   'subscription_amount_changes',
                   'subscription_date_changes',
                   'subscription_payment_method_change',
                   'default_credit_card_form',
				   'shop_subscription',
                   'multiple_subscriptions'
              );
            $this->version = $version;
            $this->id						= 'tpro2';
            $this->medthod_title            = 'TPro2';
            $this->method_description		= __( 'Transaction Pro works by adding credit card fields on the checkout and then sending the details for verification.', 'wc_tpro2' );
            $this->has_fields				= true;
            $this->trans_type				= true;

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title          	= $this->get_option( 'title' );
            $this->description    	= $this->get_option( 'description' );
            $this->enabled			= $this->get_option( 'enabled' );
            $this->client_id		= $this->get_option( 'client_id' );
            $this->merchant_id		= $this->get_option( 'merchant_id' );
            $this->security_token	= $this->get_option( 'security_token' );
            $this->paymentaction	= $this->get_option( 'paymentaction' );
            $this->send_items		= isset( $this->settings['send_items'] ) && $this->settings['send_items'] == 'yes' ? true : false;
            $this->debug_email    	= $this->get_option( 'debug_email' );
            
            add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ),10, 2 );
        }
        public static function log( $message ) {            
            if ( empty( self::$log ) ) {
                self::$log = new WC_Logger();
            }
            self::$log->add( 'tpro2', $message );            
        }
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled'           => array(
                    'title'         => __( 'Enable/Disable', 'woocommerce' ),
                    'type'          => 'checkbox',
                    'label'         => __( 'Enable TPro2', 'woocommerce' ),
                    'default'       => 'yes'
                ),
                'title'             => array(
                    'title'         => __( 'Title', 'woocommerce' ),
                    'type'          => 'text',
                    'description'   => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default'       => __( 'Credit card', 'woocommerce' ),
                    'desc_tip'      => true
                ),
                'description'       => array(
                    'title'         => __( 'Description', 'woocommerce' ),
                    'type'          => 'text',
                    'desc_tip'      => true,
                    'description'   => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
                    'default'       => __( 'Pay with your credit card.', 'woocommerce' )
                ),
                'api_details'       => array(
                    'title'         => __( 'API Credentials', 'woocommerce' ),
                    'type'          => 'title',
                    'description'   => __( 'Enter your TPro2 API credentials to process.', 'woocommerce' ),
                ),
                'client_id'         => array(
                    'title'         => __( 'Client ID', 'woocommerce' ),
                    'type'          => 'text',
                    'description'   => __( 'Get your API credentials from the TPro2 Remote Processing Administration section.', 'woocommerce' ),
                    'default'       => '',
                    'desc_tip'      => true,
                    'placeholder'   => __( 'Client ID', 'woocommerce' )
                ),
                'merchant_id'       => array(
                    'title'         => __( 'Merchant Account ID', 'woocommerce' ),
                    'type'          => 'text',
                    'description'   => __( 'Get your API credentials from the TPro2 Remote Processing Administration section.', 'woocommerce' ),
                    'default'       => '',
                    'desc_tip'      => true,
                    'placeholder'   => __( 'Merchant ID', 'woocommerce' )
                ),
                'security_token'    => array(
                    'title'         => __( 'Security Token', 'woocommerce' ),
                    'type'          => 'text',
                    'description'   => __( 'Get your API credentials from the TPro2 Remote Processing Administration section.', 'woocommerce' ),
                    'default'       => '',
                    'desc_tip'      => true,
                    'placeholder'   => __( 'Token', 'woocommerce' )
                ),
                'payment_details'   => array(
                    'title'         => __( 'Payment Settings', 'woocommerce' ),
                    'type'          => 'title',
                    'description'   => __( 'Select your processing options.', 'woocommerce' ),
                ),
                'paymentaction'     => array(
                    'title'         => __( 'Payment Action', 'woocommerce' ),
                    'type'          => 'select',
                    'class'         => 'wc-enhanced-select',
                    'description'   => __( 'Choose whether you wish to capture funds immediately or authorize the payment only.', 'woocommerce' ),
                    'default'       => 'sale',
                    'desc_tip'      => true,
                    'options'       => array(
                        'sale'          => __( 'Capture', 'woocommerce' ),
                        'authorization' => __( 'Authorize', 'woocommerce' )
                    )
                ),
                'debug_email'       => array(
                    'title'         => __( 'Debug Email', 'woocommerce'),
                    'type'          => 'email',
                    'description'   => __( 'This Email address will receive the transaction debug information.', 'woocommerce'),
                    'default'       => '',
                    'desc_tip'      => true,
                    'placeholder'   => 'you@youremail.com'
                ),
            );
        }

        function validate_fields() {
            global $woocommerce;

            $card_number    = isset( $_POST['tpro2-card-number'] ) ? woocommerce_clean( $_POST['tpro2-card-number'] ) : '';
            $card_cvc       = isset( $_POST['tpro2-card-cvc'] ) ? woocommerce_clean( $_POST['tpro2-card-cvc'] ) : '';
            $card_expiry    = isset( $_POST['tpro2-card-expiry'] ) ? woocommerce_clean( $_POST['tpro2-card-expiry'] ) : '';

            $card_number    = str_replace( array( ' ', '-' ), '', $card_number );
            $card_expiry    = array_map( 'trim', explode( '/', $card_expiry ) );
            $card_exp_month = str_pad( $card_expiry[0], 2, "0", STR_PAD_LEFT );
            $card_exp_year  = $card_expiry[1];

            if ( strlen( $card_exp_year ) == 2 )
                $card_exp_year += 2000;

            if ( ! ctype_digit( $card_cvc ) ) {
                $woocommerce->add_error( __( 'Card security code is invalid (only digits are allowed)', 'wc_tpro2' ) );
                return false;
            }

            if ( ! ctype_digit( $card_exp_month ) ||
                 ! ctype_digit( $card_exp_year ) ||
                 $card_exp_month > 12 ||
                 $card_exp_month < 1 ||
                 $card_exp_year < date( 'y' )
            ) {
                $woocommerce->add_error( __( 'Card expiration date is invalid', 'wc_tpro2' ) );
                return false;
            }

            if ( empty( $card_number ) || ! ctype_digit( $card_number ) ) {
                $woocommerce->add_error( __( 'Card number is invalid', 'wc_tpro2' ) );
                return false;
            }

            return true;
        }

        function process_payment($order_id) {
            global $woocommerce;
            $order = new WC_Order( $order_id );

            $card_number    = isset( $_POST['tpro2-card-number'] ) ? woocommerce_clean( $_POST['tpro2-card-number'] ) : '';
            $card_cvc       = isset( $_POST['tpro2-card-cvc'] ) ? woocommerce_clean( $_POST['tpro2-card-cvc'] ) : '';
            $card_expiry    = isset( $_POST['tpro2-card-expiry'] ) ? woocommerce_clean( $_POST['tpro2-card-expiry'] ) : '';

            $card_number    = str_replace( array( ' ', '-' ), '', $card_number );
            $card_expiry    = array_map( 'trim', explode( '/', $card_expiry ) );
            $card_exp_month = str_pad( $card_expiry[0], 2, "0", STR_PAD_LEFT );
            $card_exp_year  = $card_expiry[1];

            if ( strlen( $card_exp_year ) == 2 )
                $card_exp_year += 2000;
            
            try {
                $post_data = array(
                    'CLIENTID'          => $this->client_id,
                    'MERCHANTACCOUNTID' => $this->merchant_id,
                    'TOKEN'             => $this->security_token,
                    'TRANSACTIONTYPE'   => $this->paymentaction,
                    'ORDERNO'           => $order->get_order_number(),
                    'AMOUNT'            => $order->get_total(),
                    'CARDHOLDERNUMBER'  => $card_number,
                    'EXPIRESMONTH'      => $card_exp_month,
                    'EXPIRESYEAR'       => $card_exp_year,
                    'CVV2'              => $card_cvc,
                    'BNAME'				=> $order->billing_first_name . ' ' . $order->billing_last_name,
                    'BCOMPANY'          => $order->billing_company,
                    'BEMAIL'            => $order->billing_email,
                    'BADDRESS1'         => $order->billing_address_1,
                    'BADDRESS2'         => $order->billing_address_2,
                    'BCITY'             => $order->billing_city,
                    'BSTATE'            => $order->billing_state,
                    'BZIPCODE'          => $order->billing_postcode,
                    'BCOUNTRYID'        => 1, //$order->billing_country,
                    'BPHONE'            => $order->billing_phone,
                    'SNAME'             => $order->shipping_first_name . ' ' . $order->shipping_last_name,
                    'SCOMPANY'          => $order->shipping_company,
                    'SEMAIL'            => $order->shipping_email,
                    'SADDRESS1'         => $order->shipping_address_1,
                    'SADDRESS2'         => $order->shipping_address_2,
                    'SCITY'             => $order->shipping_city,
                    'SSTATE'            => $order->shipping_state,
                    'SZIPCODE'          => $order->shipping_postcode,
                    'SCOUNTRYID'        => 1, //$order->shipping_country,
                    'DEBUG'             => $this->debug_email,
                    'DESCRIPTION'       => $order->customer_note
                );
                
                $response = wp_remote_post( 'https://gateway.tprosecure.com/ccgateway.asp', array(
                        'method'        => 'POST',
                        'body'          => apply_filters( 'wc_tpro2_request', $post_data),
                        'timeout'       => 70,
                        'sslverify'     => true,
                        'user-agent'    => 'TPro3WooCommerce',
                        'httpversion'   => '1.1'
                    ) );
                if ( is_wp_error( $response ) ) {
                    throw new Exception( __( 'There was a problem connecting to the payment gateway.', 'wc_tpro2' ) );
                }

                if ( empty( $response['body'] ) )
                    throw new Exception( __( 'Empty Transaction Pro response.', 'wc_tpro2' ) );

                parse_str( $response['body'], $parsed_response );
                switch ( strtolower( $parsed_response['APPROVED'] ) ) :
                    case 'y':
                        wc_add_notice(__('Successfully Created Transaction.<br/>Authorization Code: '.$parsed_response["SEQNO"]), $notice_type = 'success' );                
                        $order->add_order_note( sprintf( __( 'Transaction Pro payment completed (Sequence Number: %s, Order Number: %s)', 'wc_tpro2' ), $parsed_response['SEQNO'], $parsed_response['ORDERNO'] ) );                        
                        $order->payment_complete($transactionnode["id"]);
                        $order->update_status('completed', __('Transaction successful', 'woocommerce'));
                        
                        //ADD TPRO CUSTOMER CODE TO USERMETA for auto payment 
                        $user_id = get_current_user_id();
                        $meta_key = 'tpro_customer_code';
                        $meta_value = $parsed_response['CUSTOMERCODE'];
                        update_user_meta( $user_id, $meta_key, $meta_value );

                        return array(
                            'result' => 'success',
                            'redirect' => $this->get_return_url($order));
                        break;
                    default:
                        wc_add_notice(__('DECLINED: '.$parsed_response['MSG']), $notice_type = 'error' );
                        return;
                        break;
                endswitch;

            }
            catch( Exception $e ) {
                if ( $this->debug=='yes' )
                    $this->log->add( 'wc_tpro2', 'Error Occurred while processing the order #' . $order );
            }
			return;
        }
        
        //RECURRING PAYMENT FUNCTION
        
        function do_recurring_payment( $order,$tpro_customer_code  ){
            WC_tpro2::log( 'Generating payment form for order ' . $order->get_order_number());
            global $woocommerce;

            try {            
                $post_data = array(
                    'CLIENTID'          => $this->client_id,
                    'MERCHANTACCOUNTID' => $this->merchant_id,
                    'TOKEN'             => $this->security_token,
                    'TRANSACTIONTYPE'   => $this->paymentaction,
                    'ORDERNO'           => $order->get_order_number(),
                    'AMOUNT'            => $order->get_total(),
                    'CUSTOMERCODE'		=> $tpro_customer_code,
                    'BNAME'				=> $order->billing_first_name . ' ' . $order->billing_last_name,
                    'BCOMPANY'          => $order->billing_company,
                    'BEMAIL'            => $order->billing_email,
                    'BADDRESS1'         => $order->billing_address_1,
                    'BADDRESS2'         => $order->billing_address_2,
                    'BCITY'             => $order->billing_city,
                    'BSTATE'            => $order->billing_state,
                    'BZIPCODE'          => $order->billing_postcode,
                    'BCOUNTRYID'        => 1, //$order->billing_country,
                    'BPHONE'            => $order->billing_phone,
                    'SNAME'             => $order->shipping_first_name . ' ' . $order->shipping_last_name,
                    'SCOMPANY'          => $order->shipping_company,
                    'SEMAIL'            => $order->shipping_email,
                    'SADDRESS1'         => $order->shipping_address_1,
                    'SADDRESS2'         => $order->shipping_address_2,
                    'SCITY'             => $order->shipping_city,
                    'SSTATE'            => $order->shipping_state,
                    'SZIPCODE'          => $order->shipping_postcode,
                    'SCOUNTRYID'        => 1, //$order->shipping_country,
                    'DEBUG'             => $this->debug_email,
                    'DESCRIPTION'       => $order->customer_note
                );                               

                $response = wp_remote_post( 'https://gateway.tprosecure.com/ccgateway.asp', array(
                        'method'        => 'POST',
                        'body'          => apply_filters( 'wc_tpro2_request', $post_data ),
                        'timeout'       => 70,
                        'sslverify'     => true,
                        'user-agent'    => 'TPro2WooCommerce',
                        'httpversion'   => '1.1'
                    ) );

                if ( is_wp_error( $response ) ) {
                    wc_add_notice( __( 'There was a problem connecting to the payment gateway.', 'wc_tpro2' ) );
                    throw new Exception( __( 'There was a problem connecting to the payment gateway.', 'wc_tpro2' ) );
                }

                if ( empty( $response['body'] ) ) {
                    wc_add_notice(  __( 'Empty Transaction Pro response.', 'wc_tpro2' ) );
                    throw new Exception( __( 'Empty Transaction Pro response.', 'wc_tpro2' ) );
                }

                parse_str( $response['body'], $parsed_response );

                switch ( strtolower( $parsed_response['APPROVED'] ) ) :
                    case 'y':
                        $order->add_order_note( sprintf( __( 'Transaction Pro payment completed (Sequence Number: %s, Order Number: %s)', 'wc_tpro2' ), $parsed_response['SEQNO'], $parsed_response['ORDERNO'] ) );
                        $order->payment_complete();
                        break;
                    default:
                        wc_add_notice(__('DECLINED: '.$parsed_response['MSG']), $notice_type = 'error' );
                        return;
                        break;
                endswitch;

            }
            catch( Exception $e ) {
                if ( $this->debug=='yes' )
                    $this->log->add( 'wc_tpro2', 'Error Occurred while processing the order #' . $order );
            }
			return;
            
        }
        
        //AUTO RENEWAL FUNCTION
        function scheduled_subscription_payment($amount_to_charge, $renewal_order)
		{
            $user_id = $renewal_order->user_id;
            $tpro_customer_code = get_user_meta($user_id, 'tpro_customer_code',true );
            $response = $this->do_recurring_payment($renewal_order,$tpro_customer_code);
            if ( is_wp_error( $response ) ) {
                $renewal_order->update_status( 'failed', sprintf( __( 'TPRO Transaction Failed (%s)', 'wc_tpro2' ), $response->get_error_message() ) );
            }
        }
    }

	function add_tpro2_gateway( $methods ) {
		$methods[] = 'WC_tpro2';
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'add_tpro2_gateway' );
}

add_action( 'plugins_loaded', 'woocommerce_tpro2_init', 0 );