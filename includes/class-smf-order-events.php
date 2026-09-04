<?php
defined('ABSPATH') || exit;

class SMF_Order_Events {
    public static function init() {
        add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'purchase'), 10, 3);
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'status_changed'), 10, 4);
        add_action('woocommerce_admin_order_data_after_billing_address', array(__CLASS__, 'order_attribution_box'));
        add_action('wp_ajax_smf_track_event', array(__CLASS__, 'browser_event'));
        add_action('wp_ajax_nopriv_smf_track_event', array(__CLASS__, 'browser_event'));
    }

    public static function purchase($order_id, $posted_data, $order) {
        self::log($order_id, 'purchase', null, $order->get_status(), 'woocommerce');
        self::attach_attribution($order);
    }

    public static function status_changed($order_id, $old_status, $new_status, $order) {
        self::log($order_id, 'status_changed', $old_status, $new_status, 'woocommerce');
    }

    public static function browser_event() {
        check_ajax_referer('smf_track', 'nonce');
        $event = isset($_POST['event']) ? sanitize_key(wp_unslash($_POST['event'])) : '';
        $allowed = array('page_view', 'view_content', 'add_to_cart', 'initiate_checkout', 'checkout_error');
        if (!in_array($event, $allowed, true)) wp_send_json_error(array('message' => 'Invalid event'), 400);

        $session = isset($_COOKIE['smf_session']) ? sanitize_text_field(wp_unslash($_COOKIE['smf_session'])) : '';
        if (!$session) $session = SMF_Tracker::save_session(array());

        $payload = array();
        if (isset($_POST['payload'])) {
            $decoded = json_decode(wp_unslash($_POST['payload']), true);
            if (is_array($decoded)) $payload = array_slice($decoded, 0, 20, true);
        }
        $event_id = isset($payload['event_id']) ? sanitize_text_field($payload['event_id']) : wp_generate_uuid4();
        $page_url = isset($payload['page_url']) ? esc_url_raw($payload['page_url']) : '';

        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'smf_tracking_events', array(
            'session_key' => $session,
            'event_name' => $event,
            'event_id' => $event_id,
            'page_url' => $page_url,
            'payload' => wp_json_encode($payload),
            'created_at' => current_time('mysql'),
        ));
        SMF_Tracker::touch_session($session, isset($_COOKIE['smf_attribution']) ? json_decode(wp_unslash($_COOKIE['smf_attribution']), true) : array());
        wp_send_json_success(array('event_id' => $event_id));
    }

    private static function attach_attribution($order) {
        $keys = array('fbclid','utm_source','utm_medium','utm_campaign','utm_content','utm_term');
        if (!empty($_COOKIE['smf_attribution'])) {
            $raw = json_decode(wp_unslash($_COOKIE['smf_attribution']), true);
            if (is_array($raw)) {
                foreach ($keys as $key) {
                    if (isset($raw[$key])) update_post_meta($order->get_id(), '_smf_' . $key, sanitize_text_field($raw[$key]));
                }
            }
        }
        if (!empty($_COOKIE['smf_session'])) update_post_meta($order->get_id(), '_smf_session_key', sanitize_text_field(wp_unslash($_COOKIE['smf_session'])));
    }

    public static function order_attribution_box($order) {
        $keys = array(
            'fbclid' => 'Facebook Click ID',
            'utm_source' => 'Source',
            'utm_medium' => 'Medium',
            'utm_campaign' => 'Campaign',
            'utm_content' => 'Content / Ad',
            'utm_term' => 'Term',
            'session_key' => 'Session',
        );
        echo '<div style="margin-top:20px;padding:12px;border:1px solid #ddd;background:#fff"><strong>Sync Meta Flow Attribution</strong><table style="width:100%;margin-top:8px">';
        foreach ($keys as $key => $label) {
            $value = get_post_meta($order->get_id(), '_smf_' . $key, true);
            if ($value !== '') echo '<tr><td style="width:35%;padding:4px 0"><strong>' . esc_html($label) . '</strong></td><td style="padding:4px 0">' . esc_html($value) . '</td></tr>';
        }
        echo '</table></div>';
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
