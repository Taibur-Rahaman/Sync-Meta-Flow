<?php
defined('ABSPATH') || exit;

/**
 * V3 Commercial SaaS foundation — local entitlements, fail-safe without remote license service.
 * Never deletes merchant data on expiry. Never trusts client-side entitlement state alone.
 */

class SMF_V3_Capability_Catalog {
    const CAPABILITIES = array(
        'basic_tracking',
        'advanced_attribution',
        'courier_intelligence',
        'automation',
        'advanced_reports',
        'ai_assistant',
    );

    const PLANS = array(
        'free' => array('basic_tracking', 'advanced_reports'),
        'pro' => array('basic_tracking', 'advanced_reports', 'advanced_attribution', 'courier_intelligence'),
        'business' => array('basic_tracking', 'advanced_reports', 'advanced_attribution', 'courier_intelligence', 'automation'),
        'enterprise' => array('basic_tracking', 'advanced_reports', 'advanced_attribution', 'courier_intelligence', 'automation', 'ai_assistant'),
    );

    public static function normalize_plan($plan) {
        $plan = sanitize_key($plan);
        return isset(self::PLANS[$plan]) ? $plan : 'free';
    }

    public static function capabilities_for_plan($plan) {
        $plan = self::normalize_plan($plan);
        return self::PLANS[$plan];
    }

    public static function known($capability) {
        return in_array(sanitize_key($capability), self::CAPABILITIES, true);
    }
}

class SMF_V3_License {
    const STATES = array('active','trial','expired','grace','suspended','unlicensed');
    private $data;

    public function __construct(array $raw = array()) {
        $plan = SMF_V3_Capability_Catalog::normalize_plan($raw['plan'] ?? 'free');
        $state = sanitize_key($raw['state'] ?? 'unlicensed');
        if (!in_array($state, self::STATES, true)) $state = 'unlicensed';
        $this->data = array(
            'merchant_id' => sanitize_text_field($raw['merchant_id'] ?? ''),
            'plan' => $plan,
            'state' => $state,
            'trial_ends_at' => sanitize_text_field($raw['trial_ends_at'] ?? ''),
            'grace_ends_at' => sanitize_text_field($raw['grace_ends_at'] ?? ''),
            'expires_at' => sanitize_text_field($raw['expires_at'] ?? ''),
            'entitlements' => array_values(array_filter(array_map('sanitize_key', (array) ($raw['entitlements'] ?? array())), array('SMF_V3_Capability_Catalog', 'known'))),
            'license_fingerprint' => sanitize_text_field($raw['license_fingerprint'] ?? ''),
        );
        if ($this->data['merchant_id'] === '') {
            $this->data['merchant_id'] = 'mrc-' . substr(hash('sha256', 'smf-local|' . (string) (defined('ABSPATH') ? ABSPATH : 'local')), 0, 16);
        }
        if (!$this->data['entitlements']) {
            $this->data['entitlements'] = SMF_V3_Capability_Catalog::capabilities_for_plan($plan);
        }
    }

    public function to_array() {
        $out = $this->data;
        // Never expose raw license tokens — fingerprint only.
        unset($out['token'], $out['license_key'], $out['secret']);
        return $out;
    }

    public function plan() { return $this->data['plan']; }
    public function state() { return $this->data['state']; }
    public function merchant_id() { return $this->data['merchant_id']; }
    public function entitlements() { return $this->data['entitlements']; }

    public function is_usable() {
        return in_array($this->data['state'], array('active','trial','grace'), true);
    }
}

class SMF_V3_License_Service {
    public static function current() {
        $raw = get_option('smf_v3_license', array());
        if (!is_array($raw) || !$raw) {
            return new SMF_V3_License(array('plan' => 'free', 'state' => 'active', 'entitlements' => SMF_V3_Capability_Catalog::capabilities_for_plan('free')));
        }
        // Strip secrets if somehow stored.
        unset($raw['token'], $raw['license_key'], $raw['secret'], $raw['access_token']);
        $license = new SMF_V3_License($raw);
        return self::apply_time_state($license);
    }

    public static function apply_time_state(SMF_V3_License $license) {
        $data = $license->to_array();
        $now = time();
        if ($data['state'] === 'trial' && $data['trial_ends_at'] !== '' && strtotime($data['trial_ends_at']) < $now) {
            if ($data['grace_ends_at'] !== '' && strtotime($data['grace_ends_at']) >= $now) {
                $data['state'] = 'grace';
            } else {
                $data['state'] = 'expired';
            }
        }
        if ($data['state'] === 'active' && $data['expires_at'] !== '' && strtotime($data['expires_at']) < $now) {
            if ($data['grace_ends_at'] !== '' && strtotime($data['grace_ends_at']) >= $now) {
                $data['state'] = 'grace';
            } else {
                $data['state'] = 'expired';
            }
        }
        return new SMF_V3_License($data);
    }

    public static function save(array $raw) {
        unset($raw['token'], $raw['license_key'], $raw['secret'], $raw['access_token'], $raw['authorization']);
        $license = new SMF_V3_License($raw);
        update_option('smf_v3_license', $license->to_array(), false);
        return $license;
    }
}

class SMF_V3_Entitlement_Checker {
    private $license;

    public function __construct(?SMF_V3_License $license = null) {
        $this->license = $license ?: SMF_V3_License_Service::current();
    }

    public function can($capability) {
        $capability = sanitize_key($capability);
        if (!SMF_V3_Capability_Catalog::known($capability)) return false;
        if (!$this->license->is_usable()) {
            // Fail safe: keep basic_tracking readable so store data remains intact/usable.
            return $capability === 'basic_tracking';
        }
        return in_array($capability, $this->license->entitlements(), true);
    }

    public function license() { return $this->license; }

    public static function check($capability) {
        return (new self())->can($capability);
    }
}

class SMF_V3_Telemetry {
    public static function enabled() {
        return get_option('smf_v3_telemetry_opt_in', 'no') === 'yes';
    }

    public static function snapshot() {
        if (!self::enabled()) {
            return array('enabled' => false, 'payload' => array(), 'blocks_plugin' => false);
        }
        $license = SMF_V3_License_Service::current();
        return array(
            'enabled' => true,
            'payload' => array(
                'plan' => $license->plan(),
                'state' => $license->state(),
                'version' => defined('SMF_VERSION') ? SMF_VERSION : '',
                'v3_enabled' => class_exists('SMF_V3_Feature_Flag') && SMF_V3_Feature_Flag::enabled(),
            ),
            'blocks_plugin' => false,
        );
    }
}

class SMF_V3_Update_Compatibility {
    public static function status() {
        $license = SMF_V3_License_Service::current();
        return array(
            'current_version' => defined('SMF_VERSION') ? SMF_VERSION : '',
            'license_state' => $license->state(),
            'plan' => $license->plan(),
            'entitlements' => $license->entitlements(),
            'schema_version' => (string) get_option('smf_schema_version', ''),
            'update_channel' => 'wordpress.org-or-standard',
            'custom_updater' => false,
            'migration_status' => 'additive',
        );
    }
}

class SMF_V3_Commercial_Service {
    public static function init() {
        if (!class_exists('SMF_V3_Feature_Flag') || !SMF_V3_Feature_Flag::enabled()) return;
        if (get_option('smf_v3_commercial_enabled', 'no') !== 'yes') return;
        add_action('admin_menu', array(__CLASS__, 'menu'), 40);
        // Integrate with onboarding without duplicating flows.
        add_filter('smf_onboarding_commercial_hint', array(__CLASS__, 'onboarding_hint'));
    }

    public static function onboarding_hint($hint) {
        $license = SMF_V3_License_Service::current();
        return 'Plan: ' . $license->plan() . ' (' . $license->state() . ')';
    }

    public static function menu() {
        add_submenu_page('sync-meta-flow', 'License', 'License', 'manage_woocommerce', 'smf-v3-commercial', array(__CLASS__, 'page'));
    }

    public static function page() {
        if (!current_user_can('manage_woocommerce')) return;
        $checker = new SMF_V3_Entitlement_Checker();
        $license = $checker->license();
        $update = SMF_V3_Update_Compatibility::status();
        $telemetry = SMF_V3_Telemetry::snapshot();
        echo '<div class="wrap smf-wrap"><div class="smf-header"><div><h1>License</h1><p>Commercial entitlements. Plugin remains functional if licensing infrastructure is unavailable.</p></div></div>';
        echo '<div class="smf-panel"><h2>Merchant</h2><p>ID: <code>' . esc_html($license->merchant_id()) . '</code> · Plan: <strong>' . esc_html($license->plan()) . '</strong> · State: <strong>' . esc_html($license->state()) . '</strong></p></div>';
        echo '<div class="smf-panel"><h2>Capabilities</h2><ul>';
        foreach (SMF_V3_Capability_Catalog::CAPABILITIES as $cap) {
            echo '<li>' . esc_html($cap) . ': ' . esc_html($checker->can($cap) ? 'enabled' : 'disabled') . '</li>';
        }
        echo '</ul></div>';
        echo '<div class="smf-panel"><h2>Update compatibility</h2><pre>' . esc_html(wp_json_encode($update, JSON_PRETTY_PRINT)) . '</pre></div>';
        echo '<div class="smf-panel"><h2>Telemetry</h2><p>' . esc_html($telemetry['enabled'] ? 'Opt-in aggregate telemetry enabled.' : 'Telemetry disabled (default).') . '</p></div></div>';
    }
}
