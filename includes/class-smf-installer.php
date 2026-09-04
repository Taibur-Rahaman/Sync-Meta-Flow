<?php
defined('ABSPATH') || exit;

class SMF_Installer {
    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $events = $wpdb->prefix . 'smf_order_events';
        $sessions = $wpdb->prefix . 'smf_tracking_sessions';
        $tracking = $wpdb->prefix . 'smf_tracking_events';

        $sql = "CREATE TABLE $events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            event_type varchar(64) NOT NULL,
            old_status varchar(32) DEFAULT NULL,
            new_status varchar(32) DEFAULT NULL,
            source varchar(64) DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id), KEY order_id (order_id), KEY event_type (event_type), KEY created_at (created_at)
        ) $charset;
        CREATE TABLE $sessions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_key varchar(64) NOT NULL,
            fbclid text DEFAULT NULL,
            utm_source varchar(255) DEFAULT NULL,
            utm_medium varchar(255) DEFAULT NULL,
            utm_campaign varchar(255) DEFAULT NULL,
            utm_content varchar(255) DEFAULT NULL,
            utm_term varchar(255) DEFAULT NULL,
            landing_url text DEFAULT NULL,
            first_seen datetime NOT NULL,
            last_seen datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY session_key (session_key), KEY fbclid (fbclid(255))
        ) $charset;
        CREATE TABLE $tracking (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_key varchar(64) NOT NULL,
            event_name varchar(64) NOT NULL,
            event_id varchar(128) DEFAULT NULL,
            page_url text DEFAULT NULL,
            payload longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id), KEY session_key (session_key), KEY event_name (event_name), KEY event_id (event_id), KEY created_at (created_at)
        ) $charset;";

        foreach (preg_split('/;\s*CREATE TABLE/', $sql) as $i => $statement) {
            if ($i > 0) $statement = 'CREATE TABLE' . $statement;
            dbDelta(trim($statement));
        }
        add_option('smf_version', SMF_VERSION);
        add_option('smf_meta_enabled', 'no');
    }

    public static function deactivate() {}
}
