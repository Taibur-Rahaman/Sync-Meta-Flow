<?php
defined('ABSPATH') || exit;

/**
 * Courier operations: queue, risk, routing and provider performance.
 * Native shipment creation remains provider-specific; recommendations never invent API calls.
 */
class SMF_Courier_Operations {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'), 35);
        add_action('admin_post_smf_save_courier_ops', array(__CLASS__, 'save'));
        add_action('woocommerce_order_status_processing', array(__CLASS__, 'maybe_auto_ship'), 20);
        add_action('woocommerce_order_status_smf-confirmed', array(__CLASS__, 'maybe_auto_ship'), 20);
    }

    public static function menu() {
        add_submenu_page('sync-meta-flow', 'Courier Operations', 'Courier Operations', 'manage_woocommerce', 'smf-courier-operations', array(__CLASS__, 'page'));
    }

    private static function settings() {
        return array(
            'auto_ship' => get_option('smf_courier_auto_ship', 'no') === 'yes',
            'default_provider' => sanitize_key((string)get_option('smf_courier_provider', 'generic')),
            'risk_window' => max(1, min(365, absint(get_option('smf_courier_risk_window', 90))))
        );
    }

    private static function status_key($status) {
        $status = strtolower(sanitize_key((string)$status));
        if (in_array($status, array('completed','smf-delivered'), true)) return 'delivered';
        if (in_array($status, array('smf-returned','refunded'), true)) return 'returned';
        if (in_array($status, array('cancelled','failed'), true)) return 'cancelled';
        if ($status === 'smf-shipped') return 'shipped';
        if ($status === 'smf-confirmed') return 'confirmed';
        return 'other';
    }

    public static function customer_risk($order_id) {
        $order_id = absint($order_id); if (!$order_id) return array('score'=>0,'label'=>'Unknown','orders'=>0,'delivered'=>0,'returned'=>0,'cancelled'=>0);
        $order = wc_get_order($order_id); if (!$order) return array('score'=>0,'label'=>'Unknown','orders'=>0,'delivered'=>0,'returned'=>0,'cancelled'=>0);
        $email = sanitize_email($order->get_billing_email()); $phone = preg_replace('/\D+/', '', (string)$order->get_billing_phone());
        $args = array('limit'=>100,'return'=>'ids','exclude'=>array($order_id));
        if ($email) $args['billing_email'] = $email;
        elseif ($phone) $args['billing_phone'] = $phone;
        else return array('score'=>0,'label'=>'Unknown','orders'=>0,'delivered'=>0,'returned'=>0,'cancelled'=>0);
        $ids = wc_get_orders($args); $stats=array('orders'=>0,'delivered'=>0,'returned'=>0,'cancelled'=>0);
        foreach ($ids as $id) { $stats['orders']++; $k=self::status_key(wc_get_order($id)->get_status()); if ($k==='delivered')$stats['delivered']++; elseif($k==='returned')$stats['returned']++; elseif($k==='cancelled')$stats['cancelled']++; }
        $score=50; if($stats['orders']>0){$score=50 + ($stats['delivered']/$stats['orders'])*40 - ($stats['returned']/$stats['orders'])*60 - ($stats['cancelled']/$stats['orders'])*30;}
        $score=max(0,min(100,round($score)));
        $label=$score>=75?'LOW RISK':($score>=45?'MEDIUM RISK':'HIGH RISK');
        return array_merge($stats,array('score'=>$score,'label'=>$label));
    }

    public static function recommend_provider($order) {
        $s=self::settings(); $configured=$s['default_provider'];
        $risk=self::customer_risk($order->get_id());
        if ($configured !== 'generic') return array('provider'=>$configured,'reason'=>'Configured default provider','risk'=>$risk);
        return array('provider'=>'generic','reason'=>$risk['label']==='HIGH RISK'?'High-risk COD/customer: review before dispatch':'No native provider selected; use a configured courier adapter','risk'=>$risk);
    }

    private static function orders_window($days) {
        return wc_get_orders(array('limit'=>200,'type'=>'shop_order','date_created'=>'>='.gmdate('Y-m-d H:i:s',time()-$days*DAY_IN_SECONDS),'orderby'=>'date','order'=>'DESC','return'=>'objects')); 
    }

    private static function provider_stats($days) {
        global $wpdb; $table=$wpdb->prefix.'smf_courier_events'; $since=gmdate('Y-m-d H:i:s',time()-$days*DAY_IN_SECONDS);
        $rows=$wpdb->get_results($wpdb->prepare("SELECT provider, COUNT(*) events, SUM(result='processed') processed, SUM(result='failed') failed FROM $table WHERE received_at >= %s GROUP BY provider ORDER BY events DESC",$since),ARRAY_A);
        return $rows ?: array();
    }

    private static function queue() {
        $orders=self::orders_window(30); $rows=array();
        foreach($orders as $o){$st=self::status_key($o->get_status()); if(!in_array($st,array('confirmed','shipped'),true))continue; $tracking=(string)$o->get_meta('_smf_tracking_number'); if($tracking)continue; $risk=self::customer_risk($o->get_id()); $rec=self::recommend_provider($o); $rows[]=array('id'=>$o->get_id(),'date'=>$o->get_date_created()?$o->get_date_created()->date_i18n('Y-m-d H:i'):'','total'=>$o->get_total(),'status'=>$o->get_status(),'risk'=>$risk,'provider'=>$rec['provider'],'reason'=>$rec['reason']);}
        return $rows;
    }

    public static function save() {
        if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized'); check_admin_referer('smf_save_courier_ops');
        update_option('smf_courier_auto_ship', !empty($_POST['auto_ship'])?'yes':'no', false);
        update_option('smf_courier_risk_window', max(1,min(365,absint($_POST['risk_window']??90))), false);
        wp_safe_redirect(admin_url('admin.php?page=smf-courier-operations&updated=1')); exit;
    }

    public static function maybe_auto_ship($order_id) {
        if (get_option('smf_courier_auto_ship','no')!=='yes') return;
        if (sanitize_key((string)get_option('smf_courier_provider','generic'))!=='steadfast') return;
        $order=wc_get_order($order_id); if(!$order || $order->get_meta('_smf_courier_consignment_id'))return;
        $risk=self::customer_risk($order_id); if($risk['label']==='HIGH RISK') { $order->add_order_note('Sync Meta Flow: automatic shipment blocked because customer risk is HIGH RISK.'); return; }
        // Reuse the existing, credentialed Steadfast adapter only.
        $result=SMF_Courier::create_shipment_for_order($order);
        if(is_wp_error($result)) $order->add_order_note('Sync Meta Flow: automatic shipment failed: '.$result->get_error_message());
    }

    public static function page() {
        if (!current_user_can('manage_woocommerce')) return;
        $s=self::settings(); $q=self::queue(); $providers=self::provider_stats($s['risk_window']);
        ?>
        <div class="wrap smf-wrap smf-settings">
            <div class="smf-header"><div><h1>Courier Operations</h1><p>Dispatch queue, customer-risk signals and courier webhook health.</p></div><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sync-meta-flow'));?>">← Dashboard</a></div>
            <div class="smf-panel"><h2>Automation & risk policy</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="smf_save_courier_ops"><?php wp_nonce_field('smf_save_courier_ops');?><label><input type="checkbox" name="auto_ship" value="1" <?php checked($s['auto_ship']);?>> Automatically create shipments for eligible orders</label><p class="smf-muted">Only the existing Steadfast adapter is eligible. High-risk customers are blocked. Pathao/RedX are never called without a native adapter.</p><p><label>Risk history window (days)<br><input class="small-text" type="number" min="1" max="365" name="risk_window" value="<?php echo esc_attr($s['risk_window']);?>"></label></p><?php submit_button('Save Operations Policy'); ?></form></div>
            <div class="smf-panel"><h2>Dispatch queue <span class="smf-muted">(last 30 days)</span></h2><div class="smf-table-wrap"><table class="smf-table"><thead><tr><th>Order</th><th>Date</th><th>Total</th><th>Status</th><th>Risk</th><th>Recommended provider</th><th>Reason</th></tr></thead><tbody><?php if(!$q):?><tr><td colspan="7">No confirmed/shipped orders are waiting for courier tracking.</td></tr><?php else: foreach($q as $r):?><tr><td><a href="<?php echo esc_url(admin_url('post.php?post='.$r['id'].'&action=edit'));?>">#<?php echo esc_html($r['id']);?></a></td><td><?php echo esc_html($r['date']);?></td><td><?php echo esc_html(number_format_i18n((float)$r['total'],2));?></td><td><?php echo esc_html(wc_get_order_status_name($r['status']));?></td><td><strong><?php echo esc_html($r['risk']['label']);?></strong> · <?php echo esc_html($r['risk']['score']);?>/100</td><td><?php echo esc_html(ucfirst($r['provider']));?></td><td><?php echo esc_html($r['reason']);?></td></tr><?php endforeach; endif;?></tbody></table></div></div>
            <div class="smf-panel"><h2>Courier webhook performance</h2><div class="smf-table-wrap"><table class="smf-table"><thead><tr><th>Provider</th><th>Events</th><th>Processed</th><th>Failed</th></tr></thead><tbody><?php if(!$providers):?><tr><td colspan="4">No courier events recorded in this window.</td></tr><?php else: foreach($providers as $r):?><tr><td><?php echo esc_html(ucfirst($r['provider']));?></td><td><?php echo esc_html($r['events']);?></td><td><?php echo esc_html($r['processed']);?></td><td><?php echo esc_html($r['failed']);?></td></tr><?php endforeach; endif;?></tbody></table></div></div>
            <div class="smf-panel"><h2>Routing policy</h2><p class="smf-muted">Current routing is deliberately conservative: the configured provider is recommended, while customer history adds a dispatch-risk warning. A future native Pathao/RedX adapter can participate without changing the order-risk layer.</p></div>
        </div>
        <?php
    }
}
