<?php
defined('ABSPATH') || exit;
class SMF_Courier_Recovery {
    const CRON='smf_retry_courier_events';
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_smf_retry_courier_event', array(__CLASS__, 'retry'));
        add_action(self::CRON, array(__CLASS__, 'process_retries'));
        if (!wp_next_scheduled(self::CRON)) wp_schedule_event(time()+300, 'hourly', self::CRON);
    }
    public static function menu() { add_submenu_page('sync-meta-flow', 'Courier Recovery', 'Courier Recovery', 'manage_woocommerce', 'smf-courier-recovery', array(__CLASS__, 'page')); }
    private static function table() { global $wpdb; return $wpdb->prefix . SMF_Courier_Timeline::TABLE_SUFFIX; }
    private static function failed_events() { global $wpdb; SMF_Courier_Timeline::ensure_table(); return $wpdb->get_results("SELECT id,provider,event_id,order_id,status,response_code,result,attempts,last_error,next_retry_at,received_at,processed_at,payload FROM " . self::table() . " WHERE result='failed' ORDER BY received_at DESC,id DESC LIMIT 50"); }
    private static function claim_manual($id) { global $wpdb; SMF_Courier_Timeline::ensure_table(); return (bool)$wpdb->query($wpdb->prepare("UPDATE ".self::table()." SET result='received',next_retry_at=NULL,processed_at=NULL WHERE id=%d AND result='failed' AND attempts<%d",absint($id),SMF_Courier_Timeline::MAX_ATTEMPTS)); }
    private static function replay($row) {
        $secret = trim((string)get_option('smf_courier_webhook_secret', ''));
        if ($secret === '') { SMF_Courier_Timeline::record_retry_failure($row->id, 'replay_blocked: courier webhook secret is not configured.'); return new WP_Error('smf_missing_webhook_secret','Courier webhook secret is required for safe replay.'); }
        $payload=(string)$row->payload;
        $signature=hash_hmac('sha256',$payload,$secret);
        $url=rest_url(SMF_Courier::NS . SMF_Courier::ROUTE);
        $response=wp_remote_post($url,array('timeout'=>20,'headers'=>array('Content-Type'=>'application/json','X-SMF-Signature'=>$signature,'X-SMF-Event-ID'=>(string)$row->event_id),'body'=>$payload));
        if(is_wp_error($response)){SMF_Courier_Timeline::record_retry_failure($row->id,'replay_wp_error: '.implode('; ',array_map('strval',$response->get_error_messages())));return $response;}
        return $response;
    }
    public static function retry() {
        if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized');
        $id=isset($_POST['event_id'])?absint($_POST['event_id']):0;
        check_admin_referer('smf_retry_courier_event_'.$id);
        SMF_Courier_Timeline::ensure_table(); global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE id=%d AND result=\'failed\' LIMIT 1',$id));
        if(!$row||empty($row->payload))wp_die('Failed courier event was not found or has no replayable payload.');
        if(!self::claim_manual($id))wp_die('Courier event is already being retried, processed, or has exhausted its retry limit.');
        $response=self::replay($row);
        $code=is_wp_error($response)?0:(int)wp_remote_retrieve_response_code($response);
        wp_safe_redirect(add_query_arg(array('page'=>'smf-courier-recovery','retried'=>$id,'code'=>$code),admin_url('admin.php'))); exit;
    }
    public static function process_retries() {
        if (!class_exists('SMF_Courier_Timeline')) return;
        $events=SMF_Courier_Timeline::get_retryable_events(10);
        foreach($events as $row){if(!SMF_Courier_Timeline::claim_retry($row->id))continue;self::replay($row);}
    }
    public static function page() {
        if (!current_user_can('manage_woocommerce')) return;
        $events=self::failed_events();$health=SMF_Courier_Timeline::health();
        echo '<div class="wrap"><h1>Courier Recovery</h1><p>Failed webhook events are retained for inspection and safe signed replay. Automatic retries use bounded exponential backoff and stop after '.esc_html(SMF_Courier_Timeline::MAX_ATTEMPTS).' attempts.</p>';
        echo '<div class="notice notice-info"><p><strong>Health:</strong> Failed '.esc_html($health['failed']).' · Retryable '.esc_html($health['retryable']).' · Processing '.esc_html($health['processing']).' · Exhausted '.esc_html($health['exhausted']).'</p></div>';
        if(isset($_GET['retried']))echo '<div class="notice notice-info"><p>Replay attempted. HTTP response: '.esc_html(absint($_GET['code'])).'.</p></div>';
        if(!$events){echo '<div class="notice notice-success"><p>No failed courier webhook events.</p></div></div>';return;}
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Provider</th><th>Event</th><th>Order</th><th>Status</th><th>HTTP</th><th>Attempts</th><th>Next retry</th><th>Failure</th><th>Received</th><th>Action</th></tr></thead><tbody>';
        foreach($events as $event){$retry=empty($event->next_retry_at)?($event->attempts>=SMF_Courier_Timeline::MAX_ATTEMPTS?'Exhausted':'Manual only'):$event->next_retry_at;$failure=!empty($event->last_error)?substr((string)$event->last_error,0,160):'-';echo '<tr><td>'.esc_html($event->id).'</td><td>'.esc_html($event->provider).'</td><td><code>'.esc_html($event->event_id).'</code></td><td>'.esc_html($event->order_id?:'-').'</td><td>'.esc_html($event->status?:'-').'</td><td>'.esc_html($event->response_code?:'-').'</td><td>'.esc_html((int)$event->attempts).'/'.esc_html(SMF_Courier_Timeline::MAX_ATTEMPTS).'</td><td>'.esc_html($retry).'</td><td>'.esc_html($failure).'</td><td>'.esc_html($event->received_at).'</td><td>'.($event->attempts<SMF_Courier_Timeline::MAX_ATTEMPTS?'<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="smf_retry_courier_event"><input type="hidden" name="event_id" value="'.esc_attr($event->id).'">'.wp_nonce_field('smf_retry_courier_event_'.$event->id,'_wpnonce',true,false).'<button class="button" type="submit">Replay</button></form>':'<span>Max attempts</span>').'</td></tr>';}
        echo '</tbody></table></div>';
    }
}
