<?php
defined('ABSPATH') || exit;

class SMF_Order_Events {
    public static function init() {
        add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'purchase'), 10, 3);
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'status_changed'), 10, 4);
    }

    public static function purchase($order_id, $posted_data, $order) {
        self::log($order_id, 'purchase', null, $order->get_status(), 'woocommerce');
        self::attach_attribution($order);
    }

    public static function status_changed($order_id, $old_status, $new_status, $order) {
        self::log($order_id, 'status_changed', $old_status, $new_status, 'woocommerce');
    }

    private static function attach_attribution($order) {
        if (!empty($_COOKIE['smf_attribution'])) {
            $raw = json_decode(wp_unslash($_COOKIE['smf_attribution']), true);
            if (is_array($raw)) {
                foreach ($raw as $key => $value) {
                    $allowed = array('fbclid','utm_source','utm_medium','utm_campaign','utm_content','utm_term');
                    if (in_array($key, $allowed, true)) update_post_meta($order->get_id(), '_smf_' . $key, sanitize_text_field($value));
                }
            }
        }
    }

    public static function log($order_id, $event_type, $old_status, $new_status, $source, $metadata = array()) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'smf_order_events', array(
            'order_id' => absint($order_id),
            'event_type' => sanitize_key($event_type),
            'old_status' => $old_status ? sanitize_key($old_status) : null,
            'new_status' => $new_status ? sanitize_key($new_status) : null,
            'source' => sanitize_key($source),
            'metadata' => $metadata ? wp_json_encode($metadata) : null,
            'created_at' => current_time('mysql'),
        ));
    }
}
