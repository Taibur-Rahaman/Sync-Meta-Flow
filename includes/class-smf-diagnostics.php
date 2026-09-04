<?php
defined('ABSPATH') || exit;
class SMF_Diagnostics {
    public static function init(){add_action('admin_menu',array(__CLASS__,'menu'));}
    public static function menu(){add_submenu_page('sync-meta-flow','Diagnostics','Diagnostics','manage_woocommerce','smf-diagnostics',array(__CLASS__,'page'));}
    private static function check($name,$ok,$detail,$level='good'){return array('name'=>$name,'ok'=>(bool)$ok,'detail'=>$detail,'level'=>$ok?'good':$level);}
    public static function checks(){
        global $wpdb;$checks=array();$wc=class_exists('WooCommerce');
        $wc_version=defined('WC_VERSION')?WC_VERSION:($wc?'installed':'inactive');
        $checks[]=self::check('WooCommerce',$wc,$wc?'Active · '.$wc_version:'Install and activate WooCommerce.','bad');
        $php=version_compare(PHP_VERSION,'7.4','>=');$checks[]=self::check('PHP',$php,PHP_VERSION.($php?'':' · PHP 7.4+ required'),'bad');
        $wp_version=get_bloginfo('version');$wp=version_compare($wp_version,'6.4','>=');$checks[]=self::check('WordPress',$wp,$wp_version.($wp?'':' · WordPress 6.4+ required'),'bad');
        foreach(array('smf_order_events','smf_tracking_sessions','smf_tracking_events','smf_capi_queue','smf_campaign_spend','smf_courier_events') as $suffix){$t=$wpdb->prefix.$suffix;$exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$t))===$t;$checks[]=self::check('DB table '.$suffix,$exists,$exists?'Present':'Missing · deactivate/activate or allow plugin upgrade to recreate schema','bad');}
        $enabled=get_option('smf_meta_enabled','no')==='yes';$pixel=trim((string)get_option('smf_meta_pixel_id',''));$token=trim((string)get_option('smf_meta_access_token',''));
        $checks[]=self::check('Meta tracking',$enabled&&$pixel,$enabled&&$pixel?'Enabled and Pixel configured':'Enable tracking and configure a Pixel.','warning');
        $checks[]=self::check('Meta CAPI',$token,$token?'Access token saved (hidden)':'Optional · CAPI access token not configured.','warning');
        $account=trim((string)get_option('smf_meta_ad_account_id',''));$checks[]=self::check('Meta Ads spend',$account,$account?'Ad Account configured':'Optional · configure an Ad Account to import spend.','warning');
        $currency=get_option('smf_meta_account_currency','');$checks[]=self::check('Ad account currency',$currency,$currency?'Detected: '.$currency:'Run a Meta spend sync to detect currency.','warning');
        $q=class_exists('SMF_Meta_CAPI')?SMF_Meta_CAPI::get_queue_stats():array('pending'=>0,'sent'=>0,'failed'=>0,'last_sent'=>null,'last_error'=>null);$checks[]=self::check('CAPI queue failures',(int)$q['failed']===0,$q['failed']?'Failed events: '.$q['failed'].' · review queue diagnostics':'No failed CAPI events.','warning');
        $cron=wp_next_scheduled('smf_process_capi_queue');$checks[]=self::check('WP-Cron CAPI',$cron!==false,$cron?'Scheduled':'CAPI cron is not scheduled.','warning');$metaCron=wp_next_scheduled('smf_sync_meta_spend');$checks[]=self::check('WP-Cron Meta spend',$metaCron!==false,$metaCron?'Scheduled':'Meta spend cron is not scheduled.','warning');
        $provider=sanitize_key((string)get_option('smf_courier_provider','generic'));$secret=trim((string)get_option('smf_courier_webhook_secret',''));$checks[]=self::check('Courier webhook',$secret!==''&&$provider!=='generic',$secret?'Signed webhook configured · provider: '.$provider:'Optional · configure a webhook secret and provider.','warning');
        $https=is_ssl();$checks[]=self::check('HTTPS',$https,$https?'Site is using HTTPS.':'Production tracking, admin and webhook endpoints should use HTTPS.','warning');
        return $checks;
    }
    public static function page(){if(!current_user_can('manage_woocommerce'))return;$checks=self::checks();$good=0;$bad=0;foreach($checks as $c){if($c['ok'])$good++;if(!$c['ok']&&$c['level']==='bad')$bad++;}$endpoint=rest_url('sync-meta-flow/v1/courier/webhook');$diagnostic=array('plugin_version'=>SMF_VERSION,'wordpress'=>get_bloginfo('version'),'php'=>PHP_VERSION,'woocommerce'=>defined('WC_VERSION')?WC_VERSION:'inactive','site_url'=>home_url(),'https'=>is_ssl(),'meta_enabled'=>get_option('smf_meta_enabled','no'),'pixel_configured'=>get_option('smf_meta_pixel_id','')!=='','capi_token_configured'=>get_option('smf_meta_access_token','')!=='','ad_account_configured'=>get_option('smf_meta_ad_account_id','')!=='','account_currency'=>get_option('smf_meta_account_currency',''),'courier_provider'=>sanitize_key((string)get_option('smf_courier_provider','generic')),'courier_webhook'=>$endpoint,'capi_queue'=>class_exists('SMF_Meta_CAPI')?SMF_Meta_CAPI::get_queue_stats():array());?>
<div class="wrap smf-wrap smf-settings"><div class="smf-header"><div><h1>Diagnostics</h1><p>Production health checks without exposing credentials.</p></div><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sync-meta-flow'));?>">← Dashboard</a></div>
<div class="smf-status <?php echo $bad?'is-warning':'is-good';?>"><span class="smf-status-dot"></span><div><strong><?php echo $bad?'Action required':'Core checks look healthy';?></strong><small><?php echo esc_html($good);?> checks passed · <?php echo esc_html($bad);?> blocking checks</small></div></div>
<div class="smf-panel"><h2>Health checks</h2><div class="smf-diagnostic-mini"><?php foreach($checks as $c):?><span><?php echo esc_html($c['name']);?></span><b class="<?php echo $c['ok']?'is-good':'is-warning';?>"><?php echo $c['ok']?'PASS':'CHECK';?> · <?php echo esc_html($c['detail']);?></b><?php endforeach;?></div></div>
<div class="smf-panel"><h2>Safe diagnostic snapshot</h2><p class="smf-muted">Credentials, access tokens and webhook secrets are never included.</p><textarea class="smf-input" rows="14" readonly><?php echo esc_textarea(wp_json_encode($diagnostic,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));?></textarea></div></div><?php }
}
