<?php
defined('ABSPATH') || exit;

/**
 * V3 Advanced Attribution — normalized entities, multi-model allocation, quality scoring.
 * Estimates are labeled; not accounting truth. Consumes V2 profitability via adapters.
 */

final class SMF_V3_Touchpoint {
    private $data;
    public function __construct(array $raw) {
        $this->data = array(
            'id' => sanitize_text_field($raw['id'] ?? ''),
            'session_key' => preg_match('/^[a-f0-9-]{36}$/', (string) ($raw['session_key'] ?? '')) ? (string) $raw['session_key'] : '',
            'source' => sanitize_text_field($raw['source'] ?? ($raw['utm_source'] ?? '')),
            'medium' => sanitize_text_field($raw['medium'] ?? ($raw['utm_medium'] ?? '')),
            'campaign' => sanitize_text_field($raw['campaign'] ?? ($raw['campaign_name'] ?? ($raw['utm_campaign'] ?? ''))),
            'campaign_id' => sanitize_text_field($raw['campaign_id'] ?? ''),
            'adset_id' => sanitize_text_field($raw['adset_id'] ?? ''),
            'ad_id' => sanitize_text_field($raw['ad_id'] ?? ''),
            'click_id' => sanitize_text_field($raw['click_id'] ?? ($raw['fbclid'] ?? '')),
            'timestamp' => sanitize_text_field($raw['timestamp'] ?? ''),
            'utm_campaign' => sanitize_text_field($raw['utm_campaign'] ?? ''),
        );
        if ($this->data['id'] === '') {
            $this->data['id'] = 'tp-' . substr(hash('sha256', wp_json_encode($this->data)), 0, 16);
        }
    }
    public function to_array() { return $this->data; }
    public function get($k, $d = '') { return array_key_exists($k, $this->data) ? $this->data[$k] : $d; }
    public function channel_key() {
        foreach (array('ad_id','adset_id','campaign_id','campaign','utm_campaign') as $k) {
            if ($this->data[$k] !== '') return $this->data[$k];
        }
        if ($this->data['source'] !== '' || $this->data['medium'] !== '') {
            return trim($this->data['source'] . '/' . $this->data['medium'], '/');
        }
        return 'Direct / Unattributed';
    }
}

final class SMF_V3_Conversion {
    private $order_id; private $value; private $currency; private $at; private $session_key;
    public function __construct($order_id, $value, $currency, $at, $session_key = '') {
        $this->order_id = absint($order_id);
        $this->value = max(0, (float) $value);
        $this->currency = strtoupper(sanitize_text_field($currency));
        $this->at = sanitize_text_field($at);
        $this->session_key = preg_match('/^[a-f0-9-]{36}$/', (string) $session_key) ? (string) $session_key : '';
    }
    public function order_id() { return $this->order_id; }
    public function value() { return $this->value; }
    public function currency() { return $this->currency; }
    public function at() { return $this->at; }
    public function session_key() { return $this->session_key; }
}

class SMF_V3_Attribution_Normalizer {
    public static function utm(array $raw) {
        $out = array();
        foreach (array('utm_source'=>'source','utm_medium'=>'medium','utm_campaign'=>'utm_campaign','utm_content'=>'utm_content','utm_term'=>'utm_term','utm_id'=>'utm_id') as $from => $to) {
            if (!empty($raw[$from]) && is_scalar($raw[$from])) $out[$to] = sanitize_text_field((string) $raw[$from]);
        }
        foreach (array('campaign_id','adset_id','ad_id','campaign_name','fbclid','fbp','fbc','session_key','timestamp') as $k) {
            if (!empty($raw[$k]) && is_scalar($raw[$k])) $out[$k] = sanitize_text_field((string) $raw[$k]);
        }
        unset($out['fbp'], $out['fbc']); // keep click id only via fbclid→click_id in Touchpoint
        if (!empty($raw['fbclid'])) $out['click_id'] = sanitize_text_field((string) $raw['fbclid']);
        return $out;
    }

    public static function touchpoints(array $rows) {
        $seen = array();
        $out = array();
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $tp = new SMF_V3_Touchpoint(self::utm($row));
            $dedupe = hash('sha256', $tp->channel_key() . '|' . $tp->get('timestamp') . '|' . $tp->get('session_key'));
            if (isset($seen[$dedupe])) continue;
            $seen[$dedupe] = true;
            $out[] = $tp;
            if (count($out) >= 50) break; // bounded
        }
        return $out;
    }

    public static function from_first_last(array $first, array $last, $session_key = '') {
        $rows = array();
        if ($first) { $first['session_key'] = $session_key; $rows[] = $first; }
        if ($last) { $last['session_key'] = $session_key; $rows[] = $last; }
        return self::touchpoints($rows);
    }
}

class SMF_V3_Attribution_Quality {
    /**
     * Deterministic quality score 0–100 from observable completeness dimensions.
     */
    public static function score(array $touchpoints, ?SMF_V3_Conversion $conversion = null, array $stats = array()) {
        $tps = array();
        foreach ($touchpoints as $tp) $tps[] = $tp instanceof SMF_V3_Touchpoint ? $tp->to_array() : (array) $tp;
        $n = count($tps);
        $identity = 0; $timestamps = 0; $source = 0; $campaign = 0;
        foreach ($tps as $t) {
            if (!empty($t['session_key']) || !empty($t['click_id'])) $identity++;
            if (!empty($t['timestamp']) && strtotime($t['timestamp'])) $timestamps++;
            if (!empty($t['source']) || !empty($t['medium'])) $source++;
            if (!empty($t['campaign_id']) || !empty($t['campaign']) || !empty($t['utm_campaign'])) $campaign++;
        }
        $identity_score = $n ? ($identity / $n) * 100 : 0;
        $timestamp_score = $n ? ($timestamps / $n) * 100 : 0;
        $source_score = $n ? ($source / $n) * 100 : 0;
        $campaign_score = $n ? ($campaign / $n) * 100 : 0;
        $linkage = ($conversion && $conversion->order_id() > 0 && ($conversion->session_key() !== '' || $n > 0)) ? 100 : ($conversion ? 40 : 0);
        $dup_rate = isset($stats['duplicate_rate']) ? max(0, min(100, (float) $stats['duplicate_rate'])) : 0;
        $unattr = isset($stats['unattributed_rate']) ? max(0, min(100, (float) $stats['unattributed_rate'])) : ($n === 0 ? 100 : 0);
        $duplicate_score = max(0, 100 - $dup_rate);
        $attribution_score = max(0, 100 - $unattr);
        $score = (int) round(
            $identity_score * 0.15 +
            $timestamp_score * 0.10 +
            $source_score * 0.15 +
            $campaign_score * 0.20 +
            $linkage * 0.20 +
            $duplicate_score * 0.10 +
            $attribution_score * 0.10
        );
        return array(
            'score' => max(0, min(100, $score)),
            'label' => $score >= 80 ? 'high' : ($score >= 50 ? 'medium' : 'low'),
            'estimate' => true,
            'dimensions' => array(
                'identity_completeness' => round($identity_score, 1),
                'timestamp_validity' => round($timestamp_score, 1),
                'source_completeness' => round($source_score, 1),
                'campaign_completeness' => round($campaign_score, 1),
                'conversion_linkage' => (float) $linkage,
                'duplicate_score' => round($duplicate_score, 1),
                'attribution_coverage' => round($attribution_score, 1),
            ),
        );
    }
}

class SMF_V3_Attribution_Allocator {
    public static function allocate(array $touchpoints, $value, $model, array $weights = array(), $conversion_at = '') {
        $model = class_exists('SMF_Attribution_Model') ? SMF_Attribution_Model::normalize_model($model) : sanitize_key($model);
        $value = max(0, (float) $value);
        $tps = array();
        foreach ($touchpoints as $tp) {
            $tps[] = $tp instanceof SMF_V3_Touchpoint ? $tp : new SMF_V3_Touchpoint((array) $tp);
        }
        if (!$tps) return array('Direct / Unattributed' => $value);

        if (count($tps) === 1 || in_array($model, array('first_touch','last_touch','first_last','assisted','position_based','time_decay'), true)) {
            $first = $tps[0]->to_array();
            $last = $tps[count($tps) - 1]->to_array();
            if ($conversion_at !== '') $weights['conversion_at'] = $conversion_at;
            if (class_exists('SMF_Attribution_Model')) {
                return SMF_Attribution_Model::allocation($first, $last, $value, $model, $weights);
            }
        }

        // Multi-touch position/time when >2 touchpoints exist
        if ($model === 'position_based' && count($tps) > 2) {
            return self::position_multi($tps, $value, $weights);
        }
        if ($model === 'time_decay' && count($tps) > 2) {
            return self::decay_multi($tps, $value, $weights, $conversion_at);
        }
        $last = $tps[count($tps) - 1];
        return array($last->channel_key() => $value);
    }

    private static function position_multi(array $tps, $value, array $weights) {
        $wf = isset($weights['first']) ? (float) $weights['first'] : 0.4;
        $wl = isset($weights['last']) ? (float) $weights['last'] : 0.4;
        $wm = isset($weights['middle']) ? (float) $weights['middle'] : 0.2;
        $n = count($tps);
        $mid_n = max(1, $n - 2);
        $out = array();
        foreach ($tps as $i => $tp) {
            $key = $tp->channel_key();
            if ($i === 0) $w = $wf;
            elseif ($i === $n - 1) $w = $wl;
            else $w = $wm / $mid_n;
            $out[$key] = ($out[$key] ?? 0) + $value * $w;
        }
        return $out;
    }

    private static function decay_multi(array $tps, $value, array $weights, $conversion_at) {
        $half = isset($weights['half_life_hours']) ? max(1, (float) $weights['half_life_hours']) : 24;
        $tc = $conversion_at !== '' ? strtotime($conversion_at) : time();
        $raw = array();
        $sum = 0.0;
        foreach ($tps as $tp) {
            $ts = strtotime($tp->get('timestamp'));
            $hours = $ts && $tc >= $ts ? ($tc - $ts) / 3600 : 48;
            $w = pow(0.5, $hours / $half);
            $raw[] = array('key' => $tp->channel_key(), 'w' => $w);
            $sum += $w;
        }
        $out = array();
        if ($sum <= 0) {
            $last = $tps[count($tps) - 1];
            return array($last->channel_key() => $value);
        }
        foreach ($raw as $row) {
            $out[$row['key']] = ($out[$row['key']] ?? 0) + $value * ($row['w'] / $sum);
        }
        return $out;
    }
}

class SMF_V3_Attribution_Pipeline {
    public function run(array $raw_touches, SMF_V3_Conversion $conversion, $model = 'last_touch', array $weights = array()) {
        $touchpoints = SMF_V3_Attribution_Normalizer::touchpoints($raw_touches);
        $dup_in = count($raw_touches);
        $dup_rate = $dup_in > 0 ? max(0, (($dup_in - count($touchpoints)) / $dup_in) * 100) : 0;
        $unattr = 0;
        foreach ($touchpoints as $tp) {
            if ($tp->channel_key() === 'Direct / Unattributed') $unattr++;
        }
        $unattr_rate = $touchpoints ? ($unattr / count($touchpoints)) * 100 : 100;
        $allocation = SMF_V3_Attribution_Allocator::allocate($touchpoints, $conversion->value(), $model, $weights, $conversion->at());
        $quality = SMF_V3_Attribution_Quality::score($touchpoints, $conversion, array(
            'duplicate_rate' => $dup_rate,
            'unattributed_rate' => $unattr_rate,
        ));
        return array(
            'model' => class_exists('SMF_Attribution_Model') ? SMF_Attribution_Model::normalize_model($model) : sanitize_key($model),
            'estimate' => true,
            'disclaimer' => 'Attribution credit is an estimate for decision support, not accounting truth.',
            'touchpoints' => array_map(function ($t) { return $t->to_array(); }, $touchpoints),
            'conversion' => array(
                'order_id' => $conversion->order_id(),
                'value' => $conversion->value(),
                'currency' => $conversion->currency(),
                'at' => $conversion->at(),
                'session_key' => $conversion->session_key(),
            ),
            'allocation' => $allocation,
            'quality' => $quality,
        );
    }

    public function compare(array $raw_touches, SMF_V3_Conversion $conversion, ?array $models = null, array $weights = array()) {
        $models = $models ?: (class_exists('SMF_Attribution_Model') ? SMF_Attribution_Model::models() : array('last_touch'));
        $out = array();
        foreach ($models as $model) {
            $out[$model] = $this->run($raw_touches, $conversion, $model, $weights);
        }
        return array('estimate' => true, 'comparisons' => $out);
    }
}

class SMF_V3_Attribution_Intelligence_Service {
    private $pipeline;
    public function __construct(?SMF_V3_Attribution_Pipeline $pipeline = null) {
        $this->pipeline = $pipeline ?: new SMF_V3_Attribution_Pipeline();
    }

    public function campaign_intelligence($days = 30, $model = 'last_touch', $currency = 'BDT') {
        $days = max(1, min(90, absint($days)));
        $model = class_exists('SMF_Attribution_Model') ? SMF_Attribution_Model::normalize_model($model) : sanitize_key($model);
        $currency = strtoupper(sanitize_text_field($currency));
        $profit = class_exists('SMF_Profitability') ? SMF_Profitability::report($days, $currency, $model) : array('summary' => array(), 'campaigns' => array());
        $campaigns = array();
        foreach ((array) ($profit['campaigns'] ?? array()) as $c) {
            $campaigns[] = array(
                'campaign' => sanitize_text_field($c['campaign'] ?? ''),
                'spend' => (float) ($c['spend'] ?? 0),
                'revenue' => (float) ($c['delivered_revenue'] ?? $c['revenue'] ?? 0),
                'conversions' => (int) ($c['delivered_orders'] ?? $c['orders'] ?? 0),
                'roas' => (float) ($c['roas'] ?? 0),
                'contribution' => (float) ($c['profit'] ?? 0),
                'attribution_confidence' => null,
                'estimate' => true,
            );
        }
        return array(
            'days' => $days,
            'model' => $model,
            'currency' => $currency,
            'estimate' => true,
            'disclaimer' => 'Campaign intelligence combines attribution estimates with contribution-profit assumptions.',
            'summary' => array(
                'spend' => (float) ($profit['summary']['spend'] ?? 0),
                'revenue' => (float) ($profit['summary']['delivered_revenue'] ?? 0),
                'roas' => (float) ($profit['summary']['roas'] ?? 0),
                'contribution' => (float) ($profit['summary']['contribution_profit'] ?? 0),
            ),
            'campaigns' => $campaigns,
        );
    }

    public function pipeline() { return $this->pipeline; }
}

class SMF_V3_Attribution_Service {
    public static function init() {
        if (!class_exists('SMF_V3_Feature_Flag') || !SMF_V3_Feature_Flag::enabled()) return;
        if (get_option('smf_v3_advanced_attribution', 'no') !== 'yes') return;
        add_action('admin_menu', array(__CLASS__, 'menu'), 31);
    }

    public static function menu() {
        add_submenu_page('sync-meta-flow', 'Attribution Models', 'Attribution Models', 'manage_woocommerce', 'smf-v3-attribution', array(__CLASS__, 'page'));
    }

    public static function page() {
        if (!current_user_can('manage_woocommerce')) return;
        $svc = new SMF_V3_Attribution_Intelligence_Service();
        $model = SMF_Attribution_Model::normalize_model(isset($_GET['model']) ? wp_unslash($_GET['model']) : get_option('smf_attribution_model', 'last_touch'));
        $intel = $svc->campaign_intelligence(30, $model);
        echo '<div class="wrap smf-wrap"><div class="smf-header"><div><h1>Attribution Models</h1><p>Advanced attribution estimates with quality scoring. Not accounting truth.</p></div></div>';
        echo '<div class="smf-status is-warning"><span class="smf-status-dot"></span><div><strong>Estimates only</strong><small>' . esc_html($intel['disclaimer']) . '</small></div></div>';
        echo '<div class="smf-panel"><h2>Campaign intelligence · ' . esc_html(SMF_Attribution_Model::label($model)) . '</h2>';
        echo '<div class="smf-revenue-grid">';
        foreach (array('spend','revenue','roas','contribution') as $k) {
            echo '<div><span>' . esc_html($k) . '</span><strong>' . esc_html(number_format_i18n((float) $intel['summary'][$k], 2)) . '</strong></div>';
        }
        echo '</div><p class="smf-muted">Models: First Touch, Last Touch, First+Last, Assisted, Position Based, Time Decay. V2 first/last storage remains authoritative when mid-funnel touches are unavailable.</p></div></div>';
    }
}
