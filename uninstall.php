<?php
/**
 * Uninstall handler for Sync Meta Flow.
 * Data is preserved by default. Delete it only when the explicit option is enabled.
 */
if(!defined('WP_UNINSTALL_PLUGIN'))exit;
if(get_option('smf_delete_data_on_uninstall','no')!=='yes')return;
global $wpdb;
foreach(array('smf_order_events','smf_tracking_sessions','smf_tracking_events','smf_capi_queue','smf_campaign_spend','smf_courier_events') as $table){$wpdb->query('DROP TABLE IF EXISTS '.$wpdb->prefix.$table);}
foreach(array('smf_version','smf_meta_enabled','smf_meta_pixel_id','smf_meta_access_token','smf_meta_ad_account_id','smf_meta_sync_days','smf_meta_last_sync','smf_meta_last_sync_count','smf_meta_last_sync_error','smf_meta_account_currency','smf_courier_webhook_secret','smf_courier_provider','smf_steadfast_api_key','smf_steadfast_secret_key','smf_courier_risk_window','smf_courier_auto_ship','smf_attribution_model','smf_delete_data_on_uninstall') as $option)delete_option($option);
wp_clear_scheduled_hook('smf_process_capi_queue');wp_clear_scheduled_hook('smf_sync_meta_spend');
