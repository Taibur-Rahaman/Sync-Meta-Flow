<?php
defined('ABSPATH') || exit;

class SMF_Meta_CAPI {
    public static function init() {
        add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'send_purchase'), 20, 3);
        add_action('woocommerce_order_status_smf-delivered', array(__CLASS__, 'send_delivered'), 20);
    }

    public static function send_purchase($order_id, $posted_data = null, $order = null) {
        $order = $order instanceof WC_Order ? $order : wc_get_order($order_id);
        if (!$order || !self::configured()) return;
        $event_id = $order->get_meta('_smf_purchase_event_id');
        if (!$event_id) {
            $event_id = 'smf-purchase-' . $order->get_id() . '-' . wp_generate_uuid4();
            $order->update_meta_data('_smf_purchase_event_id', $event_id);
            $order->save();
        }
        $user = self::user_data($order);
        $payload = array(
            'data' => array(array(
                'event_name' => 'Purchase', 'event_time' => time(), 'action_source' => 'website', 'event_id' => $event_id,
                'user_data' => $user,
                'custom_data' => array('currency' => $order->get_currency(), 'value' => (float) $order->get_total(), 'order_id' => (string) $order->get_id(), 'content_type' => 'product'),
            )),
        );
        self::post($payload);
    }

    public static function send_delivered($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || !self::configured()) return;
        $payload = array('data' => array(array(
            'event_name' => 'OrderDelivered', 'event_time' => time(), 'action_source' => 'website', 'event_id' => 'smf-delivered-' . $order->get_id(),
            'user_data' => self::user_data($order),
            'custom_data' => array('currency' => $order->get_currency(), 'value' => (float) $order->get_total(), 'order_id' => (string) $order->get_id(), 'content_type' => 'product', 'order_status' => 'delivered'),
        )));
        self::post($payload);
    }

    private static function configured() {
        return get_option('smf_meta_enabled', 'no') === 'yes' && trim((string) get_option('smf_meta_pixel_id', '')) !== '' && trim((string) get_option('smf_meta_access_token', '')) !== '';
    }

    private static function user_data($order) {
        $data = array();
        $email = strtolower(trim((string) $order->get_billing_email()));
        $phone = preg_replace('/[^0-9]/', '', (string) $order->get_billing_phone());
        if ($email) $data['em'] = array(hash('sha256', $email));
        if ($phone) $data['ph'] = array(hash('sha256', $phone));
        $session = $order->get_meta('_smf_session_key');
        if ($session) {
            $cookie = self::session_row($session);
            if (!empty($cookie['fbclid'])) $data['fbc'] = 'fb.1.' . time() * 1000 . '.' . sanitize_text_field($cookie['fbclid']);
        }
        return $data;
    }

    private static function session_row($session) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT fbclid, utm_source FROM ' . $wpdb->prefix . 'smf_tracking_sessions WHERE session_key = %s LIMIT 1', $session), ARRAY_A);
        return is_array($row) ? $row : array();
    }

    private static function post($payload) {
        $pixel_id = preg_replace('/[^0-9]/', '', (string) get_option('smf_meta_pixel_id', ''));
        $token = trim((string) get_option('smf_meta_access_token', ''));
        if (!$pixel_id || !$token) return false;
        $response = wp_remote_post('https://graph.facebook.com/v23.0/' . rawurlencode($pixel_id) . '/events', array(
            'timeout' => 15, 'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode(array_merge($payload, array('access_token' => $token))),
        ));
        if (is_wp_error($response)) return false;
        return wp_remote_retrieve_response_code($response) >= 200 && wp_remote_retrieve_response_code($response) < 300;
    }
}
