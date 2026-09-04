<?php
defined('ABSPATH') || exit;

class SMF_Courier {
    const NS = 'sync-meta-flow/v1';
    const ROUTE = '/courier/webhook';
    const STEADFAST_BASE = 'https://portal.packzy.com/api/v1';
    const SHIPMENT_LOCK_TTL = 120;

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_smf_save_courier', array(__CLASS__, 'save'));
        add_action('admin_post_smf_create_shipment', array(__CLASS__, 'create_shipment'));
        add_action('woocommerce_admin_order_data_after_billing_address', array(__CLASS__, 'order_box'));
    }

    public static function routes() {
        register_rest_route(self::NS, self::ROUTE, array(
            'methods' => 'POST', 'callback' => array(__CLASS__, 'webhook'),
            'permission_callback' => '__return_true'
        ));
    }

    private static function secret() { return trim((string)get_option('smf_courier_webhook_secret', '')); }
    private static function provider() { return sanitize_key((string)get_option('smf_courier_provider', 'generic')); }

    private static function valid_signature($request, $raw) {
        $secret = self::secret(); if ($secret === '') return false;
        $sig = $request->get_header('x-smf-signature');
        if (!$sig) $sig = $request->get_header('x-webhook-signature');
        if (!$sig) return false;
        if (stripos($sig, 'sha256=') === 0) $sig = substr($sig, 7);
        return hash_equals(hash_hmac('sha256', $raw, $secret), trim($sig));
    }

    private static function normalize_status($status) {
        $status = strtolower(sanitize_key((string)$status));
        $map = array(
            'confirmed'=>'smf-confirmed','confirm'=>'smf-confirmed','pending'=>'smf-confirmed',
            'in_review'=>'smf-confirmed','approved'=>'smf-confirmed',
            'shipped'=>'smf-shipped','in_transit'=>'smf-shipped','in-transit'=>'smf-shipped','picked'=>'smf-shipped','picked_up'=>'smf-shipped','out_for_delivery'=>'smf-shipped',
            'delivered'=>'smf-delivered','partial_delivered'=>'smf-delivered','completed'=>'completed','complete'=>'completed',
            'cancelled'=>'cancelled','canceled'=>'cancelled','cancelled_approval_pending'=>'cancelled','failed'=>'failed',
            'returned'=>'smf-returned','return'=>'smf-returned','refunded'=>'refunded','hold'=>'on-hold'
        );
        return isset($map[$status]) ? $map[$status] : false;
    }

    private static function find_order_id($data) {
        $order_id = isset($data['order_id']) ? absint($data['order_id']) : 0;
        if ($order_id) return $order_id;
        $invoice = '';
        foreach (array('order_number','invoice','merchant_invoice_id') as $key) if (!empty($data[$key])) { $invoice = sanitize_text_field($data[$key]); break; }
        if (!$invoice) return 0;
        $orders = wc_get_orders(array('limit'=>1,'return'=>'ids','meta_key'=>'_smf_courier_invoice','meta_value'=>$invoice));
        if (!empty($orders)) return absint($orders[0]);
        if (is_numeric($invoice)) return absint($invoice);
        return 0;
    }

    public static function webhook($request) {
        $raw = $request->get_body();
        if (strlen($raw) > 100000) return new WP_Error('smf_payload_too_large', 'Webhook payload is too large.', array('status'=>413));
        if (!self::valid_signature($request, $raw)) return new WP_Error('smf_invalid_signature', 'Invalid webhook signature.', array('status'=>401));
        $data = json_decode($raw, true);
        if (!is_array($data)) return new WP_Error('smf_invalid_json', 'Webhook body must be valid JSON.', array('status'=>400));
        $order_id = self::find_order_id($data);
        if (!$order_id) return new WP_Error('smf_missing_order', 'order_id or known courier invoice is required.', array('status'=>400));
        $order = wc_get_order($order_id); if (!$order) return new WP_Error('smf_order_not_found', 'WooCommerce order not found.', array('status'=>404));
        $raw_status = isset($data['status']) ? $data['status'] : (isset($data['delivery_status']) ? $data['delivery_status'] : '');
        $target = self::normalize_status($raw_status);
        if (!$target) return new WP_Error('smf_unknown_status', 'Unsupported courier status.', array('status'=>422));
        $tracking = '';
        foreach (array('tracking_number','tracking_code','consignment_id') as $key) if (!empty($data[$key])) { $tracking = sanitize_text_field((string)$data[$key]); break; }
        $provider = !empty($data['provider']) ? sanitize_key($data['provider']) : self::provider();

        try {
            $order->update_meta_data('_smf_courier_provider', $provider);
            $order->update_meta_data('_smf_courier_last_status', sanitize_key((string)$raw_status));
            $order->update_meta_data('_smf_courier_updated_at', current_time('mysql'));
            if ($tracking) $order->update_meta_data('_smf_tracking_number', $tracking);
            if (!empty($data['cod_amount'])) $order->update_meta_data('_smf_courier_cod_amount', wc_format_decimal($data['cod_amount']));
            if (!empty($data['delivery_fee'])) $order->update_meta_data('_smf_courier_delivery_fee', wc_format_decimal($data['delivery_fee']));
            $order->save();

            $current = $order->get_status(); $target_status = str_replace('wc-', '', $target);
            if ($current !== $target_status) $order->update_status($target_status, sprintf('Sync Meta Flow courier update: %s%s', sanitize_text_field((string)$raw_status), $tracking ? ' · '.$tracking : ''), true);
        } catch (Throwable $e) {
            return new WP_Error('smf_webhook_mutation_failed', 'Courier webhook order mutation failed; the event will remain retryable.', array('status'=>500));
        }

        return new WP_REST_Response(array('ok'=>true,'order_id'=>$order_id,'status'=>$target,'tracking_number'=>$tracking,'provider'=>$provider), 200);
    }

    private static function api_credentials() {
        return array('key'=>trim((string)get_option('smf_steadfast_api_key','')),'secret'=>trim((string)get_option('smf_steadfast_secret_key','')));
    }

    private static function steadfast_request($method, $path, $body=array()) {
        $c = self::api_credentials();
        if ($c['key']==='' || $c['secret']==='') return new WP_Error('smf_missing_credentials','Steadfast API key and secret key are required.');
        $args = array('method'=>$method,'timeout'=>30,'headers'=>array('Api-Key'=>$c['key'],'Secret-Key'=>$c['secret'],'Content-Type'=>'application/json','Accept'=>'application/json'));
        if ($method !== 'GET') $args['body']=wp_json_encode($body);
        $response = wp_remote_request(trailingslashit(self::STEADFAST_BASE).ltrim($path,'/'),$args);
        if (is_wp_error($response)) return $response;
        $code=wp_remote_retrieve_response_code($response); $raw=wp_remote_retrieve_body($response); $json=json_decode($raw,true);
        if ($code < 200 || $code >= 300) return new WP_Error('smf_courier_api_error','Courier API request failed.',array('status'=>$code,'body'=>is_array($json)?$json:$raw));
        if (!is_array($json)) return new WP_Error('smf_courier_invalid_response','Courier API returned invalid JSON.');
        return $json;
    }

    private static function shipment_lock_key($order_id) { return 'smf_shipment_lock_'.absint($order_id); }

    private static function acquire_shipment_lock($order_id) {
        global $wpdb;
        $key = self::shipment_lock_key($order_id);
        $token = wp_generate_uuid4();
        $now = time();
        $value = $token . ':' . $now;
        $inserted = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, $value));
        if ($inserted === 1) return $token;
        $cutoff = $now - self::SHIPMENT_LOCK_TTL;
        $replaced = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s, autoload = 'no' WHERE option_name = %s AND CAST(SUBSTRING_INDEX(option_value, ':', -1) AS UNSIGNED) <= %d", $value, $key, $cutoff));
        if ($replaced !== 1) return false;
        wp_cache_delete($key, 'options');
        return get_option($key, '') === $value ? $token : false;
    }

    private static function release_shipment_lock($order_id, $token) {
        global $wpdb;
        if (!$token) return;
        $key = self::shipment_lock_key($order_id);
        $value = get_option($key, '');
        if ($value !== $token . ':' . substr((string)$value, strrpos((string)$value, ':') + 1)) {
            $parts = explode(':', (string)$value, 2);
            if (count($parts) !== 2 || $parts[0] !== $token) return;
        }
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value LIKE %s", $key, $token . ':%'));
        wp_cache_delete($key, 'options');
    }

    private static function create_steadfast_order($order) {
        $existing_consignment = trim((string)$order->get_meta('_smf_courier_consignment_id'));
        if ($existing_consignment !== '') return new WP_Error('smf_shipment_exists','A courier shipment already exists for this order.');
        $lock = self::acquire_shipment_lock($order->get_id());
        if (!$lock) return new WP_Error('smf_shipment_in_progress','Shipment creation is already in progress for this order. Please wait and refresh.');
        try {
            $existing_consignment = trim((string)$order->get_meta('_smf_courier_consignment_id'));
            if ($existing_consignment !== '') return new WP_Error('smf_shipment_exists','A courier shipment already exists for this order.');
            $items = $order->get_items(); $names=array(); foreach ($items as $item) $names[]=$item->get_name().' x'.$item->get_quantity();
            $payload=array(
                'invoice'=>'SMF-'.$order->get_id(),
                'recipient_name'=>$order->get_formatted_billing_full_name() ?: $order->get_shipping_first_name().' '.$order->get_shipping_last_name(),
                'recipient_phone'=>$order->get_billing_phone(),
                'recipient_address'=>trim($order->get_billing_address_1().' '.$order->get_billing_address_2().' '.$order->get_billing_city().' '.$order->get_billing_state()),
                'cod_amount'=>(float)$order->get_total(),
                'note'=>substr(wp_strip_all_tags($order->get_customer_note()),0,480),
                'item_description'=>substr(implode(', ',$names),0,500),
                'total_lot'=>max(1,count($items)),
                'delivery_type'=>0
            );
            $result=self::steadfast_request('POST','/create_order',$payload);
            if (is_wp_error($result)) return $result;
            if (empty($result['consignment'])) return new WP_Error('smf_courier_create_failed','Courier did not return a consignment.');
            $cons=$result['consignment'];
            $order->update_meta_data('_smf_courier_provider','steadfast');
            $order->update_meta_data('_smf_courier_invoice',$payload['invoice']);
            if (!empty($cons['consignment_id'])) $order->update_meta_data('_smf_courier_consignment_id',sanitize_text_field((string)$cons['consignment_id']));
            if (!empty($cons['tracking_code'])) { $order->update_meta_data('_smf_tracking_number',sanitize_text_field((string)$cons['tracking_code'])); $order->update_meta_data('_smf_courier_tracking_code',sanitize_text_field((string)$cons['tracking_code'])); }
            if (isset($cons['cod_amount'])) $order->update_meta_data('_smf_courier_cod_amount',wc_format_decimal($cons['cod_amount']));
            $order->update_meta_data('_smf_courier_last_status',sanitize_key(isset($cons['status'])?$cons['status']:'in_review'));
            $order->update_meta_data('_smf_courier_created_at',current_time('mysql')); $order->save();
            if ($order->get_status()==='pending' || $order->get_status()==='on-hold') $order->update_status('smf-confirmed','Sync Meta Flow: shipment created with Steadfast.',true);
            return $cons;
        } finally {
            self::release_shipment_lock($order->get_id(), $lock);
        }
    }

    public static function create_shipment() {
        if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized');
        if (strtoupper((string)$_SERVER['REQUEST_METHOD']) !== 'POST') wp_die('Shipment creation requires POST.');
        $order_id=isset($_POST['order_id'])?absint($_POST['order_id']):0; check_admin_referer('smf_create_shipment_'.$order_id);
        $order=wc_get_order($order_id); if (!$order) wp_die('Order not found.');
        if (self::provider()!=='steadfast') wp_die('Native shipment creation is currently available for Steadfast. Pathao and RedX require their merchant API credentials/adapter configuration.');
        $result=self::create_steadfast_order($order);
        if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()));
        wp_safe_redirect(wp_get_referer()?wp_get_referer():admin_url('post.php?post='.$order_id.'&action=edit')); exit;
    }

    public static function order_box($order) {
        if (!current_user_can('manage_woocommerce')) return;
        $provider=self::provider(); $tracking=(string)$order->get_meta('_smf_tracking_number'); $consignment=(string)$order->get_meta('_smf_courier_consignment_id');
        echo '<div style="margin-top:20px;padding:12px;border:1px solid #ddd;background:#fff"><strong>Sync Meta Flow Courier</strong>';
        if ($provider==='steadfast' && !$consignment) { echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="smf_create_shipment"><input type="hidden" name="order_id" value="'.esc_attr($order->get_id()).'">'.wp_nonce_field('smf_create_shipment_'.$order->get_id(),'_wpnonce',true,false).'<p><button class="button button-primary" type="submit">Create Steadfast Shipment</button></p></form>'; }
        if ($consignment || $tracking) echo '<p><strong>Tracking:</strong> '.esc_html($tracking?:$consignment).'</p>';
        echo '<p class="description">Provider: '.esc_html($provider).'</p></div>';
    }

    public static function menu() { add_submenu_page('sync-meta-flow','Courier & Delivery','Courier & Delivery','manage_woocommerce','smf-courier',array(__CLASS__,'page')); }

    public static function save() {
        if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized'); check_admin_referer('smf_save_courier');
        update_option('smf_courier_webhook_secret', isset($_POST['webhook_secret']) ? sanitize_text_field(wp_unslash($_POST['webhook_secret'])) : '', false);
        $provider=isset($_POST['provider'])?sanitize_key($_POST['provider']):'generic';
        update_option('smf_courier_provider',in_array($provider,array('generic','pathao','steadfast','redx'),true)?$provider:'generic',false);
        if (isset($_POST['steadfast_api_key']) && trim((string)wp_unslash($_POST['steadfast_api_key'])) !== '') update_option('smf_steadfast_api_key',sanitize_text_field(wp_unslash($_POST['steadfast_api_key'])),false);
        if (isset($_POST['steadfast_secret_key']) && trim((string)wp_unslash($_POST['steadfast_secret_key'])) !== '') update_option('smf_steadfast_secret_key',sanitize_text_field(wp_unslash($_POST['steadfast_secret_key'])),false);
        wp_safe_redirect(admin_url('admin.php?page=smf-courier&updated=1')); exit;
    }

    public static function page() {
        if (!current_user_can('manage_woocommerce')) return;
        $secret=self::secret(); $provider=self::provider(); $endpoint=rest_url(self::NS.self::ROUTE); $key=(string)get_option('smf_steadfast_api_key',''); $skey=(string)get_option('smf_steadfast_secret_key',''); ?>
        <div class="wrap smf-wrap smf-settings"><div class="smf-header"><div><h1>Courier & Delivery <span style="font-size:12px">v1.4</span></h1><p>Native shipment creation for Steadfast plus a normalized webhook bridge for Pathao, Steadfast and RedX.</p></div><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sync-meta-flow')); ?>">← Dashboard</a></div>
        <div class="smf-setup-grid"><div class="smf-panel"><h2>Provider setup</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="smf_save_courier"><?php wp_nonce_field('smf_save_courier'); ?><p><label>Provider<br><select name="provider"><option value="generic" <?php selected($provider,'generic'); ?>>Generic webhook</option><option value="pathao" <?php selected($provider,'pathao'); ?>>Pathao</option><option value="steadfast" <?php selected($provider,'steadfast'); ?>>Steadfast</option><option value="redx" <?php selected($provider,'redx'); ?>>RedX</option></select></label></p><p><label>Webhook secret<br><input class="smf-input" type="password" name="webhook_secret" value="<?php echo esc_attr($secret); ?>" autocomplete="new-password"></label></p><hr><h3>Steadfast API</h3><p><label>API Key<br><input class="smf-input" type="password" name="steadfast_api_key" value="<?php echo esc_attr($key); ?>" autocomplete="new-password"></label></p><p><label>Secret Key<br><input class="smf-input" type="password" name="steadfast_secret_key" value="<?php echo esc_attr($skey); ?>" autocomplete="new-password"></label></p><?php submit_button('Save Courier Settings'); ?></form></div><div class="smf-panel"><h2>Webhook bridge</h2><p class="smf-muted">Use the endpoint for provider webhooks that you normalize to Sync Meta Flow.</p><p><label>Endpoint<br><input class="smf-input" readonly value="<?php echo esc_attr($endpoint); ?>"></label></p><pre><code>{"order_id":1234,"status":"delivered","tracking_number":"ABC123","provider":"steadfast","cod_amount":1500,"delivery_fee":70}</code></pre></div></div></div>
        <?php }
}
