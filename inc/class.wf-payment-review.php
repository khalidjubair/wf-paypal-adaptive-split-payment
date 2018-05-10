<?php

/*
 * 
 * Settings for Payment Page in Paypal Account.
 * 
 */

class WF_Payment_Review {

    public static function wf_pay_settings_for_shipping_address($pay_key, $order, $receivers_details) {
        $receiver_email = key($receivers_details);
        if ($order->shipping_first_name == '' && $order->shipping_last_name = '') {
            $first_name = $order->billing_first_name;
            $last_name = $order->billing_last_name;
            $city_name = $order->billing_city;
            $country_name = $order->billing_country;
            $address1 = $order->billing_address_1;
            $address2 = $order->billing_address_2;
            $postcode = $order->billing_postcode;
            $state = self:: wf_get_paypal_state($order->billing_country, $order->billing_state);
        } else {
            $first_name = $order->shipping_first_name;
            $last_name = $order->shipping_last_name;
            $city_name = $order->shipping_city;
            $country_name = $order->shipping_country;
            $address1 = $order->shipping_address_1;
            $address2 = $order->shipping_address_2;
            $postcode = $order->shipping_postcode;
            $state = self::wf_get_paypal_state($order->shipping_country, $order->shipping_state);
        }

        $send_array['requestEnvelope.errorLanguage'] = "en_US";
        $send_array['receiverList.receiver(0).email'] = $receiver_email;
        $send_array['senderOptions.shippingAddress.addresseeName'] = $first_name . ' ' . $last_name;
        $send_array['senderOptions.shippingAddress.street1'] = $address1;
        $send_array['senderOptions.shippingAddress.city'] = $city_name;
        $send_array['senderOptions.shippingAddress.country'] = $country_name;
        if ($state != '') {
            $send_array['senderOptions.shippingAddress.state'] = $state;
        }
        if ($address2 != '') {
            $send_array['senderOptions.shippingAddress.street2'] = $address2;
        }
        $send_array['senderOptions.shippingAddress.zip'] = $postcode;
        $send_array['payKey'] = $pay_key;
        return $send_array;
    }

    public static function wf_get_paypal_state($cc, $state) {
        $states = WC()->countries->get_states($cc);
        if (isset($states[$state])) {
            return $states[$state];
        }

        return $state;
    }

}
