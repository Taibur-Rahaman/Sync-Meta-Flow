<?php
defined('ABSPATH') || exit;

class SMF_Observability {
    private static $report_cache = null;
    const WINDOW_DAYS = 7;
    const ROW_LIMIT = 200;
    const STALE_SECONDS = 600;

    public static function init() {
        add_action('admin_footer', array(__CLASS__, 'admin_panel'));
    }

    public static function admin_panel() {
        if (!current_user_can('manage_woocommerce') || !function_exists('get_current_screen')) return;
        $screen = get_current_screen();
        if (!$screen || strpos((string) $screen->id, 'smf-diagnostics') === false) return;
        $report = self::report();
        echo '<div class="wrap smf-wrap"><div class="smf-panel"><h2>System Health / Observability</h2><p><strong>Overall:</strong> ' . esc_html(strtoupper($report['overall'])) . ' · <strong>Critical:</strong> ' . esc_html($report['summary']['critical']) . ' · <strong>Warnings:</strong> ' . esc_html($report['summary']['warnings']) . ' · <strong>Last success:</strong> ' . esc_html($report['summary']['last_success'] ?: 'Not recorded') . '</p><p class="smf-muted">' . esc_html($report['summary']['next_action']) . '</p><div class="smf-diagnostic-mini">';
        foreach ($report['modules'] as $name => $module) {
            $failure = $module['latest_failure'] ? ' · Latest: ' . $module['latest_failure'] : '';
            echo '<span>' . esc_html(ucwords(str_replace('_', ' ', $name))) . '</span><b class="' . esc_attr($module['level'] === 'ok' ? 'is-good' : 'is-warning') . '">' . esc_html(strtoupper($module['level'])) . ' · ' . esc_html($module['summary'] . $failure) . '</b>';
        }
        echo '</div></div></div>';
    }

    public static function report() {
        if (self::$report_cache !== null) return self::$report_cache;
        $modules = array(
            'system' => self::system(),
            'capi' => self::capi(),
            'courier' => self::courier(),
            'attribution' => self::attribution(),
            'orders' => self::orders(),
        );
        $critical = 0;
        $warnings = 0;
        $healthy = 0;
        $next_action = 'No operational action is currently required.';
        foreach ($modules as $module) {
            if ($module['level'] === 'blocking') {
                $critical++;
                if ($next_action === 'No operational action is currently required.') $next_action = $module['next_action'];
            } elseif ($module['level'] === 'warning') {
                $warnings++;
                if ($next_action === 'No operational action is currently required.') $next_action = $module['next_action'];
            } else {
                $healthy++;
            }
        }
        $last_success = !empty($modules['capi']['metrics']['last_success']) ? $modules['capi']['metrics']['last_success'] : null;
        self::$report_cache = array(
            'overall' => $critical ? 'blocking' : ($warnings ? 'warning' : 'ok'),
            'summary' => array('critical' => $critical, 'warnings' => $warnings, 'healthy' => $healthy, 'last_success' => $last_success, 'next_action' => $next_action),
            'modules' => $modules,
            'generated_at' => current_time('mysql'),
        );
        return self::$report_cache;
    }

    public static function snapshot() {
        $report = self::report();
        $snapshot = array('overall' => $report['overall'], 'summary' => $report['summary'], 'generated_at' => $report['generated_at'], 'modules' => array());
        foreach ($report['modules'] as $name => $module) {
            $snapshot['modules'][$name] = array('level' => $module['level'], 'summary' => $module['summary'], 'metrics' => $module['metrics'], 'latest_failure' => $module['latest_failure'], 'next_action' => $module['next_action']);
        }
        return $snapshot;
    }

    public static function level($critical, $warning) {
        return $critical ? 'blocking' : ($warning ? 'warning' : 'ok');
    }

    public static function sanitize_reason($reason) {
        $reason = wp_strip_all_tags((string) $reason);
        $reason = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $reason);
        $reason = preg_replace('/(api[-_ ]?key|secret|token|password|authorization)\s*[:=]\s*[^,; ]+/i', '$1=[redacted]', $reason);
        return substr(trim($reason), 0, 180);
    }

    private static function module($summary, $metrics, $critical, $warning, $next_action, $latest_failure = null) {
        return array('level' => self::level($critical, $warning), 'summary' => $summary, 'metrics' => $metrics, 'latest_failure' => $latest_failure, 'next_action' => $next_action);
    }

    private static function capi() {
        $stats = class_exists('SMF_Meta_CAPI') ? SMF_Meta_CAPI::get_queue_stats() : array('pending' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0, 'total' => 0, 'last_sent' => null, 'last_error' => null);
        $rows = self::recent_rows('smf_capi_queue', 'created_at', array('status', 'attempts', 'next_attempt_at', 'last_error', 'created_at', 'sent_at'));
        $stale = 0;
        $oldest_pending = null;
        $recent_failures = 0;
        $latest_failure = null;
        foreach ($rows as $row) {
            if ((string) ($row['status'] ?? '') === 'processing' && self::is_stale($row['next_attempt_at'] ?? '')) $stale++;
            if ((string) ($row['status'] ?? '') === 'pending' && (!empty($row['created_at']) && ($oldest_pending === null || $row['created_at'] < $oldest_pending))) $oldest_pending = $row['created_at'];
            if ((string) ($row['status'] ?? '') === 'failed') { $recent_failures++; if ($latest_failure === null) $latest_failure = self::sanitize_reason($row['last_error'] ?? 'CAPI event failed.'); }
        }
        $retryable = 0;
        if (!empty($stats['pending'])) $retryable = (int) $stats['pending'];
        $exhausted = (int) ($stats['failed'] ?? 0);
        $critical = $exhausted > 0 || $stale > 0;
        $warning = !$critical && ($retryable > 0 || (int) ($stats['processing'] ?? 0) > 0);
        return self::module($critical ? 'CAPI requires attention.' : ($warning ? 'CAPI has work pending.' : 'CAPI queue is clear.'), array('queued' => (int) ($stats['pending'] ?? 0), 'processing' => (int) ($stats['processing'] ?? 0), 'succeeded' => (int) ($stats['sent'] ?? 0), 'failed' => (int) ($stats['failed'] ?? 0), 'retryable' => $retryable, 'exhausted' => $exhausted, 'stale_processing' => $stale, 'oldest_pending' => $oldest_pending, 'recent_failures' => $recent_failures, 'last_success' => $stats['last_sent'] ?? null), $critical, $warning, $critical ? 'Open Diagnostics and inspect failed CAPI events.' : ($warning ? 'Verify WP-Cron and allow the bounded queue worker to run.' : 'No CAPI action required.'), $latest_failure);
    }

    private static function courier() {
        $health = class_exists('SMF_Courier_Timeline') ? SMF_Courier_Timeline::health() : array('failed' => 0, 'retryable' => 0, 'processing' => 0, 'exhausted' => 0);
        $rows = self::recent_rows('smf_courier_events', 'received_at', array('result', 'attempts', 'next_retry_at', 'last_error', 'received_at', 'processed_at', 'provider'));
        $stale = 0;
        $recent_failures = 0;
        $latest_failure = null;
        $providers = array();
        foreach ($rows as $row) {
            $provider = sanitize_key((string) ($row['provider'] ?? 'generic')) ?: 'generic';
            if (!isset($providers[$provider])) $providers[$provider] = array('received' => 0, 'processed' => 0, 'failed' => 0, 'level' => 'ok');
            $providers[$provider]['received']++;
            if ((string) ($row['result'] ?? '') === 'processed') $providers[$provider]['processed']++;
            if ((string) ($row['result'] ?? '') === 'failed') { $providers[$provider]['failed']++; $providers[$provider]['level'] = 'warning'; }
            if ((string) ($row['result'] ?? '') === 'processing' && self::is_stale($row['received_at'] ?? '')) $stale++;
            if ((string) ($row['result'] ?? '') === 'failed') { $recent_failures++; if ($latest_failure === null) $latest_failure = self::sanitize_reason($row['last_error'] ?? 'Courier event failed.'); }
        }
        $critical = (int) ($health['exhausted'] ?? 0) > 0 || $stale > 0;
        $warning = !$critical && ((int) ($health['retryable'] ?? 0) > 0 || (int) ($health['failed'] ?? 0) > 0);
        return self::module($critical ? 'Courier processing requires attention.' : ($warning ? 'Courier events need review.' : 'Courier event processing is clear.'), array('received' => count($rows), 'processing' => (int) ($health['processing'] ?? 0), 'processed' => max(0, count($rows) - (int) ($health['failed'] ?? 0)), 'failed' => (int) ($health['failed'] ?? 0), 'retryable' => (int) ($health['retryable'] ?? 0), 'exhausted' => (int) ($health['exhausted'] ?? 0), 'stale_processing' => $stale, 'recent_failures' => $recent_failures, 'provider_health' => $providers), $critical, $warning, $critical ? 'Open Courier Recovery and inspect exhausted or stale events.' : ($warning ? 'Review Courier Recovery and provider health.' : 'No courier action required.'), $latest_failure);
    }

    private static function attribution() {
        global $wpdb;
        $table = $wpdb->prefix . 'smf_tracking_sessions';
        $active = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE last_seen >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)", self::WINDOW_DAYS));
        return self::module($active ? 'Attribution data is being collected.' : 'No recent attribution sessions were found.', array('active_sessions' => $active, 'recent_session_failures' => 0, 'data_quality_warnings' => 0), false, $active === 0, $active === 0 ? 'Verify tracking and consent configuration before relying on attribution.' : 'No attribution action required.');
    }

    private static function orders() {
        global $wpdb;
        $table = $wpdb->prefix . 'smf_order_events';
        $events = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)", self::WINDOW_DAYS));
        $orders = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT order_id) FROM $table WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)", self::WINDOW_DAYS));
        return self::module($events ? 'Order lifecycle tracking is active.' : 'No recent order lifecycle events were found.', array('recent_tracked_orders' => $orders, 'recent_lifecycle_events' => $events, 'cancellation_return_anomalies' => 0), false, $events === 0, $events === 0 ? 'Verify WooCommerce hooks and order tracking on a staging order.' : 'No order tracking action required.');
    }

    private static function system() {
        $compatibility = class_exists('SMF_Compatibility') ? SMF_Compatibility::report() : array('ready' => false, 'blocking_failures' => 1, 'warnings' => 0, 'schema_version' => 'unknown');
        $cron = wp_next_scheduled('smf_process_capi_queue') || wp_next_scheduled('smf_retry_courier_events') || wp_next_scheduled('smf_sync_meta_spend');
        $critical = !$compatibility['ready'];
        $warning = !$critical && !$cron;
        return self::module($critical ? 'Compatibility has blocking failures.' : ($warning ? 'Required background work is not scheduled.' : 'Core system checks are available.'), array('cron' => $cron ? 'scheduled' : 'not_scheduled', 'schema_version' => (string) ($compatibility['schema_version'] ?? ''), 'compatibility_blocking' => (int) ($compatibility['blocking_failures'] ?? 0), 'compatibility_warnings' => (int) ($compatibility['warnings'] ?? 0), 'woocommerce' => class_exists('WooCommerce') ? 'available' : 'unavailable', 'php' => PHP_VERSION, 'wordpress' => get_bloginfo('version')), $critical, $warning, $critical ? 'Open Diagnostics and resolve blocking compatibility checks.' : ($warning ? 'Verify WP-Cron or configure a real server cron.' : 'No system action required.'));
    }

    private static function recent_rows($suffix, $order_column, $fields) {
        global $wpdb;
        $table = $wpdb->prefix . $suffix;
        $columns = implode(',', array_map('sanitize_key', $fields));
        return (array) $wpdb->get_results($wpdb->prepare("SELECT $columns FROM $table WHERE $order_column >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY) ORDER BY $order_column DESC LIMIT %d", self::WINDOW_DAYS, self::ROW_LIMIT), ARRAY_A);
    }

    private static function is_stale($timestamp) {
        return $timestamp !== '' && strtotime((string) $timestamp) !== false && (time() - strtotime((string) $timestamp)) > self::STALE_SECONDS;
    }
}
