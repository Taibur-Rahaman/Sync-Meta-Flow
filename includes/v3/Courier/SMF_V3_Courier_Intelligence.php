<?php
defined('ABSPATH') || exit;

/**
 * V3 Advanced Courier Intelligence — advisory only; providers/adapters remain authoritative.
 * No automatic provider reassignment in beta. No undocumented provider APIs.
 */

final class SMF_V3_Shipment {
    private $data;
    public function __construct(array $raw) {
        $this->data = array(
            'order_id' => absint($raw['order_id'] ?? 0),
            'provider' => sanitize_key($raw['provider'] ?? 'generic'),
            'tracking' => sanitize_text_field($raw['tracking'] ?? ''),
            'status' => sanitize_key($raw['status'] ?? ''),
            'customer_risk' => sanitize_key($raw['customer_risk'] ?? 'unknown'),
            'opened_at' => sanitize_text_field($raw['opened_at'] ?? ''),
            'updated_at' => sanitize_text_field($raw['updated_at'] ?? ''),
        );
    }
    public function to_array() { return $this->data; }
    public function get($k, $d = null) { return array_key_exists($k, $this->data) ? $this->data[$k] : $d; }
}

final class SMF_V3_Delivery_Event {
    private $data;
    public function __construct(array $raw) {
        $this->data = array(
            'event_id' => sanitize_text_field($raw['event_id'] ?? ''),
            'provider' => sanitize_key($raw['provider'] ?? 'generic'),
            'order_id' => absint($raw['order_id'] ?? 0),
            'status' => sanitize_key($raw['status'] ?? ''),
            'result' => sanitize_key($raw['result'] ?? ''),
            'received_at' => sanitize_text_field($raw['received_at'] ?? ''),
            'processed_at' => sanitize_text_field($raw['processed_at'] ?? ''),
            'attempts' => max(0, (int) ($raw['attempts'] ?? 0)),
        );
        if ($this->data['event_id'] === '') {
            $this->data['event_id'] = 'ev-' . substr(hash('sha256', wp_json_encode($this->data)), 0, 16);
        }
    }
    public function to_array() { return $this->data; }
    public function get($k, $d = null) { return array_key_exists($k, $this->data) ? $this->data[$k] : $d; }
}

class SMF_V3_Courier_Timeline_Builder {
    public static function build(array $events) {
        $seen = array();
        $timeline = array();
        foreach ($events as $raw) {
            $ev = $raw instanceof SMF_V3_Delivery_Event ? $raw : new SMF_V3_Delivery_Event((array) $raw);
            $hash = hash('sha256', $ev->get('event_id') . '|' . $ev->get('status') . '|' . $ev->get('received_at'));
            if (isset($seen[$hash])) continue;
            $seen[$hash] = true;
            $timeline[] = $ev->to_array();
            if (count($timeline) >= 200) break;
        }
        usort($timeline, function ($a, $b) {
            return strcmp((string) $a['received_at'], (string) $b['received_at']);
        });
        return $timeline;
    }

    public static function outcome(array $timeline) {
        $outcome = 'open';
        foreach ($timeline as $ev) {
            $s = sanitize_key($ev['status'] ?? '');
            if (in_array($s, array('delivered','smf-delivered','completed'), true)) $outcome = 'delivered';
            elseif (in_array($s, array('returned','smf-returned','refunded'), true)) $outcome = 'returned';
            elseif (in_array($s, array('cancelled','canceled','failed'), true)) $outcome = 'cancelled';
            elseif (in_array($s, array('shipped','smf-shipped','in_transit'), true) && $outcome === 'open') $outcome = 'shipped';
        }
        return $outcome;
    }
}

class SMF_V3_Courier_Risk {
    public static function normalize_customer_label($label) {
        $label = strtoupper(sanitize_text_field($label));
        if (strpos($label, 'HIGH') !== false) return 'high';
        if (strpos($label, 'MEDIUM') !== false || strpos($label, 'MED') !== false) return 'medium';
        if (strpos($label, 'LOW') !== false) return 'low';
        return 'unknown';
    }

    public static function customer_from_stats(array $stats) {
        if (class_exists('SMF_Courier_Operations')) {
            $risk = SMF_Courier_Operations::risk_score($stats);
            return array(
                'score' => (int) $risk['score'],
                'category' => self::normalize_customer_label($risk['label']),
                'orders' => (int) ($stats['orders'] ?? 0),
                'delivered' => (int) ($stats['delivered'] ?? 0),
                'returned' => (int) ($stats['returned'] ?? 0),
                'cancelled' => (int) ($stats['cancelled'] ?? 0),
                'estimate' => true,
            );
        }
        $o = max(0, (int) ($stats['orders'] ?? 0));
        $d = max(0, (int) ($stats['delivered'] ?? 0));
        $r = max(0, (int) ($stats['returned'] ?? 0));
        $c = max(0, (int) ($stats['cancelled'] ?? 0));
        $score = 50;
        if ($o > 0) $score = (int) round(50 + ($d / $o) * 40 - ($r / $o) * 60 - ($c / $o) * 30);
        $score = max(0, min(100, $score));
        return array(
            'score' => $score,
            'category' => $score >= 75 ? 'low' : ($score >= 45 ? 'medium' : 'high'),
            'orders' => $o, 'delivered' => $d, 'returned' => $r, 'cancelled' => $c,
            'estimate' => true,
        );
    }

    /**
     * Deterministic shipment risk estimates from available signals — not predictive ML accuracy claims.
     */
    public static function shipment(array $shipment, array $provider = array(), array $customer = array()) {
        $late = 20; $ret = 20; $cancel = 20; $degrade = 20;
        $status = sanitize_key($shipment['status'] ?? '');
        $opened = strtotime((string) ($shipment['opened_at'] ?? ''));
        $sla_hours = max(1, (int) ($provider['delivery_sla_hours'] ?? 72));
        if ($opened && !in_array($status, array('delivered','smf-delivered','completed','returned','cancelled'), true)) {
            $age_h = max(0, (time() - $opened) / 3600);
            $late = (int) min(95, round(($age_h / $sla_hours) * 70));
        }
        $ret_rate = (float) ($provider['return_rate'] ?? 0);
        $cancel_rate = (float) ($provider['cancellation_rate'] ?? 0);
        $ret = (int) min(95, round($ret_rate));
        $cancel = (int) min(95, round($cancel_rate));
        $health = (int) ($provider['health_score'] ?? 100);
        $degrade = (int) max(0, min(95, 100 - $health));
        $cust = self::normalize_customer_label($customer['category'] ?? ($customer['label'] ?? 'unknown'));
        if ($cust === 'high') { $ret = min(95, $ret + 15); $cancel = min(95, $cancel + 10); }
        if ($cust === 'medium') { $ret = min(95, $ret + 5); }
        return array(
            'late_delivery_risk' => $late,
            'return_risk' => $ret,
            'cancellation_risk' => $cancel,
            'provider_degradation_risk' => $degrade,
            'overall' => (int) round(($late + $ret + $cancel + $degrade) / 4),
            'estimate' => true,
            'disclaimer' => 'Risk scores are deterministic operational heuristics from observed rates and age, not predictive guarantees.',
        );
    }
}

class SMF_V3_Courier_Provider_Intelligence {
    public static function from_rows(array $rows, $delivery_sla_hours = 72) {
        $out = array();
        foreach ($rows as $r) {
            $events = max(0, (int) ($r['events'] ?? 0));
            $processed = max(0, (int) ($r['processed'] ?? 0));
            $failed = max(0, (int) ($r['failed'] ?? 0));
            $retried = max(0, (int) ($r['retried'] ?? 0));
            $delivered = max(0, (int) ($r['delivered'] ?? 0));
            $returned = max(0, (int) ($r['returned'] ?? 0));
            $cancelled = max(0, (int) ($r['cancelled'] ?? 0));
            $orders = max(0, (int) ($r['delivery_orders'] ?? $r['orders'] ?? 0));
            $stale = max(0, (int) ($r['stale'] ?? 0));
            $success = $events ? ($processed / $events) * 100 : 100;
            $failure = $events ? ($failed / $events) * 100 : 0;
            $retry = $events ? ($retried / $events) * 100 : 0;
            $stale_rate = $events ? ($stale / $events) * 100 : 0;
            $delivery_rate = $orders ? ($delivered / $orders) * 100 : (float) ($r['success_rate'] ?? 100);
            $return_rate = $orders ? ($returned / $orders) * 100 : 0;
            $cancel_rate = $orders ? ($cancelled / $orders) * 100 : 0;
            $health = isset($r['health_score']) ? (int) $r['health_score'] : (int) round(max(0, min(100, $success * 0.5 + (100 - $failure) * 0.3 + (100 - $retry) * 0.2)));
            $sla_breaches = (int) ($r['delivery_sla_breaches'] ?? $r['sla_breaches'] ?? 0);
            $sla_samples = max(1, (int) ($r['delivery_samples'] ?? $delivered ?: 1));
            $sla_compliance = max(0, 100 - (($sla_breaches / $sla_samples) * 100));
            $rec = self::recommend($health, $failure, $return_rate);
            $out[] = array(
                'provider' => sanitize_key($r['provider'] ?? 'generic'),
                'success_rate' => round($success, 1),
                'delivery_rate' => round($delivery_rate, 1),
                'return_rate' => round($return_rate, 1),
                'cancellation_rate' => round($cancel_rate, 1),
                'average_processing_seconds' => round((float) ($r['avg_processing_seconds'] ?? 0), 1),
                'average_delivery_hours' => round((float) ($r['avg_delivery_hours'] ?? 0), 1),
                'retry_rate' => round($retry, 1),
                'stale_event_rate' => round($stale_rate, 1),
                'failure_rate' => round($failure, 1),
                'sla_compliance' => round($sla_compliance, 1),
                'delivery_sla_hours' => (int) $delivery_sla_hours,
                'health_score' => max(0, min(100, $health)),
                'recommendation' => $rec,
                'estimate' => true,
            );
        }
        usort($out, function ($a, $b) { return $b['health_score'] <=> $a['health_score']; });
        return $out;
    }

    public static function recommend($health, $failure_rate, $return_rate) {
        $health = (int) $health;
        if ($health >= 80 && $failure_rate < 5 && $return_rate < 15) return 'PROVIDER_HEALTHY';
        if ($health >= 60) return 'PROVIDER_WATCH';
        if ($health < 40 || $failure_rate >= 25) return 'PROVIDER_DEGRADED';
        if ($return_rate >= 25) return 'REVIEW_PROVIDER';
        return 'REVIEW_SHIPMENT';
    }

    public static function from_v2($days = 30) {
        $days = max(1, min(90, absint($days)));
        if (!class_exists('SMF_Courier_Operations') || !method_exists('SMF_Courier_Operations', 'provider_intelligence')) {
            return array();
        }
        if (!class_exists('SMF_Courier_Timeline')) {
            return array();
        }
        try {
            $rows = SMF_Courier_Operations::provider_intelligence($days);
        } catch (Throwable $e) {
            return array();
        }
        $sla = max(1, min(720, absint(get_option('smf_courier_delivery_sla', 72))));
        return self::from_rows(is_array($rows) ? $rows : array(), $sla);
    }
}

class SMF_V3_Courier_Intelligence_Engine {
    public function journey(array $shipment_raw, array $events, array $customer_stats = array(), array $provider_row = array()) {
        $shipment = new SMF_V3_Shipment($shipment_raw);
        $timeline = SMF_V3_Courier_Timeline_Builder::build($events);
        $outcome = SMF_V3_Courier_Timeline_Builder::outcome($timeline);
        $customer = SMF_V3_Courier_Risk::customer_from_stats($customer_stats);
        $provider = $provider_row ?: array('health_score' => 100, 'return_rate' => 0, 'cancellation_rate' => 0, 'delivery_sla_hours' => 72);
        $risk = SMF_V3_Courier_Risk::shipment($shipment->to_array(), $provider, $customer);
        $rec = SMF_V3_Courier_Provider_Intelligence::recommend(
            (int) ($provider['health_score'] ?? 100),
            (float) ($provider['failure_rate'] ?? 0),
            (float) ($provider['return_rate'] ?? 0)
        );
        return array(
            'order_id' => $shipment->get('order_id'),
            'shipment' => $shipment->to_array(),
            'provider' => sanitize_key($provider['provider'] ?? $shipment->get('provider')),
            'events' => $timeline,
            'timeline' => $timeline,
            'outcome' => $outcome,
            'customer_risk' => $customer,
            'shipment_risk' => $risk,
            'recommendation' => $rec,
            'optimization' => $this->optimization_advice(array($provider)),
            'estimate' => true,
        );
    }

    public function optimization_advice(array $providers) {
        if (!$providers) {
            return array(
                'preferred_provider' => '',
                'risk_warning' => 'Insufficient historical data for provider preference.',
                'sla_warning' => '',
                'return_warning' => '',
                'auto_assign' => false,
            );
        }
        $best = $providers[0];
        foreach ($providers as $p) {
            if (($p['health_score'] ?? 0) > ($best['health_score'] ?? 0)) $best = $p;
        }
        $sla_warning = (($best['sla_compliance'] ?? 100) < 80) ? 'Preferred provider SLA compliance is below 80% in the observed window.' : '';
        $return_warning = (($best['return_rate'] ?? 0) >= 20) ? 'Preferred provider return rate is elevated.' : '';
        $risk_warning = (($best['recommendation'] ?? '') === 'PROVIDER_DEGRADED') ? 'Top observed provider appears degraded.' : '';
        return array(
            'preferred_provider' => sanitize_key($best['provider'] ?? ''),
            'risk_warning' => $risk_warning,
            'sla_warning' => $sla_warning,
            'return_warning' => $return_warning,
            'auto_assign' => false,
            'disclaimer' => 'Advisory only. Beta does not automatically change provider assignment.',
        );
    }

    public function provider_report($days = 30) {
        $providers = SMF_V3_Courier_Provider_Intelligence::from_v2($days);
        return array(
            'days' => max(1, min(90, absint($days))),
            'providers' => $providers,
            'optimization' => $this->optimization_advice($providers),
            'estimate' => true,
            'reuses' => array('retry' => 'SMF_Courier_Recovery', 'timeline' => 'SMF_Courier_Timeline', 'state' => 'SMF_Courier_State', 'health' => 'SMF_Courier_Operations'),
        );
    }
}

class SMF_V3_Courier_Intelligence_Service {
    public static function init() {
        if (!class_exists('SMF_V3_Feature_Flag') || !SMF_V3_Feature_Flag::enabled()) return;
        if (get_option('smf_v3_courier_intelligence', 'no') !== 'yes') return;
        add_action('admin_menu', array(__CLASS__, 'menu'), 36);
    }

    public static function menu() {
        add_submenu_page('sync-meta-flow', 'Courier Intelligence', 'Courier Intelligence', 'manage_woocommerce', 'smf-v3-courier-intelligence', array(__CLASS__, 'page'));
    }

    public static function page() {
        if (!current_user_can('manage_woocommerce')) return;
        $engine = new SMF_V3_Courier_Intelligence_Engine();
        $report = $engine->provider_report(30);
        echo '<div class="wrap smf-wrap"><div class="smf-header"><div><h1>Courier Intelligence</h1><p>Provider performance, risk heuristics and advisory optimization. No automatic reassignment.</p></div></div>';
        echo '<div class="smf-status is-warning"><span class="smf-status-dot"></span><div><strong>Advisory estimates</strong><small>' . esc_html($report['optimization']['disclaimer'] ?? '') . '</small></div></div>';
        echo '<div class="smf-panel"><h2>Providers · 30 days</h2><div class="smf-table-wrap"><table class="smf-table"><thead><tr><th>Provider</th><th>Health</th><th>Delivery</th><th>Return</th><th>SLA</th><th>Recommendation</th></tr></thead><tbody>';
        if (empty($report['providers'])) {
            echo '<tr><td colspan="6">No provider intelligence available in this window.</td></tr>';
        } else {
            foreach ($report['providers'] as $p) {
                echo '<tr><td>' . esc_html($p['provider']) . '</td><td>' . esc_html((string) $p['health_score']) . '</td><td>' . esc_html((string) $p['delivery_rate']) . '%</td><td>' . esc_html((string) $p['return_rate']) . '%</td><td>' . esc_html((string) $p['sla_compliance']) . '%</td><td>' . esc_html($p['recommendation']) . '</td></tr>';
            }
        }
        echo '</tbody></table></div></div></div>';
    }
}
