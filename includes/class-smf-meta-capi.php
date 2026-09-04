<?php
defined('ABSPATH') || exit;

class SMF_Meta_CAPI {
    public static function init() {
        add_action('woocommerce_order_status_completed', array(__CLASS__, 'send_delivered'), 20);
    }

    public static function send_delivered($order_id) {
        if (get_option('smf_meta_enabled') !== 'yes') return;
        $pixel_id = trim((string) get_option('smf_meta_pixel_id', ''));
        $token = trim((string) get_option('smf_meta_access_token', ''));
        if (!$pixel_id || !$token) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $payload = array(
            'data' => array(array(
                'event_name' => 'Purchase',
                'event_time' => time(),
                'action_source' => 'website',
                'event_id' => 'smf-delivered-' . $order_id,
                'custom_data' => array(
                    'currency' => $order->get_currency(),
                    'value' => (float) $order->get_total(),
                    'order_id' => (string) $order_id,
                    'content_type' => 'product',
                    'smf_order_status' => 'delivered',
                ),
            )),
        );

        wp_remote_post('https://graph.facebook.com/v23.0/' . rawurlencode($pixel_id) . '/events', array(
            'timeout' => 10,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode(array_merge($payload, array('access_token' => $token))),
        ));
    }
}
