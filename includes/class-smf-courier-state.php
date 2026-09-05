<?php
defined('ABSPATH') || exit;

class SMF_Courier_State {
    const ROUTE = '/sync-meta-flow/v1/courier/webhook';

    public static function init() {
        add_filter('rest_pre_dispatch', array(__CLASS__, 'guard_webhook_state'), 20, 3);
    }

    private static function normalize($status) {
        $status = strtolower(sanitize_key((string) $status));
        $map = array(
            'confirmed' => 'confirmed', 'confirm' => 'confirmed', 'pending' => 'confirmed', 'in_review' => 'confirmed', 'approved' => 'confirmed',
            'shipped' => 'shipped', 'in_transit' => 'shipped', 'in-transit' => 'shipped', 'picked' => 'shipped', 'picked_up' => 'shipped', 'out_for_delivery' => 'shipped',
            'delivered' => 'delivered', 'partial_delivered' => 'delivered', 'completed' => 'delivered', 'complete' => 'delivered',
            'returned' => 'returned', 'return' => 'returned', 'refunded' => 'returned',
            'cancelled' => 'cancelled', 'canceled' => 'cancelled', 'cancelled_approval_pending' => 'cancelled', 'failed' => 'cancelled'
        );
        return isset($map[$status]) ? $map[$status] : '';
    }

    private static function order_id($data) {
        $id = !empty($data['order_id']) ? absint($data['order_id']) : 0;
        if ($id) return $id;
        $invoice = '';
        foreach (array('order_number', 'invoice', 'merchant_invoice_id') as $key) {
            if (isset($data[$key]) && is_scalar($data[$key]) && trim((string) $data[$key]) !== '') {
                $invoice = sanitize_text_field((string) $data[$key]);
                break;
            }
        }
        if ($invoice === '') return 0;
        $orders = wc_get_orders(array('limit' => 1, 'return' => 'ids', 'meta_key' => '_smf_courier_invoice', 'meta_value' => $invoice));
        if (!empty($orders)) return absint($orders[0]);
        return is_numeric($invoice) ? absint($invoice) : 0;
    }

    private static function payload_value($data, $keys) {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key]) && trim((string) $data[$key]) !== '') return sanitize_text_field((string) $data[$key]);
        }
        return '';
    }

    private static function identity_matches($order, $data) {
        $checks = array(
            array('_smf_courier_invoice', array('order_number', 'invoice', 'merchant_invoice_id')),
            array('_smf_courier_consignment_id', array('consignment_id', 'consignment', 'consignment_id_number')),
            array('_smf_tracking_number', array('tracking_number', 'tracking_code')),
            array('_smf_courier_tracking_code', array('tracking_number', 'tracking_code')),
        );
        foreach ($checks as $check) {
            $stored = trim((string) $order->get_meta($check[0]));
            $incoming = self::payload_value($data, $check[1]);
            if ($stored !== '' && $incoming !== '' && !hash_equals(strtolower($stored), strtolower($incoming))) return false;
        }
        return true;
    }

    private static function transition_allowed($current, $target) {
        if ($current === $target || $current === '') return true;
        $allowed = array(
            'confirmed' => array('shipped', 'delivered', 'returned', 'cancelled'),
            'shipped' => array('delivered', 'returned', 'cancelled'),
            'delivered' => array('returned'),
            'returned' => array(),
            'cancelled' => array(),
        );
        return isset($allowed[$current]) && in_array($target, $allowed[$current], true);
    }

    public static function guard_webhook_state($result, $server, $request) {
        if ((string) $request->get_route() !== self::ROUTE || strtoupper($request->get_method()) !== 'POST') return $result;
        // Preserve an earlier REST response, notably timeline dedupe/lock responses.
        if ($result instanceof WP_REST_Response || is_wp_error($result)) return $result;
        // This guard must never make decisions about an unauthenticated webhook.
        if (class_exists('SMF_Courier_Timeline') && !SMF_Courier_Timeline::webhook_signature_valid($request)) return $result;
        $data = json_decode($request->get_body(), true);
        if (!is_array($data)) return $result;
        $target = self::normalize(isset($data['status']) ? $data['status'] : (isset($data['delivery_status']) ? $data['delivery_status'] : ''));
        if ($target === '') return $result;
        $order_id = self::order_id($data);
        if (!$order_id) return $result;
        $order = wc_get_order($order_id);
        if (!$order) return $result;
        if (!self::identity_matches($order, $data)) {
            if (class_exists('SMF_Courier_Timeline')) SMF_Courier_Timeline::mark_webhook_ignored($request);
            return new WP_REST_Response(array(
                'ok' => true,
                'ignored' => true,
                'reason' => 'courier_identity_mismatch',
                'order_id' => $order_id,
            ), 200);
        }
        $current = self::normalize($order->get_status());
        if (self::transition_allowed($current, $target)) return $result;
        if (class_exists('SMF_Courier_Timeline')) SMF_Courier_Timeline::mark_webhook_ignored($request);
        return new WP_REST_Response(array(
            'ok' => true,
            'ignored' => true,
            'reason' => 'stale_or_invalid_transition',
            'order_id' => $order_id,
            'current_status' => $current ?: $order->get_status(),
            'requested_status' => $target,
        ), 200);
    }
}
