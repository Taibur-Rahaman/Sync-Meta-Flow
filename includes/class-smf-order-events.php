<?php
defined('ABSPATH') || exit;

class SMF_Order_Events {
    public static function init() {
        add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'purchase'), 10, 3);
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'status_changed'), 10, 4);
        add_action('woocommerce_admin_order_data_after_billing_address', array(__CLASS__, 'order_attribution_box'));
        add_action('wp_ajax_smf_track_event', array(__CLASS__, 'browser_event'));
        add_action('wp_ajax_nopriv_smf_track_event', array(__CLASS__, 'browser_event'));
    }

    public static function purchase($order_id, $posted_data, $order) {
        self::attach_attribution($order);
        self::log($order_id, 'purchase', null, $order->get_status(), 'woocommerce', self::order_snapshot($order));
        if (!$order->get_meta('_smf_purchase_event_id')) {
            $order->update_meta_data('_smf_purchase_event_id', 'smf-purchase-' . $order->get_id() . '-' . wp_generate_uuid4());
            $order->save();
        }
    }

    public static function status_changed($order_id, $old_status, $new_status, $order) {
        self::log($order_id, 'status_changed', $old_status, $new_status, 'woocommerce', self::order_snapshot($order));
    }

    public static function browser_event() {
        check_ajax_referer('smf_track', 'nonce');
        $event = isset($_POST['event']) ? sanitize_key(wp_unslash($_POST['event'])) : '';
        $allowed = array('page_view', 'view_content', 'add_to_cart', 'initiate_checkout', 'checkout_error', 'purchase');
        if (!in_array($event, $allowed, true)) wp_send_json_error(array('message' => 'Invalid event'), 400);
        $session = isset($_COOKIE['smf_session']) ? sanitize_text_field(wp_unslash($_COOKIE['smf_session'])) : '';
        if (!$session || !preg_match('/^[a-f0-9-]{36}$/', $session)) $session = SMF_Tracker::save_session(array());
        $payload = array();
        if (isset($_POST['payload'])) {
            $raw_payload = wp_unslash($_POST['payload']);
            if (strlen($raw_payload) > 10000) wp_send_json_error(array('message' => 'Payload too large'), 413);
            $decoded = json_decode($raw_payload, true);
            if (is_array($decoded)) $payload = array_slice($decoded, 0, 20, true);
        }
        $event_id = isset($payload['event_id']) ? sanitize_text_field($payload['event_id']) : wp_generate_uuid4();
        global $wpdb;
        $table = $wpdb->prefix . 'smf_tracking_events';
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE event_id = %s LIMIT 1", $event_id));
        if (!$existing) $wpdb->insert($table, array('session_key'=>$session,'event_name'=>$event,'event_id'=>$event_id,'page_url'=>isset($payload['page_url']) ? esc_url_raw($payload['page_url']) : '','payload'=>wp_json_encode($payload),'created_at'=>current_time('mysql')));
        $attribution = !empty($_COOKIE['smf_attribution']) ? json_decode(wp_unslash($_COOKIE['smf_attribution']), true) : array();
        SMF_Tracker::touch_session($session, is_array($attribution) ? $attribution : array());
        wp_send_json_success(array('event_id'=>$event_id,'duplicate'=>(bool)$existing));
    }

    private static function attach_attribution($order) {
        $keys = array('fbclid','utm_source','utm_medium','utm_campaign','utm_content','utm_term','utm_id','campaign_id','adset_id','ad_id');
        $raw = !empty($_COOKIE['smf_attribution']) ? json_decode(wp_unslash($_COOKIE['smf_attribution']), true) : array();
        if (is_array($raw)) foreach ($keys as $key) if (isset($raw[$key])) $order->update_meta_data('_smf_' . $key, sanitize_text_field($raw[$key]));
        if (!empty($_COOKIE['smf_session'])) $order->update_meta_data('_smf_session_key', sanitize_text_field(wp_unslash($_COOKIE['smf_session'])));
        if (!empty($_COOKIE['_fbp'])) $order->update_meta_data('_smf_fbp', sanitize_text_field(wp_unslash($_COOKIE['_fbp'])));
        if (!empty($_COOKIE['smf_fbc'])) $order->update_meta_data('_smf_fbc', sanitize_text_field(wp_unslash($_COOKIE['smf_fbc'])));
        $order->save();
    }

    private static function order_snapshot($order) {
        return array(
            'order_total'=>(float)$order->get_total(), 'currency'=>$order->get_currency(),
            'campaign'=>sanitize_text_field((string)$order->get_meta('_smf_utm_campaign')),
            'content'=>sanitize_text_field((string)$order->get_meta('_smf_utm_content')),
            'utm_id'=>sanitize_text_field((string)$order->get_meta('_smf_utm_id')),
            'campaign_id'=>sanitize_text_field((string)$order->get_meta('_smf_campaign_id')),
            'adset_id'=>sanitize_text_field((string)$order->get_meta('_smf_adset_id')),
            'ad_id'=>sanitize_text_field((string)$order->get_meta('_smf_ad_id')),
            'source'=>sanitize_text_field((string)$order->get_meta('_smf_utm_source')),
            'medium'=>sanitize_text_field((string)$order->get_meta('_smf_utm_medium')),
            'session_key'=>sanitize_text_field((string)$order->get_meta('_smf_session_key')),
        );
    }

    public static function status_label($status) {
        $map=array('pending'=>'Pending','processing'=>'Processing','on-hold'=>'On hold','completed'=>'Completed','cancelled'=>'Cancelled','refunded'=>'Refunded','failed'=>'Failed','smf-confirmed'=>'Confirmed','smf-shipped'=>'Shipped','smf-delivered'=>'Delivered','smf-returned'=>'Returned');
        return isset($map[$status])?$map[$status]:ucwords(str_replace(array('-','_'),' ',(string)$status));
    }

    public static function get_recent_flow($limit=20) {
        global $wpdb; $limit=max(1,min(100,absint($limit)));
        $events=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}smf_order_events ORDER BY created_at DESC,id DESC LIMIT %d",$limit*5)); $orders=array();
        foreach($events as $event){$id=(int)$event->order_id;if(!$id||isset($orders[$id]))continue;$meta=$event->metadata?json_decode($event->metadata,true):array();$orders[$id]=array('order_id'=>$id,'status'=>self::status_label($event->new_status),'status_key'=>$event->new_status,'total'=>isset($meta['order_total'])?(float)$meta['order_total']:0,'currency'=>isset($meta['currency'])?sanitize_text_field($meta['currency']):'','campaign'=>!empty($meta['campaign'])?sanitize_text_field($meta['campaign']):'Direct / Unattributed','content'=>isset($meta['content'])?sanitize_text_field($meta['content']):'','ad_id'=>isset($meta['ad_id'])?sanitize_text_field($meta['ad_id']):'','updated_at'=>$event->created_at);if(count($orders)>=$limit)break;}
        return array_values($orders);
    }

    public static function get_flow_metrics() {
        global $wpdb; $table=$wpdb->prefix.'smf_order_events';
        $rows=$wpdb->get_results("SELECT order_id,new_status,metadata,created_at,id FROM $table ORDER BY order_id ASC,created_at ASC,id ASC"); $orders=array();
        foreach($rows as $row){$id=(int)$row->order_id;if(!$id)continue;if(!isset($orders[$id]))$orders[$id]=array('total'=>0,'status'=>'','campaign'=>'','adset_id'=>'','ad_id'=>'');$meta=$row->metadata?json_decode($row->metadata,true):array();if(isset($meta['order_total'])&&(float)$meta['order_total']>0)$orders[$id]['total']=(float)$meta['order_total'];foreach(array('campaign','adset_id','ad_id') as $f)if(!empty($meta[$f]))$orders[$id][$f]=sanitize_text_field($meta[$f]);$orders[$id]['status']=$row->new_status;}
        $metrics=array('orders'=>count($orders),'purchase_revenue'=>0,'confirmed_revenue'=>0,'shipped_revenue'=>0,'delivered_revenue'=>0,'cancelled_revenue'=>0,'returned_revenue'=>0,'delivered_orders'=>0,'cancelled_orders'=>0,'returned_orders'=>0);$campaigns=array();$ads=array();
        foreach($orders as $order){$total=(float)$order['total'];$status=(string)$order['status'];$metrics['purchase_revenue']+=$total;$campaign=$order['campaign']?:'Direct / Unattributed';if(!isset($campaigns[$campaign]))$campaigns[$campaign]=array('campaign'=>$campaign,'orders'=>0,'delivered'=>0,'cancelled'=>0,'returned'=>0,'revenue'=>0,'delivered_revenue'=>0);$campaigns[$campaign]['orders']++;$campaigns[$campaign]['revenue']+=$total;if(in_array($status,array('smf-confirmed','processing','on-hold','completed','smf-shipped','smf-delivered'),true))$metrics['confirmed_revenue']+=$total;if(in_array($status,array('smf-shipped','smf-delivered','completed'),true))$metrics['shipped_revenue']+=$total;if(in_array($status,array('smf-delivered','completed'),true)){$metrics['delivered_orders']++;$metrics['delivered_revenue']+=$total;$campaigns[$campaign]['delivered']++;$campaigns[$campaign]['delivered_revenue']+=$total;}if($status==='cancelled'){$metrics['cancelled_orders']++;$metrics['cancelled_revenue']+=$total;$campaigns[$campaign]['cancelled']++;}if($status==='smf-returned'){$metrics['returned_orders']++;$metrics['returned_revenue']+=$total;$campaigns[$campaign]['returned']++;}}
        $metrics['delivered_rate']=$metrics['orders']?round($metrics['delivered_orders']/$metrics['orders']*100,1):0;$metrics['realized_rate']=$metrics['purchase_revenue']?round($metrics['delivered_revenue']/$metrics['purchase_revenue']*100,1):0;$metrics['campaigns']=array_values($campaigns);usort($metrics['campaigns'],function($a,$b){return $b['delivered_revenue']<=>$a['delivered_revenue'];});return $metrics;
    }

    public static function order_attribution_box($order) {
        $keys=array('fbclid'=>'Facebook Click ID','utm_source'=>'Source','utm_medium'=>'Medium','utm_campaign'=>'Campaign','utm_content'=>'Content / Ad','utm_id'=>'UTM ID','campaign_id'=>'Campaign ID','adset_id'=>'Ad Set ID','ad_id'=>'Ad ID','session_key'=>'Session');echo '<div style="margin-top:20px;padding:12px;border:1px solid #ddd;background:#fff"><strong>Sync Meta Flow Attribution</strong><table style="width:100%;margin-top:8px">';foreach($keys as $key=>$label){$value=$order->get_meta('_smf_'.$key);if($value!=='')echo '<tr><td style="width:35%;padding:4px 0"><strong>'.esc_html($label).'</strong></td><td style="padding:4px 0">'.esc_html($value).'</td></tr>';}echo '</table></div>';
    }

    public static function log($order_id,$event_type,$old_status,$new_status,$source,$metadata=array()){global $wpdb;$wpdb->insert($wpdb->prefix.'smf_order_events',array('order_id'=>absint($order_id),'event_type'=>sanitize_key($event_type),'old_status'=>$old_status?sanitize_key($old_status):null,'new_status'=>$new_status?sanitize_key($new_status):null,'source'=>sanitize_key($source),'metadata'=>$metadata?wp_json_encode($metadata):null,'created_at'=>current_time('mysql')));}
}
