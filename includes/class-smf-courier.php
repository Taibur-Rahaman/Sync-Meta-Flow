<?php
defined('ABSPATH') || exit;

class SMF_Courier {
    const NS = 'sync-meta-flow/v1';
    const ROUTE = '/courier/webhook';

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_smf_save_courier', array(__CLASS__, 'save'));
    }

    public static function routes() {
        register_rest_route(self::NS, self::ROUTE, array(
            'methods' => 'POST', 'callback' => array(__CLASS__, 'webhook'),
            'permission_callback' => '__return_true'
        ));
    }

    private static function secret() { return trim((string)get_option('smf_courier_webhook_secret', '')); }

    private static function valid_signature($request, $raw) {
        $secret = self::secret(); if ($secret === '') return false;
        $sig = $request->get_header('x-smf-signature');
        if (!$sig) $sig = $request->get_header('x-webhook-signature');
        if (!$sig) return false;
        if (stripos($sig, 'sha256=') === 0) $sig = substr($sig, 7);
        return hash_equals(hash_hmac('sha256', $raw, $secret), trim($sig));
    }

    public static function webhook($request) {
        $raw = $request->get_body();
        if (strlen($raw) > 100000) return new WP_Error('smf_payload_too_large', 'Webhook payload is too large.', array('status'=>413));
        if (!self::valid_signature($request, $raw)) return new WP_Error('smf_invalid_signature', 'Invalid webhook signature.', array('status'=>401));
        $data = json_decode($raw, true);
        if (!is_array($data)) return new WP_Error('smf_invalid_json', 'Webhook body must be valid JSON.', array('status'=>400));
        $order_id = isset($data['order_id']) ? absint($data['order_id']) : 0;
        if (!$order_id && isset($data['order_number']) && function_exists('wc_get_order_id_by_order_key')) $order_id = absint(wc_get_order_id_by_order_key(sanitize_text_field($data['order_number'])));
        if (!$order_id) return new WP_Error('smf_missing_order', 'order_id is required.', array('status'=>400));
        $order = wc_get_order($order_id); if (!$order) return new WP_Error('smf_order_not_found', 'WooCommerce order not found.', array('status'=>404));

        $status = strtolower(sanitize_key(isset($data['status']) ? $data['status'] : ''));
        $map = array('confirmed'=>'smf-confirmed','confirm'=>'smf-confirmed','shipped'=>'smf-shipped','in_transit'=>'smf-shipped','in-transit'=>'smf-shipped','delivered'=>'smf-delivered','completed'=>'completed','complete'=>'completed','cancelled'=>'cancelled','canceled'=>'cancelled','failed'=>'failed','returned'=>'smf-returned','return'=>'smf-returned','refunded'=>'refunded');
        if (!isset($map[$status])) return new WP_Error('smf_unknown_status', 'Unsupported courier status.', array('status'=>422));
        $tracking = isset($data['tracking_number']) ? sanitize_text_field($data['tracking_number']) : '';
        $provider = isset($data['provider']) ? sanitize_key($data['provider']) : 'courier';
        if ($tracking) $order->update_meta_data('_smf_tracking_number', $tracking);
        $order->update_meta_data('_smf_courier_provider', $provider);
        $order->update_meta_data('_smf_courier_last_status', $status);
        $order->update_meta_data('_smf_courier_updated_at', current_time('mysql'));
        $order->save();
        $target = $map[$status];
        if ($order->get_status() !== str_replace('wc-', '', $target)) $order->update_status($target, sprintf('Sync Meta Flow courier update: %s%s', $status, $tracking ? ' · '.$tracking : ''), true);
        return new WP_REST_Response(array('ok'=>true,'order_id'=>$order_id,'status'=>$target,'tracking_number'=>$tracking), 200);
    }

    public static function menu() { add_submenu_page('sync-meta-flow','Courier & Delivery','Courier & Delivery','manage_woocommerce','smf-courier',array(__CLASS__,'page')); }

    public static function save() {
        if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized'); check_admin_referer('smf_save_courier');
        update_option('smf_courier_webhook_secret', isset($_POST['webhook_secret']) ? sanitize_text_field(wp_unslash($_POST['webhook_secret'])) : '', false);
        $provider = isset($_POST['provider']) ? sanitize_key($_POST['provider']) : 'generic';
        update_option('smf_courier_provider', in_array($provider,array('generic','pathao','steadfast','redx'),true) ? $provider : 'generic', false);
        wp_safe_redirect(admin_url('admin.php?page=smf-courier&updated=1')); exit;
    }

    public static function page() {
        if (!current_user_can('manage_woocommerce')) return;
        $secret=self::secret(); $provider=get_option('smf_courier_provider','generic'); $endpoint=rest_url(self::NS.self::ROUTE); ?>
        <div class="wrap smf-wrap smf-settings"><div class="smf-header"><div><h1>Courier & Delivery</h1><p>Connect courier updates to the WooCommerce order flow.</p></div><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sync-meta-flow')); ?>">← Dashboard</a></div>
        <div class="smf-setup-grid"><div class="smf-panel"><h2>Webhook bridge</h2><p class="smf-muted">POST normalized JSON and sign the raw body with HMAC-SHA256.</p><p><label>Endpoint<br><input class="smf-input" readonly value="<?php echo esc_attr($endpoint); ?>"></label></p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="smf_save_courier"><?php wp_nonce_field('smf_save_courier'); ?><p><label>Provider<br><select name="provider"><option value="generic" <?php selected($provider,'generic'); ?>>Generic adapter</option><option value="pathao" <?php selected($provider,'pathao'); ?>>Pathao</option><option value="steadfast" <?php selected($provider,'steadfast'); ?>>Steadfast</option><option value="redx" <?php selected($provider,'redx'); ?>>RedX</option></select></label></p><p><label>Webhook secret<br><input class="smf-input" type="password" name="webhook_secret" value="<?php echo esc_attr($secret); ?>" autocomplete="new-password"></label></p><?php submit_button('Save Courier Settings'); ?></form></div><div class="smf-panel"><h2>Payload</h2><pre><code>{"order_id":1234,"status":"delivered","tracking_number":"ABC123","provider":"pathao"}</code></pre><p class="smf-muted">Header: <code>X-SMF-Signature: sha256=&lt;HMAC&gt;</code></p><p><strong>States:</strong> Confirmed → Shipped → Delivered → Returned / Cancelled.</p></div></div></div><?php
    }
}
