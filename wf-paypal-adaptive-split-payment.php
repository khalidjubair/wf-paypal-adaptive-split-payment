<?php
/**
 * Plugin Name: WF PayPal Adaptive Split Payment
 * Plugin URI:https://xpeedstudio.com
 * Description: The ultimate WooCommerce Supported PayPal Adaptive Split Payment System
 * Author: XpeedStudio
 * Author URI: https://xpeedstudio.com
 * Version:1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define('WF_PAYPAL_ADAPTIVE_SPLIT_PAYMENT_DIR_PATH', plugin_dir_path(__FILE__));
define('WF_PAYPAL_ADAPTIVE_SPLIT_PAYMENT_DIR_URL', plugin_dir_url(__FILE__));
define('WF_PAYPAL_ADAPTIVE_SPLIT_PAYMENT_VERSION', '1.0');
include_once (ABSPATH . 'wp-admin/includes/plugin.php');
require_once WF_PAYPAL_ADAPTIVE_SPLIT_PAYMENT_DIR_PATH.'inc/class.wf-payment-review-page.php';
require_once WF_PAYPAL_ADAPTIVE_SPLIT_PAYMENT_DIR_PATH.'inc/wf-paypal-adaptive-init.php';


require_once( wp_normalize_path(ABSPATH).'wp-load.php');


if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    if (class_exists('WF_Paypal_Adaptive_Split_Payment')) {

    }
}