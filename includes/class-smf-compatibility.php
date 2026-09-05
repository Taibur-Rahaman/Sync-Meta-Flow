<?php
/**
 * Runtime compatibility and readiness checks.
 */
defined('ABSPATH') || exit;

class SMF_Compatibility {
    public static function checks() {
        global $wpdb;

        $checks = array();
        $php_ok = version_compare(PHP_VERSION, '7.4', '>=');
        $checks[] = self::check('PHP', $php_ok, PHP_VERSION . ($php_ok ? '' : ' · PHP 7.4+ required'), 'blocking');

        $wp_version = get_bloginfo('version');
        $wp_ok = version_compare($wp_version, '6.4', '>=');
        $checks[] = self::check('WordPress', $wp_ok, $wp_version . ($wp_ok ? '' : ' · WordPress 6.4+ required'), 'blocking');

        $wc_available = class_exists('WooCommerce');
        $wc_version = defined('WC_VERSION') ? WC_VERSION : 'inactive';
        $wc_ok = $wc_available && version_compare($wc_version, '7.0', '>=');
        $checks[] = self::check('WooCommerce', $wc_ok, $wc_available ? $wc_version : 'Install and activate WooCommerce.', 'blocking');

        $required = array('wc_get_order', 'wc_get_orders', 'wp_json_encode', 'register_rest_route', 'wp_next_scheduled', 'wp_schedule_event', 'wp_clear_scheduled_hook', 'current_user_can', 'check_admin_referer', 'check_ajax_referer');
        $missing = array();
        foreach ($required as $function) {
            if (!function_exists($function)) {
                $missing[] = $function;
            }
        }
        $required_classes = array('WP_Error', 'WP_REST_Response');
        $missing_classes = array();
        foreach ($required_classes as $class) {
            if (!class_exists($class)) {
                $missing_classes[] = $class;
            }
        }
        $missing_dependencies = array_merge($missing, $missing_classes);
        $checks[] = self::check('Required APIs', empty($missing_dependencies), empty($missing_dependencies) ? 'Required WordPress/WooCommerce APIs available.' : 'Missing: ' . implode(', ', $missing_dependencies), 'blocking');

        $hpos = self::hpos_status();
        $checks[] = self::check('HPOS', $hpos['ok'], $hpos['detail'], $hpos['level']);

        $tables = array('smf_order_events', 'smf_tracking_sessions', 'smf_tracking_events', 'smf_capi_queue', 'smf_campaign_spend', 'smf_courier_events');
        $missing_tables = array();
        foreach ($tables as $suffix) {
            $table = $wpdb->prefix . $suffix;
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
                $missing_tables[] = $suffix;
            }
        }
        $checks[] = self::check('Plugin tables', empty($missing_tables), empty($missing_tables) ? 'All required tables are present.' : 'Missing: ' . implode(', ', $missing_tables), 'blocking');

        $db_ok = empty($wpdb->last_error);
        $checks[] = self::check('Database', $db_ok, $db_ok ? 'Database connection is responding.' : 'Database error reported by WordPress.', 'blocking');

        $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $scheduled = wp_next_scheduled('smf_process_capi_queue') || wp_next_scheduled('smf_retry_courier_events') || wp_next_scheduled('smf_sync_meta_spend');
        if ($cron_disabled) {
            $checks[] = self::check('Cron', false, 'WP-Cron is disabled; verify a server cron invokes wp-cron.php.', 'warning');
        } else {
            $checks[] = self::check('Cron', (bool) $scheduled, $scheduled ? 'At least one plugin task is scheduled.' : 'No plugin task is scheduled yet.', 'warning');
        }

        $schema = (string) get_option('smf_schema_version', '1.0');
        $checks[] = self::check('Schema', $schema !== '', 'Schema version ' . $schema, 'blocking');

        return $checks;
    }

    public static function report() {
        $checks = self::checks();
        $blocking = 0;
        $warnings = 0;
        foreach ($checks as $check) {
            if (!$check['ok'] && $check['level'] === 'blocking') {
                $blocking++;
            } elseif ($check['level'] === 'warning') {
                $warnings++;
            }
        }

        return array(
            'plugin_version' => defined('SMF_VERSION') ? SMF_VERSION : '',
            'runtime' => array(
                'php' => array('detected' => PHP_VERSION, 'minimum' => '7.4'),
                'wordpress' => array('detected' => get_bloginfo('version'), 'minimum' => '6.4'),
                'woocommerce' => array('detected' => defined('WC_VERSION') ? WC_VERSION : 'inactive', 'minimum' => '7.0'),
            ),
            'schema_version' => (string) get_option('smf_schema_version', '1.0'),
            'ready' => $blocking === 0,
            'blocking_failures' => $blocking,
            'warnings' => $warnings,
            'checks' => $checks,
        );
    }

    private static function check($name, $ok, $detail, $level) {
        if ($level === 'bad') {
            $level = 'blocking';
        }
        return array(
            'name' => $name,
            'ok' => (bool) $ok,
            'detail' => $detail,
            'level' => $ok ? ($level === 'warning' ? 'warning' : 'ok') : ($level === 'ok' ? 'blocking' : $level),
        );
    }

    private static function hpos_status() {
        if (!class_exists('WooCommerce')) {
            return array('ok' => false, 'detail' => 'Unavailable until WooCommerce is active.', 'level' => 'blocking');
        }
        if (class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil') && method_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
            $enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            return array('ok' => true, 'detail' => $enabled ? 'Enabled.' : 'Disabled; legacy order storage is active.', 'level' => 'good');
        }
        return array('ok' => true, 'detail' => 'HPOS status API unavailable; using WooCommerce compatibility APIs.', 'level' => 'warning');
    }
}
