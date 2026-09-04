<?php
defined('ABSPATH') || exit;

/**
 * Production request hardening for public Sync Meta Flow endpoints.
 */
class SMF_Security {
    const COURIER_ROUTE = '/sync-meta-flow/v1/courier/webhook';
    const RATE_LIMIT = 60;
    const RATE_WINDOW = 60;

    public static function init() {
        add_filter('rest_pre_dispatch', array(__CLASS__, 'protect_courier_webhook'), 5, 3);
    }

    public static function protect_courier_webhook($result, $server, $request) {
        if (strpos((string) $request->get_route(), self::COURIER_ROUTE) !== 0 || strtoupper($request->get_method()) !== 'POST') {
            return $result;
        }

        $length = $request->get_header('content-length');
        if ($length !== '' && (int) $length > 100000) {
            return new WP_Error('smf_payload_too_large', 'Webhook payload is too large.', array('status' => 413));
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $key = 'smf_wh_rl_' . md5($ip);
        $count = (int) get_transient($key);
        if ($count >= self::RATE_LIMIT) {
            return new WP_Error('smf_rate_limited', 'Too many webhook requests. Try again later.', array(
                'status' => 429,
                'retry_after' => self::RATE_WINDOW,
            ));
        }
        set_transient($key, $count + 1, self::RATE_WINDOW);
        return $result;
    }
}
