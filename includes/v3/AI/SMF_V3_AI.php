<?php
defined('ABSPATH') || exit;

/**
 * V3 AI Merchant Assistant — intelligence layer only, never an autonomous agent.
 * No unrestricted DB access. No secrets/PII in prompts. Execution stays in automation policy path.
 */

interface SMF_V3_AI_Provider_Interface {
    public function complete(array $context, $question);
}

class SMF_V3_AI_Deterministic_Provider implements SMF_V3_AI_Provider_Interface {
    public function complete(array $context, $question) {
        $question = strtolower(sanitize_text_field($question));
        $metrics = $context['metrics'] ?? array();
        $recs = $context['recommendations'] ?? array();
        $courier = $context['courier'] ?? array();
        $answer = 'Insufficient grounded data to answer confidently.';
        $evidence = array();
        $confidence = 40;
        $next = 'Review Decision Center and Diagnostics with a 30-day window.';

        if (strpos($question, 'roas') !== false) {
            $roas = $metrics['roas'] ?? null;
            if ($roas === null) {
                $answer = 'ROAS data is unavailable in the current bounded context.';
            } else {
                $answer = 'Observed ROAS in the current window is ' . $roas . '× (estimate from contribution reporting).';
                $evidence[] = 'roas=' . $roas;
                $confidence = 70;
                $next = 'Compare campaign-level ROAS and contribution profit before changing spend.';
            }
        } elseif (strpos($question, 'campaign') !== false) {
            $answer = $recs ? ('Campaigns needing attention: ' . implode('; ', array_slice($recs, 0, 5))) : 'No campaign attention thresholds were present in the bounded context.';
            $evidence = array_slice($recs, 0, 5);
            $confidence = $recs ? 75 : 50;
            $next = 'Open Decision Center; approve any automation actions explicitly.';
        } elseif (strpos($question, 'return') !== false) {
            $rr = $courier['return_rate'] ?? ($metrics['return_rate'] ?? null);
            $answer = $rr === null ? 'Return-rate data is unavailable.' : ('Observed return-related signal is ' . $rr . ' (heuristic/aggregate).');
            if ($rr !== null) { $evidence[] = 'return_signal=' . $rr; $confidence = 65; }
            $next = 'Review courier intelligence and customer quality for the same window.';
        } elseif (strpos($question, 'courier') !== false) {
            $answer = !empty($courier['recommendation']) ? ('Courier advisory: ' . $courier['recommendation']) : 'Courier intelligence is unavailable in context.';
            if (!empty($courier['provider'])) $evidence[] = 'provider=' . $courier['provider'];
            if (!empty($courier['recommendation'])) $evidence[] = 'recommendation=' . $courier['recommendation'];
            $confidence = !empty($courier['recommendation']) ? 70 : 45;
            $next = 'Do not auto-reassign providers; review Courier Operations.';
        } elseif (strpos($question, 'risk') !== false || strpos($question, 'today') !== false || strpos($question, 'review') !== false) {
            $answer = $recs ? ('Top review items: ' . implode('; ', array_slice($recs, 0, 5))) : 'No high-priority operational recommendations were available.';
            $evidence = array_slice($recs, 0, 5);
            $confidence = $recs ? 70 : 45;
            $next = 'Use Automation in observe/recommend mode; never bypass approval for high-risk actions.';
        }

        return array(
            'answer' => sanitize_text_field($answer),
            'evidence' => array_map('sanitize_text_field', $evidence),
            'confidence' => max(0, min(100, (int) $confidence)),
            'recommended_next_step' => sanitize_text_field($next),
            'provider' => 'deterministic',
            'can_execute' => false,
        );
    }
}

class SMF_V3_AI_Context_Builder {
    public static function build($days = 30) {
        $days = max(1, min(90, absint($days)));
        $metrics = array();
        $recommendations = array();
        $courier = array();
        $alerts = array();

        if (class_exists('SMF_Profitability')) {
            try {
                $report = SMF_Profitability::report($days, 'BDT', 'last_touch');
                $metrics = array(
                    'roas' => isset($report['summary']['roas']) ? round((float) $report['summary']['roas'], 2) : null,
                    'contribution_profit' => isset($report['summary']['contribution_profit']) ? round((float) $report['summary']['contribution_profit'], 2) : null,
                    'margin' => isset($report['summary']['contribution_margin']) ? round((float) $report['summary']['contribution_margin'], 2) : null,
                    'spend' => isset($report['summary']['spend']) ? round((float) $report['summary']['spend'], 2) : null,
                );
            } catch (Throwable $e) {
                $metrics = array();
            }
        }
        if (class_exists('SMF_Decision_Engine')) {
            try {
                $pack = SMF_Decision_Engine::recommendations($days, 'last_touch');
                foreach (array_slice((array) ($pack['recommendations'] ?? array()), 0, 10) as $item) {
                    $recommendations[] = sanitize_text_field(($item['priority'] ?? '') . ' ' . ($item['title'] ?? ''));
                }
            } catch (Throwable $e) {
                // Keep assistant available when optional courier deps are missing in offline tests.
            }
        }
        if (class_exists('SMF_V3_Courier_Intelligence_Engine')) {
            try {
                $report = (new SMF_V3_Courier_Intelligence_Engine())->provider_report($days);
                if (!empty($report['providers'][0])) {
                    $p = $report['providers'][0];
                    $courier = array(
                        'provider' => sanitize_key($p['provider'] ?? ''),
                        'recommendation' => sanitize_text_field($p['recommendation'] ?? ''),
                        'return_rate' => $p['return_rate'] ?? null,
                        'health_score' => $p['health_score'] ?? null,
                    );
                }
            } catch (Throwable $e) {
                $courier = array();
            }
        }
        if (class_exists('SMF_Observability')) {
            try {
                $obs = SMF_Observability::report();
                if (($obs['overall'] ?? '') !== 'healthy') {
                    $alerts[] = 'observability:' . sanitize_key($obs['overall'] ?? 'unknown');
                }
            } catch (Throwable $e) {
                // ignore
            }
        }

        $context = array(
            'days' => $days,
            'metrics' => $metrics,
            'recommendations' => $recommendations,
            'courier' => $courier,
            'alerts' => $alerts,
            'generated_at' => current_time('mysql'),
        );
        return self::redact($context);
    }

    public static function redact(array $context) {
        $blocked = array('password','token','access_token','api_key','secret','authorization','bearer','webhook_secret','payload','email','phone','address');
        array_walk_recursive($context, function (&$value, $key) use ($blocked) {
            if (in_array(sanitize_key((string) $key), $blocked, true)) $value = '[redacted]';
            if (is_string($value) && preg_match('/(Bearer\s+\S+|sk-[A-Za-z0-9]+)/', $value)) $value = '[redacted]';
        });
        return $context;
    }
}

class SMF_V3_AI_Assistant {
    private $provider;

    public function __construct(?SMF_V3_AI_Provider_Interface $provider = null) {
        $this->provider = $provider ?: new SMF_V3_AI_Deterministic_Provider();
    }

    public function ask($question, $days = 30) {
        if (class_exists('SMF_V3_Entitlement_Checker') && !SMF_V3_Entitlement_Checker::check('ai_assistant')) {
            // Still allow local deterministic answers when commercial layer says no? Spec: fail safely.
            // Keep assistant readable with explicit limitation when unentitled.
            if (get_option('smf_v3_commercial_enabled', 'no') === 'yes') {
                return array(
                    'answer' => 'AI assistant capability is not entitled on the current plan.',
                    'evidence' => array(),
                    'confidence' => 100,
                    'recommended_next_step' => 'Upgrade plan or disable commercial gating for local advisory mode.',
                    'can_execute' => false,
                );
            }
        }
        $context = SMF_V3_AI_Context_Builder::build($days);
        $result = $this->provider->complete($context, $question);
        $result['can_execute'] = false;
        $result['execution_path'] = 'AI suggestion → deterministic validation → policy → approval → controlled action';
        $result['context_days'] = $context['days'];
        return $result;
    }

    public function rank_recommendations(array $recommendations) {
        // AI may summarize/rank deterministic recommendations; never override safety policy.
        $out = array();
        foreach ($recommendations as $rec) {
            if ($rec instanceof SMF_V3_Automation_Recommendation) $out[] = $rec->to_array();
            elseif (is_array($rec)) $out[] = $rec;
        }
        usort($out, function ($a, $b) {
            $rank = array('critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3);
            $ra = $rank[sanitize_key($a['severity'] ?? $a['risk'] ?? 'medium')] ?? 9;
            $rb = $rank[sanitize_key($b['severity'] ?? $b['risk'] ?? 'medium')] ?? 9;
            return $ra <=> $rb;
        });
        return array('ranked' => array_slice($out, 0, 20), 'overrides_policy' => false, 'can_execute' => false);
    }
}

class SMF_V3_AI_Service {
    public static function init() {
        if (!class_exists('SMF_V3_Feature_Flag') || !SMF_V3_Feature_Flag::enabled()) return;
        if (get_option('smf_v3_ai_enabled', 'no') !== 'yes') return;
        add_action('admin_menu', array(__CLASS__, 'menu'), 41);
        add_action('admin_post_smf_v3_ai_ask', array(__CLASS__, 'handle_ask'));
    }

    public static function menu() {
        add_submenu_page('sync-meta-flow', 'AI Assistant', 'AI Assistant', 'manage_woocommerce', 'smf-v3-ai', array(__CLASS__, 'page'));
    }

    public static function handle_ask() {
        if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized', 403);
        check_admin_referer('smf_v3_ai_ask');
        $q = sanitize_text_field(wp_unslash($_POST['question'] ?? ''));
        set_transient('smf_v3_ai_last', (new SMF_V3_AI_Assistant())->ask($q, 30), 120);
        wp_safe_redirect(admin_url('admin.php?page=smf-v3-ai'));
        exit;
    }

    public static function page() {
        if (!current_user_can('manage_woocommerce')) return;
        $last = get_transient('smf_v3_ai_last');
        echo '<div class="wrap smf-wrap"><div class="smf-header"><div><h1>AI Assistant</h1><p>Grounded merchant assistant. Never executes actions autonomously.</p></div></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="smf-panel">';
        echo '<input type="hidden" name="action" value="smf_v3_ai_ask">';
        wp_nonce_field('smf_v3_ai_ask');
        echo '<p><label>Question<br><input class="large-text" name="question" placeholder="Why did my ROAS drop?"></label></p>';
        echo '<button class="button button-primary">Ask</button></form>';
        if (is_array($last)) {
            echo '<div class="smf-panel"><h2>Answer</h2><p>' . esc_html($last['answer'] ?? '') . '</p>';
            echo '<p><strong>Confidence:</strong> ' . esc_html((string) ($last['confidence'] ?? 0)) . '</p>';
            echo '<p><strong>Next step:</strong> ' . esc_html($last['recommended_next_step'] ?? '') . '</p>';
            echo '<p class="smf-muted">Evidence: ' . esc_html(implode(' · ', (array) ($last['evidence'] ?? array()))) . '</p></div>';
        }
        echo '<p class="smf-muted">Execution path remains policy-gated automation. AI cannot override safety controls.</p></div>';
    }
}
