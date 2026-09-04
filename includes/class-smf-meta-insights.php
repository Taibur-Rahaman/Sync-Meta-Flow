<?php
defined('ABSPATH') || exit;

class SMF_Meta_Insights {
    const GRAPH_VERSION = 'v23.0';
    const CRON_HOOK = 'smf_sync_meta_spend';

    public static function init() {
        add_filter('cron_schedules', array(__CLASS__, 'cron_schedule'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'cron_sync'));
        add_action('admin_post_smf_sync_meta_spend', array(__CLASS__, 'manual_sync'));
        if (!wp_next_scheduled(self::CRON_HOOK)) wp_schedule_event(time() + 300, 'six_hours', self::CRON_HOOK);
    }
    public static function cron_schedule($schedules) {
        $schedules['six_hours'] = array('interval'=>21600,'display'=>'Every 6 hours'); return $schedules;
    }
    public static function manual_sync() {
        if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized');
        check_admin_referer('smf_sync_meta_spend');
        $r=self::sync(); set_transient('smf_meta_sync_notice',array('status'=>is_wp_error($r)?'error':'synced','message'=>is_wp_error($r)?$r->get_error_message():sprintf('%d spend rows synced.',(int)$r)),60);
        wp_safe_redirect(admin_url('admin.php?page=smf-spend')); exit;
    }
    public static function cron_sync(){self::sync();}
    public static function sync() {
        $token=trim((string)get_option('smf_meta_access_token','')); $account=trim((string)get_option('smf_meta_ad_account_id',''));
        if($token===''||$account==='') return new WP_Error('smf_missing_meta_credentials','Meta access token and Ad Account ID are required.');
        $days=(int)get_option('smf_meta_sync_days',30); if(!in_array($days,array(7,30,90),true))$days=30;
        $until=current_time('Y-m-d'); $since=wp_date('Y-m-d',strtotime('-'.($days-1).' days',current_time('timestamp')));
        $account=preg_replace('/^act_/','',$account);
        $url=add_query_arg(array('fields'=>'date_start,date_stop,campaign_id,adset_id,ad_id,spend','time_range'=>wp_json_encode(array('since'=>$since,'until'=>$until)),'level'=>'ad','limit'=>500,'access_token'=>$token),'https://graph.facebook.com/'.self::GRAPH_VERSION.'/act_'.rawurlencode($account).'/insights');
        $all=array();$pages=0;
        while($url&&$pages<20){$response=wp_remote_get($url,array('timeout'=>30,'headers'=>array('Accept'=>'application/json')));if(is_wp_error($response))return $response;$code=wp_remote_retrieve_response_code($response);$body=json_decode(wp_remote_retrieve_body($response),true);if($code<200||$code>=300||!is_array($body)){return new WP_Error('smf_meta_api_error',isset($body['error']['message'])?$body['error']['message']:'Meta Insights API request failed.',array('status'=>$code));}if(!empty($body['data']))$all=array_merge($all,$body['data']);$url=!empty($body['paging']['next'])?esc_url_raw($body['paging']['next']):'';$pages++;}
        global $wpdb;$table=$wpdb->prefix.'smf_campaign_spend';
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE source=%s AND spend_date BETWEEN %s AND %s",'meta_api',$since,$until));$count=0;
        foreach($all as $row){$date=!empty($row['date_start'])?sanitize_text_field($row['date_start']):'';$amount=isset($row['spend'])?(float)$row['spend']:0;if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||$amount<0)continue;$campaign=!empty($row['campaign_id'])?sanitize_text_field($row['campaign_id']):'';$adset=!empty($row['adset_id'])?sanitize_text_field($row['adset_id']):'';$ad=!empty($row['ad_id'])?sanitize_text_field($row['ad_id']):'';$wpdb->insert($table,array('spend_date'=>$date,'campaign_id'=>$campaign,'adset_id'=>$adset,'ad_id'=>$ad,'amount'=>$amount,'currency'=>'BDT','source'=>'meta_api','created_at'=>current_time('mysql')),array('%s','%s','%s','%s','%f','%s','%s','%s'));if($wpdb->insert_id)$count++;}
        update_option('smf_meta_last_sync',current_time('mysql'),false);update_option('smf_meta_last_sync_count',$count,false);return $count;
    }
}
