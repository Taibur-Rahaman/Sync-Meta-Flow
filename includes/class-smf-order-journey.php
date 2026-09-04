<?php
defined('ABSPATH') || exit;

class SMF_Order_Journey {
    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'meta_box'));
    }

    public static function meta_box() {
        add_meta_box('smf-order-journey', 'Sync Meta Flow Journey', array(__CLASS__, 'render'), 'shop_order', 'normal', 'high');
    }

    public static function render($post) {
        $order = wc_get_order($post->ID);
        if (!$order) return;
        global $wpdb;
        $events = $wpdb->get_results($wpdb->prepare("SELECT event_type,old_status,new_status,source,metadata,created_at FROM {$wpdb->prefix}smf_order_events WHERE order_id=%d ORDER BY created_at ASC,id ASC", $order->get_id()));
        $first = (string) $order->get_meta('_smf_first_utm_campaign');
        $last = (string) $order->get_meta('_smf_last_utm_campaign');
        $source = (string) $order->get_meta('_smf_last_utm_source');
        $provider = (string) $order->get_meta('_smf_courier_provider');
        $tracking = (string) $order->get_meta('_smf_tracking_number');
        echo '<div style="padding:4px 0">';
        echo '<p><strong>First-touch:</strong> '.esc_html($first ?: 'Direct / Unattributed').'<br><strong>Last-touch:</strong> '.esc_html($last ?: 'Direct / Unattributed').($source ? '<br><strong>Source:</strong> '.esc_html($source) : '').'</p>';
        if ($provider || $tracking) echo '<p><strong>Courier:</strong> '.esc_html($provider ?: '—').($tracking ? ' · '.esc_html($tracking) : '').'</p>';
        if (!$events) { echo '<p>No Sync Meta Flow events recorded for this order.</p></div>'; return; }
        echo '<ol style="margin-left:20px">';
        foreach ($events as $event) {
            $label = $event->event_type === 'purchase' ? 'Purchase' : SMF_Order_Events::status_label($event->new_status ?: $event->event_type);
            $meta = $event->metadata ? json_decode($event->metadata, true) : array();
            $note = '';
            if (is_array($meta) && !empty($meta['last_campaign'])) $note = ' · '.sanitize_text_field($meta['last_campaign']);
            echo '<li style="margin:7px 0"><strong>'.esc_html($label).'</strong>'.esc_html($note).' <span class="description">'.esc_html(mysql2date(get_option('date_format').' '.get_option('time_format'), $event->created_at)).'</span></li>';
        }
        echo '</ol></div>';
    }
}
