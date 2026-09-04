<?php
defined('ABSPATH') || exit;

class SMF_Meta_CAPI {
    const MAX_ATTEMPTS = 5;

    public static function init() {
        add_filter('cron_schedules', array(__CLASS__, 'cron_schedules'));
        add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'send_purchase'), 20, 3);
        add_action('woocommerce_order_status_smf-delivered', array(__CLASS__, 'send_delivered'), 20);
        add_action('smf_process_capi_queue', array(__CLASS__, 'process_queue'));
        if (!wp_next_scheduled('smf_process_capi_queue')) {
            wp_schedule_event(time() + 60, 'five_minutes', 'smf_process_capi_queue');
        }
    }

    public static function cron_schedules($schedules) {
        if (!isset($schedules['five_minutes'])) {
            $schedules['five_minutes'] = array('interval' => 300, 'display' => 'Every 5 minutes');
        }
        return $schedules;
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
        self::queue_event($order->get_id(), 'Purchase', $event_id, self::purchase_payload($order, $event_id));
    }

    public static function send_delivered($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || !self::configured()) return;
        $event_id = 'smf-delivered-' . $order->get_id();
        self::queue_event($order->get_id(), 'OrderDelivered', $event_id, self::delivered_payload($order, $event_id));
    }

    private static function purchase_payload($order, $event_id) {
        return array('data' => array(array(
            'event_name' => 'Purchase', 'event_time' => time(), 'action_source' => 'website', 'event_id' => $event_id,
            'user_data' => self::user_data($order),
            'custom_data' => array('currency' => $order->get_currency(), 'value' => (float) $order->get_total(), 'order_id' => (string) $order->get_id(), 'content_type' => 'product'),
        )));
    }

    private static function delivered_payload($order, $event_id) {
        return array('data' => array(array(
            'event_name' => 'OrderDelivered', 'event_time' => time(), 'action_source' => 'website', 'event_id' => $event_id,
            'user_data' => self::user_data($order),
            'custom_data' => array('currency' => $order->get_currency(), 'value' => (float) $order->get_total(), 'order_id' => (string) $order->get_id(), 'content_type' => 'product', 'order_status' => 'delivered'),
        )));
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
            $row = self::session_row($session);
            if (!empty($row['fbclid'])) $data['fbc'] = 'fb.1.' . time() * 1000 . '.' . sanitize_text_field($row['fbclid']);
        }
        $fbp = $order->get_meta('_smf_fbp');
        if ($fbp) $data['fbp'] = sanitize_text_field($fbp);
        return $data;
    }

    private static function session_row($session) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT fbclid, utm_source FROM ' . $wpdb->prefix . 'smf_tracking_sessions WHERE session_key = %s LIMIT 1', $session), ARRAY_A);
        return is_array($row) ? $row : array();
    }

    private static function queue_event($order_id, $event_name, $event_id, $payload) {
        global $wpdb;
        $table = $wpdb->prefix . 'smf_capi_queue';
        $now = current_time('mysql');
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO $table (order_id,event_name,event_id,payload,attempts,status,next_attempt_at,created_at) VALUES (%d,%s,%s,%s,0,'pending',%s,%s)",
            absint($order_id), sanitize_text_field($event_name), sanitize_text_field($event_id), wp_json_encode($payload), $now, $now
        ));
        return $inserted !== false;
    }

    public static function process_queue() {
        if (!self::configured()) return;
        global $wpdb;
        $table = $wpdb->prefix . 'smf_capi_queue';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status = 'pending' AND next_attempt_at <= %s ORDER BY id ASC LIMIT 10", current_time('mysql')), ARRAY_A);
        foreach ((array) $rows as $row) {
            $payload = json_decode($row['payload'], true);
            if (!is_array($payload)) {
                self::fail_row($row, 'Invalid queued payload.');
                continue;
            }
            $result = self::post($payload);
            if ($result['success']) {
                $wpdb->update($table, array('status' => 'sent', 'sent_at' => current_time('mysql'), 'last_error' => null), array('id' => (int) $row['id']), array('%s', '%s', '%s'), array('%d'));
            } else {
                self::fail_row($row, $result['error']);
            }
        }
    }

    private static function fail_row($row, $error) {
        global $wpdb;
        $table = $wpdb->prefix . 'smf_capi_queue';
        $attempts = ((int) $row['attempts']) + 1;
        if ($attempts >= self::MAX_ATTEMPTS) {
            $wpdb->update($table, array('attempts' => $attempts, 'status' => 'failed', 'last_error' => substr((string) $error, 0, 1000)), array('id' => (int) $row['id']), array('%d', '%s', '%s'), array('%d'));
            return;
        }
        $delays = array(300, 900, 3600, 10800, 43200);
        $delay = $delays[min($attempts - 1, count($delays) - 1)];
        $next = wp_date('Y-m-d H:i:s', current_time('timestamp') + $delay, wp_timezone());
        $wpdb->update($table, array('attempts' => $attempts, 'status' => 'pending', 'next_attempt_at' => $next, 'last_error' => substr((string) $error, 0, 1000)), array('id' => (int) $row['id']), array('%d', '%s', '%s', '%s'), array('%d'));
    }

    public static function get_queue_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'smf_capi_queue';
        $stats = array('pending' => 0, 'sent' => 0, 'failed' => 0, 'total' => 0, 'last_sent' => null, 'last_error' => null);
        $rows = $wpdb->get_results("SELECT status, COUNT(*) count FROM $table GROUP BY status", ARRAY_A);
        foreach ((array) $rows as $row) if (isset($stats[$row['status']])) $stats[$row['status']] = (int) $row['count'];
        $stats['total'] = $stats['pending'] + $stats['sent'] + $stats['failed'];
        $stats['last_sent'] = $wpdb->get_var("SELECT sent_at FROM $table WHERE status='sent' ORDER BY sent_at DESC LIMIT 1");
        $stats['last_error'] = $wpdb->get_var("SELECT last_error FROM $table WHERE last_error IS NOT NULL AND last_error <> '' ORDER BY id DESC LIMIT 1");
        return $stats;
    }

    public static function test_connection() {
        if (!current_user_can('manage_woocommerce')) wp_send_json_error(array('message' => 'Permission denied.'), 403);
        check_ajax_referer('smf_admin', 'nonce');
        $pixel_id = preg_replace('/[^0-9]/', '', (string) get_option('smf_meta_pixel_id', ''));
        $token = trim((string) get_option('smf_meta_access_token', ''));
        if (!$pixel_id || !$token) wp_send_json_error(array('message' => 'Add a Meta Pixel ID and Conversions API access token first.'), 400);
        $url = 'https://graph.facebook.com/v23.0/' . rawurlencode($pixel_id) . '?fields=id,name&access_token=' . rawurlencode($token);
        $response = wp_remote_get($url, array('timeout' => 15));
        if (is_wp_error($response)) wp_send_json_error(array('message' => $response->get_error_message()), 502);
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code >= 200 && $code < 300 && !empty($body['id'])) wp_send_json_success(array('message' => 'Meta connection successful.', 'pixel_id' => $body['id'], 'name' => isset($body['name']) ? $body['name'] : ''));
        $message = !empty($body['error']['message']) ? $body['error']['message'] : 'Meta returned HTTP ' . $code . '.';
        wp_send_json_error(array('message' => $message), $code >= 400 && $code < 600 ? $code : 502);
    }

    private static function post($payload) {
        $pixel_id = preg_replace('/[^0-9]/', '', (string) get_option('smf_meta_pixel_id', ''));
        $token = trim((string) get_option('smf_meta_access_token', ''));
        if (!$pixel_id || !$token) return array('success' => false, 'error' => 'Meta Pixel ID or access token is missing.');
        $response = wp_remote_post('https://graph.facebook.com/v23.0/' . rawurlencode($pixel_id) . '/events', array(
            'timeout' => 15, 'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode(array_merge($payload, array('access_token' => $token))),
        ));
        if (is_wp_error($response)) return array('success' => false, 'error' => $response->get_error_message());
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) return array('success' => true, 'error' => '');
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return array('success' => false, 'error' => !empty($body['error']['message']) ? $body['error']['message'] : 'Meta returned HTTP ' . $code . '.');
    }
}
