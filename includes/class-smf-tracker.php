<?php
defined('ABSPATH') || exit;

class SMF_Tracker {
    private static $attribution_fields = array('fbclid','utm_source','utm_medium','utm_campaign','utm_content','utm_term','utm_id','campaign_id','adset_id','ad_id');

    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_action('wp_head', array(__CLASS__, 'render_meta_pixel'), 1);
        add_action('wp_footer', array(__CLASS__, 'render_script'), 99);
    }

    public static function enqueue() {
        if (is_admin()) return;
        $order_id = self::get_received_order_id();
        $order = $order_id ? wc_get_order($order_id) : false;
        $purchase_id = $order ? $order->get_meta('_smf_purchase_event_id') : '';
        $fbc = self::get_fbc_cookie();
        $fbp = isset($_COOKIE['_fbp']) ? sanitize_text_field(wp_unslash($_COOKIE['_fbp'])) : '';
        wp_enqueue_script('smf-tracker', SMF_URL . 'assets/js/tracker.js', array('jquery'), SMF_VERSION, true);
        wp_localize_script('smf-tracker', 'SMF_DATA', array(
            'ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('smf_track'),
            'sessionKey' => self::get_or_create_session(),
            'productId' => function_exists('is_product') && is_product() ? get_the_ID() : 0,
            'productName' => function_exists('is_product') && is_product() ? get_the_title() : '',
            'isProduct' => function_exists('is_product') && is_product(),
            'isCheckout' => function_exists('is_checkout') && is_checkout() && (!function_exists('is_order_received_page') || !is_order_received_page()),
            'isCart' => function_exists('is_cart') && is_cart(),
            'isOrderReceived' => (bool) $order,
            'orderId' => $order ? $order->get_id() : 0,
            'orderTotal' => $order ? (float) $order->get_total() : 0,
            'purchaseEventId' => $purchase_id,
            'currency' => $order ? $order->get_currency() : (function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : ''),
            'metaPixelId' => self::meta_enabled() ? preg_replace('/[^0-9]/', '', (string) get_option('smf_meta_pixel_id', '')) : '',
            'fbp' => $fbp,
            'fbc' => $fbc,
        ));
    }

    private static function get_received_order_id() {
        if (!function_exists('is_order_received_page') || !is_order_received_page()) return 0;
        $id = absint(get_query_var('order-received'));
        if (!$id && isset($_GET['order-received'])) $id = absint($_GET['order-received']);
        return $id;
    }

    public static function meta_enabled() {
        return get_option('smf_meta_enabled', 'no') === 'yes' && trim((string) get_option('smf_meta_pixel_id', '')) !== '';
    }

    public static function render_meta_pixel() {
        if (is_admin() || !self::meta_enabled()) return;
        $pixel = preg_replace('/[^0-9]/', '', (string) get_option('smf_meta_pixel_id', ''));
        if (!$pixel) return;
        ?>
        <script>
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '<?php echo esc_js($pixel); ?>');
        </script>
        <?php
    }

    public static function render_script() {
        if (is_admin()) return;
        $values = array();
        foreach (self::$attribution_fields as $key) if (isset($_GET[$key])) $values[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
        echo '<script>window.SMF_ATTRIBUTION=' . wp_json_encode($values) . ';</script>';
    }

    private static function get_fbc_cookie() {
        if (isset($_COOKIE['smf_fbc'])) {
            $value = sanitize_text_field(wp_unslash($_COOKIE['smf_fbc']));
            if (strpos($value, 'fb.1.') === 0) return $value;
        }
        if (!empty($_GET['fbclid'])) return 'fb.1.' . round(microtime(true) * 1000) . '.' . sanitize_text_field(wp_unslash($_GET['fbclid']));
        return '';
    }

    public static function get_or_create_session() {
        $cookie = isset($_COOKIE['smf_session']) ? sanitize_text_field(wp_unslash($_COOKIE['smf_session'])) : '';
        if ($cookie && preg_match('/^[a-f0-9-]{36}$/', $cookie)) return $cookie;
        $data = self::request_attribution();
        $key = self::save_session($data);
        if (!headers_sent()) setcookie('smf_session', $key, time() + (30 * DAY_IN_SECONDS), COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
        return $key;
    }

    private static function request_attribution() {
        $data = array();
        foreach (self::$attribution_fields as $key) if (isset($_GET[$key])) $data[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
        $data['fbp'] = isset($_COOKIE['_fbp']) ? sanitize_text_field(wp_unslash($_COOKIE['_fbp'])) : '';
        $data['fbc'] = self::get_fbc_cookie();
        return $data;
    }

    public static function save_session($data) {
        global $wpdb;
        $key = wp_generate_uuid4(); $now = current_time('mysql');
        $row = array('session_key'=>$key,'landing_url'=>isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : null,'first_seen'=>$now,'last_seen'=>$now);
        foreach (self::$attribution_fields as $field) $row[$field] = isset($data[$field]) ? sanitize_text_field($data[$field]) : null;
        $row['fbp'] = !empty($data['fbp']) ? sanitize_text_field($data['fbp']) : null;
        $row['fbc'] = !empty($data['fbc']) ? sanitize_text_field($data['fbc']) : null;
        $wpdb->insert($wpdb->prefix . 'smf_tracking_sessions', $row);
        return $key;
    }

    public static function touch_session($session_key, $attribution = array()) {
        global $wpdb;
        if (!preg_match('/^[a-f0-9-]{36}$/', $session_key)) return false;
        $updates = array('last_seen'=>current_time('mysql'));
        foreach (self::$attribution_fields as $field) if (!empty($attribution[$field])) $updates[$field] = sanitize_text_field($attribution[$field]);
        if (!empty($attribution['fbp'])) $updates['fbp'] = sanitize_text_field($attribution['fbp']);
        if (!empty($attribution['fbc'])) $updates['fbc'] = sanitize_text_field($attribution['fbc']);
        return false !== $wpdb->update($wpdb->prefix . 'smf_tracking_sessions', $updates, array('session_key'=>$session_key));
    }
}
