<?php
/**
 * Uninstall handler for Sync Meta Flow.
 * Data is preserved by default. Delete it only when the explicit option is enabled.
 */
if(!defined('WP_UNINSTALL_PLUGIN'))exit;
if(get_option('smf_delete_data_on_uninstall','no')!=='yes')return;
global $wpdb;
foreach(array('smf_order_events','smf_tracking_sessions','smf_tracking_events','smf_capi_queue','smf_campaign_spend','smf_courier_events') as $table){$wpdb->query('DROP TABLE IF EXISTS '.$wpdb->prefix.$table);}
foreach(array('smf_version','smf_schema_version','smf_v3_enabled','smf_v3_automation_enabled','smf_v3_automation_mode','smf_v3_automation_dry_run','smf_v3_automation_max_risk','smf_v3_automation_cooldown','smf_v3_automation_rate_limit','smf_v3_automation_approvals','smf_v3_automation_idempotency','smf_v3_automation_audit','smf_v3_automation_health','smf_v3_automation_cooldowns','smf_v3_advanced_attribution','smf_v3_courier_intelligence','smf_v3_commercial_enabled','smf_v3_telemetry_opt_in','smf_v3_license','smf_v3_ai_enabled','smf_courier_events_schema','smf_onboarding_completed','smf_onboarding_dismissed','smf_onboarding_step','smf_onboarding_attribution_reviewed','smf_onboarding_profitability_reviewed','smf_onboarding_meta_skipped','smf_onboarding_courier_skipped','smf_meta_enabled','smf_meta_pixel_id','smf_meta_access_token','smf_meta_ad_account_id','smf_meta_sync_days','smf_meta_last_sync','smf_meta_last_sync_count','smf_meta_last_sync_error','smf_meta_account_currency','smf_courier_webhook_secret','smf_courier_provider','smf_steadfast_api_key','smf_steadfast_secret_key','smf_courier_risk_window','smf_courier_processing_sla','smf_courier_delivery_sla','smf_courier_auto_ship','smf_attribution_model','smf_delete_data_on_uninstall','smf_cogs_percent','smf_payment_fee_percent','smf_courier_delivery_cost','smf_courier_return_cost') as $option)delete_option($option);
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",'smf_shipment_lock_%'));
wp_clear_scheduled_hook('smf_process_capi_queue');wp_clear_scheduled_hook('smf_sync_meta_spend');wp_clear_scheduled_hook('smf_retry_courier_events');
