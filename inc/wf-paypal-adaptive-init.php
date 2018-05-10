<?php
/**
 * Plugin URI:https://xpeedstudio.com
 * Description: The ultimate WooCommerce Supported PayPal Adaptive Split Payment System
 * Author: XpeedStudio
 * Author URI: https://xpeedstudio.com
 * Version:1.0.0
 */


if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'wf_check_woocommerce_is_active_for_split');
    return; // Return to stop the existing function to be call
}

function wf_check_woocommerce_is_active_for_split() {
    ?>
    <div class="error">
        <p><?php _e('PayPal Adaptive Split Payment will not work until WooCommerce Plugin is Activated. Please Activate the WooCommerce Plugin.', 'wf-paypal-adaptive-split-payment'); ?>
    </div>
    <?php
}


function init_paypal_adaptive() {
    if (!class_exists('WC_Payment_Gateway'))
        return;

    class WF_Paypal_Adaptive_Split_Payment extends WC_Payment_Gateway {

        function __construct() {
            $this->id = 'wf_paypal_adaptive';
            $this->method_title = 'PayPal Adaptive Split Payment';
            $this->has_fields = true;
            $this->icon = plugins_url('images/paypal.jpg', __FILE__);
            $this->init_form_fields();
            $this->init_settings();
            $this->split_by = $this->get_option('_split_by');
            $this->title = $this->get_option('title');
            $this->order_status = $this->get_option('troubleshoot_option');
            $this->description = $this->get_option('description');
            $this->shipping_details = $this->get_option('wf_shipping_details');
            $this->restrict_payment_gateways = $this->get_option('wf_restrict_payment_gateways');
            $this->testmode = $this->get_option('testmode');
            $this->notify_url = esc_url_raw(add_query_arg(array('ipn' => 'set'), site_url('/')));
            $this->security_user_id = $this->get_option('security_user_id');
            $this->security_password = $this->get_option('security_password');
            $this->security_signature = $this->get_option('security_signature');
            $this->security_application_id = $this->get_option('security_application_id');
            $this->table_checkbox = $this->get_option('split_table_checkbox');
            $this->payment_mode = $this->get_option('_wf_payment_mode');
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'save_account_details'));
        }

        public static function add_custom_meta_box() {
            $paypal_adaptive_payment = new WF_Paypal_Adaptive_Split_Payment();
            if ($paypal_adaptive_payment->table_checkbox == 'yes') {
                add_meta_box("demo-meta-box", __('Payment Split Details', 'wf-paypal-adaptive-split-payment'), "WF_Paypal_Adaptive_Split_Payment::custom_meta_box_markup", "shop_order", "advanced", "high", null);
            }
        }

        public static function custom_meta_box_markup($post) {
            $order_id = $post->ID;
            $pay_count = get_post_meta($order_id, 'pay_count', true);
            $total_amount = get_post_meta($order_id, 'wf_order_amt', true);
            $array = get_post_meta($order_id, 'wf_order_recievers', true);
            $payment_mode = new WF_Paypal_Adaptive_Split_Payment();
            ?>

            <table class="split_meta_table" style="width:100%">
                <th width="33.33%"><?php echo __('Receiver', 'wf-paypal-adaptive-split-payment'); ?></th>
                <th width="33.33%"><?php echo __('Percentage', 'wf-paypal-adaptive-split-payment'); ?>  </th>
                <th width="33.33%"><?php echo __('Amount', 'wf-paypal-adaptive-split-payment'); ?></th>

                <?php
                for ($i = 0; $i < $pay_count; $i++) {
                    if ($payment_mode->payment_mode != 'parallel') {
                        if ($array['receiverList.receiver(' . $i . ').primary'] == 'true') {
                            for ($j = 1; $j < $pay_count; $j++) {
                                $array['receiverList.receiver(' . $i . ').amount'] = $array['receiverList.receiver(' . $i . ').amount'] - $array['receiverList.receiver(' . $j . ').amount'];
                            }
                        }
                    }
                    ?>
                    <tr>
                        <td align="center">
                            <?php echo $array['receiverList.receiver(' . $i . ').email'];
                            ?>
                        </td>
                        <td align="center">
                            <?php echo $array['receiverList.receiver(' . $i . ').amount'] * 100 / $total_amount; ?> 
                        </td>
                        <td align="center">
                            <?php echo get_woocommerce_currency_symbol() . $array['receiverList.receiver(' . $i . ').amount'];
                            ?>  
                        </td >
                    </tr>
                <?php } ?>
            </table>          
            <?php
        }

        function init_form_fields() {
            global $wp_roles;
            if (!$wp_roles) {
                $wp_roles = new WP_Roles();
            }

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'checkbox',
                    'label' => __('PayPal Adaptive Split Payment', 'wf-paypal-adaptive-split-payment'),
                    'default' => 'yes'
                ),
                '_wf_payment_mode' => array(
                    'title' => __('Payment Mode', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'select',
                    'label' => __('PayPal Adaptive', 'wf-paypal-adaptive-split-payment'),
                    'default' => 'parallel',
                    'options' => array('parallel' => __('Parallel', 'wf-paypal-adaptive-split-payment'), 'chained' => __('Chained', 'wf-paypal-adaptive-split-payment'), 'delayed_chained' => __('Delayed Chained', 'wf-paypal-adaptive-split-payment'))
                ),
                '_wf_payment_parallel_fees' => array(
                    'title' => __('Payment Fees by', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'select',
                    'label' => __('Payment Fees by', 'wf-paypal-adaptive-split-payment'),
                    'default' => 'EACHRECEIVER',
                    'options' => array('SENDER' => __('Sender', 'wf-paypal-adaptive-split-payment'), 'EACHRECEIVER' => __('Each Receiver', 'wf-paypal-adaptive-split-payment'))
                ),
                '_wf_payment_chained_fees' => array(
                    'title' => __('Payment Fees by', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'select',
                    'label' => __('Payment Fees by', 'wf-paypal-adaptive-split-payment'),
                    'default' => 'EACHRECEIVER',
                    'options' => array('PRIMARYRECEIVER' => __('Primary Receiver', 'wf-paypal-adaptive-split-payment'), 'EACHRECEIVER' => __('Each Receiver', 'wf-paypal-adaptive-split-payment'))
                ),
                '_wf_delay_chained_period' => array(
                    'title' => __('No. of Days to Execute Payment to Receiver ', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'number',
                    'css' => 'width:10%',
                    'description' => __('Maximum delay up to 90 days can be set by the Admin.', 'wf-paypal-adaptive-split-payment'),
                    'default' => __('90', 'wf-paypal-adaptive-split-payment'),
                    'custom_attributes' => array(
                        'min' => 1,
                        'max' => 90,
                        'required' => 'required'
                    )
                ),
                'title' => array(
                    'title' => __('Title', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'wf-paypal-adaptive-split-payment'),
                    'default' => __('PayPal Adaptive Split Payment', 'wf-paypal-adaptive-split-payment'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'textarea',
                    'default' => 'Pay with PayPal Adaptive Split Payment. You can pay with your credit card if you don�t have a PayPal account',
                    'desc_tip' => true,
                    'description' => __('This controls the description which the user sees during checkout.', 'wf-paypal-adaptive-split-payment'),
                ),
                'wf_restrict_payment_gateways' => array(
                    'title' => __('Enable/Disable', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'checkbox',
                    'label' => __('Show only Paypal Split Payment Gateway when paypal split enabled products are added to the cart', 'wf-paypal-adaptive-split-payment'),
                    'default' => 'no'
                ),
                'wf_shipping_details' => array(
                    'title' => __('Use Customer Shipping Address in PayPal', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'select',
                    'description' => __('when enable, the customer\'s shipping address will be in PayPal shipping address', 'wf-paypal-adaptive-split-payment'),
                    'default' => 'show',
                    'options' => array('show' => __('Enable', 'wf-paypal-adaptive-split-payment'), 'hide' => __('Disable', 'wf-paypal-adaptive-split-payment'))
                ),
                'apidetails' => array(
                    'title' => __('API Authentication', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'title',
                    'description' => '',
                ),
                'security_user_id' => array(
                    'title' => __('API User ID', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'default' => '',
                    'desc_tip' => true,
                    'description' => __('Please enter your API User ID associated with your paypal account', 'wf-paypal-adaptive-split-payment'),
                ),
                'security_password' => array(
                    'title' => __('API Password', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'default' => '',
                    'desc_tip' => true,
                    'description' => __('Please enter your API Password associated with your paypal account', 'wf-paypal-adaptive-split-payment'),
                ),
                'security_signature' => array(
                    'title' => __('API Signature', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'default' => '',
                    'desc_tip' => true,
                    'description' => __('Please enter your API Signature associated with your paypal account', 'wf-paypal-adaptive-split-payment'),
                ),
                'security_application_id' => array(
                    'title' => __('Application ID', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'default' => '',
                    'desc_tip' => true,
                    'description' => __('Please enter your Application ID created with your paypal account', 'wf-paypal-adaptive-split-payment'),
                ),
                'hide_product_field_user_role' => array(
                    'title' => __('Hide Single Product Page PayPal Adaptive Settings for following User Roles', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'multiselect',
                    'css' => 'min-width:350px;',
                    'default' => array(get_role('multi_vendor') != null ? 'multi_vendor' : ''),
                    'options' => $wp_roles->get_names(),
                    'desc_tip' => true,
                    'description' => __('Hide Single Product Field based on User Role', 'wf-paypal-adaptive-split-payment'),
                ),
                'receivers_details' => array(
                    'title' => __('Receiver Details', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'title',
                    'description' => '',
                ),
                'pri_r_paypal_enable' => array(
                    'title' => __('Enable Receiver 1', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'checkbox',
                    'label' => __('', 'wf-paypal-adaptive-split-payment'),
                    'default' => 'yes',
                    'disabled' => true
                ),
                'pri_r_paypal_mail' => array(
                    'title' => __('Receiver 1 PayPal Mail', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'default' => '',
                    'desc_tip' => true,
                    'description' => __('Please enter the receiver 1 paypal mail', 'wf-paypal-adaptive-split-payment'),
                ),
                'pri_r_amount_percentage' => array(
                    'title' => __('Receiver 1 Payment Percentage', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'default' => '',
                    'desc_tip' => true,
                    'description' => __('Please enter the receiver 1 Payment Percentage ', 'wf-paypal-adaptive-split-payment'),
                ),
                'sec_r1_paypal_enable' => array(
                    'title' => __('Enable Receiver 2', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'checkbox',
                    'label' => __('', 'wf-paypal-adaptive-split-payment'),
                    'default' => 'yes'
                ),
                'sec_r1_paypal_mail' => array(
                    'title' => __('Receiver 2 PayPal Mail', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'default' => '',
                    'desc_tip' => true,
                    'description' => __('Please enter the receiver 2 paypal mail', 'wf-paypal-adaptive-split-payment'),
                ),
                'sec_r1_amount_percentage' => array(
                    'title' => __('Receiver 2 Payment Percentage', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'default' => '',
                    'desc_tip' => true,
                    'description' => __('Please enter the percentage of payment should be sent to receiver 2', 'wf-paypal-adaptive-split-payment'),
                ),
                'sec_r2_paypal_enable' => array(
                    'title' => __('Enable Receiver 3', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'checkbox',
                    'label' => __('', 'wf-paypal-adaptive-split-payment'),
                    'default' => ''
                ),
                'sec_r2_paypal_mail' => array(
                    'title' => __('Receiver 3 PayPal Mail', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'default' => '',
                    'desc_tip' => true,
                    'description' => __('Please enter the  receiver 3 paypal mail', 'wf-paypal-adaptive-split-payment'),
                ),
                'sec_r2_amount_percentage' => array(
                    'title' => __('Receiver 3 Payment Percentage', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'default' => '',
                    'desc_tip' => true,
                    'description' => __('Please enter how much percentage of payment should be sent to receiver 3', 'wf-paypal-adaptive-split-payment'),
                ),
                'sec_r3_paypal_enable' => array(
                    'title' => __('Enable Receiver 4', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'checkbox',
                    'label' => __('', 'wf-paypal-adaptive-split-payment'),
                    'default' => ''
                ),
                'sec_r3_paypal_mail' => array(
                    'title' => __('Receiver 4 PayPal Mail', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'default' => '',
                    'desc_tip' => true,
                    'description' => __('Please enter the receiver 4 paypal mail', 'wf-paypal-adaptive-split-payment'),
                ),
                'sec_r3_amount_percentage' => array(
                    'title' => __('Receiver 4 Payment Percentage', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'default' => '',
                    'desc_tip' => true,
                    'description' => __('Please enter how much percentage of payment should be sent to receiver 4', 'wf-paypal-adaptive-split-payment'),
                ),
                'sec_r4_paypal_enable' => array(
                    'title' => __('Enable Receiver 5', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'checkbox',
                    'label' => __('', 'wf-paypal-adaptive-split-payment'),
                    'default' => ''
                ),
                'sec_r4_paypal_mail' => array(
                    'title' => __('Receiver 5 PayPal Mail', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'default' => '',
                    'desc_tip' => true,
                    'description' => __('Please enter the  receiver 5 paypal mail', 'wf-paypal-adaptive-split-payment'),
                ),
                'sec_r4_amount_percentage' => array(
                    'title' => __('Receiver 5 Payment Percentage', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'default' => '',
                    'desc_tip' => true,
                    'description' => __('Please enter how much percentage of payment should be sent to receiver 5', 'wf-paypal-adaptive-split-payment'),
                ),
                'sec_r5_paypal_enable' => array(
                    'title' => __('Enable Receiver 6', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'checkbox',
                    'label' => __('', 'wf-paypal-adaptive-split-payment'),
                    'default' => ''
                ),
                'sec_r5_paypal_mail' => array(
                    'title' => __('Receiver 6 PayPal Mail', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'default' => '',
                    'desc_tip' => true,
                    'description' => __('Please enter the  receiver 6 paypal mail', 'wf-paypal-adaptive-split-payment'),
                ),
                'sec_r5_amount_percentage' => array(
                    'title' => __('Receiver 6 Payment Percentage', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'text',
                    'default' => '',
                    'desc_tip' => true,
                    'description' => __('Please enter how much percentage of payment should be sent to  receiver 6', 'wf-paypal-adaptive-split-payment'),
                ),
                'split_table' => array(
                    'title' => __('Order Details', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'title',
                    'description' => '',
                ),
                'split_table_checkbox' => array(
                    'title' => __('Display Split Payment Details In Order Details Page ', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'checkbox',
                    'label' => __('Enable To Display Split Payment Details', 'wf-paypal-adaptive-split-payment'),
                    'default' => 'no',
                ),
                'testing' => array(
                    'title' => __('Gateway Testing', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'title',
                    'description' => '',
                ),
                'testmode' => array(
                    'title' => __('PayPal Adaptive sandbox', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'checkbox',
                    'label' => __('Enable PayPal Adaptive sandbox', 'wf-paypal-adaptive-split-payment'),
                    'default' => 'no',
                    'description' => sprintf(__('PayPal Adaptive sandbox can be used to test payments. Sign up for a developer account <a href="%s">here</a>.', 'wf-paypal-adaptive-split-payment'), 'https://developer.paypal.com/'),
                ),
                'retry_delayed_cron_job' => array(
                    'title' => __('No. of days to Retry Delayed Payment to Secondary Receiver(s) for', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'number',
                    'css' => 'width:8%',
                    'default' => '3',
                    'description' => __('Note: Works only for Delayed Chained Payment after transaction is failed ', 'wf-paypal-adaptive-split-payment'),
                    'custom_attributes' => array(
                        'min' => 1,
                        'required' => 'required'
                    )
                ),
                'troubleshoot' => array(
                    'title' => __('Troubleshoot ', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'title',
                    'description' => '',
                ),
                'troubleshoot_option' => array(
                    'title' => __('WooCommerce order status is changed based on', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'select',
                    'default' => '1',
                    'options' => array('1' => __('IPN Response', 'wf-paypal-adaptive-split-payment'), '2' => __('Payment Status', 'wf-paypal-adaptive-split-payment')),
                    'description' => __('Try changing to \'Payment Status\' if order status is not automatically changing to completed due to problem in receiving IPN Response', 'wf-paypal-adaptive-split-payment')
                ),
                'delayed_pay_transaction_log' => array(
                    'type' => 'transaction_log'
                ),
                'paypal_adaptive_bulk_update' => array(
                    'title' => __('Bulk Update Settings', 'wf-paypal-adaptive-split-payment'),
                    'type' => 'title',
                    'description' => '',
                ),
                'paypal_adaptive_bulk_update_settings' => array(
                    'type' => 'bulk_update_settings',
                ),
            );
        }

        function generate_bulk_update_settings_html() {
            global $woocommerce;
            ob_start();
            ?>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="bulk_update_settings"><?php _e('Select Products', 'wf-paypal-adaptive-split-payment'); ?></label>
                </th>
                <td class="forminp forminp-select">

                    <?php if (get_option('bulk_product_selection_adaptive_split') == '') { ?>
                        <select name="bulk_product_selection_adaptive_split" id="bulk_product_selection_adaptive_split">
                            <option  value="1"><?php _e('All Products', 'wf-paypal-adaptive-split-payment'); ?></option>
                            <option value="2"><?php _e('Selected Products', 'wf-paypal-adaptive-split-payment'); ?></option>
                            <option value="3"><?php _e('All Categories', 'wf-paypal-adaptive-split-payment'); ?></option>
                            <option value="4"><?php _e('Selected Categories', 'wf-paypal-adaptive-split-payment'); ?></option>
                        </select>
                    <?php } ?>

                    <?php if (get_option('bulk_product_selection_adaptive_split') != '') { ?>
                        <select name="bulk_product_selection_adaptive_split" id="bulk_product_selection_adaptive_split">
                            <option <?php if (get_option('bulk_product_selection_adaptive_split') == '1') { ?> selected="" <?php } ?>  value="1"><?php _e('All Products', 'wf-paypal-adaptive-split-payment'); ?></option>
                            <option <?php if (get_option('bulk_product_selection_adaptive_split') == '2') { ?> selected="" <?php } ?> value="2"><?php _e('Selected Products', 'wf-paypal-adaptive-split-payment'); ?></option>
                            <option <?php if (get_option('bulk_product_selection_adaptive_split') == '3') { ?> selected="" <?php } ?> value="3"><?php _e('All Categories', 'wf-paypal-adaptive-split-payment'); ?></option>
                            <option <?php if (get_option('bulk_product_selection_adaptive_split') == '4') { ?> selected="" <?php } ?> value="4"><?php _e('Selected Categories', 'wf-paypal-adaptive-split-payment'); ?></option>
                        </select>
                    <?php } ?>
                </td>
            </tr>
            <tr valign="top" class="wfpaypal_adaptive_split_select_products">
                <th class="titledesc " scope="row">
                    <label for="adaptive_selected_products"><?php _e('Select Products', 'wf-paypal-adaptive-split-payment'); ?></label>
                </th>
                <td class="forminp forminp-select">
                    <?php
                    echo self::search_product_selection('woocommerce_json_search_products_and_variations', 'true', 'paypal_bulk_update_selected_products', '', '', '');
                    ?>
                </td>
            </tr>
            <tr valign="top" class="wfpaypal_adaptive_split_select_categories">
                <th class="titledesc" scope="row">
                    <label for="adaptive_selected_categories"><?php _e('Select Category', 'wf-paypal-adaptive-split-payment'); ?></label>
                </th>
                <td class="forminp forminp-select">
                    <?php
                    echo self::search_category_selection();
                    ?>
                </td>
            </tr>

            <tr valign="top" class="wfpaypal_adaptive_choose_different_mode">
                <th class="titledesc" scope="row">
                    <label for="adaptive_choose_product_leve_config"><?php _e('Adaptive Payment', 'wf-paypal-adaptive-split-payment'); ?> </label>
                </th>
                <td class="forminp forminp-select">

                    <?php if (get_option('wfpaypaladaptive_split_config') != '') { ?>
                        <select name="wfpaypaladaptive_split_config" id="wfpaypaladaptive_split_config">
                            <option <?php if (get_option('wfpaypaladaptive_split_config') == '1') { ?> selected="" <?php } ?> value="1"><?php _e('Use Global Settings', 'wf-paypal-adaptive-split-payment'); ?></option>
                            <option <?php if (get_option('wfpaypaladaptive_split_config') == '2') { ?> selected="" <?php } ?> value="2"><?php _e('Use Category Settings', 'wf-paypal-adaptive-split-payment'); ?></option>
                            <option <?php if (get_option('wfpaypaladaptive_split_config') == '3') { ?> selected="" <?php } ?> value="3"><?php _e('Use Product Settings', 'wf-paypal-adaptive-split-payment'); ?></option>
                        </select>
                    <?php } ?>

                    <?php if (get_option('wfpaypaladaptive_split_config') == '') { ?>
                        <select name="wfpaypaladaptive_split_config" id="wfpaypaladaptive_split_config">
                            <option value="1"><?php _e('Use Global Settings', 'wf-paypal-adaptive-split-payment'); ?></option>
                            <option value="2"><?php _e('Use Category Settings', 'wf-paypal-adaptive-split-payment'); ?></option>
                            <option value="3"><?php _e('Use Product Settings', 'wf-paypal-adaptive-split-payment'); ?></option>
                        </select>
                    <?php } ?>
                </td>
            </tr>
            <?php
            for ($i = 1; $i <= 6; $i++) {
                ?>
                <tr valign="top" class="wfpaypal_adaptive_split_use_category_or_product">
                    <th class="titledesc" scope = "row">
                        <label for = "<?php echo 'receiver_' . $i . ''; ?>">Enable Receiver <?php echo $i; ?></label>
                    </th>
                    <td class="forminp forminp-select">
                        <input style="width: auto;" id = "<?php echo '_wf_paypal_rec_' . $i . '_enable'; ?>" type = "checkbox"  aria-required = "false" size = "40"  value ="1" <?php checked(1, get_option('_wf_paypal_rec_' . $i . '_enable')); ?>name = "<?php echo '_wf_paypal_rec_' . $i . '_enable'; ?>">
                        <p class = "description">Enable Receiver <?php echo $i; ?></p>
                    </td>
                </tr>
                <tr valign="top" class="wfpaypal_adaptive_split_use_category_or_product">
                    <th class="titledesc" scope = "row">
                        <label for = "<?php echo 'receiver_' . $i . '_mail'; ?>">Receiver <?php echo $i; ?> Email</label>
                    </th>
                    <td class="forminp forminp-select">
                        <input id = "<?php echo '_wf_paypal_rec_' . $i . '_mail_id'; ?>" type = "text" aria-required = "false" size = "40" value = "<?php echo get_option('_wf_paypal_rec_' . $i . '_mail_id'); ?>" name = "<?php echo '_wf_paypal_rec_' . $i . '_mail_id'; ?>">
                        <p class = "description">Receiver <?php echo $i; ?> Mail.</p>
                    </td>
                </tr>
                <tr valign="top" class="wfpaypal_adaptive_split_use_category_or_product">
                    <th class="titledesc" scope = "row">
                        <label for = "<?php echo 'receiver_' . $i . '_percent'; ?>">Receiver <?php echo $i; ?> Payment Percentage</label>
                    </th>
                    <td class="forminp forminp-select">
                        <input id = "<?php echo '_wf_paypal_rec_' . $i . '_percent'; ?>" type = "text" aria-required = "false" size = "40" value = "<?php echo get_option('_wf_paypal_rec_' . $i . '_percent'); ?>" name = "<?php echo '_wf_paypal_rec_' . $i . '_percent'; ?>">
                        <p class = "description">Receiver <?php echo $i; ?> Payment Percentage</p>
                    </td>
                </tr>

                <?php
            }
            ?>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="wfpaypaladaptive_update_button"><?php _e('', 'wf-paypal-adaptive-split-payment'); ?></label>
                </th>
                <td class="forminp forminp-select">
                    <input type="submit" class="wfpaypaladaptive_update_button button-primary" value="Bulk Update"/>
                    <img class="wfpaypaladaptive_gif_button"  src="<?php echo plugins_url('images/update.gif', __FILE__); ?>" style="width:32px;height:32px;position:absolute;display:none;"/>
                </td>
            </tr>
            <script type="text/javascript">
                jQuery(function () {
                    var selected_info;
                    var sub_select;
                    var arr = {};
                    wfpaypal_adaptive_show_hide('1');
                    wfpaypal_adaptive_config_level_show_hide('1');
                    overall_level_selection();
                    product_level_selection();
                    function overall_level_selection() {
                        jQuery(document).ready(function () {

                            var current_value = jQuery('#bulk_product_selection_adaptive_split').val();
                            wfpaypal_adaptive_show_hide(current_value);
                        });
                        jQuery(document).on('change', '#bulk_product_selection_adaptive_split', function () {
                            var current_value = jQuery(this).val();
                            wfpaypal_adaptive_show_hide(current_value);
                        });
                    }
                    function product_level_selection() {
                        //for use global settings/use category settings/use product settings
                        jQuery(document).ready(function () {

                            var another_value = jQuery('#wfpaypaladaptive_split_config').val();
                            var overall_level_selection = jQuery('#bulk_product_selection_adaptive_split').val();
                            wfpaypal_adaptive_config_level_show_hide(overall_level_selection, another_value);
                        });
                        jQuery(document).on('change', '#wfpaypaladaptive_split_config', function () {
                            var another_value = jQuery(this).val();
                            var overall_level_selection = jQuery('#bulk_product_selection_adaptive_split').val();
                            wfpaypal_adaptive_config_level_show_hide(overall_level_selection, another_value);
                        });
                    }

                    function wfpaypal_adaptive_show_hide(current_value) {

                        //if 1 then all products, 2 selected products, 3 all categories, 4 selected categories
                        var current_selection = jQuery('#wfpaypaladaptive_split_config').val();
                        if (current_value === '1') {
                            jQuery('.wfpaypal_adaptive_split_select_products').hide();
                            jQuery('.wfpaypal_adaptive_split_select_categories').hide();
                            jQuery('.wfpaypal_adaptive_choose_different_mode').show();
                            wfpaypal_adaptive_config_level_show_hide(current_value, current_selection);
                        } else if (current_value === '2') {
                            jQuery('.wfpaypal_adaptive_split_select_products').show();
                            jQuery('.wfpaypal_adaptive_split_select_categories').hide();
                            jQuery('.wfpaypal_adaptive_choose_different_mode').show();
                            wfpaypal_adaptive_config_level_show_hide(current_value, current_selection);
                        } else if (current_value === '3') {
                            jQuery('.wfpaypal_adaptive_split_select_products').hide();
                            jQuery('.wfpaypal_adaptive_split_select_categories').hide();
                            jQuery('.wfpaypal_adaptive_choose_different_mode').hide();
                            wfpaypal_adaptive_config_level_show_hide(current_value, '1');
                        } else {
                            jQuery('.wfpaypal_adaptive_split_select_products').hide();
                            jQuery('.wfpaypal_adaptive_split_select_categories').show();
                            jQuery('.wfpaypal_adaptive_choose_different_mode').hide();
                            wfpaypal_adaptive_config_level_show_hide(current_value, '1');

                        }
                    }

                    function wfpaypal_adaptive_config_level_show_hide(parent_value, current_value) {
                        console.log(parent_value, current_value);
                        if ((parent_value === '1' && current_value === '3') || (parent_value === '2' && current_value === '3') || ((parent_value === '3') || (parent_value === '4')) && ((current_value === '1') || (current_value === '2'))) {
                            jQuery('.wfpaypal_adaptive_split_use_category_or_product').show();
                        } else {
                            jQuery('.wfpaypal_adaptive_split_use_category_or_product').hide();
                        }

                    }

                    jQuery(document).on('click', '.wfpaypaladaptive_update_button', function () {
                        if (jQuery('#bulk_product_selection_adaptive_split').val() != '1' && jQuery('#bulk_product_selection_adaptive_split').val() != '2') {
                            function validateEmail(email)
                            {
                                var x = email;
                                var atpos = x.indexOf("@");
                                var dotpos = x.lastIndexOf(".");
                                if (atpos < 1 || dotpos < atpos + 2 || dotpos + 2 >= x.length)
                                {
                                    return false;
                                } else {
                                    return true;
                                }
                            }
                            var enable1 = Number(jQuery('#_wf_paypal_rec_1_percent').val());
                            var enable2 = Number(jQuery('#_wf_paypal_rec_2_percent').val());
                            var enable3 = Number(jQuery('#_wf_paypal_rec_3_percent').val());
                            var enable4 = Number(jQuery('#_wf_paypal_rec_4_percent').val());
                            var enable5 = Number(jQuery('#_wf_paypal_rec_5_percent').val());
                            var enable6 = Number(jQuery('#_wf_paypal_rec_6_percent').val());

                            var email1 = jQuery('#_wf_paypal_rec_1_mail_id').val();
                            var email2 = jQuery('#_wf_paypal_rec_2_mail_id').val();
                            var email3 = jQuery('#_wf_paypal_rec_3_mail_id').val();
                            var email4 = jQuery('#_wf_paypal_rec_4_mail_id').val();
                            var email5 = jQuery('#_wf_paypal_rec_5_mail_id').val();
                            var email6 = jQuery('#_wf_paypal_rec_6_mail_id').val();


                            var percent1 = jQuery('#_wf_paypal_rec_1_percent').val();
                            var percent2 = jQuery('#_wf_paypal_rec_2_percent').val();
                            var percent3 = jQuery('#_wf_paypal_rec_3_percent').val();
                            var percent4 = jQuery('#_wf_paypal_rec_4_percent').val();
                            var percent5 = jQuery('#_wf_paypal_rec_5_percent').val();
                            var percent6 = jQuery('#_wf_paypal_rec_6_percent').val();

                            var enable1_jq = jQuery('#_wf_paypal_rec_1_enable');
                            var enable2_jq = jQuery('#_wf_paypal_rec_2_enable');
                            var enable3_jq = jQuery('#_wf_paypal_rec_3_enable');
                            var enable4_jq = jQuery('#_wf_paypal_rec_4_enable');
                            var enable5_jq = jQuery('#_wf_paypal_rec_5_enable');
                            var enable6_jq = jQuery('#_wf_paypal_rec_6_enable');

                            var overall_total = 0;

                            var percentage = [];
                            if (jQuery('#_wf_paypal_rec_1_enable').is(":checked")) {
                                if (validateEmail(email1)) {
                                    if (percent1 != '') {
                                        overall_total += enable1;
                                    } else {
                                        alert("Please Check Payment Percent for enabled Receiver");
                                        return false;
                                    }
                                } else {
                                    alert("Please Check Email address for enabled Receiver");
                                    return false;
                                }
                            }
                            if (jQuery('#_wf_paypal_rec_2_enable').is(":checked")) {
                                if (validateEmail(email2)) {
                                    if (percent2 != '') {
                                        overall_total += enable2;
                                    } else {
                                        alert("Please Check Payment Percent for enabled Receiver");
                                        return false;
                                    }
                                } else {
                                    alert("Please Check Email address for enabled Receiver");
                                    return false;
                                }
                            }
                            if (jQuery('#_wf_paypal_rec_3_enable').is(":checked")) {
                                if (validateEmail(email3)) {
                                    if (percent3 != '') {
                                        overall_total += enable3;
                                    } else {
                                        alert("Please Check Payment Percent for enabled Receiver");
                                        return false;
                                    }
                                } else {
                                    alert("Please Check Email address for enabled Receiver");
                                    return false;
                                }
                            }
                            if (jQuery('#_wf_paypal_rec_4_enable').is(":checked")) {
                                if (validateEmail(email4)) {
                                    if (percent4 != '') {
                                        overall_total += enable4;
                                    } else {
                                        alert("Please Check Payment Percent for enabled Receiver");
                                        return false;
                                    }
                                } else {
                                    alert("Please Check Email address for enabled Receiver");
                                    return false;
                                }
                            }
                            if (jQuery('#_wf_paypal_rec_5_enable').is(":checked")) {
                                if (validateEmail(email5)) {
                                    if (percent5 != '') {
                                        overall_total += enable5;
                                    } else {
                                        alert("Please Check Payment Percent for enabled Receiver");
                                        return false;
                                    }
                                } else {
                                    alert("Please Check Email address for enabled Receiver");
                                    return false;
                                }
                            }
                            if (jQuery('#_wf_paypal_rec_6_enable').is(":checked")) {
                                if (validateEmail(email6)) {
                                    if (percent6 != '') {
                                        overall_total += enable6;
                                    } else {
                                        alert("Please Check Payment Percent for enabled Receiver");
                                        return false;
                                    }

                                } else {
                                    alert("Please Check Email address for enabled Receiver");
                                    return false;
                                }
                            }

                            if (!enable1_jq.is(":checked") && !enable2_jq.is(":checked") && !enable3_jq.is(":checked") && !enable4_jq.is(":checked") && !enable5_jq.is(":checked") && !enable6_jq.is(":checked")) {
                                alert("Please enable atleast one Receiver");
                                return false;
                            }

                            if (overall_total != 100) {
                                alert("The Sum of enabled Receiver percentages should be equal to 100");
                                return false;
                            }
                        }

                        jQuery('.wfpaypaladaptive_gif_button').css('display', 'inline-block');

                        var overall_selection = jQuery('#bulk_product_selection_adaptive_split').val();
                        if (overall_selection === '3' || overall_selection === '4') {
                            var sub_selection = '1';
                        } else {
                            var sub_selection = jQuery('#wfpaypaladaptive_split_config').val();
                        }

                        if (((overall_selection === '1' || overall_selection === '2') && sub_selection === '1') || ((overall_selection === '1' || overall_selection === '2') && sub_selection === '2')) {
                            //For All Products/Selected Products
                            if (overall_selection === '2') {
                                selected_info = jQuery('#paypal_bulk_update_selected_products').val();
                            } else {
                                selected_info = 'all_products';
                            }

                            if (sub_selection === '1') {
                                sub_select = "global";
                            } else {
                                sub_select = "category";
                            }

                        } else if ((overall_selection === '1' && sub_selection === '3') || (overall_selection === '2' && sub_selection === '3') || ((overall_selection === '3' || overall_selection === '4') && (sub_selection === '1' || sub_selection === '2'))) {
                            if (overall_selection === '2') {
                                selected_info = jQuery('#paypal_bulk_update_selected_products').val();
                            } else if (overall_selection === '1') {
                                selected_info = 'all_products';
                            } else if (overall_selection === '3') {
                                selected_info = "all_categories";
                            } else {
                                selected_info = jQuery('#wfpaypaladaptive_category_selection').val();
                            }

                            if (sub_selection === '3') {
                                sub_select = '';
                            }
                            var i = 1;

                            for (i = 1; i <= 6; i++) {
                                var enable_name = '_wf_paypal_rec_' + i + '_enable';
                                var receiver_mail_name = '_wf_paypal_rec_' + i + '_mail_id';
                                var receiver_amount_name = '_wf_paypal_rec_' + i + '_percent';
                                var enable = jQuery('#_wf_paypal_rec_' + i + '_enable').is(':checked') ? "yes" : "no";
                                var receiver_mail = jQuery('#' + receiver_mail_name).val();
                                var receiver_amount = jQuery('#' + receiver_amount_name).val();
                                arr[enable_name] = enable;
                                arr[receiver_mail_name] = receiver_mail;
                                arr[receiver_amount_name] = receiver_amount;
                            }

                        }

                        var dataparam = ({
                            action: 'wfpaypal_adaptive_bulk_action',
                            selected_info: selected_info,
                            overall_selection: overall_selection,
                            sub_select: sub_select,
                            receiver_info: arr,
                        });
                        function getproductData(id) {
                            return jQuery.ajax({
                                type: 'POST',
                                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                                data: ({
                                    action: 'wfpaypal_adaptive_bulk_action',
                                    ids: id,
                                    sub_select: sub_select,
                                    receiver_info: arr,
                                }),
                                success: function (response) {
                                    console.log(response);
                                },
                                dataType: 'json',
                                async: false
                            });
                        }
                        jQuery.post("<?php echo admin_url('admin-ajax.php'); ?>", dataparam,
                                function (response) {
                                    console.log(response);

                                    if (response !== 'success' && response != null) {
                                        var j = 1;
                                        var i, j, temparray, chunk = 10;
                                        for (i = 0, j = response.length; i < j; i += chunk) {
                                            temparray = response.slice(i, i + chunk);
                                            console.log(temparray.length);
                                            getproductData(temparray);
                                        }
                                        jQuery.when(getproductData()).done(function (a1) {
                                            console.log('Ajax Done Successfully');
                                            jQuery('.submit .button-primary').trigger('click');
                                        });
                                    } else {
                                        var newresponse = response.replace(/\s/g, '');
                                        if (newresponse === 'success') {
                                            jQuery('.submit .button-primary').trigger('click');
                                        }
                                    }
                                }, 'json');
                        return false;
                    });

                });
            </script>


            <?php
            return ob_get_clean();
        }

        public function save_account_details() {
            if (isset($_POST['bulk_product_selection_adaptive_split'])) {
                update_option('bulk_product_selection_adaptive_split', $_POST['bulk_product_selection_adaptive_split']);
            }
            if (isset($_POST['paypal_bulk_update_selected_products'])) {
                update_option('paypal_bulk_update_selected_products', $_POST['paypal_bulk_update_selected_products']);
            }
            if (isset($_POST['wfpaypaladaptive_category_selection'])) {
                update_option('wfpaypaladaptive_category_selection', $_POST['wfpaypaladaptive_category_selection']);
            }
            if (isset($_POST['wfpaypaladaptive_split_config'])) {
                update_option('wfpaypaladaptive_split_config', $_POST['wfpaypaladaptive_split_config']);
            }

            if (isset($_POST['_wf_paypal_rec_1_enable'])) {
                update_option('_wf_paypal_rec_1_enable', $_POST['_wf_paypal_rec_1_enable']);
            }
            if (isset($_POST['_wf_paypal_rec_2_enable'])) {
                update_option('_wf_paypal_rec_2_enable', $_POST['_wf_paypal_rec_2_enable']);
            }
            if (isset($_POST['_wf_paypal_rec_3_enable'])) {
                update_option('_wf_paypal_rec_3_enable', $_POST['_wf_paypal_rec_3_enable']);
            }
            if (isset($_POST['_wf_paypal_rec_4_enable'])) {
                update_option('_wf_paypal_rec_4_enable', $_POST['_wf_paypal_rec_4_enable']);
            }
            if (isset($_POST['_wf_paypal_rec_5_enable'])) {
                update_option('_wf_paypal_rec_5_enable', $_POST['_wf_paypal_rec_5_enable']);
            }
            if (isset($_POST['_wf_paypal_rec_6_enable'])) {
                update_option('_wf_paypal_rec_6_enable', $_POST['_wf_paypal_rec_6_enable']);
            }

            if (isset($_POST['_wf_paypal_rec_1_mail_id'])) {
                update_option('_wf_paypal_rec_1_mail_id', $_POST['_wf_paypal_rec_1_mail_id']);
            }
            if (isset($_POST['_wf_paypal_rec_2_mail_id'])) {
                update_option('_wf_paypal_rec_2_mail_id', $_POST['_wf_paypal_rec_2_mail_id']);
            }
            if (isset($_POST['_wf_paypal_rec_3_mail_id'])) {
                update_option('_wf_paypal_rec_3_mail_id', $_POST['_wf_paypal_rec_3_mail_id']);
            }
            if (isset($_POST['_wf_paypal_rec_4_mail_id'])) {
                update_option('_wf_paypal_rec_4_mail_id', $_POST['_wf_paypal_rec_4_mail_id']);
            }
            if (isset($_POST['_wf_paypal_rec_5_mail_id'])) {
                update_option('_wf_paypal_rec_5_mail_id', $_POST['_wf_paypal_rec_5_mail_id']);
            }
            if (isset($_POST['_wf_paypal_rec_6_mail_id'])) {
                update_option('_wf_paypal_rec_6_mail_id', $_POST['_wf_paypal_rec_6_mail_id']);
            }

            if (isset($_POST['_wf_paypal_rec_1_percent'])) {
                update_option('_wf_paypal_rec_1_percent', $_POST['_wf_paypal_rec_1_percent']);
            }
            if (isset($_POST['_wf_paypal_rec_2_percent'])) {
                update_option('_wf_paypal_rec_2_percent', $_POST['_wf_paypal_rec_2_percent']);
            }
            if (isset($_POST['_wf_paypal_rec_3_percent'])) {
                update_option('_wf_paypal_rec_3_percent', $_POST['_wf_paypal_rec_3_percent']);
            }
            if (isset($_POST['_wf_paypal_rec_4_percent'])) {
                update_option('_wf_paypal_rec_4_percent', $_POST['_wf_paypal_rec_4_percent']);
            }
            if (isset($_POST['_wf_paypal_rec_5percent'])) {
                update_option('_wf_paypal_rec_5percent', $_POST['_wf_paypal_rec_5_percent']);
            }
            if (isset($_POST['_wf_paypal_rec_6_percent'])) {
                update_option('_wf_paypal_rec_6_percent', $_POST['_wf_paypal_rec_6_percent']);
            }
        }

        public static function paypal_adaptive_bulk_selection() {
            if ($_POST) {

                if (isset($_POST['ids'])) {


                    $products = $_POST['ids'];
                    foreach ($products as $product) {
                        if (isset($_POST['sub_select']) && $_POST['sub_select'] != '') {
                            $sub_select = $_POST['sub_select'];
                            if ($sub_select === 'global') {
                                //for global selection
                                update_post_meta($product, '_enable_wf_paypal_adaptive', 'disable');
                            } else {
                                // for category selection
                                update_post_meta($product, '_enable_wf_paypal_adaptive', 'enable_category');
                            }
                        } else {
                            // for receiver updation
                            update_post_meta($product, '_enable_wf_paypal_adaptive', 'enable_indiv');
                            if ($_POST['receiver_info']) {
                                $receiver_info = $_POST['receiver_info'];
                                $data_primary_enable = $receiver_info['_wf_paypal_rec_1_enable'];
                                $data_primary_mail_id = $receiver_info['_wf_paypal_rec_1_mail_id'];
                                $data_primary_percent = $receiver_info['_wf_paypal_rec_1_percent'];

                                update_post_meta($product, '_wf_paypal_primary_1_enable', $data_primary_enable);
                                update_post_meta($product, '_wf_paypal_primary_rec_mail_id', $data_primary_mail_id);
                                update_post_meta($product, '_wf_paypal_primary_rec_percent', $data_primary_percent);

                                for ($j = 2; $j <= 6; $j++) {
                                    $get_data_enable = $receiver_info['_wf_paypal_rec_' . $j . '_enable'];
                                    $get_sec_mail_id = $receiver_info['_wf_paypal_rec_' . $j . '_mail_id'];
                                    $get_sec_percent = $receiver_info['_wf_paypal_rec_' . $j . '_percent'];

                                    update_post_meta($product, '_wf_paypal_sec_' . ($j - 1) . '_enable', $get_data_enable);
                                    update_post_meta($product, '_wf_paypal_sec_' . ($j - 1) . '_rec_mail_id', $get_sec_mail_id);
                                    update_post_meta($product, '_wf_paypal_sec_' . ($j - 1) . '_rec_percent', $get_sec_percent);
                                }
                            }
                        }
                    }
                } else {
                    if (isset($_POST['selected_info'])) {
                        $selected_info = $_POST['selected_info'];
                        if ($selected_info === 'all_products') {
                            //for all products get the list of ids
                            $args = array('post_type' => 'product', 'posts_per_page' => '-1', 'post_status' => 'publish', 'fields' => 'ids', 'cache_results' => false);
                            $products = get_posts($args);
                            echo json_encode($products);
                        } elseif ($selected_info === 'all_categories') {
                            // for all categories 
                            $get_available_terms = get_terms(array(
                                'taxonomy' => 'product_cat',
                            ));

                            if (is_array($get_available_terms) && !empty($get_available_terms)) {
                                $get_array_information = $_POST['receiver_info'];
                                foreach ($get_available_terms as $term) {
                                    $term_id = $term->term_id;
                                    if (is_array($get_array_information) && !empty($get_array_information)) {
                                        foreach ($get_array_information as $new_key => $new_value) {
                                            update_woocommerce_term_meta($term_id, $new_key, $new_value);
                                        }
                                    }
                                }
                            }
                            echo json_encode("success");
                        } elseif (isset($_POST['overall_selection'])) {
                            if ($_POST['overall_selection'] === '2') {
                                // for selected products 
                                $selected_products = $_POST['selected_info'];

                                $newarray = $selected_products;
                                if (!is_array($selected_products)) {
                                    $newarray = (array) explode(',', $selected_products);
                                }
                                echo json_encode($newarray);
                            } elseif ($_POST['overall_selection'] === '4') {
                                // for all categories
                                $selected_categories = $_POST['selected_info'];

                                if (is_array($selected_categories) && !empty($selected_categories)) {
                                    foreach ($selected_categories as $each_category) {
                                        // each_category as term_id;
                                        $get_array_information = $_POST['receiver_info'];

                                        $term_id = $each_category;
                                        if (is_array($get_array_information) && !empty($get_array_information)) {
                                            foreach ($get_array_information as $new_key => $new_value) {
                                                update_woocommerce_term_meta($term_id, $new_key, $new_value);
                                            }
                                        }
                                    }
                                }
                                echo json_encode("success");
                            }
                        }
                    } else {
                        echo json_encode('success');
                    }
                }
            }

            exit();
        }

        public static function search_category_selection() {
            ob_start();
            $get_available_terms = get_terms(array(
                'taxonomy' => 'product_cat',
            ));

            $get_category_selection = array();

            if (is_array($get_available_terms) && !empty($get_available_terms)) {
                foreach ($get_available_terms as $key => $value) {
                    $get_category_selection[$value->term_id] = $value->name;
                }
            }
            ?>
            <select name="wfpaypaladaptive_category_selection[]" id="wfpaypaladaptive_category_selection"  multiple="multiple">
                <?php
                $select_categories = (array) get_option('wfpaypaladaptive_category_selection');


                if (!empty($get_category_selection)) {
                    foreach ($get_category_selection as $newkey => $newvalue) {
                        if (in_array($newkey, $select_categories)) {
                            $array = "selected=selected";
                        } else {
                            $array = '';
                        }
                        ?>
                        <option value="<?php echo $newkey; ?>" <?php echo $array; ?>><?php echo $newvalue; ?></option>
                        <?php
                    }
                }
                ?>
            </select>
            <?php
            echo self::add_chosen_or_select2('wfpaypaladaptive_category_selection', 'Select Categories', 'true');

            return ob_get_clean();
            ?>
            <?php
        }

        public static function search_product_selection($product_and_variation, $multiple, $name, $iteration, $value, $subname) {
            global $woocommerce;
            ob_start();
            if ($product_and_variation == '1') {
                $product_selection = "woocommerce_json_search_products";
            } else {
                $product_selection = 'woocommerce_json_search_products_and_variations';
            }

            if ($multiple == 'true') {
                if ($iteration != '') {
                    $multiple_name = $name . "[$iteration]" . "[$subname]";
                } else {
                    $multiple_name = $name;
                }
            } else {
                if ($iteration != '') {
                    $multiple_name = $name . "[$iteration]" . "[$subname]";
                } else {
                    $multiple_name = $name;
                }
            }

            if ($multiple == true) {
                $new_attribute = 'multiple';
            } else {
                $new_attribute = '';
            }

            if ($iteration == '') {
                $option_name = get_option($name);
            } else {
                $option_name = $value[$subname];
            }

            if ((float) $woocommerce->version > (float) ('2.2.0')) {
                ?>
                <!-- For Latest -->
                <input type="hidden" class="wc-product-search" id='<?php echo $name; ?>' style="width: 100%;" name="<?php echo $multiple_name; ?>" data-allow_clear="true" data-placeholder="<?php _e('Search for a product&hellip;', 'donationsystem'); ?>" data-action="<?php echo $product_selection; ?>" data-multiple="<?php echo $multiple; ?>" data-selected="<?php
                $json_ids = array();
                if ($option_name != "") {
                    $list_of_produts = $option_name;
                    if (!is_array($list_of_produts)) {
                        $product_ids = array_filter(array_map('absint', (array) explode(',', $list_of_produts)));
                        foreach ($product_ids as $product_id) {
                            $product = wc_get_product($product_id);
                            if ($product) {
                                $json_ids[$product_id] = wp_kses_post($product->get_formatted_name());
                            }
                        } echo $multiple == 'true' ? esc_attr(json_encode($json_ids)) : $product->get_formatted_name();
                    } else {
                        foreach ($list_of_produts as $product_id) {
                            $product = wc_get_product($product_id);
                            if ($product) {
                                $json_ids[$product_id] = wp_kses_post($product->get_formatted_name());
                            }
                        } echo $multiple == 'true' ? esc_attr(json_encode($json_ids)) : $product->get_formatted_name();
                    }
                }
                ?>" value="<?php echo implode(',', array_keys($json_ids)); ?>" />

                <?php
            } else {
                ?>
                <!-- For Old Version -->
                <select id='<?php echo $name; ?>' <?php echo $new_attribute; ?> name="<?php echo $multiple_name; ?>" class="">
                    <?php
                    if ($option_name != "") {
                        $list_of_produts = $option_name;
                        foreach ($list_of_produts as $rs_free_id) {
                            echo '<option value="' . $rs_free_id . '" ';
                            selected(1, 1);
                            echo '>' . ' #' . $rs_free_id . ' &ndash; ' . get_the_title($rs_free_id);
                            ?>
                            <?php
                        }
                    } else {
                        ?>
                        <option value=""></option>
                        <?php
                    }
                    ?>
                </select>

                <?php
                echo self::add_chosen_to_product($name, $product_selection);
            }

            return ob_get_clean();
        }

        // Backward Compatibility Chosen

        public static function add_chosen_to_product($id, $product_selection) {
            global $woocommerce;
            ob_start();
            ?>
            <script type="text/javascript">
            <?php if ((float) $woocommerce->version <= (float) ('2.2.0')) { ?>
                    jQuery(function () {
                        jQuery("select#<?php echo $id; ?>").ajaxChosen({
                            method: 'GET',
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            dataType: 'json',
                            afterTypeDelay: 100,
                            data: {
                                action: '<?php echo $product_selection; ?>',
                                security: '<?php echo wp_create_nonce("search-products"); ?>'
                            }
                        }, function (data) {
                            var terms = {};

                            jQuery.each(data, function (i, val) {
                                terms[i] = val;
                            });
                            return terms;
                        });
                    });
            <?php } ?>
            </script>
            <?php
            $getcontent = ob_get_clean();
            return $getcontent;
        }

        // Add Chosen/Select2 for Backward Compatibility

        public static function add_chosen_or_select2($id, $placeholder, $multiple) {
            ob_start();
            global $woocommerce;
            ?>
            <script type="text/javascript">
                jQuery(function () {
            <?php
            if ((float) $woocommerce->version <= (float) ('2.2.0')) {
                if ($multiple == 'true') {
                    ?>
                            jQuery('#<?php echo $id; ?>').chosen({placeholder_text_multiple: "<?php echo $placeholder; ?>"});
                    <?php
                } else {
                    ?>
                            jQuery('#<?php echo $id; ?>').chosen({placeholder_text_single: "<?php echo $placeholder; ?>"});
                    <?php
                }
            } else {
                ?>
                        jQuery('#<?php echo $id; ?>').select2({placeholder: '<?php echo $placeholder; ?>'});
            <?php } ?>
                });
            </script>
            <?php
            $content = ob_get_clean();

            return $content;
        }

        function generate_transaction_log_html() {
            global $woocommerce;

            $get_order_statuses = $woocommerce->version < 2.2 ? wf_paypal_get_order_statuses() : wc_get_order_statuses();

            $args = array(
                'post_type' => 'shop_order',
                'posts_per_page' => -1,
                'post_status' => array_keys($get_order_statuses),
                'meta_key' => 'wf_is_split_payment',
                'meta_value' => 'yes'
            );

            $myorders = get_posts($args);

            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc"><?php _e('Delayed Payment Transaction Log', 'wf-paypal-adaptive-split-payment'); ?>:</th>
                <td class="forminp" id="delayed_pay_log">
                    <table class="widefat wc_input_table" style="border-spacing: 5px;">
                        <thead>
                            <tr>
                                <th style="width:6%;">&nbsp;&nbsp;<?php _e('S.No', 'wf-paypal-adaptive-split-payment'); ?></th>
                                <th style="width:22%;"><?php _e('Primary Receiver', 'wf-paypal-adaptive-split-payment'); ?></th>
                                <th style="width:28%;"><?php _e('Secondary Receiver / <br>Splitted Amount', 'wf-paypal-adaptive-split-payment'); ?></th>
                                <th style="width:8%;"><?php _e('Order ID', 'wf-paypal-adaptive-split-payment'); ?></th>
                                <th style="width:13%;"><?php _e('Order Total', 'wf-paypal-adaptive-split-payment'); ?></th>
                                <th><?php _e('Pay<br>Secondary<br>Receiver', 'wf-paypal-adaptive-split-payment'); ?></th>
                                <th><?php _e('Payment Status', 'wf-paypal-adaptive-split-payment'); ?></th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            $i = 1;

                            if (wf_is_delayed_payment_logs_present($myorders)) {

                                foreach ($myorders as $eachorder) {

                                    $orderid = $eachorder->ID;

                                    $transaction_order = get_post_meta($orderid, 'wf_delayed_payment_orders', true);

                                    $order = new WC_Order($orderid);
                                    $order_status = $order->status;

                                    if ($order_status == 'trash' || !$order_status) {
                                        $orderurl = '#' . $orderid;
                                    } else {
                                        $orderurl = '<a href=post.php?post=' . $orderid . '&action=edit>#' . $orderid . '</a>';
                                    }

                                    if (is_array($transaction_order)) {
                                        ?>
                                        <tr>
                                            <td><?php echo $i; ?>.</td>
                                            <td><?php echo $transaction_order['pri_receivr']; ?></td>
                                            <td><?php
                                                foreach ($transaction_order['sec_receivr'] as $eachreceiver) {
                                                    echo $eachreceiver . '<br>';
                                                }
                                                ?></td>
                                            <td><?php echo $orderurl; ?></td>
                                            <td><?php echo $transaction_order['total_order_amt'] . ' ' . get_woocommerce_currency(); ?></td>
                                            <td><?php if ($transaction_order['result'] == 'Success') { ?>
                                                    <input type="button" disabled class="wf_manual_pay_action" id="wf_manual_pay_action_<?php echo $i; ?>" style="margin-left:17%;width:60%" data-orderid="<?php echo $orderid; ?>" value="Pay" name="status" readonly/>
                                                <?php } else { ?>
                                                    <input type="button" class="wf_manual_pay_action" id="wf_manual_pay_action_<?php echo $i; ?>" style="margin-left:17%;width:60%;" data-orderid="<?php echo $orderid; ?>" data-rowid="<?php echo $i; ?>"  value="Pay" name="status" readonly/>
                                                <?php } ?>
                                                <span class="showresponse_<?php echo $i; ?>" style="display:none;"></span>
                                            </td>
                                            <td><?php echo $transaction_order['result']; ?></td>
                                        </tr>
                                        <?php
                                        $i++;
                                    }
                                }
                                ?> <script type="text/javascript">

                                    jQuery(document).ready(function () {
                                        jQuery(".wf_manual_pay_action").click(function (e) {

                                            e.preventDefault();

                                            jQuery(this).prop("disabled", true);
                                            document.body.style.cursor = 'wait';

                                            var orderid = jQuery(this).data('orderid');
                                            var rowid = jQuery(this).data('rowid');

                                            var dataparam = {
                                                action: "manual_pay_call_for_delayedchain",
                                                orderid: orderid
                                            };
                                            jQuery.post("<?php echo admin_url('admin-ajax.php'); ?>", dataparam, function (response) {
                                                console.log('Got this from the server: ' + response);

                                                document.body.style.cursor = 'default';

                                                if (response == 'Success') {
                                                    jQuery(".showresponse_" + rowid).css("display", "block");
                                                    jQuery(".showresponse_" + rowid).html(response);
                                                    jQuery(".showresponse_" + rowid).fadeOut(4000, "linear");
                                                } else {
                                                    jQuery("#wf_manual_pay_action_" + rowid).prop("disabled", false);
                                                    jQuery(".showresponse_" + rowid).css("display", "block");
                                                    jQuery(".showresponse_" + rowid).html(response);
                                                    jQuery(".showresponse_" + rowid).fadeOut(4000, "linear");
                                                }
                                            });
                                        });
                                    });

                            </script><?php
                        } else {
                            ?>
                            <tr>
                                <td></td>
                                <td>No Logs Found.</td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </td>
            </tr>
            <?php
            return ob_get_clean();
        }

        function checkme() {
            global $woocommerce;
        }

        function process_payment($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);

            $primary_receiver_mail = $this->get_option('pri_r_paypal_mail'); // techstumbling -email
            $order_total_amount = $order->order_total;
            $success_url = $this->get_return_url($order);
            $cancel_url = str_replace("&amp;", "&", $order->get_cancel_order_url());
            $security_user_id = $this->security_user_id;
            $security_password = $this->security_password;
            $security_signature = $this->security_signature;
            $security_application_id = $this->security_application_id;
            if ("yes" == $this->testmode) {
                $paypal_pay_action_url = "https://svcs.sandbox.paypal.com/AdaptivePayments/Pay";
                $paypal_set_options_action_url = "https://svcs.sandbox.paypal.com/AdaptivePayments/SetPaymentOptions";
                $paypal_pay_auth_without_key_url = "https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_ap-payment&paykey=";
            } else {
                $paypal_pay_action_url = "https://svcs.paypal.com/AdaptivePayments/Pay";
                $paypal_set_options_action_url = "https://svcs.paypal.com/AdaptivePayments/SetPaymentOptions";
                $paypal_pay_auth_without_key_url = "https://www.paypal.com/cgi-bin/webscr?cmd=_ap-payment&paykey=";
            }
            $ipnNotificationUrl = esc_url_raw(add_query_arg(array('ipn' => 'set', 'self_custom' => $order_id), site_url('/')));
            $headers_array = array("X-PAYPAL-SECURITY-USERID" => $security_user_id,
                "X-PAYPAL-SECURITY-PASSWORD" => $security_password,
                "X-PAYPAL-SECURITY-SIGNATURE" => $security_signature,
                "X-PAYPAL-APPLICATION-ID" => $security_application_id,
                "X-PAYPAL-REQUEST-DATA-FORMAT" => "NV",
                "X-PAYPAL-RESPONSE-DATA-FORMAT" => "JSON",
            );
            $receivers_key_value = array();

            foreach ($order->get_items() as $items) {

                if ("enable_indiv" == get_post_meta($items['product_id'], "_enable_wf_paypal_adaptive", true)) {

                    if (array_key_exists(get_post_meta($items['product_id'], "_wf_paypal_primary_rec_mail_id", true), $receivers_key_value)) {
                        $previous_amount = $receivers_key_value[get_post_meta($items['product_id'], "_wf_paypal_primary_rec_mail_id", true)];
                        $x_share = ($order->get_line_total($items) * get_post_meta($items['product_id'], "_wf_paypal_primary_rec_percent", true)) / 100;
                        $calculated = $previous_amount + $x_share;
                        $receivers_key_value[get_post_meta($items['product_id'], "_wf_paypal_primary_rec_mail_id", true)] = $calculated;
                    } else {
                        $x_share = ($order->get_line_total($items) * get_post_meta($items['product_id'], "_wf_paypal_primary_rec_percent", true)) / 100;
                        $receivers_key_value[get_post_meta($items['product_id'], "_wf_paypal_primary_rec_mail_id", true)] = $x_share;
                    }
                    for ($i = 1; $i <= 5; $i++) {
                        if ("yes" == get_post_meta($items['product_id'], '_wf_paypal_sec_' . $i . '_enable', true)) {
                            if (array_key_exists(get_post_meta($items['product_id'], '_wf_paypal_sec_' . $i . '_rec_mail_id', true), $receivers_key_value)) {
                                $previous_amount = $receivers_key_value[get_post_meta($items['product_id'], '_wf_paypal_sec_' . $i . '_rec_mail_id', true)];
                                $x_share = ($order->get_line_total($items) * get_post_meta($items['product_id'], '_wf_paypal_sec_' . $i . '_rec_percent', true)) / 100;
                                $calculated = $previous_amount + $x_share;
                                $receivers_key_value[get_post_meta($items['product_id'], '_wf_paypal_sec_' . $i . '_rec_mail_id', true)] = $calculated;
                            } else {
                                $x_share = ($order->get_line_total($items) * get_post_meta($items['product_id'], '_wf_paypal_sec_' . $i . '_rec_percent', true)) / 100;
                                $receivers_key_value[get_post_meta($items['product_id'], '_wf_paypal_sec_' . $i . '_rec_mail_id', true)] = $x_share;
                            }
                        }
                    }
                } elseif ("enable_category" == get_post_meta($items['product_id'], "_enable_wf_paypal_adaptive", true)) {
                    $wf_paypal_product_category = wp_get_post_terms($items['product_id'], 'product_cat');

                    $category_count = count($wf_paypal_product_category);
                    if ($category_count > 0 && 1 >= $category_count) {
                        $categ_meta = get_metadata('woocommerce_term', $wf_paypal_product_category[0]->term_id);
                        for ($i = 1; $i <= 6; $i++) {
                            if ("yes" == get_woocommerce_term_meta($wf_paypal_product_category[0]->term_id, '_wf_paypal_rec_' . $i . '_enable', true)) {
                                if (array_key_exists(get_woocommerce_term_meta($wf_paypal_product_category[0]->term_id, '_wf_paypal_rec_' . $i . '_mail_id', true), $receivers_key_value)) {
                                    $previous_amount = $receivers_key_value[get_woocommerce_term_meta($wf_paypal_product_category[0]->term_id, '_wf_paypal_rec_' . $i . '_mail_id', true)];
                                    $x_share = ($order->get_line_total($items) * get_woocommerce_term_meta($wf_paypal_product_category[0]->term_id, '_wf_paypal_rec_' . $i . '_percent', true)) / 100;
                                    $calculated = $previous_amount + $x_share;
                                    $receivers_key_value[get_woocommerce_term_meta($wf_paypal_product_category[0]->term_id, '_wf_paypal_rec_' . $i . '_mail_id', true)] = $calculated;
                                } else {
                                    $x_share = ($order->get_line_total($items) * get_woocommerce_term_meta($wf_paypal_product_category[0]->term_id, '_wf_paypal_rec_' . $i . '_percent', true)) / 100;
                                    $receivers_key_value[get_woocommerce_term_meta($wf_paypal_product_category[0]->term_id, '_wf_paypal_rec_' . $i . '_mail_id', true)] = $x_share;
                                }
                            }
                        }
                    } else {
                        $percentagecalculator = array();
                        if (is_array($wf_paypal_product_category)) {
                            foreach ($wf_paypal_product_category as $each_product_category) {
                                $categ_meta = get_metadata('woocommerce_term', $each_product_category->term_id);
                                for ($i = 1; $i <= 6; $i++) {
                                    if ("yes" == @get_woocommerce_term_meta($each_product_category->term_id, '_wf_paypal_rec_' . $i . '_enable', true)) {
                                        if (array_key_exists(@get_woocommerce_term_meta($each_product_category->term_id, '_wf_paypal_rec_' . $i . '_mail_id', true), $receivers_key_value)) {
                                            $previous_amount = @$receivers_key_value[get_woocommerce_term_meta($each_product_category->term_id, '_wf_paypal_rec_' . $i . '_mail_id', true)];
                                            $x_share = ($order->get_line_total($items) * get_woocommerce_term_meta($each_product_category->term_id, '_wf_paypal_rec_' . $i . '_percent', true)) / 100;
                                            $calculated = $previous_amount + $x_share;
                                            @$receivers_key_value[get_woocommerce_term_meta($each_product_category->term_id, '_wf_paypal_rec_' . $i . '_mail_id', true)] = $calculated;
                                        } else {
                                            $x_share = ($order->get_line_total($items) * get_woocommerce_term_meta($each_product_category->term_id, '_wf_paypal_rec_' . $i . '_percent', true)) / 100;
                                            @$receivers_key_value[get_woocommerce_term_meta($each_product_category->term_id, '_wf_paypal_rec_' . $i . '_mail_id', true)] = $x_share;
                                        }
                                        @$percentagecalculator[$each_product_category->term_id] += get_woocommerce_term_meta($each_product_category->term_id, '_wf_paypal_rec_' . $i . '_percent', true);
                                    }
                                }
                                if (@$percentagecalculator[$each_product_category->term_id] == 100) {
                                    break;
                                }
                            }
                        }
                    }
                } elseif (("disable" == get_post_meta($items['product_id'], "_enable_wf_paypal_adaptive", true)) || ("" == get_post_meta($items['product_id'], "_enable_wf_paypal_adaptive", true))) {
                    if (array_key_exists($this->get_option('pri_r_paypal_mail'), $receivers_key_value)) {
                        $previous_amount = $receivers_key_value[$this->get_option('pri_r_paypal_mail')];
                        $x_share = ($order->get_line_total($items) * $this->get_option('pri_r_amount_percentage')) / 100;
                        $calculated = $previous_amount + $x_share;
                        $receivers_key_value[$this->get_option('pri_r_paypal_mail')] = $calculated;
                    } else {
                        $x_share = ($order->get_line_total($items) * $this->get_option('pri_r_amount_percentage')) / 100;
                        $receivers_key_value[$this->get_option('pri_r_paypal_mail')] = $x_share;
                    }

                    for ($i = 1; $i <= 5; $i++) {
                        if ("yes" == $this->get_option('sec_r' . $i . '_paypal_enable')) {
                            if (array_key_exists($this->get_option('sec_r' . $i . '_paypal_mail'), $receivers_key_value)) {
                                $previous_amount = $receivers_key_value[$this->get_option('sec_r' . $i . '_paypal_mail')];
                                $x_share = ($order->get_line_total($items) * $this->get_option('sec_r' . $i . '_amount_percentage')) / 100;
                                $calculated = $previous_amount + $x_share;
                                $receivers_key_value[$this->get_option('sec_r' . $i . '_paypal_mail')] = $calculated;
                            } else {
                                $x_share = ($order->get_line_total($items) * $this->get_option('sec_r' . $i . '_amount_percentage')) / 100;
                                $receivers_key_value[$this->get_option('sec_r' . $i . '_paypal_mail')] = $x_share;
                            }
                        }
                    }
                }
            }

            //individual product split
            //Primary user percent is needed because in parallel we'll specify each person's percent as it goes to each one seperatly
            $primary_user_percentage = $this->get_option('pri_r_amount_percentage');
            $primary_user_amount = round((($order_total_amount * $primary_user_percentage) / 100), 2); // rounding to avoid paypal float problem 589023
            //getting user email,amount and setting percent
            for ($user = 1; $user <= 5; $user++) {
                ${'secondary_user' . $user . '_mail'} = $this->get_option('sec_r' . $user . '_paypal_mail');
                ${'secondary_user' . $user . '_percentage'} = $this->get_option('sec_r' . $user . '_amount_percentage');
                ${'secondary_user' . $user . '_amount'} = round((($order_total_amount * ${'secondary_user' . $user . '_percentage'}) / 100), 2);
            }

            $payment_mode = $this->get_option('_wf_payment_mode');

            if ("parallel" == $payment_mode) {
                $memo = 'Paypal Adaptive Parallel Payment';
                $paymentfeesby = $this->get_option('_wf_payment_parallel_fees');
            } elseif ('chained' == $payment_mode) {
                $memo = 'Paypal Adaptive Chained Payment';
                $paymentfeesby = $this->get_option('_wf_payment_chained_fees');
            } else {
                $memo = 'Paypal Adaptive Delayed Chained Payment';
                $paymentfeesby = $this->get_option('_wf_payment_chained_fees');
            }

            //setting default and primary user datas
            $data_array = array(
                'actionType' => "CREATE",
                'feesPayer' => $paymentfeesby,
                'returnUrl' => $success_url,
                'cancelUrl' => $cancel_url,
                'custom' => $order_id,
                'memo' => $memo,
                'ipnNotificationUrl' => $ipnNotificationUrl,
                'requestEnvelope.errorLanguage' => 'en_US',
                'currencyCode' => get_woocommerce_currency(),
            );
            //calculating cart total
            $manual_cart_total_amount = array_sum($receivers_key_value);

            //getting the percentage for individual based on the cart total
            $receivers_key_percent = array();
            foreach ($receivers_key_value as $key => $value) {
                $receivers_key_percent[$key] = ($value / $manual_cart_total_amount) * 100;
            }

            //setting the amount based on percentage above calculated
            $receivers_mail_amount = array();
            foreach ($receivers_key_percent as $receiver => $percent) {
                $receivers_mail_amount[$receiver] = round((($order->order_total * $percent) / 100), 2);
            }

            //calculating order total
            $manual_order_total_amount = array_sum($receivers_mail_amount);
            //sorting for high order, so we can compensate if the order total is not equal

            if ($manual_order_total_amount > $order->order_total) {
                $amount_to_compensate = $manual_order_total_amount - $order->order_total;
                $first_person_count = 0;
                foreach ($receivers_mail_amount as $mail => $amount) {
                    if ($first_person_count == 0) {
                        $receivers_mail_amount[$mail] = $receivers_mail_amount[$mail] - $amount_to_compensate;
                    }
                    $first_person_count++;
                }
            } elseif ($manual_order_total_amount < $order->order_total) {
                $amount_to_compensate = $order->order_total - $manual_order_total_amount;
                $first_person_count = 0;
                foreach ($receivers_mail_amount as $mail => $amount) {
                    if ($first_person_count == 0) {
                        $receivers_mail_amount[$mail] = $receivers_mail_amount[$mail] + $amount_to_compensate;
                    }
                    $first_person_count++;
                }
            }

            if ("parallel" == $payment_mode) {
                $pay_count = 0;
                foreach ($receivers_mail_amount as $mail => $amount) {
                    $data_array['receiverList.receiver(' . $pay_count . ').amount'] = $amount;
                    $data_array['receiverList.receiver(' . $pay_count . ').email'] = $mail;
                    $pay_count++;
                    update_post_meta($order_id, 'pay_count', $pay_count);
                }
            } elseif ("chained" == $payment_mode || "delayed_chained" == $payment_mode) {
                $pay_count = 0;
                $total_amount = array_sum($receivers_mail_amount); //calculate total here too, so if compensated it will be added here correctly

                foreach ($receivers_mail_amount as $mail => $amount) {
                    if ($pay_count == 0) {
                        $data_array['receiverList.receiver(' . $pay_count . ').amount'] = $total_amount; // this is a primary user so total amount
                        $data_array['receiverList.receiver(' . $pay_count . ').email'] = $mail;
                        $data_array['receiverList.receiver(' . $pay_count . ').primary'] = "true";
                    } else {
                        $data_array['receiverList.receiver(' . $pay_count . ').amount'] = $amount;
                        $data_array['receiverList.receiver(' . $pay_count . ').email'] = $mail;
                        $data_array['receiverList.receiver(' . $pay_count . ').primary'] = "false";
                    }
                    $pay_count++;
                    update_post_meta($order_id, 'pay_count', $pay_count);
                }
            }
            $delay_payment_period = "delayed_chained" == $payment_mode ? $this->get_option('_wf_delay_chained_period') : 0;
            $pay_result = wf_get_cURL_adaptive_split_response($paypal_pay_action_url, $headers_array, $data_array);
            if ($pay_result) {
                $jso = json_decode($pay_result);
                if ("Success" == $jso->responseEnvelope->ack) {
                    $ack = "Success";
                    if ($this->shipping_details == 'show') {
                        $result = FP_Paypal_Payment_Review_page::wf_pay_settings_for_shipping_address($jso->payKey, $order, $receivers_mail_amount);
                        $pay_address_result = wf_get_cURL_adaptive_split_response($paypal_set_options_action_url, $headers_array, $result);
                        $result_jso = json_decode($pay_address_result);
                        $ack = $result_jso->responseEnvelope->ack;
                    }
                    if ("Success" == $ack) {
                        @$payment_url = $paypal_pay_auth_without_key_url . $jso->payKey;
                        update_post_meta($order_id, 'wf_payKey', $jso->payKey);
                        update_post_meta($order_id, 'wf_order_amt', $order->order_total);
                        update_post_meta($order_id, 'wf_payment_mode', $payment_mode);
                        update_post_meta($order_id, 'wf_delay_period', $delay_payment_period);
                        update_post_meta($order_id, 'wf_feesPayer', $paymentfeesby);
                        update_post_meta($order_id, 'wf_is_split_payment', 'yes');
                        update_post_meta($order_id, 'wf_order_recievers', $data_array);
                        //redirect to paypal
                        return array(
                            'result' => 'success',
                            'redirect' => $payment_url
                        );
                    } else {
                        // No pay key obtained. Something wrong with admin setup
                        $error_code = "<br>Error Code: " . $result_jso->error[0]->errorId;
                        wc_add_notice(__($result_jso->error[0]->message, 'wf-paypal-adaptive-split-payment') . $error_code, 'error');
                        return;
                    }
                } else {

                    // No pay key obtained. Something wrong with admin setup
                    $error_code = "<br>Error Code: " . $jso->error[0]->errorId;
                    wc_add_notice(__($jso->error[0]->message, 'wf-paypal-adaptive-split-payment') . $error_code, 'error');
                    return;
                }
            } else {
                wc_add_notice(__('Sorry, Something went wrong'), 'error');
                return;
            }
        }

    }

    function wf_is_delayed_payment_logs_present($myorders) {

        if ($myorders && is_array($myorders)) {

            foreach ($myorders as $eachorder) {
                $orderid = $eachorder->ID;
                $transaction_order = get_post_meta($orderid, 'wf_delayed_payment_orders', true);

                if (is_array($transaction_order)) {
                    return true;
                }
            }
        }
        return false;
    }

    function wf_manual_pay_call_callbackfn() {

        if (isset($_POST['orderid'])) {

            $orderid = (int) $_POST['orderid'];

            $response = wf_record_delayed_payment_response($orderid, true, false);

            $ack = isset($response->responseEnvelope->ack) ? $response->responseEnvelope->ack : 'Failure';

            $ack = isset($response->error[0]->message) ? $response->error[0]->message : $ack;

            if ($ack == 'Success') {
                $order = new WC_Order($orderid);
                $order->add_order_note(__('Acknowledgement Received Delayed payment to Secondary Receiver(s) Successful', 'wf-paypal-adaptive-split-payment'));
                wp_clear_scheduled_hook('wf_schedule_to_execute_delay_payment_for_secondary_receivers', array($orderid));
            }

            echo $ack;
        }
        exit;
    }

    add_action('wp_ajax_manual_pay_call_for_delayedchain', 'wf_manual_pay_call_callbackfn');
    add_action('woocommerce_thankyou', 'wf_adaptive_split_thankyou', 10, 1);

    function wf_adaptive_split_thankyou($order_id) {
        if (get_post_meta($order_id, 'wf_check_checkout_page_duplication', true) != $order_id) {
            update_post_meta($order_id, 'wf_check_checkout_page_duplication', $order_id);
            $neworder = new WF_Paypal_Adaptive_Split_Payment();
            $order = new WC_Order($order_id);
            $order_status_response = $neworder->get_option('troubleshoot_option');

            $pay_key = get_post_meta($order_id, 'wf_payKey', true);
            $payment_mode = get_post_meta($order_id, 'wf_payment_mode', true);
            $delay_period = get_post_meta($order_id, 'wf_delay_period', true);

            $is_ipn_set = !empty($pay_key) ? $pay_key : false;
            if ($is_ipn_set) {
                //check Order-Status if it is based On IPN Response 
                if ($order_status_response == 1) {
                    if ($order->payment_method == 'wf_paypal_adaptive') {
                        if ($order->status != 'processing' || $order->status != 'completed') {
                            $order->update_status('on-hold', __('Awaiting IPN Response', 'wf-paypal-adaptive-split-payment'));
                        }
                    }
                }
                //check Order-Status if it is based On Payment Status
                else {
                    if ($order->status == 'pending' || $order->status != 'processing') {
                        //get API Credentials
                        $security_user_id = $neworder->security_user_id;
                        $security_password = $neworder->security_password;
                        $security_signature = $neworder->security_signature;
                        $security_application_id = $neworder->security_application_id;

                        //headers data
                        $headers_array = array("X-PAYPAL-SECURITY-USERID" => $security_user_id,
                            "X-PAYPAL-SECURITY-PASSWORD" => $security_password,
                            "X-PAYPAL-SECURITY-SIGNATURE" => $security_signature,
                            "X-PAYPAL-APPLICATION-ID" => $security_application_id,
                            "X-PAYPAL-REQUEST-DATA-FORMAT" => "NV",
                            "X-PAYPAL-RESPONSE-DATA-FORMAT" => "JSON",
                        );

                        //body data
                        $data_array = array(
                            'payKey' => $pay_key,
                            'requestEnvelope.errorLanguage' => 'en_US',
                        );

                        //check mode
                        if ("yes" == $neworder->testmode) {
                            $pay_result = wf_get_cURL_adaptive_split_response('https://svcs.sandbox.paypal.com/AdaptivePayments/PaymentDetails', $headers_array, $data_array);
                        } else {
                            $pay_result = wf_get_cURL_adaptive_split_response('https://svcs.paypal.com/AdaptivePayments/PaymentDetails', $headers_array, $data_array);
                        }

                        // Decode Payment details
                        $jso = json_decode($pay_result);
                        $payment_status = $jso->status; // status of payment

                        if (isset($order->id) && isset($jso->status)) {
                            // if order exist
                            if ($payment_status == 'COMPLETED' || $payment_status == 'CREATED' || $payment_status == 'PROCESSING' || $payment_status == 'INCOMPLETE') {// check payment status
                                $order->payment_complete();
//                                $order->update_status('completed');

                                if ($payment_mode == 'parallel' || $payment_mode == 'chained') {
                                    $order->add_order_note(__('Acknowledgement Received Payment Successful', 'wf-paypal-adaptive-split-payment'));
                                }
                                //payment order details of delayed_chained
                                if ($payment_mode == 'delayed_chained' && $delay_period > 0 && $jso->status == 'INCOMPLETE') {
                                    $order->add_order_note(__('Acknowledgement Received Payment to Primary Receiver Successful', 'wf-paypal-adaptive-split-payment'));

                                    //check seconday receivers
                                    wf_record_delayed_payment_response($order->id);

                                    $timestamp = time() + (int) ($delay_period * 86400);

                                    wf_trigger_cron_event_to_schedule_delay_payment($order_id, $timestamp);
                                }
                            } else {
                                $order->update_status('cancelled');
                                $order->add_order_note(__('Acknowledgement Received Payment Failed', 'wf-paypal-adaptive-split-payment'));
                            }
                        }
                    }
                }
            }
        }
    }

    /*
     * 
     * Create new Gateway Method.
     * 
     */

    function add_wf_payment($methods) {
        $methods[] = 'WF_Paypal_Adaptive_Split_Payment';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_wf_payment');
    /*
     * 
     * 
     * 
     * 
     */

    function wf_filter_payment_gateways($available_gateways) {
        global $woocommerce;
        if (function_exists('WC')) {
            $cart_content_count = WC()->cart->cart_contents_count;
            $cart_content = WC()->cart->get_cart();
            foreach ($cart_content as $key => $value) {
                $productid = $value['data']->id;
                $get_product = get_product($productid);
                if (check_wf_product_in_cart($productid)) {
                    foreach ($available_gateways as $key => $eachgateway) {
                        $paypal_adaptive_payment = new WF_Paypal_Adaptive_Split_Payment();
                        if ($paypal_adaptive_payment->restrict_payment_gateways == 'yes') {
                            if (($eachgateway->id != 'wf_paypal_adaptive')) {
                                unset($available_gateways[$eachgateway->id]);
                            }
                        }
                    }
                    return $available_gateways;
                }
            }
        }
        return $available_gateways;
    }

    add_filter('woocommerce_available_payment_gateways', 'wf_filter_payment_gateways');

    /*
     * 
     * Check PayPal Split Contains Enabled Product is in Cart
     * 
     */

    function check_wf_product_in_cart($product_id) {
        $check_in_which_level = get_post_meta($product_id, '_enable_wf_paypal_adaptive', true);

        if ($check_in_which_level == "enable_indiv") {
            return true;
        } elseif ($check_in_which_level == "enable_category") {
            return true;
        } elseif ($check_in_which_level == "disable" || $check_in_which_level == '') {
            return true;
        }
        return false;
    }

    /*
     * 
     * Validate Email and Price in setting Page.
     * 
     */

    function wf_paypal_add_validation_script() {
        global $woocommerce;
        if (isset($_GET['section'])) {
            if ($_GET['section'] == 'wf-paypal-adaptive-split-payment' || $_GET['section'] == 'wf_paypal_adaptive') {
                ?>
                <script type="text/javascript">
                    jQuery(document).ready(function () {
                <?php if ((float) $woocommerce->version <= (float) ('2.2.0')) { ?>
                            jQuery('#woocommerce_wf_paypal_adaptive_hide_product_field_user_role').chosen();
                    <?php
                } else {
                    ?>
                            jQuery('#woocommerce_wf_paypal_adaptive_hide_product_field_user_role').select2();
                    <?php
                }
                ?>
                        var currentstatemode = jQuery('#woocommerce_wf_paypal_adaptive__payment_mode').val();


                        if (currentstatemode === 'parallel') {
                            jQuery('#woocommerce_wf_paypal_adaptive__payment_parallel_fees').parent().parent().parent().show();
                            jQuery('#woocommerce_wf_paypal_adaptive__payment_chained_fees').parent().parent().parent().hide();
                            jQuery('#woocommerce_wf_paypal_adaptive__delay_chained_period').parent().parent().parent().hide();
                        } else if (currentstatemode === 'chained') {
                            jQuery('#woocommerce_wf_paypal_adaptive__payment_chained_fees').parent().parent().parent().show();
                            jQuery('#woocommerce_wf_paypal_adaptive__payment_parallel_fees').parent().parent().parent().hide();
                            jQuery('#woocommerce_wf_paypal_adaptive__delay_chained_period').parent().parent().parent().hide();
                        } else {
                            jQuery('#woocommerce_wf_paypal_adaptive__payment_chained_fees').parent().parent().parent().show();
                            jQuery('#woocommerce_wf_paypal_adaptive__payment_parallel_fees').parent().parent().parent().hide();
                            jQuery('#woocommerce_wf_paypal_adaptive__delay_chained_period').parent().parent().parent().show();
                        }



                        jQuery('#woocommerce_wf_paypal_adaptive__payment_mode').change(function () {
                            var presentstate = jQuery(this).val();




                            if (presentstate === 'parallel') {
                                jQuery('#woocommerce_wf_paypal_adaptive__payment_parallel_fees').parent().parent().parent().show();
                                jQuery('#woocommerce_wf_paypal_adaptive__payment_chained_fees').parent().parent().parent().hide();
                                jQuery('#woocommerce_wf_paypal_adaptive__delay_chained_period').parent().parent().parent().hide();
                            } else if (presentstate === 'chained') {
                                jQuery('#woocommerce_wf_paypal_adaptive__payment_chained_fees').parent().parent().parent().show();
                                jQuery('#woocommerce_wf_paypal_adaptive__payment_parallel_fees').parent().parent().parent().hide();
                                jQuery('#woocommerce_wf_paypal_adaptive__delay_chained_period').parent().parent().parent().hide();
                            } else {
                                jQuery('#woocommerce_wf_paypal_adaptive__payment_chained_fees').parent().parent().parent().show();
                                jQuery('#woocommerce_wf_paypal_adaptive__payment_parallel_fees').parent().parent().parent().hide();
                                jQuery('#woocommerce_wf_paypal_adaptive__delay_chained_period').parent().parent().parent().show();
                            }

                        });
                        jQuery('#woocommerce_wf_paypal_adaptive_pri_r_paypal_enable').attr('checked', 'checked');
                        var wf_paypal_enable = [];
                        for (var i = 1; i <= 5; i++) {
                            wf_paypal_enable[i] = jQuery('#woocommerce_wf_paypal_adaptive_sec_r' + i + '_paypal_enable');
                        }

                        //enable/disable event handle for secondary receiver
                        for (var k = 1; k <= 5; k++) {
                            if (wf_paypal_enable[k].is(":checked")) {
                                wf_paypal_enable[k].parent().parent().parent().parent().next().css('display', 'table-row');
                                wf_paypal_enable[k].parent().parent().parent().parent().next().next().css('display', 'table-row');
                            } else {
                                wf_paypal_enable[k].parent().parent().parent().parent().next().css('display', 'none');
                                wf_paypal_enable[k].parent().parent().parent().parent().next().next().css('display', 'none');
                            }
                        }

                        wf_paypal_enable[1].change(function () {
                            if (wf_paypal_enable[1].is(":checked")) {
                                wf_paypal_enable[1].parent().parent().parent().parent().next().css('display', 'table-row');
                                wf_paypal_enable[1].parent().parent().parent().parent().next().next().css('display', 'table-row');
                            } else {
                                wf_paypal_enable[1].parent().parent().parent().parent().next().css('display', 'none');
                                wf_paypal_enable[1].parent().parent().parent().parent().next().next().css('display', 'none');
                            }
                        });
                        wf_paypal_enable[2].change(function () {
                            if (wf_paypal_enable[2].is(":checked")) {
                                wf_paypal_enable[2].parent().parent().parent().parent().next().css('display', 'table-row');
                                wf_paypal_enable[2].parent().parent().parent().parent().next().next().css('display', 'table-row');
                            } else {
                                wf_paypal_enable[2].parent().parent().parent().parent().next().css('display', 'none');
                                wf_paypal_enable[2].parent().parent().parent().parent().next().next().css('display', 'none');
                            }
                        });
                        wf_paypal_enable[3].change(function () {
                            if (wf_paypal_enable[3].is(":checked")) {
                                wf_paypal_enable[3].parent().parent().parent().parent().next().css('display', 'table-row');
                                wf_paypal_enable[3].parent().parent().parent().parent().next().next().css('display', 'table-row');
                            } else {
                                wf_paypal_enable[3].parent().parent().parent().parent().next().css('display', 'none');
                                wf_paypal_enable[3].parent().parent().parent().parent().next().next().css('display', 'none');
                            }
                        });
                        wf_paypal_enable[4].change(function () {
                            if (wf_paypal_enable[4].is(":checked")) {
                                wf_paypal_enable[4].parent().parent().parent().parent().next().css('display', 'table-row');
                                wf_paypal_enable[4].parent().parent().parent().parent().next().next().css('display', 'table-row');
                            } else {
                                wf_paypal_enable[4].parent().parent().parent().parent().next().css('display', 'none');
                                wf_paypal_enable[4].parent().parent().parent().parent().next().next().css('display', 'none');
                            }
                        });
                        wf_paypal_enable[5].change(function () {
                            if (wf_paypal_enable[5].is(":checked")) {
                                wf_paypal_enable[5].parent().parent().parent().parent().next().css('display', 'table-row');
                                wf_paypal_enable[5].parent().parent().parent().parent().next().next().css('display', 'table-row');
                            } else {
                                wf_paypal_enable[5].parent().parent().parent().parent().next().css('display', 'none');
                                wf_paypal_enable[5].parent().parent().parent().parent().next().next().css('display', 'none');
                            }
                        });
                        function validateEmail(email)
                        {
                            var x = email;
                            var atpos = x.indexOf("@");
                            var dotpos = x.lastIndexOf(".");
                            if (atpos < 1 || dotpos < atpos + 2 || dotpos + 2 >= x.length)
                            {
                                return false;
                            } else {
                                return true;
                            }
                        }
                        //validation for 100% on submit and email validation etc
                        jQuery('#mainform').submit(function () {
                            var wf_paypal_pri_percent = jQuery('#woocommerce_wf_paypal_adaptive_pri_r_amount_percentage');
                            var wf_paypal_mail = [];
                            for (var i = 1; i <= 5; i++) {
                                wf_paypal_mail[i] = jQuery('#woocommerce_wf_paypal_adaptive_sec_r' + i + '_paypal_mail');
                            }
                            var wf_paypal_percent = [];
                            for (var i = 1; i <= 5; i++) {
                                wf_paypal_percent[i] = jQuery('#woocommerce_wf_paypal_adaptive_sec_r' + i + '_amount_percentage');
                            }

                            var wf_paypal_total_percent = 0; //declare

                            for (var j = 1; j <= 5; j++) {
                                if (wf_paypal_enable[j].is(":checked")) {

                                    if (!validateEmail(wf_paypal_mail[j].val())) {
                                        alert("Please Check Email address for enabled Receiver");
                                        return false;
                                    }
                                    if (wf_paypal_percent[j].val().length == 0) {
                                        alert("Percentage should not be empty for enabled Receiver");
                                        return false;
                                    } else {
                                        wf_paypal_total_percent = wf_paypal_total_percent + parseFloat(wf_paypal_percent[j].val());
                                    }
                                }
                            }
                            wf_paypal_total_percent = wf_paypal_total_percent + parseFloat(wf_paypal_pri_percent.val());
                            if (wf_paypal_total_percent != 100) {
                                alert("The Sum of enabled Receiver percentages should be equal to 100");
                                return false;
                            }


                        });
                    });</script>
                <?php
            }
        }
        if (isset($_GET['taxonomy'])) {
            if ($_GET['taxonomy'] == 'product_cat' && $_GET['post_type'] == 'product') {
                ?>
                <script type="text/javascript">
                    jQuery(document).ready(function () {
                        var wf_paypal_enable = [];
                        for (var i = 1; i <= 6; i++) {
                            wf_paypal_enable[i] = jQuery('#_wf_paypal_rec_' + i + '_enable');
                        }


                        function validateEmail(email)
                        {
                            var x = email;
                            var atpos = x.indexOf("@");
                            var dotpos = x.lastIndexOf(".");
                            if (atpos < 1 || dotpos < atpos + 2 || dotpos + 2 >= x.length)
                            {
                                return false;
                            } else {
                                return true;
                            }
                        }
                        //validation for 100% on submit and email validation etc
                        jQuery('#edittag').submit(function () {

                            var wf_paypal_mail = [];
                            for (var i = 1; i <= 6; i++) {
                                wf_paypal_mail[i] = jQuery('#_wf_paypal_rec_' + i + '_mail_id');
                            }
                            var wf_paypal_percent = [];
                            for (var i = 1; i <= 6; i++) {
                                wf_paypal_percent[i] = jQuery('#_wf_paypal_rec_' + i + '_percent');
                            }

                            var wf_paypal_total_percent = 0; //declare

                            for (var j = 1; j <= 6; j++) {
                                if (wf_paypal_enable[j].is(":checked")) {

                                    if (!validateEmail(wf_paypal_mail[j].val())) {
                                        alert("Please Check Email address for enabled Receiver");
                                        return false;
                                    }
                                    if (wf_paypal_percent[j].val().length == 0) {
                                        alert("Percentage should not be empty for enabled Receiver");
                                        return false;
                                    } else {
                                        wf_paypal_total_percent = wf_paypal_total_percent + parseFloat(wf_paypal_percent[j].val());
                                    }
                                }
                            }
                            console.log(wf_paypal_total_percent);
                            //wf_paypal_total_percent = wf_paypal_total_percent + parseFloat(wf_paypal_pri_percent.val());
                            if (wf_paypal_total_percent != 100) {
                                alert("The Sum of enabled Receiver percentages should be equal to 100");
                                return false;
                            }


                        });


                    });</script>
                <?php
            }
        }
        if (isset($_GET['action'])) {
            if ($_GET['action'] == 'edit') {
                ?>
                <script type="text/javascript">
                    jQuery(document).ready(function () {
                        jQuery('#_wf_paypal_primary_1_enable').attr('checked', 'checked');
                        jQuery('#_wf_paypal_primary_1_enable').attr("disabled", true);
                        var wf_paypal_enable = [];
                        for (var i = 1; i <= 5; i++) {
                            wf_paypal_enable[i] = jQuery('#_wf_paypal_sec_' + i + '_enable');
                        }

                        if (jQuery('#_enable_wf_paypal_adaptive').val() != "enable_indiv") {
                            jQuery('.wf_paypal_split_indiv').css('display', 'none');
                        } else {
                            jQuery('.wf_paypal_split_indiv').css('display', 'block');
                        }

                        jQuery('#_enable_wf_paypal_adaptive').change(function () {
                            if (jQuery(this).val() != "enable_indiv") {
                                jQuery('.wf_paypal_split_indiv').css('display', 'none');
                            } else {
                                jQuery('.wf_paypal_split_indiv').css('display', 'block');
                            }
                        });
                        //enable/disable event handle for secondary receiver
                        for (var k = 1; k <= 5; k++) {
                            if (wf_paypal_enable[k].is(":checked")) {
                                //                                 alert(jQuery('#_enable_wf_paypal_adaptive').val());
                                if (jQuery('#_enable_wf_paypal_adaptive').val() == "enable_indiv") {
                                    wf_paypal_enable[k].parent().next().css('display', 'block');
                                    wf_paypal_enable[k].parent().next().next().css('display', 'block');
                                }
                            } else {
                                wf_paypal_enable[k].parent().next().css('display', 'none');
                                wf_paypal_enable[k].parent().next().next().css('display', 'none');
                            }
                        }

                        wf_paypal_enable[1].change(function () {
                            if (wf_paypal_enable[1].is(":checked")) {
                                wf_paypal_enable[1].parent().next().css('display', 'block');
                                wf_paypal_enable[1].parent().next().next().css('display', 'block');
                            } else {
                                wf_paypal_enable[1].parent().next().css('display', 'none');
                                wf_paypal_enable[1].parent().next().next().css('display', 'none');
                            }
                        });
                        wf_paypal_enable[2].change(function () {
                            if (wf_paypal_enable[2].is(":checked")) {
                                wf_paypal_enable[2].parent().next().css('display', 'block');
                                wf_paypal_enable[2].parent().next().next().css('display', 'block');
                            } else {
                                wf_paypal_enable[2].parent().next().css('display', 'none');
                                wf_paypal_enable[2].parent().next().next().css('display', 'none');
                            }
                        });
                        wf_paypal_enable[3].change(function () {
                            if (wf_paypal_enable[3].is(":checked")) {
                                wf_paypal_enable[3].parent().next().css('display', 'block');
                                wf_paypal_enable[3].parent().next().next().css('display', 'block');
                            } else {
                                wf_paypal_enable[3].parent().next().css('display', 'none');
                                wf_paypal_enable[3].parent().next().next().css('display', 'none');
                            }
                        });
                        wf_paypal_enable[4].change(function () {
                            if (wf_paypal_enable[4].is(":checked")) {
                                wf_paypal_enable[4].parent().next().css('display', 'block');
                                wf_paypal_enable[4].parent().next().next().css('display', 'block');
                            } else {
                                wf_paypal_enable[4].parent().next().css('display', 'none');
                                wf_paypal_enable[4].parent().next().next().css('display', 'none');
                            }
                        });
                        wf_paypal_enable[5].change(function () {
                            if (wf_paypal_enable[5].is(":checked")) {
                                wf_paypal_enable[5].parent().next().css('display', 'block');
                                wf_paypal_enable[5].parent().next().next().css('display', 'block');
                            } else {
                                wf_paypal_enable[5].parent().next().css('display', 'none');
                                wf_paypal_enable[5].parent().next().next().css('display', 'none');
                            }
                        });
                        function validateEmail(email)
                        {
                            var x = email;
                            var atpos = x.indexOf("@");
                            var dotpos = x.lastIndexOf(".");
                            if (atpos < 1 || dotpos < atpos + 2 || dotpos + 2 >= x.length)
                            {
                                return false;
                            } else {
                                return true;
                            }
                        }
                        //validation for 100% on submit and email validation etc
                        jQuery('#post').submit(function () {

                            var wf_paypal_pri_percent = jQuery('#_wf_paypal_primary_rec_percent');
                            var wf_paypal_mail = [];
                            for (var i = 1; i <= 5; i++) {
                                wf_paypal_mail[i] = jQuery('#_wf_paypal_sec_' + i + '_rec_mail_id');
                            }
                            var wf_paypal_percent = [];
                            for (var i = 1; i <= 5; i++) {
                                wf_paypal_percent[i] = jQuery('#_wf_paypal_sec_' + i + '_rec_percent');
                            }

                            var wf_paypal_total_percent = 0; //declare
                            if (jQuery('#_enable_wf_paypal_adaptive').length > 0) {
                                if (jQuery('#_enable_wf_paypal_adaptive').val() == 'enable_indiv') {
                                    for (var j = 1; j <= 5; j++) {
                                        if (wf_paypal_enable[j].is(":checked")) {

                                            if (!validateEmail(wf_paypal_mail[j].val())) {
                                                alert("Please Check Email address for enabled Receiver");
                                                return false;
                                            }
                                            if (wf_paypal_percent[j].val().length == 0) {
                                                alert("Percentage should not be empty for enabled Receiver");
                                                return false;
                                            } else {
                                                wf_paypal_total_percent = wf_paypal_total_percent + parseFloat(wf_paypal_percent[j].val());
                                            }
                                        }
                                    }
                                    wf_paypal_total_percent = wf_paypal_total_percent + parseFloat(wf_paypal_pri_percent.val());
                                    if (wf_paypal_total_percent != 100) {
                                        alert("The Sum of enabled Receiver percentages should be equal to 100");
                                        return false;
                                    }
                                }
                            }

                        });
                    });
                </script>
                <?php
            }
        }
    }

    add_action('admin_head', 'wf_paypal_add_validation_script');
}

add_action('plugins_loaded', 'init_paypal_adaptive');


/*
 * 
 * Plugin Translation
 * 
 */

function wf_paypal_translate_file() {
    load_plugin_textdomain('wf-paypal-adaptive-split-payment', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_action('plugins_loaded', 'wf_paypal_translate_file');

/*
 * 
 * Connect Paypal Via curl using Credentials.
 * 
 */

function wf_get_cURL_adaptive_split_response($url, $headers_array, $data_array) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSLVERSION, 6);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data_array));

    if (!empty($headers_array)) {
        $headers = array();
        foreach ($headers_array as $name => $value) {
            $headers[] = "{$name}: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    } else {
        curl_setopt($ch, CURLOPT_HEADER, false);
    }

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

/*
 * 
 * IPN handler function
 * 
 */

function wf_check_ipn_split_to_complete_order() {

    if (isset($_REQUEST['ipn'])) { //if ipn not exist
        $paypal_ipn_url = "https://www.sandbox.paypal.com/cgi-bin/webscr";
        $ipn_post = !empty($_POST) ? $_POST : false;

        if ($ipn_post) {
            $neworder = new WF_Paypal_Adaptive_Split_Payment();
            $received_order_id = $_GET['self_custom'];
            $order = new WC_Order($received_order_id);
            $order_status_response = $neworder->get_option('troubleshoot_option');
            header('HTTP/1.1 200 OK');

            if ($order_status_response == 1 || $order->status == 'on-hold') {//check if it is based on ipn status
                // read POST data
                $raw_post_data = file_get_contents('php://input');
                $raw_post_array = explode('&', $raw_post_data);
                $myPost = array();
                foreach ($raw_post_array as $keyval) {
                    $keyval = explode('=', $keyval);
                    if (count($keyval) == 2)
                        $myPost[urldecode($keyval[0])] = urldecode($keyval[1]);
                }

                // read the IPN message sent from PayPal and prepend 'cmd=_notify-validate'
                $req = 'cmd=_notify-validate';
                if (function_exists('get_magic_quotes_gpc')) {
                    $get_magic_quotes_exists = true;
                }

                foreach ($myPost as $key => $value) {
                    if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
                        $value = urlencode(stripslahes($value));
                    } else {
                        $value = urlencode($value);
                    }
                    $req .= "&$key=$value";
                }

                //connect the paypal via curl
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $paypal_ipn_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSLVERSION, 6);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
                curl_setopt($ch, CURLOPT_HEADER, false);

                //responce from paypal
                $response = curl_exec($ch);
                curl_close($ch);


                if (strcmp($response, "VERIFIED") == 0) {//if invalid not exist
                    $payment_status = $myPost['status']; //Payment status
                    $get_pay_key = get_post_meta($received_order_id, 'wf_payKey', true);
                    $payment_mode = get_post_meta($received_order_id, 'wf_payment_mode', true);
                    $delay_period = get_post_meta($received_order_id, 'wf_delay_period', true);
                    $is_ipn_set = !empty($get_pay_key) ? $get_pay_key : false;

                    if ($is_ipn_set) {
                        if (isset($order->id) && isset($myPost['status'])) { // if order exist
                            if ($payment_status == 'COMPLETED' || $payment_status == 'CREATED' || $payment_status == 'PROCESSING' || $payment_status == 'INCOMPLETE') {
                                $order->payment_complete();
                                $order->update_status('completed');

                                if ($payment_mode == 'parallel' || $payment_mode == 'chained') {
                                    $order->add_order_note(__('Acknowledgement Received Payment Successful', 'wf-paypal-adaptive-split-payment'));
                                }
                                //payment order details of delayed_chained
                                if ($payment_mode == 'delayed_chained' && $delay_period > 0 && $payment_status == 'INCOMPLETE') {
                                    $order->add_order_note(__('Acknowledgement Received Payment to Primary Receiver Successful', 'wf-paypal-adaptive-split-payment'));
                                    //check seconday receivers
                                    wf_record_delayed_payment_response($order->id);

                                    $timestamp = time() + (int) ($delay_period * 86400);

                                    wf_trigger_cron_event_to_schedule_delay_payment($order_id, $timestamp);
                                }
                            } else {
                                $order->update_status('cancelled');
                                $order->add_order_note(__('Acknowledgement Received Payment Failed', 'wf-paypal-adaptive-split-payment'));
                            }
                        }
                    }
                }
            }
        }
    }
}

add_action('init', 'wf_check_ipn_split_to_complete_order', 99);

/*
 * 
 * Trigger cron event until schedule delay payment completed.
 * 
 */

function wf_trigger_cron_event_to_schedule_delay_payment($orderid, $timestamp) {

    if ($timestamp >= 0) {
        if (!wp_next_scheduled('wf_schedule_to_execute_delay_payment_for_secondary_receivers', array($orderid))) {
            wp_schedule_single_event($timestamp, 'wf_schedule_to_execute_delay_payment_for_secondary_receivers', array($orderid));
        }
    }
}

add_action('wf_schedule_to_execute_delay_payment_for_secondary_receivers', 'wf_execute_delay_payment_on_scheduled', 10, 1);


/*
 * 
 * To Perform Secondary Recievers Payment when Event Trigger.  
 * 
 */

function wf_perform_pay_call($orderid, $action) {

    $neworder = new WF_Paypal_Adaptive_Split_Payment();
    $security_user_id = $neworder->security_user_id;
    $security_password = $neworder->security_password;
    $security_signature = $neworder->security_signature;
    $security_application_id = $neworder->security_application_id;

    $payKey = get_post_meta($orderid, 'wf_payKey', true);

    $headers_array = array(
        "X-PAYPAL-SECURITY-USERID" => $security_user_id,
        "X-PAYPAL-SECURITY-PASSWORD" => $security_password,
        "X-PAYPAL-SECURITY-SIGNATURE" => $security_signature,
        "X-PAYPAL-APPLICATION-ID" => $security_application_id,
        "X-PAYPAL-REQUEST-DATA-FORMAT" => "NV",
        "X-PAYPAL-RESPONSE-DATA-FORMAT" => "JSON",
    );

    $data_array = array(
        'payKey' => $payKey,
        'requestEnvelope.errorLanguage' => 'en_US',
    );

    if ("yes" == $neworder->testmode) {
        $pay_result = wf_get_cURL_adaptive_split_response('https://svcs.sandbox.paypal.com/AdaptivePayments/' . $action, $headers_array, $data_array);
    } else {
        $pay_result = wf_get_cURL_adaptive_split_response('https://svcs.paypal.com/AdaptivePayments/' . $action, $headers_array, $data_array);
    }

    $response = json_decode($pay_result);

    return $response;
}

function wf_execute_delay_payment_on_scheduled($orderid) {
    wf_record_delayed_payment_response($orderid, true, true);
}

/*
 * 
 * Response From Paypal after Completed Secondary Receivers. 
 * 
 */

function wf_record_delayed_payment_response($orderid, $executePayment = false, $execute_delayed_cron = false) {//For Delayed Chained Payments Only.
    $payresponse = $executePayment ? wf_perform_pay_call($orderid, 'ExecutePayment') : false; //Perform pay request to pay secondary receiver(s)

    $retrieve_payment_info = wf_perform_pay_call($orderid, 'PaymentDetails'); //Retrieve payment informations from the pay key

    $payment_informations = isset($retrieve_payment_info->paymentInfoList->paymentInfo) ? $retrieve_payment_info->paymentInfoList->paymentInfo : '';

    $pri_receiver = '';
    $sec_receiver = '';

    $total_order_amount = get_post_meta($orderid, 'wf_order_amt', true);

    if (is_array($payment_informations)) {

        foreach ($payment_informations as $info) {

            if ($info->receiver->primary == 'true') {

                $pri_receiver = $info->receiver->email;
            }
            if ($info->receiver->primary == 'false') {

                $sec_receiver[] = $info->receiver->email . ' / ' . $info->receiver->amount;
            }
        }
    }

    $new_log = array();

    if (is_array($sec_receiver)) {

        if ($payresponse) {

            if ($payresponse->responseEnvelope->ack == 'Success' && $payresponse->paymentExecStatus == 'COMPLETED') {

                wp_clear_scheduled_hook('wf_schedule_to_execute_delay_payment_for_secondary_receivers', array($orderid));

                $new_log = array('pri_receivr' => $pri_receiver, 'sec_receivr' => $sec_receiver, 'orderid' => $orderid, 'total_order_amt' => $total_order_amount, 'result' => 'Success');
            } else {//If payment error occurs reschedule cron to execute payment
                $error_msg = isset($payresponse->error[0]->message) ? $payresponse->error[0]->message : 'Payment Failure';

                $new_log = array('pri_receivr' => $pri_receiver, 'sec_receivr' => $sec_receiver, 'orderid' => $orderid, 'total_order_amt' => $total_order_amount, 'result' => $error_msg);

                if ($execute_delayed_cron) {

                    $newobj = new WF_Paypal_Adaptive_Split_Payment();
                    $no_of_days = $newobj->get_option('retry_delayed_cron_job');

                    for ($i = 1; $i <= $no_of_days; $i++) {

                        $timestamp = time() + (86400 * $i);

                        wp_schedule_single_event($timestamp, 'wf_schedule_to_execute_delay_payment_for_secondary_receivers', array($orderid));
                    }
                }
            }
        } else {
            $payment_ack = $retrieve_payment_info->responseEnvelope->ack == 'Success' ? 'AwaitingPay' : 'Error';

            $payment_ack = isset($retrieve_payment_info->error[0]->message) ? $retrieve_payment_info->error[0]->message : $payment_ack;

            if ($payment_ack == 'AwaitingPay') {

                $new_log = array('pri_receivr' => $pri_receiver, 'sec_receivr' => $sec_receiver, 'orderid' => $orderid, 'total_order_amt' => $total_order_amount, 'result' => $payment_ack);
            } else {
                $new_log = array('pri_receivr' => '--', 'sec_receivr' => '--', 'orderid' => $orderid, 'total_order_amt' => $total_order_amount, 'result' => $payment_ack);
            }
        }

        update_post_meta($orderid, 'wf_delayed_payment_orders', $new_log);
    }

    return $payresponse;
}

function wf_is_ipn_set($ipn_url, $matches) {

    if ($matches == 'str_match') {
        preg_match_all('/\S(ipn=set&self_custom=)/', $ipn_url, $matches);
    } else {
        preg_match_all('/\d+/', $ipn_url, $matches);
    }

    return $matches;
}

/**
 * Add a custom product tab.
 */
function wf_paypal_adaptive_tabs( $original_prodata_tabs) {

    $fundraising_tab = array(
        'paypal_adaptive' => array( 'label' => __( 'Paypal Adaptive', 'wc-paypal-adaptive-split-payment' ), 'target' => 'wf_paypal_adaptive', ),
    );
    $insert_at_position = 1; // Change this for desire position
    $tabs = array_slice( $original_prodata_tabs, 0, $insert_at_position, true ); // First part of original tabs
    $tabs = array_merge( $tabs, $fundraising_tab ); // Add new
    $tabs = array_merge( $tabs, array_slice( $original_prodata_tabs, $insert_at_position, null, true ) ); // Glue the second part of original
    return $tabs;
}
add_filter( 'woocommerce_product_data_tabs', 'wf_paypal_adaptive_tabs' );

/*
 * 
 * Create Setting in Product Page.
 * 
 */

function wf_paypal_adaptive_tab_content() {
    global $woocommerce, $post;
    $currency_label = get_woocommerce_currency_symbol();
    $paypal_adaptive_payment = new WF_Paypal_Adaptive_Split_Payment();
    $gethidedroles = $paypal_adaptive_payment->settings['hide_product_field_user_role'];
    $getcurrentuser = wp_get_current_user();
    $getcurrentroles = $getcurrentuser->roles;
    $array_intersect_roles = array_intersect((array) $gethidedroles, (array) $getcurrentroles);

    if ($array_intersect_roles) {
        echo '<div id="wf_paypal_adaptive" class="panel woocommerce_options_panel" style="display:none;">';
        echo '<div class="options_group">';
    } else {
        echo '<div id="wf_paypal_adaptive" class="panel woocommerce_options_panel">';
        echo '<div class="options_group">';
    }

    woocommerce_wp_select(
        array(
            'id' => '_enable_wf_paypal_adaptive',
            'label' => __('Adaptive Payment', 'wf-paypal-adaptive-split-payment'),
            'options' => array(
                'disable' => __('Use Global Settings', 'wf-paypal-adaptive-split-payment'),
                'enable_category' => __('Use Category Settings', 'wf-paypal-adaptive-split-payment'),
                'enable_indiv' => __('Use Product Settings', 'wf-paypal-adaptive-split-payment'),
            )
        )
    );

    woocommerce_wp_checkbox(
        array(
            'id' => '_wf_paypal_primary_1_enable',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Enable Receiver 1', 'wf-paypal-adaptive-split-payment'),
            'description' => __('Enable Receiver 1', 'wf-paypal-adaptive-split-payment')
        )
    );


    woocommerce_wp_text_input(
        array(
            'id' => '_wf_paypal_primary_rec_mail_id',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Receiver 1 PayPal Mail', 'wf-paypal-adaptive-split-payment'),
            'placeholder' => 'Receiver 1 PayPal Mail',
            'desc_tip' => 'true',
            'description' => __('Enter Receiver 1 PayPal Mail', 'wf-paypal-adaptive-split-payment')
        )
    );
    woocommerce_wp_text_input(
        array(
            'id' => '_wf_paypal_primary_rec_percent',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Receiver 1 Payment Percentage', 'wf-paypal-adaptive-split-payment'),
            'placeholder' => 'Receiver 1 Payment Percentage',
            'desc_tip' => 'true',
            'description' => __('Enter Receiver 1 Payment Percentage', 'wf-paypal-adaptive-split-payment')
        )
    );
    woocommerce_wp_checkbox(
        array(
            'id' => '_wf_paypal_sec_1_enable',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Enable Receiver 2', 'wf-paypal-adaptive-split-payment'),
            'description' => __('Enable Receiver 2', 'wf-paypal-adaptive-split-payment')
        )
    );
    woocommerce_wp_text_input(
        array(
            'id' => '_wf_paypal_sec_1_rec_mail_id',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Receiver 2 PayPal Mail', 'wf-paypal-adaptive-split-payment'),
            'placeholder' => 'Receiver 2 PayPal Mail',
            'desc_tip' => 'true',
            'description' => __('Enter Receiver 2 PayPal Mail', 'wf-paypal-adaptive-split-payment')
        )
    );
    woocommerce_wp_text_input(
        array(
            'id' => '_wf_paypal_sec_1_rec_percent',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Receiver 2 Payment Percentage', 'wf-paypal-adaptive-split-payment'),
            'placeholder' => 'Receiver 2 Payment Percentage',
            'desc_tip' => 'true',
            'description' => __('Enter Receiver 2 Payment Percentage', 'wf-paypal-adaptive-split-payment')
        )
    );
    woocommerce_wp_checkbox(
        array(
            'id' => '_wf_paypal_sec_2_enable',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Enable Receiver 3', 'wf-paypal-adaptive-split-payment'),
            'description' => __('Enable Receiver 3', 'wf-paypal-adaptive-split-payment')
        )
    );
    woocommerce_wp_text_input(
        array(
            'id' => '_wf_paypal_sec_2_rec_mail_id',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Receiver 3 PayPal Mail', 'wf-paypal-adaptive-split-payment'),
            'placeholder' => 'Receiver 3 PayPal Mail',
            'desc_tip' => 'true',
            'description' => __('Enter Receiver 3 PayPal Mail', 'wf-paypal-adaptive-split-payment')
        )
    );
    woocommerce_wp_text_input(
        array(
            'id' => '_wf_paypal_sec_2_rec_percent',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Receiver 3 Payment Percentage', 'wf-paypal-adaptive-split-payment'),
            'placeholder' => 'Receiver 3 Payment Percentage',
            'desc_tip' => 'true',
            'description' => __('Enter Receiver 3 Payment Percentage', 'wf-paypal-adaptive-split-payment')
        )
    );
    woocommerce_wp_checkbox(
        array(
            'id' => '_wf_paypal_sec_3_enable',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Enable Receiver 4', 'wf-paypal-adaptive-split-payment'),
            'description' => __('Enable Receiver 4', 'wf-paypal-adaptive-split-payment')
        )
    );
    woocommerce_wp_text_input(
        array(
            'id' => '_wf_paypal_sec_3_rec_mail_id',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Receiver 4 PayPal Mail', 'wf-paypal-adaptive-split-payment'),
            'placeholder' => 'Receiver 4 PayPal Mail',
            'desc_tip' => 'true',
            'description' => __('Enter Receiver 4 PayPal Mail', 'wf-paypal-adaptive-split-payment')
        )
    );
    woocommerce_wp_text_input(
        array(
            'id' => '_wf_paypal_sec_3_rec_percent',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Receiver 4 Payment Percentage', 'wf-paypal-adaptive-split-payment'),
            'placeholder' => 'Receiver 4 Payment Percentage',
            'desc_tip' => 'true',
            'description' => __('Enter Receiver 4 Payment Percentage', 'wf-paypal-adaptive-split-payment')
        )
    );
    woocommerce_wp_checkbox(
        array(
            'id' => '_wf_paypal_sec_4_enable',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Enable Receiver 5', 'wf-paypal-adaptive-split-payment'),
            'description' => __('Enable Receiver 5', 'wf-paypal-adaptive-split-payment')
        )
    );
    woocommerce_wp_text_input(
        array(
            'id' => '_wf_paypal_sec_4_rec_mail_id',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Receiver 5 PayPal Mail', 'wf-paypal-adaptive-split-payment'),
            'placeholder' => 'Receiver 5 PayPal Mail',
            'desc_tip' => 'true',
            'description' => __('Enter Receiver 5 PayPal Mail', 'wf-paypal-adaptive-split-payment')
        )
    );
    woocommerce_wp_text_input(
        array(
            'id' => '_wf_paypal_sec_4_rec_percent',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Receiver 5 Payment Percentage', 'wf-paypal-adaptive-split-payment'),
            'placeholder' => 'Receiver 5 Payment Percentage',
            'desc_tip' => 'true',
            'description' => __('Enter Receiver 5 Payment Percentage', 'wf-paypal-adaptive-split-payment')
        )
    );
    woocommerce_wp_checkbox(
        array(
            'id' => '_wf_paypal_sec_5_enable',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Enable Receiver 6', 'wf-paypal-adaptive-split-payment'),
            'description' => __('Enable Receiver 6', 'wf-paypal-adaptive-split-payment')
        )
    );
    woocommerce_wp_text_input(
        array(
            'id' => '_wf_paypal_sec_5_rec_mail_id',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Receiver 6 PayPal Mail', 'wf-paypal-adaptive-split-payment'),
            'placeholder' => 'Receiver 6 PayPal Mail',
            'desc_tip' => 'true',
            'description' => __('Enter Receiver 6 PayPal Mail', 'wf-paypal-adaptive-split-payment')
        )
    );
    woocommerce_wp_text_input(
        array(
            'id' => '_wf_paypal_sec_5_rec_percent',
            'wrapper_class' => 'wf_paypal_split_indiv',
            'label' => __('Receiver 6 Payment Percentage', 'wf-paypal-adaptive-split-payment'),
            'placeholder' => 'Receiver 6 Payment Percentage',
            'desc_tip' => 'true',
            'description' => __('Enter Receiver 6 Payment Percentage', 'wf-paypal-adaptive-split-payment')
        )
    );

    echo '</div>';
    echo '</div>';

}


/*
 *
 * Save Product Page Meta Emails.
 *
 *
 */

function wf_paypal_adaptive_save_product_meta($post_id) {
    $primary_rec = $_POST['_wf_paypal_primary_rec_mail_id'];
    $primary_rec_percent = $_POST['_wf_paypal_primary_rec_percent'];
    for ($i = 1; $i <= 5; $i++) {
        ${'sec_rec_' . $i . 'mail'} = $_POST['_wf_paypal_sec_' . $i . '_rec_mail_id'];
        ${'sec_rec_' . $i . '_percent'} = $_POST['_wf_paypal_sec_' . $i . '_rec_percent'];
    }
    if (!empty($primary_rec)) {
        update_post_meta($post_id, '_wf_paypal_primary_rec_mail_id', esc_attr($primary_rec));
    }
    if (!empty($primary_rec_percent)) {
        update_post_meta($post_id, '_wf_paypal_primary_rec_percent', esc_attr($primary_rec_percent));
    }
    for ($i = 1; $i <= 5; $i++) {
        if (!empty(${'sec_rec_' . $i . 'mail'})) {
            update_post_meta($post_id, '_wf_paypal_sec_' . $i . '_rec_mail_id', esc_attr(${'sec_rec_' . $i . 'mail'}));
        }
        if (!empty(${'sec_rec_' . $i . '_percent'})) {
            update_post_meta($post_id, '_wf_paypal_sec_' . $i . '_rec_percent', esc_attr(${'sec_rec_' . $i . '_percent'}));
        }
        $enable_sec_rec = isset($_POST['_wf_paypal_sec_' . $i . '_enable']) ? 'yes' : 'no';
        update_post_meta($post_id, '_wf_paypal_sec_' . $i . '_enable', $enable_sec_rec);
    }
    $wf_adaptive_enable = isset($_POST['_enable_wf_paypal_adaptive']) ? 'yes' : 'no';
    update_post_meta($post_id, '_enable_wf_paypal_adaptive', esc_attr($wf_adaptive_enable));

    $wf_adaptive_select = $_POST['_enable_wf_paypal_adaptive'];
    if (!empty($wf_adaptive_select)) {
        update_post_meta($post_id, '_enable_wf_paypal_adaptive', esc_attr($wf_adaptive_select));
    }
}

//add_action('woocommerce_product_options_general_product_data', 'wf_paypal_adaptive_tab_content');
add_action( 'woocommerce_product_data_panels', 'wf_paypal_adaptive_tab_content' );
add_action('woocommerce_process_product_meta', 'wf_paypal_adaptive_save_product_meta');

/*
 *
 * check if already an individual split is present or not
 * if present then don't allow the adding
 * remove previous cart content, if adding is a individual split
 *
 */

function wf_paypal_remove_previous_add_new_product($product_id, $quantity) {
    global $woocommerce;
    $check_already = "no";
    foreach ($woocommerce->cart->get_cart() as $items) {
        if ("enable_indiv" == get_post_meta($items['product_id'], "_enable_wf_paypal_adaptive", true)) {
            wc_add_notice('Sell Individually Product is already in cart', 'error');
            $check_already = "yes";
            return false;
        }
    }
    if ($check_already == "no") {
        if ("enable_indiv" == get_post_meta($product_id, "_enable_wf_paypal_adaptive", true)) {
            $woocommerce->cart->empty_cart();
        }
    }
    return array($item_details, $product_id, $variation_id);
}

//add_filter('woocommerce_add_to_cart_validation', 'wf_paypal_remove_previous_add_new_product', 10, 2);

/*
 *
 * take mail first product page or category or global.
 *
 *
 */

function wf_paypal_cart_validation_for_rec_limit() {
    global $woocommerce;
    $count = 0;
    $receivers = array();
    foreach ($woocommerce->cart->get_cart() as $items) {
        //check from individual product
        if ("enable_indiv" == get_post_meta($items['product_id'], "_enable_wf_paypal_adaptive", true)) {
            //check if already present or not
            if (!in_array(get_post_meta($items['product_id'], "_wf_paypal_primary_rec_mail_id", true), $receivers)) {
                $receivers[] = get_post_meta($items['product_id'], "_wf_paypal_primary_rec_mail_id", true);
                $count = $count + 1;
            }
            for ($i = 1; $i <= 5; $i++) {
                if ("yes" == get_post_meta($items['product_id'], '_wf_paypal_sec_' . $i . '_enable', true)) {
                    //check if already present or not
                    if (!in_array(get_post_meta($items['product_id'], '_wf_paypal_sec_' . $i . '_rec_mail_id', true), $receivers)) {
                        $receivers[] = get_post_meta($items['product_id'], '_wf_paypal_sec_' . $i . '_rec_mail_id', true);
                        $count++;
                    }
                }
            }
        } elseif ("enable_category" == get_post_meta($items['product_id'], "_enable_wf_paypal_adaptive", true)) {
            $wf_paypal_product_category = wp_get_post_terms($items['product_id'], 'product_cat');
            $categ_meta = get_metadata('woocommerce_term', $wf_paypal_product_category[0]->term_id);

            $categ_meta = get_woocommerce_term_meta($wf_paypal_product_category[0]->term_id);

            for ($i = 1; $i <= 6; $i++) {
                if ("yes" == get_woocommerce_term_meta($wf_paypal_product_category[0]->term_id, '_wf_paypal_rec_' . $i . '_enable', true)) {
                    if (!in_array(get_woocommerce_term_meta($wf_paypal_product_category[0]->term_id, '_wf_paypal_rec_' . $i . '_mail_id', true), $receivers)) {
                        $receivers[] = get_woocommerce_term_meta($wf_paypal_product_category[0]->term_id, '_wf_paypal_rec_' . $i . '_mail_id', true);
                        $count++;
                    }
                }
            }
        } elseif (("disable" == get_post_meta($items['product_id'], "_enable_wf_paypal_adaptive", true)) || (" " == get_post_meta($items['product_id'], "_enable_wf_paypal_adaptive", true))) {
            $fppap = new WF_Paypal_Adaptive_Split_Payment();
            if (!in_array($fppap->get_option('pri_r_paypal_mail'), $receivers)) {
                $receivers[] = $fppap->get_option('pri_r_paypal_mail');
                $count++;
            }

            for ($i = 1; $i <= 5; $i++) {
                if ("yes" == $fppap->get_option('sec_r' . $i . '_paypal_enable')) {
                    if (!in_array($fppap->get_option('sec_r' . $i . '_paypal_mail'), $receivers)) {
                        $receivers[] = $fppap->get_option('sec_r' . $i . '_paypal_mail');
                        $count++;
                    }
                }
            }
        }
    }
    if ($count > 6) {
        wc_add_notice('Please change or reduce cart products to make a successful sale. As it reached more than 6 paypal receivers', 'error');
    } else {
// wc_add_notice('ok', 'error');
    }
}

add_action('woocommerce_checkout_process', 'wf_paypal_cart_validation_for_rec_limit');

/*
 * 
 * Adding new Fields in category .
 * 
 */

function wf_paypal_category_new_fields() {
    $term = '';
    for ($i = 1; $i <= 6; $i++) {
        ?>
        <div class = "form-field">
            <?php
            if (isset($term->term_id)) {
                $term_value = $term->term_id;
            } else {
                $term_value = "";
            }
            ?>
            <label for = "<?php echo 'receiver_' . $i . ''; ?>">Enable Receiver <?php echo $i; ?></label>
            <input style="width: auto;" id = "<?php echo '_wf_paypal_rec_' . $i . '_enable'; ?>" type = "checkbox" aria-required = "false" size = "40" value = "<?php echo get_woocommerce_term_meta($term_value, '_wf_paypal_rec_' . $i . '_enable', true); ?>"<?php checked("yes", get_woocommerce_term_meta($term_value, '_wf_paypal_rec_' . $i . '_enable', true)); ?> name = "<?php echo '_wf_paypal_rec_' . $i . '_enable'; ?>">
            <p class = "description">Enable Receiver <?php echo $i; ?></p>

        </div>
        <div class = "form-field">

            <label for = "<?php echo 'receiver_' . $i . '_mail'; ?>">Receiver <?php echo $i; ?> Email</label>
            <input id = "<?php echo '_wf_paypal_rec_' . $i . '_mail_id'; ?>" type = "text" aria-required = "false" size = "40" value = "<?php echo get_woocommerce_term_meta($term_value, '_wf_paypal_rec_' . $i . '_mail_id', true); ?>" name = "<?php echo '_wf_paypal_rec_' . $i . '_mail_id'; ?>">
            <p class = "description">Receiver <?php echo $i; ?> Mail.</p>

        </div>
        <div class = "form-field">

            <label for = "<?php echo 'receiver_' . $i . '_percent'; ?>">Receiver <?php echo $i; ?> Payment Percentage</label>
            <input id = "<?php echo '_wf_paypal_rec_' . $i . '_percent'; ?>" type = "text" aria-required = "false" size = "40" value = "<?php echo get_woocommerce_term_meta($term_value, '_wf_paypal_rec_' . $i . '_percent', true); ?>" name = "<?php echo '_wf_paypal_rec_' . $i . '_percent'; ?>">
            <p class = "description">Receiver <?php echo $i; ?> Payment Percentage</p>

        </div>



        <?php
    }
}

add_action('product_cat_add_form_fields', 'wf_paypal_category_new_fields');

/*
 * 
 * Adding new fields in Category Edit Fields.
 * 
 * 
 */

function wf_paypal_category_edit_fields($term, $taxonomy) {

    for ($i = 1; $i <= 6; $i++) {
        ?>
        <tr class = "form-field">
            <th scope = "row">
                <label for = "<?php echo 'receiver_' . $i . ''; ?>">Enable Receiver <?php echo $i; ?></label>
            </th>
            <td align="left">
                <input style="width: auto;" id = "<?php echo '_wf_paypal_rec_' . $i . '_enable'; ?>" type = "checkbox" aria-required = "false" size = "40" value = "<?php echo get_woocommerce_term_meta($term->term_id, '_wf_paypal_rec_' . $i . '_enable', true); ?>"<?php checked("yes", get_woocommerce_term_meta($term->term_id, '_wf_paypal_rec_' . $i . '_enable', true)); ?> name = "<?php echo '_wf_paypal_rec_' . $i . '_enable'; ?>">
                <p class = "description">Enable Receiver <?php echo $i; ?></p>
            </td>
        </tr>
        <tr class = "form-field">
            <th scope = "row">
                <label for = "<?php echo 'receiver_' . $i . '_mail'; ?>">Receiver <?php echo $i; ?> Email</label>
            </th>
            <td>
                <input id = "<?php echo '_wf_paypal_rec_' . $i . '_mail_id'; ?>" type = "text" aria-required = "false" size = "40" value = "<?php echo get_woocommerce_term_meta($term->term_id, '_wf_paypal_rec_' . $i . '_mail_id', true); ?>" name = "<?php echo '_wf_paypal_rec_' . $i . '_mail_id'; ?>">
                <p class = "description">Receiver <?php echo $i; ?> Mail.</p>
            </td>
        </tr>
        <tr class = "form-field">
            <th scope = "row">
                <label for = "<?php echo 'receiver_' . $i . '_percent'; ?>">Receiver <?php echo $i; ?> Payment Percentage</label>
            </th>
            <td>
                <input id = "<?php echo '_wf_paypal_rec_' . $i . '_percent'; ?>" type = "text" aria-required = "false" size = "40" value = "<?php echo get_woocommerce_term_meta($term->term_id, '_wf_paypal_rec_' . $i . '_percent', true); ?>" name = "<?php echo '_wf_paypal_rec_' . $i . '_percent'; ?>">
                <p class = "description">Receiver <?php echo $i; ?> Payment Percentage</p>
            </td>
        </tr>

        <?php
    }
}

add_action('product_cat_edit_form_fields', 'wf_paypal_category_edit_fields', 10, 2);

/*
 * 
 * Save Category fields.
 * 
 */

function wf_paypal_category_save($term_id, $tt_id, $taxonomy) {
    for ($i = 1; $i <= 6; $i++) {
        if (isset($_POST['_wf_paypal_rec_' . $i . '_mail_id'])) {
            update_woocommerce_term_meta($term_id, '_wf_paypal_rec_' . $i . '_mail_id', esc_attr($_POST['_wf_paypal_rec_' . $i . '_mail_id']));
        }
        if (isset($_POST['_wf_paypal_rec_' . $i . '_percent'])) {
            update_woocommerce_term_meta($term_id, '_wf_paypal_rec_' . $i . '_percent', esc_attr($_POST['_wf_paypal_rec_' . $i . '_percent']));
        }

        $enable_sec_rec = isset($_POST['_wf_paypal_rec_' . $i . '_enable']) ? 'yes' : 'no';
        update_woocommerce_term_meta($term_id, '_wf_paypal_rec_' . $i . '_enable', $enable_sec_rec);
    }
}

function wf_paypal_get_order_statuses() {

    $order_statuses = array(
        'wc-pending' => _x('Pending Payment', 'Order status', 'woocommerce'),
        'wc-processing' => _x('Processing', 'Order status', 'woocommerce'),
        'wc-on-hold' => _x('On Hold', 'Order status', 'woocommerce'),
        'wc-completed' => _x('Completed', 'Order status', 'woocommerce'),
        'wc-cancelled' => _x('Cancelled', 'Order status', 'woocommerce'),
        'wc-refunded' => _x('Refunded', 'Order status', 'woocommerce'),
        'wc-failed' => _x('Failed', 'Order status', 'woocommerce'),
    );
    return $order_statuses;
}

add_action('edit_term', 'wf_paypal_category_save', 10, 3);
add_action('created_term', 'wf_paypal_category_save', 10, 3);

add_action('wp_ajax_wfpaypal_adaptive_bulk_action', array('WF_Paypal_Adaptive_Split_Payment', 'paypal_adaptive_bulk_selection'));
add_action('add_meta_boxes', array('WF_Paypal_Adaptive_Split_Payment', 'add_custom_meta_box'));



