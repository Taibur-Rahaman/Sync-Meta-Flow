<?php
defined('ABSPATH') || exit;

class SMF_Privacy {
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'policy'));
        add_filter('wp_privacy_personal_data_exporters', array(__CLASS__, 'exporter'));
        add_filter('wp_privacy_personal_data_erasers', array(__CLASS__, 'eraser'));
    }

    public static function policy() {
        if (!function_exists('wp_add_privacy_policy_content')) return;
        wp_add_privacy_policy_content('Sync Meta Flow', '<p>Sync Meta Flow may store advertising attribution identifiers, browser/session identifiers, tracking events, WooCommerce order-flow events and courier event payloads. Depending on configuration, hashed customer email/phone and Meta browser identifiers may be sent to Meta for Conversions API events. Courier integrations may transmit order and delivery information to the selected provider. Site owners are responsible for consent, retention and third-party disclosures.</p>');
    }

    public static function exporter($exporters) {
        $exporters['sync-meta-flow'] = array(
            'exporter_friendly_name' => 'Sync Meta Flow',
            'callback' => array(__CLASS__, 'export'),
        );
        return $exporters;
    }

    public static function eraser($erasers) {
        $erasers['sync-meta-flow'] = array(
            'eraser_friendly_name' => 'Sync Meta Flow',
            'callback' => array(__CLASS__, 'erase'),
        );
        return $erasers;
    }

    private static function order_ids($email) {
        if (!function_exists('wc_get_orders')) return array();
        $orders = wc_get_orders(array('limit' => -1, 'return' => 'ids', 'billing_email' => sanitize_email($email)));
        return array_map('absint', (array) $orders);
    }

    public static function export($email, $page = 1) {
        global $wpdb;
        $ids = self::order_ids($email);
        $data = array();
        $event_table = $wpdb->prefix . 'smf_order_events';
        $queue_table = $wpdb->prefix . 'smf_capi_queue';
        $courier_table = $wpdb->prefix . 'smf_courier_events';
        $session_table = $wpdb->prefix . 'smf_tracking_sessions';
        $tracking_table = $wpdb->prefix . 'smf_tracking_events';
        foreach ($ids as $order_id) {
            $events = $wpdb->get_results($wpdb->prepare("SELECT event_type,old_status,new_status,source,metadata,created_at FROM $event_table WHERE order_id=%d ORDER BY id ASC", $order_id), ARRAY_A);
            $queue = $wpdb->get_results($wpdb->prepare("SELECT event_name,event_id,attempts,status,last_error,created_at,sent_at FROM $queue_table WHERE order_id=%d ORDER BY id ASC", $order_id), ARRAY_A);
            $courier = $wpdb->get_results($wpdb->prepare("SELECT provider,event_id,status,result,received_at,processed_at FROM $courier_table WHERE order_id=%d ORDER BY id ASC", $order_id), ARRAY_A);
            $order = wc_get_order($order_id);
            $session_key = $order ? sanitize_text_field((string) $order->get_meta('_smf_session_key')) : '';
            $sessions = $session_key ? $wpdb->get_results($wpdb->prepare("SELECT session_key,fbclid,fbp,fbc,utm_source,utm_medium,utm_campaign,utm_content,utm_term,utm_id,campaign_id,adset_id,ad_id,first_touch,last_touch,landing_url,first_seen,last_seen FROM $session_table WHERE session_key=%s", $session_key), ARRAY_A) : array();
            $tracking = $session_key ? $wpdb->get_results($wpdb->prepare("SELECT session_key,event_name,event_id,page_url,payload,created_at FROM $tracking_table WHERE session_key=%s ORDER BY id ASC", $session_key), ARRAY_A) : array();
            if ($events || $queue || $courier || $sessions || $tracking) {
                $data[] = array('name' => 'Sync Meta Flow order data', 'value' => wp_json_encode(array('order_id' => $order_id, 'order_events' => $events, 'capi_queue' => $queue, 'courier_events' => $courier, 'tracking_sessions' => $sessions, 'tracking_events' => $tracking)), 'extra_data' => array());
            }
        }
        return array('data' => $data, 'done' => true);
    }

    public static function erase($email, $page = 1) {
        global $wpdb;
        $ids = self::order_ids($email);
        $deleted = false;
        foreach ($ids as $order_id) {
            $order = wc_get_order($order_id);
            $session_key = $order ? sanitize_text_field((string) $order->get_meta('_smf_session_key')) : '';
            foreach (array('smf_order_events', 'smf_capi_queue', 'smf_courier_events') as $suffix) {
                $wpdb->delete($wpdb->prefix . $suffix, array('order_id' => $order_id), array('%d'));
            }
            if ($session_key && preg_match('/^[a-f0-9-]{36}$/', $session_key)) {
                $wpdb->delete($wpdb->prefix . 'smf_tracking_events', array('session_key' => $session_key), array('%s'));
                $wpdb->delete($wpdb->prefix . 'smf_tracking_sessions', array('session_key' => $session_key), array('%s'));
            }
            if ($order) {
                foreach (array('_smf_fbclid','_smf_fbp','_smf_fbc','_smf_utm_source','_smf_utm_medium','_smf_utm_campaign','_smf_utm_content','_smf_utm_term','_smf_utm_id','_smf_campaign_id','_smf_adset_id','_smf_ad_id','_smf_first_fbclid','_smf_first_utm_source','_smf_first_utm_medium','_smf_first_utm_campaign','_smf_first_utm_content','_smf_first_utm_term','_smf_first_utm_id','_smf_first_campaign_id','_smf_first_adset_id','_smf_first_ad_id','_smf_last_fbclid','_smf_last_utm_source','_smf_last_utm_medium','_smf_last_utm_campaign','_smf_last_utm_content','_smf_last_utm_term','_smf_last_utm_id','_smf_last_campaign_id','_smf_last_adset_id','_smf_last_ad_id','_smf_session_key','_smf_purchase_event_id') as $key) $order->delete_meta_data($key);
                $order->save();
            }
            $deleted = true;
        }
        return array('items_removed' => $deleted, 'items_retained' => false, 'messages' => array(), 'done' => true);
    }
}
