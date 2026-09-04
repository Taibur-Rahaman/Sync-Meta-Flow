<?php
defined('ABSPATH') || exit;

class SMF_Courier_Timeline {
    const TABLE_SUFFIX = 'smf_courier_events';

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'ensure_table'));
        add_filter('rest_pre_dispatch', array(__CLASS__, 'dedupe_webhook'), 10, 3);
        add_filter('rest_post_dispatch', array(__CLASS__, 'mark_webhook_result'), 10, 3);
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'status_changed'), 20, 4);
        add_action('woocommerce_admin_order_data_after_billing_address', array(__CLASS__, 'order_timeline'), 20);
    }

    public static function ensure_table() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        if (get_option('smf_courier_events_schema', '') === SMF_VERSION && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) return;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,provider varchar(32) NOT NULL DEFAULT 'generic',event_id varchar(128) NOT NULL,event_hash char(64) NOT NULL,order_id bigint(20) unsigned DEFAULT NULL,status varchar(64) DEFAULT NULL,payload longtext DEFAULT NULL,response_code smallint(5) unsigned DEFAULT NULL,result varchar(20) NOT NULL DEFAULT 'received',received_at datetime NOT NULL,processed_at datetime DEFAULT NULL,PRIMARY KEY(id),UNIQUE KEY event_hash(event_hash),KEY provider_event(provider,event_id),KEY order_id(order_id),KEY received_at(received_at)) $charset;");
        update_option('smf_courier_events_schema', SMF_VERSION, false);
    }

    private static function table() { global $wpdb; return $wpdb->prefix . self::TABLE_SUFFIX; }

    private static function signature_valid($request, $raw) {
        $secret = trim((string)get_option('smf_courier_webhook_secret', ''));
        if ($secret === '') return false;
        $sig = $request->get_header('x-smf-signature');
        if (!$sig) $sig = $request->get_header('x-webhook-signature');
        if (!$sig) return false;
        if (stripos($sig, 'sha256=') === 0) $sig = substr($sig, 7);
        return hash_equals(hash_hmac('sha256', $raw, $secret), trim($sig));
    }

    private static function request_identity($request) {
        $raw = $request->get_body();
        $data = json_decode($raw, true);
        if (!is_array($data)) return false;
        $event_id = '';
        foreach (array('event_id', 'webhook_id', 'eventId', 'id') as $key) {
            if (isset($data[$key]) && is_scalar($data[$key]) && trim((string)$data[$key]) !== '') { $event_id = sanitize_text_field((string)$data[$key]); break; }
        }
        if ($event_id === '') $event_id = trim((string)$request->get_header('x-smf-event-id'));
        if ($event_id === '') $event_id = trim((string)$request->get_header('x-event-id'));
        if ($event_id === '') $event_id = hash('sha256', $raw);
        $hash = hash('sha256', $raw);
        $provider = !empty($data['provider']) ? sanitize_key($data['provider']) : sanitize_key((string)get_option('smf_courier_provider', 'generic'));
        $order_id = !empty($data['order_id']) ? absint($data['order_id']) : 0;
        $status = !empty($data['status']) ? sanitize_key((string)$data['status']) : (!empty($data['delivery_status']) ? sanitize_key((string)$data['delivery_status']) : '');
        return array('raw'=>$raw,'data'=>$data,'event_id'=>substr($event_id, 0, 128),'hash'=>$hash,'provider'=>$provider ?: 'generic','order_id'=>$order_id ?: null,'status'=>$status ?: null);
    }

    public static function dedupe_webhook($result, $server, $request) {
        if (strpos((string)$request->get_route(), '/sync-meta-flow/v1/courier/webhook') !== 0 || strtoupper($request->get_method()) !== 'POST') return $result;
        $identity = self::request_identity($request);
        if (!$identity || !self::signature_valid($request, $identity['raw'])) return $result;
        self::ensure_table();
        global $wpdb;
        $table = self::table();
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id,result,order_id,status FROM $table WHERE event_hash=%s LIMIT 1", $identity['hash']));
        if ($existing) {
            return new WP_REST_Response(array('ok'=>true,'duplicate'=>true,'event_id'=>$identity['event_id'],'order_id'=>$existing->order_id ? (int)$existing->order_id : $identity['order_id'],'status'=>$existing->status,'result'=>$existing->result), 200);
        }
        $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (provider,event_id,event_hash,order_id,status,payload,received_at) VALUES (%s,%s,%s,%s,%s,%s,%s)", $identity['provider'],$identity['event_id'],$identity['hash'],$identity['order_id'],$identity['status'],wp_json_encode($identity['data']),current_time('mysql')));
        return $result;
    }

    public static function mark_webhook_result($response, $server, $request) {
        if (strpos((string)$request->get_route(), '/sync-meta-flow/v1/courier/webhook') !== 0 || strtoupper($request->get_method()) !== 'POST') return $response;
        $identity = self::request_identity($request);
        if (!$identity) return $response;
        self::ensure_table();
        global $wpdb;
        $code = method_exists($response, 'get_status') ? (int)$response->get_status() : 500;
        $result = ($code >= 200 && $code < 300) ? 'processed' : 'failed';
        $wpdb->query($wpdb->prepare("UPDATE " . self::table() . " SET response_code=%d,result=%s,processed_at=%s WHERE event_hash=%s AND result='received'", $code,$result,current_time('mysql'),$identity['hash']));
        return $response;
    }

    public static function status_changed($order_id, $old_status, $new_status, $order) {
        $provider = (string)$order->get_meta('_smf_courier_provider');
        if ($provider === '') return;
        global $wpdb;
        $table = self::table();
        $event_id = 'status-' . absint($order_id) . '-' . sanitize_key($new_status) . '-' . gmdate('YmdHis');
        $hash = hash('sha256', $event_id . '|' . $order_id . '|' . $new_status);
        $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (provider,event_id,event_hash,order_id,status,payload,received_at,result,processed_at) VALUES (%s,%s,%s,%d,%s,%s,%s,'processed',%s)", $provider,$event_id,$hash,absint($order_id),sanitize_key($new_status),wp_json_encode(array('source'=>'woocommerce','old_status'=>$old_status,'new_status'=>$new_status)),current_time('mysql'),current_time('mysql')));
    }

    public static function get_events($order_id, $limit = 20) {
        global $wpdb;
        $limit = max(1, min(100, absint($limit)));
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM " . self::table() . " WHERE order_id=%d ORDER BY received_at DESC,id DESC LIMIT %d", absint($order_id), $limit));
    }

    public static function order_timeline($order) {
        if (!current_user_can('manage_woocommerce')) return;
        $events = self::get_events($order->get_id(), 20);
        if (!$events) return;
        echo '<div style="margin-top:12px;padding:12px;border:1px solid #ddd;background:#fff"><strong>Courier Event Timeline</strong><div style="margin-top:8px">';
        foreach ($events as $event) {
            $label = $event->status ? ucwords(str_replace(array('_','-'), ' ', $event->status)) : 'Event';
            $when = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->received_at);
            $state = $event->result === 'failed' ? 'Failed' : ($event->result === 'processed' ? 'Processed' : 'Received');
            echo '<div style="padding:7px 0;border-top:1px solid #eee"><strong>' . esc_html($label) . '</strong> · ' . esc_html($state) . '<br><span class="description">' . esc_html($event->provider) . ' · ' . esc_html($when) . '</span></div>';
        }
        echo '</div></div>';
    }
}
