<?php
defined('ABSPATH') || exit;

class SMF_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function menu() {
        add_menu_page('Sync Meta Flow', 'Meta Flow', 'manage_woocommerce', 'sync-meta-flow', array(__CLASS__, 'dashboard'), 'dashicons-chart-area', 56);
        add_submenu_page('sync-meta-flow', 'Settings', 'Settings', 'manage_woocommerce', 'smf-settings', array(__CLASS__, 'settings_page'));
    }

    public static function settings() {
        register_setting('smf_settings', 'smf_meta_enabled', array('sanitize_callback' => function($v){ return $v === 'yes' ? 'yes' : 'no'; }));
        register_setting('smf_settings', 'smf_meta_pixel_id', 'sanitize_text_field');
        register_setting('smf_settings', 'smf_meta_access_token', 'sanitize_text_field');
    }

    public static function assets($hook) {
        if (strpos($hook, 'sync-meta-flow') === false) return;
        wp_enqueue_style('smf-admin', SMF_URL . 'assets/css/admin.css', array(), SMF_VERSION);
    }

    public static function dashboard() {
        global $wpdb;
        $table = $wpdb->prefix . 'smf_order_events';
        $orders = (int) $wpdb->get_var("SELECT COUNT(DISTINCT order_id) FROM $table");
        $delivered = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT order_id) FROM $table WHERE new_status = %s", 'completed'));
        $cancelled = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT order_id) FROM $table WHERE new_status = %s", 'cancelled'));
        ?>
        <div class="wrap smf-wrap"><h1>Sync Meta Flow</h1><p>WooCommerce order-flow and Meta attribution overview.</p>
        <div class="smf-cards">
            <div class="smf-card"><span>Tracked Orders</span><strong><?php echo esc_html($orders); ?></strong></div>
            <div class="smf-card"><span>Delivered</span><strong><?php echo esc_html($delivered); ?></strong></div>
            <div class="smf-card"><span>Cancelled</span><strong><?php echo esc_html($cancelled); ?></strong></div>
        </div>
        <h2>Flow</h2><div class="smf-flow"><b>Purchase</b><span>→</span><b>Confirmed</b><span>→</span><b>Shipped</b><span>→</span><b>Delivered</b></div>
        </div><?php
    }

    public static function settings_page() { ?>
        <div class="wrap"><h1>Sync Meta Flow Settings</h1>
        <form method="post" action="options.php"><?php settings_fields('smf_settings'); ?>
        <table class="form-table">
        <tr><th scope="row">Enable Meta CAPI</th><td><label><input type="checkbox" name="smf_meta_enabled" value="yes" <?php checked(get_option('smf_meta_enabled'), 'yes'); ?>> Enable</label></td></tr>
        <tr><th scope="row">Meta Pixel ID</th><td><input class="regular-text" name="smf_meta_pixel_id" value="<?php echo esc_attr(get_option('smf_meta_pixel_id', '')); ?>"></td></tr>
        <tr><th scope="row">Meta Access Token</th><td><input type="password" class="regular-text" name="smf_meta_access_token" value="<?php echo esc_attr(get_option('smf_meta_access_token', '')); ?>"></td></tr>
        </table><?php submit_button(); ?></form></div><?php }
}
