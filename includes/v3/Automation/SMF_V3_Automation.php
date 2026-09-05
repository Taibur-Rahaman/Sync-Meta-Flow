<?php
defined('ABSPATH') || exit;

/**
 * V3 Controlled Automation — Observe → Recommend → Explain → Approve → Execute → Verify → Audit.
 * Defaults: V3 off, automation off, mode=observe, dry-run=yes. CRITICAL actions are never autonomous.
 */

class SMF_V3_Automation_Recommendation {
    const TYPES = array('scale_campaign','reduce_spend','review_campaign','fix_capi','review_courier','review_return_rate','review_margin');
    private $data;

    public function __construct(array $data) {
        $type = sanitize_key($data['type'] ?? 'review_campaign');
        if (!in_array($type, self::TYPES, true)) $type = 'review_campaign';
        $this->data = array(
            'id' => sanitize_text_field($data['id'] ?? ''),
            'rule_key' => sanitize_key($data['rule_key'] ?? ''),
            'type' => $type,
            'title' => sanitize_text_field($data['title'] ?? ''),
            'explanation' => sanitize_text_field($data['explanation'] ?? ''),
            'severity' => self::normalize_severity($data['severity'] ?? 'medium'),
            'confidence' => max(0, min(100, (int) ($data['confidence'] ?? 0))),
            'evidence' => self::safe_map($data['evidence'] ?? array()),
            'affected_entity' => sanitize_text_field($data['affected_entity'] ?? ($data['subject_key'] ?? '')),
            'recommended_action' => sanitize_text_field($data['recommended_action'] ?? ($data['action'] ?? '')),
            'expected_effect' => sanitize_text_field($data['expected_effect'] ?? ($data['expected_outcome'] ?? '')),
            'created_at' => sanitize_text_field($data['created_at'] ?? current_time('mysql')),
            'expires_at' => sanitize_text_field($data['expires_at'] ?? ''),
            'risk' => SMF_V3_Automation_Policy::normalize_risk($data['risk'] ?? 'medium'),
            'action_type' => sanitize_key($data['action_type'] ?? 'acknowledge_recommendation'),
        );
    }

    private static function normalize_severity($v) {
        $v = sanitize_key($v);
        return in_array($v, array('low','medium','high','critical'), true) ? $v : 'medium';
    }

    private static function safe_map($evidence) {
        $out = array();
        $blocked = array('token','secret','password','authorization','access_token','api_key','webhook_secret','payload','bearer');
        foreach ((array) $evidence as $key => $value) {
            $k = sanitize_key($key);
            if ($k === '' || in_array($k, $blocked, true)) continue;
            $out[$k] = is_scalar($value) ? sanitize_text_field((string) $value) : sanitize_text_field(wp_json_encode($value));
        }
        return $out;
    }

    public function get($key, $default = null) { return array_key_exists($key, $this->data) ? $this->data[$key] : $default; }
    public function to_array() { return $this->data; }
    public function is_expired($now = null) {
        $expires = $this->data['expires_at'];
        if ($expires === '') return false;
        $now = $now ?: current_time('mysql');
        return strtotime($expires) !== false && strtotime($expires) < strtotime($now);
    }
}

class SMF_V3_Automation_Policy {
    const VERSION = '1.0';
    const MODES = array('observe','recommend','automate');
    const RISKS = array('low'=>0,'medium'=>1,'high'=>2,'critical'=>3);
    const DEFAULT_ALLOWED = array('refresh_diagnostics','acknowledge_recommendation','retry_capi_event','retry_courier_event','recalculate_attribution');

    private $mode; private $dry_run; private $allowed; private $max_risk; private $cooldown; private $rate_limit; private $enabled;

    public function __construct($mode = 'observe', $dry_run = true, $allowed = null, $max_risk = 'medium', $cooldown = 3600, $rate_limit = 20, $enabled = false) {
        $this->mode = sanitize_key($mode);
        $this->dry_run = (bool) $dry_run;
        $this->allowed = is_array($allowed) ? array_values(array_map('sanitize_key', $allowed)) : self::DEFAULT_ALLOWED;
        $this->max_risk = self::normalize_risk($max_risk);
        $this->cooldown = max(0, (int) $cooldown);
        $this->rate_limit = max(1, min(100, (int) $rate_limit));
        $this->enabled = (bool) $enabled;
    }

    public static function normalize_risk($risk) {
        $risk = sanitize_key($risk);
        return isset(self::RISKS[$risk]) ? $risk : 'medium';
    }

    public static function current() {
        return new self(
            sanitize_key((string) get_option('smf_v3_automation_mode', 'observe')),
            get_option('smf_v3_automation_dry_run', 'yes') !== 'no',
            self::DEFAULT_ALLOWED,
            sanitize_key((string) get_option('smf_v3_automation_max_risk', 'medium')),
            absint(get_option('smf_v3_automation_cooldown', 3600)),
            absint(get_option('smf_v3_automation_rate_limit', 20)),
            get_option('smf_v3_automation_enabled', 'no') === 'yes' && get_option('smf_v3_enabled', 'no') === 'yes'
        );
    }

    public function enabled() { return $this->enabled; }
    public function mode() { return in_array($this->mode, self::MODES, true) ? $this->mode : 'observe'; }
    public function dry_run() { return $this->dry_run; }
    public function version() { return self::VERSION; }
    public function cooldown() { return $this->cooldown; }
    public function rate_limit() { return $this->rate_limit; }
    public function max_risk() { return $this->max_risk; }
    public function allowed_actions() { return $this->allowed; }

    public function allows($action_type, $risk) {
        $risk = self::normalize_risk($risk);
        if ($risk === 'critical') return false;
        if (!in_array(sanitize_key($action_type), $this->allowed, true)) return false;
        return self::RISKS[$risk] <= self::RISKS[$this->max_risk];
    }

    public function requires_approval($risk) {
        $risk = self::normalize_risk($risk);
        if ($risk === 'critical' || $risk === 'high') return true;
        if ($this->mode() !== 'automate') return true;
        return $risk !== 'low';
    }

    public function to_array() {
        return array(
            'enabled' => $this->enabled(),
            'mode' => $this->mode(),
            'dry_run' => $this->dry_run(),
            'max_risk' => $this->max_risk,
            'cooldown' => $this->cooldown,
            'rate_limit' => $this->rate_limit,
            'allowed_actions' => $this->allowed,
            'version' => self::VERSION,
        );
    }
}

class SMF_V3_Automation_Action_Intent {
    private $data;

    public function __construct($recommendation_id, $action_type, $target, $risk = 'medium', $idempotency_key = '') {
        $recommendation_id = sanitize_text_field($recommendation_id);
        $action_type = sanitize_key($action_type);
        $target = sanitize_text_field($target);
        $risk = SMF_V3_Automation_Policy::normalize_risk($risk);
        $key = $idempotency_key !== '' ? sanitize_text_field($idempotency_key) : hash('sha256', $recommendation_id . '|' . $action_type . '|' . $target);
        $this->data = array(
            'action_id' => 'act-' . substr(hash('sha256', $key), 0, 24),
            'recommendation_id' => $recommendation_id,
            'action_type' => $action_type,
            'target' => $target,
            'risk' => $risk,
            'requires_approval' => true,
            'idempotency_key' => $key,
            'created_at' => current_time('mysql'),
        );
    }

    public function get($key, $default = null) { return array_key_exists($key, $this->data) ? $this->data[$key] : $default; }
    public function to_array() { return $this->data; }
}

class SMF_V3_Automation_Result {
    private $status; private $message; private $data;
    private function __construct($status, $message, array $data = array()) {
        $this->status = sanitize_key($status);
        $this->message = sanitize_text_field($message);
        $this->data = $data;
    }
    public static function make($status, $message, array $data = array()) { return new self($status, $message, $data); }
    public function status() { return $this->status; }
    public function message() { return $this->message; }
    public function data() { return $this->data; }
    public function to_array() { return array('status' => $this->status, 'message' => $this->message, 'data' => $this->data); }
    public function ok() { return in_array($this->status, array('dry_run','succeeded','verified','approved','rejected','expired','duplicate'), true); }
}

class SMF_V3_Automation_Store {
    const MAX_ENTRIES = 100;
    const OPTION_APPROVALS = 'smf_v3_automation_approvals';
    const OPTION_IDEMPOTENCY = 'smf_v3_automation_idempotency';
    const OPTION_AUDIT = 'smf_v3_automation_audit';
    const OPTION_HEALTH = 'smf_v3_automation_health';
    const OPTION_COOLDOWNS = 'smf_v3_automation_cooldowns';

    public static function get_list($option) {
        $raw = get_option($option, array());
        return is_array($raw) ? $raw : array();
    }

    public static function put_list($option, array $list) {
        if (count($list) > self::MAX_ENTRIES) $list = array_slice($list, -self::MAX_ENTRIES);
        update_option($option, $list, false);
        return $list;
    }

    public static function health() {
        $defaults = array(
            'recommendations' => 0, 'approvals' => 0, 'rejections' => 0, 'expirations' => 0,
            'executions' => 0, 'failures' => 0, 'verification_failures' => 0, 'dry_runs' => 0,
        );
        $h = self::get_list(self::OPTION_HEALTH);
        return array_merge($defaults, array_intersect_key($h, $defaults));
    }

    public static function bump($key, $by = 1) {
        $h = self::health();
        if (!array_key_exists($key, $h)) return $h;
        $h[$key] = max(0, (int) $h[$key] + (int) $by);
        update_option(self::OPTION_HEALTH, $h, false);
        return $h;
    }

    public static function find_approval($recommendation_id) {
        $recommendation_id = sanitize_text_field($recommendation_id);
        foreach (self::get_list(self::OPTION_APPROVALS) as $row) {
            if (($row['recommendation_id'] ?? '') === $recommendation_id) return $row;
        }
        return null;
    }

    public static function save_approval(array $row) {
        $list = self::get_list(self::OPTION_APPROVALS);
        $found = false;
        foreach ($list as $i => $existing) {
            if (($existing['recommendation_id'] ?? '') === ($row['recommendation_id'] ?? '')) {
                $list[$i] = $row;
                $found = true;
                break;
            }
        }
        if (!$found) $list[] = $row;
        self::put_list(self::OPTION_APPROVALS, $list);
        return $row;
    }

    public static function idempotency_get($key) {
        $key = sanitize_text_field($key);
        foreach (self::get_list(self::OPTION_IDEMPOTENCY) as $row) {
            if (($row['key'] ?? '') === $key) return $row;
        }
        return null;
    }

    public static function idempotency_put($key, array $result) {
        $list = self::get_list(self::OPTION_IDEMPOTENCY);
        $row = array('key' => sanitize_text_field($key), 'result' => $result, 'at' => current_time('mysql'));
        $replaced = false;
        foreach ($list as $i => $existing) {
            if (($existing['key'] ?? '') === $row['key']) {
                $list[$i] = $row;
                $replaced = true;
                break;
            }
        }
        if (!$replaced) $list[] = $row;
        self::put_list(self::OPTION_IDEMPOTENCY, $list);
        return $row;
    }

    public static function audit(array $entry) {
        $blocked = array('token','secret','password','authorization','access_token','api_key','webhook_secret','payload','bearer');
        foreach ($blocked as $k) unset($entry[$k]);
        $entry['at'] = sanitize_text_field($entry['at'] ?? current_time('mysql'));
        $list = self::get_list(self::OPTION_AUDIT);
        $list[] = $entry;
        self::put_list(self::OPTION_AUDIT, $list);
        return $entry;
    }

    public static function cooldown_hit($action_type, $cooldown) {
        $action_type = sanitize_key($action_type);
        $map = self::get_list(self::OPTION_COOLDOWNS);
        $last = isset($map[$action_type]) ? (int) $map[$action_type] : 0;
        $now = (int) current_time('timestamp');
        if ($last > 0 && ($now - $last) < (int) $cooldown) return true;
        return false;
    }

    public static function cooldown_mark($action_type) {
        $map = self::get_list(self::OPTION_COOLDOWNS);
        $map[sanitize_key($action_type)] = (int) current_time('timestamp');
        if (count($map) > self::MAX_ENTRIES) $map = array_slice($map, -self::MAX_ENTRIES, null, true);
        update_option(self::OPTION_COOLDOWNS, $map, false);
    }

    public static function execution_count_window($seconds = 3600) {
        $since = time() - max(1, (int) $seconds);
        $n = 0;
        foreach (self::get_list(self::OPTION_AUDIT) as $row) {
            if (($row['kind'] ?? '') !== 'execution') continue;
            $ts = strtotime((string) ($row['at'] ?? ''));
            if ($ts && $ts >= $since) $n++;
        }
        return $n;
    }
}

class SMF_V3_Automation_Action_Registry {
    private $actions = array();

    public function __construct() {
        $this->register('acknowledge_recommendation', array($this, 'act_acknowledge'), 'low');
        $this->register('refresh_diagnostics', array($this, 'act_refresh_diagnostics'), 'low');
        $this->register('retry_capi_event', array($this, 'act_retry_capi'), 'medium');
        $this->register('retry_courier_event', array($this, 'act_retry_courier'), 'medium');
        $this->register('recalculate_attribution', array($this, 'act_recalc_attribution'), 'low');
    }

    public function register($key, $callable, $default_risk = 'medium') {
        $key = sanitize_key($key);
        if ($key === '' || !is_callable($callable)) return false;
        if (isset($this->actions[$key])) return false;
        $this->actions[$key] = array('handler' => $callable, 'risk' => SMF_V3_Automation_Policy::normalize_risk($default_risk));
        return true;
    }

    public function has($key) { return isset($this->actions[sanitize_key($key)]); }
    public function keys() { return array_keys($this->actions); }
    public function default_risk($key) { return $this->actions[sanitize_key($key)]['risk'] ?? 'medium'; }

    public function execute($key, array $context = array(), $dry_run = true) {
        $key = sanitize_key($key);
        if (!isset($this->actions[$key])) {
            return SMF_V3_Automation_Result::make('failed', 'Action type is not registered.', array('action_type' => $key));
        }
        if ($dry_run) {
            return SMF_V3_Automation_Result::make('dry_run', 'Dry-run validated action; no mutation performed.', array(
                'action_type' => $key,
                'dry_run' => true,
                'target' => sanitize_text_field($context['target'] ?? ''),
            ));
        }
        try {
            $result = call_user_func($this->actions[$key]['handler'], $context);
            return $result instanceof SMF_V3_Automation_Result ? $result : SMF_V3_Automation_Result::make('succeeded', 'Action completed.', is_array($result) ? $result : array());
        } catch (Throwable $e) {
            return SMF_V3_Automation_Result::make('failed', 'Action execution failed.', array('action_type' => $key));
        }
    }

    private function act_acknowledge(array $context) {
        return SMF_V3_Automation_Result::make('succeeded', 'Recommendation acknowledged.', array(
            'recommendation_id' => sanitize_text_field($context['recommendation_id'] ?? ''),
        ));
    }

    private function act_refresh_diagnostics(array $context) {
        $report = class_exists('SMF_Observability') ? SMF_Observability::report() : array('overall' => 'unknown');
        $overall = sanitize_key($report['overall'] ?? 'unknown');
        return SMF_V3_Automation_Result::make('succeeded', 'Diagnostics refreshed from local aggregates.', array(
            'overall' => $overall,
            'modules' => isset($report['modules']) ? count((array) $report['modules']) : 0,
        ));
    }

    private function act_retry_capi(array $context) {
        if (!class_exists('SMF_Meta_CAPI')) {
            return SMF_V3_Automation_Result::make('failed', 'CAPI module unavailable.');
        }
        if (method_exists('SMF_Meta_CAPI', 'process_queue')) {
            SMF_Meta_CAPI::process_queue();
        }
        $stats = method_exists('SMF_Meta_CAPI', 'get_queue_stats') ? SMF_Meta_CAPI::get_queue_stats() : array();
        return SMF_V3_Automation_Result::make('succeeded', 'CAPI queue processing requested.', array(
            'pending' => (int) ($stats['pending'] ?? 0),
            'failed' => (int) ($stats['failed'] ?? 0),
        ));
    }

    private function act_retry_courier(array $context) {
        if (!class_exists('SMF_Courier_Recovery') || !method_exists('SMF_Courier_Recovery', 'retry')) {
            return SMF_V3_Automation_Result::make('failed', 'Courier recovery unavailable.');
        }
        SMF_Courier_Recovery::retry();
        return SMF_V3_Automation_Result::make('succeeded', 'Courier retry pass requested.', array('target' => sanitize_text_field($context['target'] ?? '')));
    }

    private function act_recalc_attribution(array $context) {
        $model = class_exists('SMF_Attribution_Model')
            ? SMF_Attribution_Model::normalize_model($context['model'] ?? 'last_touch')
            : 'last_touch';
        return SMF_V3_Automation_Result::make('succeeded', 'Attribution model normalized for reporting refresh.', array(
            'model' => $model,
            'estimate' => true,
        ));
    }
}

class SMF_V3_Automation_Engine {
    private $registry;
    private $dispatcher;

    public function __construct(?SMF_V3_Automation_Action_Registry $registry = null, ?SMF_V3_Event_Dispatcher_Interface $dispatcher = null) {
        $this->registry = $registry ?: new SMF_V3_Automation_Action_Registry();
        $this->dispatcher = $dispatcher;
    }

    public function registry() { return $this->registry; }

    public function recommendations($days = 30, $model = 'last_touch') {
        $days = max(1, min(90, absint($days)));
        $model = class_exists('SMF_Attribution_Model') ? SMF_Attribution_Model::normalize_model($model) : sanitize_key($model);
        $out = array();
        if (!class_exists('SMF_Decision_Engine')) return $out;
        $pack = SMF_Decision_Engine::recommendations($days, $model);
        $created = current_time('mysql');
        $expires = gmdate('Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS);
        foreach ((array) ($pack['recommendations'] ?? array()) as $item) {
            $mapped = $this->map_item($item, $days, $model, $created, $expires);
            $out[] = new SMF_V3_Automation_Recommendation($mapped);
            $this->emit('recommendation_created', $mapped['id'], array('type' => $mapped['type'], 'risk' => $mapped['risk']));
        }
        if ($out) SMF_V3_Automation_Store::bump('recommendations', count($out));
        return $out;
    }

    private function map_item(array $item, $days, $model, $created, $expires) {
        $title = sanitize_text_field($item['title'] ?? 'Review');
        $type = $this->type($item);
        $rule = $this->rule_key($item, $type);
        $id = 'rec-' . hash('sha256', $rule . '|' . $title . '|' . absint($days) . '|' . sanitize_key($model));
        $priority = strtoupper((string) ($item['priority'] ?? 'MEDIUM'));
        $risk = $this->risk_for($item, $type);
        $action_type = $this->action_for($type);
        return array(
            'id' => $id,
            'rule_key' => $rule,
            'type' => $type,
            'title' => $title,
            'explanation' => sanitize_text_field(($item['detail'] ?? '') . ' Recommended action: ' . ($item['action'] ?? '')),
            'severity' => $priority === 'HIGH' ? 'high' : ($priority === 'LOW' ? 'low' : 'medium'),
            'confidence' => $priority === 'HIGH' ? 90 : 70,
            'evidence' => array(
                'period_days' => (string) absint($days),
                'model' => sanitize_key($model),
                'source' => 'v2_decision_engine',
                'item_type' => sanitize_key($item['type'] ?? 'campaign'),
            ),
            'affected_entity' => $title,
            'recommended_action' => sanitize_text_field($item['action'] ?? 'Review in Decision Center'),
            'expected_effect' => 'Merchant review and controlled follow-up without autonomous spend or routing changes.',
            'created_at' => $created,
            'expires_at' => $expires,
            'risk' => $risk,
            'action_type' => $action_type,
        );
    }

    private function rule_key(array $item, $type) {
        return sanitize_key(($item['type'] ?? 'campaign') . '_' . $type);
    }

    private function type(array $item) {
        $t = sanitize_key($item['type'] ?? 'campaign');
        $title = strtolower((string) ($item['title'] ?? ''));
        if ($t === 'system') return (strpos($title, 'capi') !== false) ? 'fix_capi' : 'review_campaign';
        if ($t === 'courier') return (strpos($title, 'return') !== false) ? 'review_return_rate' : 'review_courier';
        if ($t === 'business' || strpos($title, 'margin') !== false) return 'review_margin';
        if (strpos($title, 'scale') !== false) return 'scale_campaign';
        if (strpos($title, 'stop') !== false || strpos($title, 'reduce') !== false) return 'reduce_spend';
        return 'review_campaign';
    }

    private function risk_for(array $item, $type) {
        if (in_array($type, array('scale_campaign','reduce_spend'), true)) return 'high';
        if ($type === 'fix_capi' || $type === 'review_courier') return 'medium';
        if (($item['priority'] ?? '') === 'HIGH') return 'high';
        return 'medium';
    }

    private function action_for($type) {
        if ($type === 'fix_capi') return 'retry_capi_event';
        if ($type === 'review_courier') return 'retry_courier_event';
        if ($type === 'review_campaign' || $type === 'review_margin' || $type === 'review_return_rate') return 'refresh_diagnostics';
        return 'acknowledge_recommendation';
    }

    public function approve($recommendation_id, $actor, $nonce_ok = false, $can_manage = false) {
        if (!$can_manage || !$nonce_ok) {
            return SMF_V3_Automation_Result::make('failed', 'Approval requires capability and valid nonce.');
        }
        $recommendation_id = sanitize_text_field($recommendation_id);
        $existing = SMF_V3_Automation_Store::find_approval($recommendation_id);
        if ($existing && ($existing['status'] ?? '') === 'approved') {
            return SMF_V3_Automation_Result::make('duplicate', 'Recommendation already approved.', $existing);
        }
        $row = array(
            'recommendation_id' => $recommendation_id,
            'status' => 'approved',
            'actor' => sanitize_text_field($actor),
            'at' => current_time('mysql'),
        );
        SMF_V3_Automation_Store::save_approval($row);
        SMF_V3_Automation_Store::bump('approvals');
        SMF_V3_Automation_Store::audit(array('kind' => 'approval', 'recommendation_id' => $recommendation_id, 'actor' => $row['actor'], 'status' => 'approved'));
        $this->emit('recommendation_approved', $recommendation_id, array('actor' => $row['actor']));
        return SMF_V3_Automation_Result::make('approved', 'Recommendation approved.', $row);
    }

    public function reject($recommendation_id, $actor, $nonce_ok = false, $can_manage = false) {
        if (!$can_manage || !$nonce_ok) {
            return SMF_V3_Automation_Result::make('failed', 'Rejection requires capability and valid nonce.');
        }
        $recommendation_id = sanitize_text_field($recommendation_id);
        $row = array(
            'recommendation_id' => $recommendation_id,
            'status' => 'rejected',
            'actor' => sanitize_text_field($actor),
            'at' => current_time('mysql'),
        );
        SMF_V3_Automation_Store::save_approval($row);
        SMF_V3_Automation_Store::bump('rejections');
        SMF_V3_Automation_Store::audit(array('kind' => 'rejection', 'recommendation_id' => $recommendation_id, 'actor' => $row['actor'], 'status' => 'rejected'));
        $this->emit('recommendation_rejected', $recommendation_id, array('actor' => $row['actor']));
        return SMF_V3_Automation_Result::make('rejected', 'Recommendation rejected.', $row);
    }

    public function expire_if_needed(SMF_V3_Automation_Recommendation $recommendation) {
        if (!$recommendation->is_expired()) return null;
        $id = $recommendation->get('id');
        $row = array('recommendation_id' => $id, 'status' => 'expired', 'actor' => 'system', 'at' => current_time('mysql'));
        SMF_V3_Automation_Store::save_approval($row);
        SMF_V3_Automation_Store::bump('expirations');
        SMF_V3_Automation_Store::audit(array('kind' => 'expiration', 'recommendation_id' => $id, 'status' => 'expired'));
        $this->emit('automation_expired', $id, array());
        return SMF_V3_Automation_Result::make('expired', 'Recommendation expired.', $row);
    }

    public function simulate(SMF_V3_Automation_Recommendation $recommendation) {
        return $this->run($recommendation, array('actor' => 'system', 'force_dry_run' => true, 'skip_approval' => false));
    }

    /**
     * Full controlled pipeline: policy → approval → idempotency → execute → verify → audit.
     */
    public function run(SMF_V3_Automation_Recommendation $recommendation, array $opts = array()) {
        $policy = isset($opts['policy']) && $opts['policy'] instanceof SMF_V3_Automation_Policy ? $opts['policy'] : SMF_V3_Automation_Policy::current();
        $actor = sanitize_text_field($opts['actor'] ?? 'system');
        $force_dry = !empty($opts['force_dry_run']) || $policy->dry_run();
        $action_type = sanitize_key($recommendation->get('action_type', 'acknowledge_recommendation'));
        $risk = SMF_V3_Automation_Policy::normalize_risk($recommendation->get('risk', 'medium'));
        $intent = new SMF_V3_Automation_Action_Intent($recommendation->get('id'), $action_type, $recommendation->get('affected_entity'), $risk);

        $this->emit('automation_requested', $intent->get('action_id'), array('recommendation_id' => $recommendation->get('id'), 'action_type' => $action_type));

        $expired = $this->expire_if_needed($recommendation);
        if ($expired) return $expired;

        if (!$policy->enabled() && empty($opts['allow_disabled'])) {
            return SMF_V3_Automation_Result::make('blocked', 'Automation is disabled by default.');
        }

        if ($policy->mode() === 'observe') {
            return SMF_V3_Automation_Result::make('approval_required', 'Automation is in observe mode; recommendations only.', array('mode' => 'observe'));
        }

        if (!$this->registry->has($action_type)) {
            return SMF_V3_Automation_Result::make('failed', 'Action type is not in the registry.');
        }

        if (!$policy->allows($action_type, $risk)) {
            return SMF_V3_Automation_Result::make('blocked', 'Policy does not allow this action or risk level.', array('risk' => $risk, 'action_type' => $action_type));
        }

        if (SMF_V3_Automation_Store::cooldown_hit($action_type, $policy->cooldown())) {
            return SMF_V3_Automation_Result::make('blocked', 'Action is in cooldown.');
        }

        if (SMF_V3_Automation_Store::execution_count_window(3600) >= $policy->rate_limit()) {
            return SMF_V3_Automation_Result::make('blocked', 'Rate limit exceeded for the current window.');
        }

        $needs_approval = $policy->requires_approval($risk) && empty($opts['skip_approval']);
        if ($needs_approval) {
            $approval = SMF_V3_Automation_Store::find_approval($recommendation->get('id'));
            if (!$approval || ($approval['status'] ?? '') !== 'approved') {
                return SMF_V3_Automation_Result::make('approval_required', 'Explicit approval is required before execution.', array(
                    'recommendation_id' => $recommendation->get('id'),
                    'risk' => $risk,
                ));
            }
        }

        $idem = SMF_V3_Automation_Store::idempotency_get($intent->get('idempotency_key'));
        if ($idem) {
            return SMF_V3_Automation_Result::make('duplicate', 'Idempotent replay; action was not executed again.', array('prior' => $idem['result'] ?? array()));
        }

        $this->emit('automation_started', $intent->get('action_id'), array('dry_run' => $force_dry));
        $result = $this->registry->execute($action_type, array(
            'recommendation_id' => $recommendation->get('id'),
            'target' => $recommendation->get('affected_entity'),
            'model' => $recommendation->get('evidence')['model'] ?? 'last_touch',
        ), $force_dry);

        if ($result->status() === 'dry_run') SMF_V3_Automation_Store::bump('dry_runs');
        if ($result->status() === 'failed') {
            SMF_V3_Automation_Store::bump('failures');
            $this->emit('automation_failed', $intent->get('action_id'), array('message' => $result->message()));
        } else {
            SMF_V3_Automation_Store::bump('executions');
            $this->emit('automation_succeeded', $intent->get('action_id'), array('status' => $result->status()));
        }

        $verified = $this->verify($recommendation, $result, $force_dry);
        if ($verified->status() === 'verification_failed') {
            SMF_V3_Automation_Store::bump('verification_failures');
        } else {
            $this->emit('automation_verified', $intent->get('action_id'), array('status' => $verified->status()));
        }

        SMF_V3_Automation_Store::idempotency_put($intent->get('idempotency_key'), $result->to_array());
        if (!$force_dry && $result->status() !== 'failed') SMF_V3_Automation_Store::cooldown_mark($action_type);

        SMF_V3_Automation_Store::audit(array(
            'kind' => 'execution',
            'recommendation_id' => $recommendation->get('id'),
            'action_id' => $intent->get('action_id'),
            'action_type' => $action_type,
            'actor' => $actor,
            'approval' => SMF_V3_Automation_Store::find_approval($recommendation->get('id')),
            'execution' => $result->to_array(),
            'verification' => $verified->to_array(),
            'dry_run' => $force_dry,
            'failure' => $result->status() === 'failed' ? $result->message() : '',
        ));

        return SMF_V3_Automation_Result::make($force_dry ? 'dry_run' : $result->status(), $result->message(), array(
            'intent' => $intent->to_array(),
            'execution' => $result->to_array(),
            'verification' => $verified->to_array(),
            'policy' => $policy->to_array(),
        ));
    }

    public function verify(SMF_V3_Automation_Recommendation $recommendation, SMF_V3_Automation_Result $result, $dry_run = true) {
        if ($dry_run || $result->status() === 'dry_run') {
            return SMF_V3_Automation_Result::make('verified', 'Dry-run verification: validation path completed without mutation.', array('dry_run' => true));
        }
        if ($result->status() === 'failed') {
            return SMF_V3_Automation_Result::make('verification_failed', 'Execution failed; expected state not reached.');
        }
        $action = $recommendation->get('action_type');
        if ($action === 'refresh_diagnostics' && class_exists('SMF_Observability')) {
            $report = SMF_Observability::report();
            if (!isset($report['overall'])) {
                return SMF_V3_Automation_Result::make('verification_failed', 'Diagnostics report missing overall state.');
            }
        }
        if ($action === 'recalculate_attribution' && class_exists('SMF_Attribution_Model')) {
            $model = SMF_Attribution_Model::normalize_model($recommendation->get('evidence')['model'] ?? 'last_touch');
            if ($model === '') {
                return SMF_V3_Automation_Result::make('verification_failed', 'Attribution model invalid after refresh.');
            }
        }
        return SMF_V3_Automation_Result::make('verified', 'Post-execution state checks passed.', array('action_type' => $action));
    }

    public function health() {
        return array_merge(SMF_V3_Automation_Store::health(), array(
            'policy' => SMF_V3_Automation_Policy::current()->to_array(),
            'registry' => $this->registry->keys(),
        ));
    }

    private function emit($name, $id, array $payload) {
        if (!$this->dispatcher && class_exists('SMF_V3_Synchronous_Dispatcher')) {
            $this->dispatcher = new SMF_V3_Synchronous_Dispatcher();
        }
        if (!$this->dispatcher || !class_exists('SMF_V3_Event_Envelope')) return;
        $this->dispatcher->dispatch(new SMF_V3_Event_Envelope($name, '1.0', sanitize_text_field($id), current_time('mysql'), $payload));
    }
}

class SMF_V3_Automation_Service {
    private static $engine;

    public static function init() {
        if (!SMF_V3_Feature_Flag::automation_enabled()) return;
        add_action('admin_menu', array(__CLASS__, 'menu'), 27);
        add_action('admin_post_smf_v3_automation_approve', array(__CLASS__, 'handle_approve'));
        add_action('admin_post_smf_v3_automation_reject', array(__CLASS__, 'handle_reject'));
        add_action('admin_post_smf_v3_automation_run', array(__CLASS__, 'handle_run'));
    }

    public static function engine() {
        if (!self::$engine) {
            $dispatcher = null;
            if (class_exists('SMF_V3_Bootstrap') && SMF_V3_Bootstrap::container() && SMF_V3_Bootstrap::container()->has('events')) {
                $dispatcher = SMF_V3_Bootstrap::container()->get('events');
            }
            self::$engine = new SMF_V3_Automation_Engine(new SMF_V3_Automation_Action_Registry(), $dispatcher);
        }
        return self::$engine;
    }

    public static function menu() {
        add_submenu_page('sync-meta-flow', 'Automation', 'Automation', 'manage_woocommerce', 'smf-v3-automation', array(__CLASS__, 'page'));
    }

    public static function handle_approve() {
        if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized', 403);
        check_admin_referer('smf_v3_automation_approve');
        $id = sanitize_text_field(wp_unslash($_POST['recommendation_id'] ?? ''));
        $actor = wp_get_current_user() ? sanitize_text_field(wp_get_current_user()->user_login) : 'admin';
        self::engine()->approve($id, $actor, true, true);
        wp_safe_redirect(admin_url('admin.php?page=smf-v3-automation&updated=approved'));
        exit;
    }

    public static function handle_reject() {
        if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized', 403);
        check_admin_referer('smf_v3_automation_reject');
        $id = sanitize_text_field(wp_unslash($_POST['recommendation_id'] ?? ''));
        $actor = wp_get_current_user() ? sanitize_text_field(wp_get_current_user()->user_login) : 'admin';
        self::engine()->reject($id, $actor, true, true);
        wp_safe_redirect(admin_url('admin.php?page=smf-v3-automation&updated=rejected'));
        exit;
    }

    public static function handle_run() {
        if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized', 403);
        check_admin_referer('smf_v3_automation_run');
        $id = sanitize_text_field(wp_unslash($_POST['recommendation_id'] ?? ''));
        $recs = self::engine()->recommendations(30, get_option('smf_attribution_model', 'last_touch'));
        foreach ($recs as $rec) {
            if ($rec->get('id') === $id) {
                self::engine()->run($rec, array('actor' => 'admin', 'allow_disabled' => false));
                break;
            }
        }
        wp_safe_redirect(admin_url('admin.php?page=smf-v3-automation&updated=run'));
        exit;
    }

    public static function page() {
        if (!current_user_can('manage_woocommerce')) return;
        $policy = SMF_V3_Automation_Policy::current();
        $health = self::engine()->health();
        $recs = self::engine()->recommendations(30, get_option('smf_attribution_model', 'last_touch'));
        echo '<div class="wrap smf-wrap"><div class="smf-header"><div><h1>Automation</h1><p>Controlled recommendations with policy, approval, dry-run, verification and audit.</p></div></div>';
        echo '<div class="smf-status is-good"><span class="smf-status-dot"></span><div><strong>Mode: ' . esc_html($policy->mode()) . '</strong><small>Dry-run ' . esc_html($policy->dry_run() ? 'enabled' : 'disabled') . ' · Critical actions never autonomous</small></div></div>';
        echo '<div class="smf-panel"><h2>Automation health</h2><div class="smf-revenue-grid">';
        foreach (array('recommendations','approvals','rejections','expirations','executions','failures','verification_failures','dry_runs') as $k) {
            echo '<div><span>' . esc_html($k) . '</span><strong>' . esc_html((string) ($health[$k] ?? 0)) . '</strong></div>';
        }
        echo '</div></div><div class="smf-panel"><h2>Recommendations</h2>';
        if (!$recs) {
            echo '<p class="smf-muted">No recommendations for the current window.</p>';
        } else {
            echo '<div class="smf-diagnostic-mini">';
            foreach ($recs as $rec) {
                $a = $rec->to_array();
                echo '<span>' . esc_html(strtoupper($a['severity']) . ' · ' . $a['type']) . '</span>';
                echo '<b class="is-warning">' . esc_html($a['title'] . ' — ' . $a['explanation']) . '</b>';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;margin-right:6px">';
                echo '<input type="hidden" name="action" value="smf_v3_automation_approve"><input type="hidden" name="recommendation_id" value="' . esc_attr($a['id']) . '">';
                wp_nonce_field('smf_v3_automation_approve');
                echo '<button class="button">Approve</button></form>';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;margin-right:6px">';
                echo '<input type="hidden" name="action" value="smf_v3_automation_reject"><input type="hidden" name="recommendation_id" value="' . esc_attr($a['id']) . '">';
                wp_nonce_field('smf_v3_automation_reject');
                echo '<button class="button">Reject</button></form>';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
                echo '<input type="hidden" name="action" value="smf_v3_automation_run"><input type="hidden" name="recommendation_id" value="' . esc_attr($a['id']) . '">';
                wp_nonce_field('smf_v3_automation_run');
                echo '<button class="button button-primary">Run (policy)</button></form>';
            }
            echo '</div>';
        }
        echo '</div><p class="smf-muted">Safe internal actions only. External budget/routing mutations are not autonomous in this release.</p></div>';
    }
}
