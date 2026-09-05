<?php
defined('ABSPATH') || exit;

class SMF_Installer {
    public static function activate(){self::create_tables();self::ensure_options();update_option('smf_schema_version','1.1',false);update_option('smf_version',SMF_VERSION);}
    public static function maybe_upgrade(){if(get_option('smf_version')!==SMF_VERSION){self::create_tables();self::ensure_options();update_option('smf_schema_version','1.1',false);update_option('smf_version',SMF_VERSION);}}
    private static function ensure_options(){
        add_option('smf_schema_version','1.1'); add_option('smf_v3_enabled','no');
        add_option('smf_v3_automation_enabled','no'); add_option('smf_v3_automation_mode','observe');
        add_option('smf_v3_automation_dry_run','yes'); add_option('smf_v3_automation_max_risk','medium');
        add_option('smf_v3_automation_cooldown',3600); add_option('smf_v3_automation_rate_limit',20);
        add_option('smf_v3_advanced_attribution','no');
        add_option('smf_v3_courier_intelligence','no');
        add_option('smf_v3_commercial_enabled','no'); add_option('smf_v3_telemetry_opt_in','no');
        add_option('smf_v3_ai_enabled','no');
        add_option('smf_onboarding_completed','no'); add_option('smf_onboarding_dismissed','no'); add_option('smf_onboarding_step',1);
        add_option('smf_onboarding_attribution_reviewed','no'); add_option('smf_onboarding_profitability_reviewed','no'); add_option('smf_onboarding_meta_skipped','no'); add_option('smf_onboarding_courier_skipped','no');
        add_option('smf_meta_enabled','no'); add_option('smf_attribution_model','last_touch');
        add_option('smf_delete_data_on_uninstall','no'); add_option('smf_courier_provider','generic');
        add_option('smf_courier_risk_window',90);
        add_option('smf_cogs_percent',0); add_option('smf_payment_fee_percent',0);
        add_option('smf_courier_delivery_cost',0); add_option('smf_courier_return_cost',0);
    }
    private static function create_tables(){global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$charset=$wpdb->get_charset_collate();$events=$wpdb->prefix.'smf_order_events';$sessions=$wpdb->prefix.'smf_tracking_sessions';$tracking=$wpdb->prefix.'smf_tracking_events';$queue=$wpdb->prefix.'smf_capi_queue';$spend=$wpdb->prefix.'smf_campaign_spend';$courier=$wpdb->prefix.'smf_courier_events';
        dbDelta("CREATE TABLE $events (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,order_id bigint(20) unsigned NOT NULL,event_type varchar(64) NOT NULL,old_status varchar(32) DEFAULT NULL,new_status varchar(32) DEFAULT NULL,source varchar(64) DEFAULT NULL,metadata longtext DEFAULT NULL,created_at datetime NOT NULL,PRIMARY KEY(id),KEY order_id(order_id),KEY event_type(event_type),KEY event_created(event_type,created_at),KEY order_created(order_id,created_at,id),KEY created_at(created_at)) $charset;");
        dbDelta("CREATE TABLE $sessions (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,session_key varchar(64) NOT NULL,fbclid text DEFAULT NULL,fbp varchar(255) DEFAULT NULL,fbc varchar(255) DEFAULT NULL,utm_source varchar(255) DEFAULT NULL,utm_medium varchar(255) DEFAULT NULL,utm_campaign varchar(255) DEFAULT NULL,utm_content varchar(255) DEFAULT NULL,utm_term varchar(255) DEFAULT NULL,utm_id varchar(255) DEFAULT NULL,campaign_id varchar(255) DEFAULT NULL,adset_id varchar(255) DEFAULT NULL,ad_id varchar(255) DEFAULT NULL,first_touch longtext DEFAULT NULL,last_touch longtext DEFAULT NULL,landing_url text DEFAULT NULL,first_seen datetime NOT NULL,last_seen datetime NOT NULL,PRIMARY KEY(id),UNIQUE KEY session_key(session_key),KEY fbclid(fbclid(255)),KEY utm_campaign(utm_campaign(191))) $charset;");
        dbDelta("CREATE TABLE $tracking (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,session_key varchar(64) NOT NULL,event_name varchar(64) NOT NULL,event_id varchar(128) DEFAULT NULL,page_url text DEFAULT NULL,payload longtext DEFAULT NULL,created_at datetime NOT NULL,PRIMARY KEY(id),KEY session_key(session_key),KEY event_name(event_name),KEY event_time(event_name,created_at),KEY event_id(event_id),KEY created_at(created_at)) $charset;");
        dbDelta("CREATE TABLE $queue (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,order_id bigint(20) unsigned NOT NULL,event_name varchar(64) NOT NULL,event_id varchar(128) NOT NULL,payload longtext NOT NULL,attempts smallint(5) unsigned NOT NULL DEFAULT 0,status varchar(20) NOT NULL DEFAULT 'pending',next_attempt_at datetime NOT NULL,last_error text DEFAULT NULL,created_at datetime NOT NULL,sent_at datetime DEFAULT NULL,PRIMARY KEY(id),UNIQUE KEY event_id(event_id),KEY status_next(status,next_attempt_at),KEY order_id(order_id)) $charset;");
        dbDelta("CREATE TABLE $spend (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,spend_date date NOT NULL,campaign_id varchar(255) DEFAULT NULL,adset_id varchar(255) DEFAULT NULL,ad_id varchar(255) DEFAULT NULL,amount decimal(20,4) NOT NULL DEFAULT 0,currency varchar(3) NOT NULL DEFAULT 'BDT',source varchar(32) NOT NULL DEFAULT 'manual',created_at datetime NOT NULL,PRIMARY KEY(id),KEY spend_date(spend_date),KEY spend_currency(spend_date,currency),KEY campaign_id(campaign_id),KEY adset_id(adset_id),KEY ad_id(ad_id),KEY source(source)) $charset;");
        dbDelta("CREATE TABLE $courier (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,provider varchar(32) NOT NULL DEFAULT 'generic',event_id varchar(128) NOT NULL,event_hash char(64) NOT NULL,order_id bigint(20) unsigned DEFAULT NULL,status varchar(64) DEFAULT NULL,payload longtext DEFAULT NULL,response_code smallint(5) unsigned DEFAULT NULL,result varchar(20) NOT NULL DEFAULT 'received',attempts smallint(5) unsigned NOT NULL DEFAULT 0,last_error text DEFAULT NULL,next_retry_at datetime DEFAULT NULL,last_attempt_at datetime DEFAULT NULL,received_at datetime NOT NULL,processed_at datetime DEFAULT NULL,PRIMARY KEY(id),UNIQUE KEY event_hash(event_hash),KEY provider_event(provider,event_id),KEY order_received(order_id,received_at,id),KEY received_provider(received_at,provider),KEY order_id(order_id),KEY received_at(received_at),KEY retry_state(result,next_retry_at),KEY attempt_state(attempts,result)) $charset;");
    }
    public static function deactivate(){wp_clear_scheduled_hook('smf_process_capi_queue');wp_clear_scheduled_hook('smf_sync_meta_spend');wp_clear_scheduled_hook('smf_retry_courier_events');}
}
