<?php
defined('ABSPATH') || exit;

class SMF_Tracker {
    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_action('wp_footer', array(__CLASS__, 'render_script'), 99);
    }

    public static function enqueue() {
        if (is_admin()) return;
        wp_enqueue_script('smf-tracker', SMF_URL . 'assets/js/tracker.js', array('jquery'), SMF_VERSION, true);
        wp_localize_script('smf-tracker', 'SMF_DATA', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smf_track'),
            'sessionKey' => self::get_or_create_session(),
            'productId' => function_exists('is_product') && is_product() ? get_the_ID() : 0,
            'productName' => function_exists('is_product') && is_product() ? get_the_title() : '',
            'isProduct' => function_exists('is_product') && is_product(),
            'isCheckout' => function_exists('is_checkout') && is_checkout(),
            'isCart' => function_exists('is_cart') && is_cart(),
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '',
        ));
    }

    public static function render_script() {
        if (is_admin()) return;
        $allowed = array('fbclid','utm_source','utm_medium','utm_campaign','utm_content','utm_term');
        $values = array();
        foreach ($allowed as $key) {
            if (isset($_GET[$key])) $values[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
        }
        if ($values) echo '<script>window.SMF_ATTRIBUTION=' . wp_json_encode($values) . ';</script>';
    }

    public static function get_or_create_session() {
        $cookie = isset($_COOKIE['smf_session']) ? sanitize_text_field(wp_unslash($_COOKIE['smf_session'])) : '';
        if ($cookie && preg_match('/^[a-f0-9-]{36}$/', $cookie)) return $cookie;

        $data = array();
        $allowed = array('fbclid','utm_source','utm_medium','utm_campaign','utm_content','utm_term');
        foreach ($allowed as $key) {
            if (isset($_GET[$key])) $data[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
        }
        $key = self::save_session($data);
        if (!headers_sent()) {
            setcookie('smf_session', $key, time() + (30 * DAY_IN_SECONDS), COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
        }
        return $key;
    }

    public static function save_session($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'smf_tracking_sessions';
        $key = wp_generate_uuid4();
        $now = current_time('mysql');
        $allowed = array('fbclid','utm_source','utm_medium','utm_campaign','utm_content','utm_term');
        $row = array(
            'session_key' => $key,
            'landing_url' => isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : null,
            'first_seen' => $now,
            'last_seen' => $now,
        );
        foreach ($allowed as $field) $row[$field] = isset($data[$field]) ? sanitize_text_field($data[$field]) : null;
        $wpdb->insert($table, $row);
        return $key;
    }

    public static function touch_session($session_key, $attribution = array()) {
        global $wpdb;
        if (!preg_match('/^[a-f0-9-]{36}$/', $session_key)) return false;
        $table = $wpdb->prefix . 'smf_tracking_sessions';
        $updates = array('last_seen' => current_time('mysql'));
        $allowed = array('fbclid','utm_source','utm_medium','utm_campaign','utm_content','utm_term');
        foreach ($allowed as $field) {
            if (!empty($attribution[$field])) $updates[$field] = sanitize_text_field($attribution[$field]);
        }
        return false !== $wpdb->update($table, $updates, array('session_key' => $session_key));
    }
}
