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
        add_submenu_page('sync-meta-flow', 'Setup', 'Setup', 'manage_woocommerce', 'smf-settings', array(__CLASS__, 'settings_page'));
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
        $delivered = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT order_id) FROM $table WHERE new_status IN (%s, %s)", 'smf-delivered', 'completed'));
        $cancelled = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT order_id) FROM $table WHERE new_status = %s", 'cancelled'));
        $enabled = get_option('smf_meta_enabled', 'no') === 'yes';
        $pixel = trim((string) get_option('smf_meta_pixel_id', ''));
        $connected = $enabled && $pixel !== '';
        ?>
        <div class="wrap smf-wrap">
            <div class="smf-header"><div><h1>Sync Meta Flow</h1><p>Simple WooCommerce tracking for Meta ads.</p></div><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=smf-settings')); ?>">Setup & Settings</a></div>
            <div class="smf-status <?php echo $connected ? 'is-good' : 'is-warning'; ?>"><span class="smf-status-dot"></span><div><strong><?php echo $connected ? 'Meta tracking is active' : 'Finish your Meta setup'; ?></strong><small><?php echo $connected ? 'Your store is ready for automatic browser tracking.' : 'Connect your Pixel and enable tracking to get started.'; ?></small></div><?php if (!$connected): ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=smf-settings')); ?>">Complete setup</a><?php endif; ?></div>
            <div class="smf-cards">
                <div class="smf-card"><span>Tracked Orders</span><strong><?php echo esc_html($orders); ?></strong><small>WooCommerce orders seen</small></div>
                <div class="smf-card"><span>Delivered</span><strong><?php echo esc_html($delivered); ?></strong><small>Completed / delivered</small></div>
                <div class="smf-card"><span>Cancelled</span><strong><?php echo esc_html($cancelled); ?></strong><small>Orders cancelled</small></div>
            </div>
            <div class="smf-panel"><h2>Customer Flow</h2><p class="smf-muted">Everything is tracked automatically after setup.</p><div class="smf-flow"><b>PageView</b><span>→</span><b>Add to Cart</b><span>→</span><b>Checkout</b><span>→</span><b>Purchase</b><span>→</span><b>Delivered</b></div></div>
            <div class="smf-panel smf-checklist"><h2>Quick health check</h2><div class="smf-check"><span>✓</span> WooCommerce detected</div><div class="smf-check"><span><?php echo $pixel ? '✓' : '○'; ?></span> Meta Pixel <?php echo $pixel ? 'configured' : 'needs setup'; ?></div><div class="smf-check"><span><?php echo $enabled ? '✓' : '○'; ?></span> Automatic tracking <?php echo $enabled ? 'enabled' : 'disabled'; ?></div><div class="smf-check"><span>✓</span> Facebook click & UTM attribution ready</div></div>
        </div>
        <?php
    }

    public static function settings_page() {
        $enabled = get_option('smf_meta_enabled', 'no') === 'yes';
        $pixel = trim((string) get_option('smf_meta_pixel_id', ''));
        ?>
        <div class="wrap smf-wrap smf-settings">
            <div class="smf-header"><div><h1>Meta Setup</h1><p>Connect once. Sync Meta Flow handles the tracking automatically.</p></div></div>
            <form method="post" action="options.php">
                <?php settings_fields('smf_settings'); ?>
                <div class="smf-setup-grid">
                    <div class="smf-panel smf-main-setup">
                        <div class="smf-step"><span>1</span><div><h2>Enable tracking</h2><p>No code or theme changes required.</p><label class="smf-toggle"><input type="checkbox" name="smf_meta_enabled" value="yes" <?php checked($enabled); ?>><i></i><b><?php echo $enabled ? 'Tracking enabled' : 'Enable tracking'; ?></b></label></div></div>
                        <div class="smf-step"><span>2</span><div><h2>Connect your Meta Pixel</h2><p>Paste the Pixel ID from Meta Events Manager.</p><input class="smf-input" name="smf_meta_pixel_id" value="<?php echo esc_attr($pixel); ?>" placeholder="Example: 123456789012345"><small class="smf-help">Meta Events Manager → Data Sources → your Pixel.</small></div></div>
                        <div class="smf-step"><span>3</span><div><h2>Conversions API <em>Optional</em></h2><p>Add an access token for server-side events. You can leave this blank during setup.</p><input type="password" class="smf-input" name="smf_meta_access_token" value="<?php echo esc_attr(get_option('smf_meta_access_token', '')); ?>" placeholder="Meta access token"></div></div>
                        <div class="smf-events"><h2>Tracked automatically</h2><div class="smf-event-grid"><?php foreach (array('PageView','ViewContent','AddToCart','InitiateCheckout','Purchase','Confirmed','Shipped','Delivered','Cancelled','Returned') as $event): ?><span>✓ <?php echo esc_html($event); ?></span><?php endforeach; ?></div></div>
                        <?php submit_button('Save & Enable'); ?>
                    </div>
                    <div class="smf-panel smf-side-setup"><div class="smf-big-icon">✓</div><h2>Set it and forget it</h2><p>Sync Meta Flow detects WooCommerce and keeps the customer's ad attribution attached to the order.</p><hr><strong>You don't need to:</strong><ul><li>edit theme code</li><li>add JavaScript</li><li>configure every WooCommerce event</li><li>manually track Facebook clicks</li></ul></div>
                </div>
            </form>
        </div>
        <?php
    }
}
