<?php
defined('ABSPATH') || exit;

/**
 * Failed courier webhook recovery and operational visibility.
 */
class SMF_Courier_Recovery {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_smf_retry_courier_event', array(__CLASS__, 'retry'));
    }

    public static function menu() {
        add_submenu_page('sync-meta-flow', 'Courier Recovery', 'Courier Recovery', 'manage_woocommerce', 'smf-courier-recovery', array(__CLASS__, 'page'));
    }

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . SMF_Courier_Timeline::TABLE_SUFFIX;
    }

    private static function failed_events() {
        global $wpdb;
        SMF_Courier_Timeline::ensure_table();
        return $wpdb->get_results("SELECT id,provider,event_id,order_id,status,response_code,result,received_at,processed_at,payload FROM " . self::table() . " WHERE result='failed' ORDER BY received_at DESC,id DESC LIMIT 50");
    }

    public static function retry() {
        if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized');
        $id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        check_admin_referer('smf_retry_courier_event_' . $id);
        SMF_Courier_Timeline::ensure_table();
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d AND result=\'failed\' LIMIT 1', $id));
        if (!$row || empty($row->payload)) wp_die('Failed courier event was not found or has no replayable payload.');
        $secret = trim((string)get_option('smf_courier_webhook_secret', ''));
        if ($secret === '') wp_die('Courier webhook secret is required for safe replay.');
        $payload = (string)$row->payload;
        $signature = hash_hmac('sha256', $payload, $secret);
        $url = rest_url(SMF_Courier::NS . SMF_Courier::ROUTE);
        $response = wp_remote_post($url, array('timeout'=>20,'headers'=>array('Content-Type'=>'application/json','X-SMF-Signature'=>$signature,'X-SMF-Event-ID'=>(string)$row->event_id),'body'=>$payload));
        if (is_wp_error($response)) wp_die(esc_html($response->get_error_message()));
        $code = (int)wp_remote_retrieve_response_code($response);
        wp_safe_redirect(add_query_arg(array('page'=>'smf-courier-recovery','retried'=>$id,'code'=>$code), admin_url('admin.php'))); exit;
    }

    public static function page() {
        if (!current_user_can('manage_woocommerce')) return;
        $events = self::failed_events();
        echo '<div class="wrap"><h1>Courier Recovery</h1><p>Failed webhook events are retained for inspection and safe signed replay. Replay uses the stored payload and the configured Sync Meta Flow webhook secret.</p>';
        if (isset($_GET['retried'])) echo '<div class="notice notice-info"><p>Replay attempted. HTTP response: ' . esc_html(absint($_GET['code'])) . '.</p></div>';
        if (!$events) { echo '<div class="notice notice-success"><p>No failed courier webhook events.</p></div></div>'; return; }
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Provider</th><th>Event</th><th>Order</th><th>Status</th><th>HTTP</th><th>Received</th><th>Action</th></tr></thead><tbody>';
        foreach ($events as $event) {
            echo '<tr><td>' . esc_html($event->id) . '</td><td>' . esc_html($event->provider) . '</td><td><code>' . esc_html($event->event_id) . '</code></td><td>' . esc_html($event->order_id ?: '-') . '</td><td>' . esc_html($event->status ?: '-') . '</td><td>' . esc_html($event->response_code ?: '-') . '</td><td>' . esc_html($event->received_at) . '</td><td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="smf_retry_courier_event"><input type="hidden" name="event_id" value="' . esc_attr($event->id) . '">' . wp_nonce_field('smf_retry_courier_event_' . $event->id, '_wpnonce', true, false) . '<button class="button" type="submit">Replay</button></form></td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
