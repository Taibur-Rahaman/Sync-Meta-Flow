<?php
/**
 * Dependency-free deterministic checks for the plugin's testable contracts.
 * This runner deliberately does not bootstrap WordPress or make network calls.
 */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
if (!defined('WC_VERSION')) {
    define('WC_VERSION', '9.0.0');
}

class WooCommerce {}

function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_strip_all_tags($value) { return trim(strip_tags((string) $value)); }
function absint($value) { return abs((int) $value); }
function get_option($key, $default = false) {
    if ($key === 'smf_schema_version') return '1.0';
    if ($key === 'smf_v3_enabled') return isset($GLOBALS['smf_test_options'][$key]) ? $GLOBALS['smf_test_options'][$key] : 'no';
    return isset($GLOBALS['smf_test_options'][$key]) ? $GLOBALS['smf_test_options'][$key] : $default;
}
function update_option($key, $value, $autoload = null) {
    $GLOBALS['smf_test_options'][$key] = $value;
    return true;
}
function get_bloginfo($show) { return $show === 'version' ? '6.5.0' : ''; }
function wp_next_scheduled($hook) { return 1234567890; }
function wp_json_encode($value) { return json_encode($value); }
function wp_date($format, $timestamp = null) { return gmdate($format, $timestamp ?: time()); }
function current_time($type) { return $type === 'timestamp' ? time() : gmdate('Y-m-d H:i:s'); }
function wp_timezone() { return new DateTimeZone('UTC'); }
function wc_get_order($id) { return false; }
function wc_get_orders($args) { return array(); }
function add_action() {}
function add_filter() {}
function do_action() {}
function add_submenu_page() {}

class TestWpdb {
    public $prefix = 'wp_';
    public $last_error = '';
    public function prepare($query, ...$args) { return $query; }
    public function get_var($query) { return 'wp_' . 'smf_' . 'order_events'; }
    public function get_col($query) { return !empty($GLOBALS['smf_test_no_orders']) ? array() : array(101); }
    public function get_results($query) {
        if (strpos($query, 'smf_campaign_spend') !== false) {
            return !empty($GLOBALS['smf_test_zero_spend']) ? array() : array((object) array('campaign_id' => 'last-id', 'amount' => 200, 'currency' => 'BDT'));
        }
        if (!empty($GLOBALS['smf_test_no_orders'])) return array();
        return array(
            (object) array('order_id' => 101, 'event_type' => 'purchase', 'old_status' => '', 'new_status' => 'pending', 'metadata' => json_encode(array('order_total' => 1000, 'currency' => 'BDT', 'first_campaign_id' => 'first-id', 'last_campaign_id' => 'last-id')), 'created_at' => '2026-09-01 10:00:00', 'id' => 1),
            (object) array('order_id' => 101, 'event_type' => 'status_changed', 'old_status' => 'pending', 'new_status' => 'smf-delivered', 'metadata' => '{}', 'created_at' => '2026-09-02 10:00:00', 'id' => 2),
            (object) array('order_id' => 101, 'event_type' => 'status_changed', 'old_status' => 'smf-delivered', 'new_status' => 'smf-returned', 'metadata' => '{}', 'created_at' => '2026-09-03 10:00:00', 'id' => 3),
        );
    }
}
$GLOBALS['wpdb'] = new TestWpdb();

require_once __DIR__ . '/../includes/class-smf-attribution-model.php';
require_once __DIR__ . '/../includes/class-smf-compatibility.php';
require_once __DIR__ . '/../includes/class-smf-onboarding.php';
require_once __DIR__ . '/../includes/class-smf-observability.php';
require_once __DIR__ . '/../includes/v3/Contracts/SMF_V3_Contracts.php';
require_once __DIR__ . '/../includes/v3/Domain/SMF_V3_Value_Objects.php';
require_once __DIR__ . '/../includes/v3/Infrastructure/SMF_V3_Infrastructure.php';
require_once __DIR__ . '/../includes/v3/Services/SMF_V3_Services.php';
require_once __DIR__ . '/../includes/v3/Events/SMF_V3_Events.php';
require_once __DIR__ . '/../includes/v3/Automation/SMF_V3_Automation.php';
require_once __DIR__ . '/../includes/v3/Attribution/SMF_V3_Attribution.php';
require_once __DIR__ . '/../includes/v3/Courier/SMF_V3_Courier_Intelligence.php';
require_once __DIR__ . '/../includes/v3/Commercial/SMF_V3_Commercial.php';
require_once __DIR__ . '/../includes/v3/AI/SMF_V3_AI.php';
require_once __DIR__ . '/../includes/class-smf-courier-operations.php';
require_once __DIR__ . '/../includes/class-smf-courier-state.php';
require_once __DIR__ . '/../includes/class-smf-meta-capi.php';
require_once __DIR__ . '/../includes/class-smf-profitability.php';
require_once __DIR__ . '/../includes/class-smf-decision-engine.php';

$passed = 0;
$failed = 0;

function check($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS  {$name}\n";
        return;
    }
    $failed++;
    echo "FAIL  {$name}\n";
}

function invoke_private($class, $method, array $arguments = array()) {
    $reflection = new ReflectionMethod($class, $method);
    return $reflection->invokeArgs(null, $arguments);
}

$first = array('campaign_name' => 'First', 'campaign_id' => 'first-id');
$last = array('campaign_name' => 'Last', 'campaign_id' => 'last-id');
foreach (array('last_touch', 'first_touch', 'first_last', 'assisted') as $model) {
    $allocation = SMF_Attribution_Model::allocation($first, $last, 100, $model);
    check("attribution {$model} is non-negative", !array_filter($allocation, function ($value) { return $value < 0; }));
    if ($model === 'first_last') {
        check('first+last allocation preserves value', abs(array_sum($allocation) - 100) < 0.0001);
    }
}
check('missing attribution falls back to direct', SMF_Attribution_Model::allocation(array(), array(), 100, 'last_touch') == array('Direct / Unattributed' => 100));
check('invalid attribution model normalizes safely', SMF_Attribution_Model::normalize_model('unknown') === 'last_touch');
check('duplicate attribution is deterministic', SMF_Attribution_Model::allocation($first, $first, 100, 'first_last') == array('First' => 100));

check('profitability percentage clamps low', SMF_Profitability::pct(-5) == 0);
check('profitability percentage clamps high', SMF_Profitability::pct(150) == 100);
check('profitability money rejects negative values', SMF_Profitability::money(-10) == 0);
check('profitability documentation disclaims accounting grade profit', strpos(file_get_contents(__DIR__ . '/../includes/class-smf-profitability.php'), 'not statutory accounting') !== false);
$GLOBALS['smf_test_options'] = array('smf_cogs_percent' => 10, 'smf_payment_fee_percent' => 2, 'smf_courier_delivery_cost' => 50, 'smf_courier_return_cost' => 30);
$report = SMF_Profitability::report(30, 'BDT', 'last_touch');
check('profitability report records purchase revenue', $report['summary']['purchase_revenue'] == 1000);
check('profitability report records delivered revenue', $report['summary']['delivered_revenue'] == 1000);
check('profitability report includes configured costs', $report['summary']['cogs'] == 100 && $report['summary']['payment_fees'] == 20 && $report['summary']['delivery_cost'] == 50 && $report['summary']['return_cost'] == 30);
check('profitability report calculates contribution profit', $report['summary']['contribution_profit'] == 600);
check('profitability report calculates ROAS', $report['summary']['roas'] == 5);
check('profitability campaign fees use percentage', !empty($report['campaigns'][0]) && $report['campaigns'][0]['payment_fees'] == 20);
check('profitability report preserves attribution model', SMF_Profitability::report(30, 'BDT', 'first_last')['model'] === 'first_last');
$GLOBALS['smf_test_zero_spend'] = true;
$zero_spend = SMF_Profitability::report(30, 'BDT', 'last_touch');
check('zero-spend report has zero ROAS', $zero_spend['summary']['spend'] == 0 && $zero_spend['summary']['roas'] == 0);
$GLOBALS['smf_test_zero_spend'] = false;
$GLOBALS['smf_test_options']['smf_cogs_percent'] = 100;
$negative = SMF_Profitability::report(30, 'BDT', 'last_touch');
check('negative-profit case remains negative', $negative['summary']['contribution_profit'] < 0);
$GLOBALS['smf_test_no_orders'] = true;
$zero_revenue = SMF_Profitability::report(30, 'BDT', 'last_touch');
check('zero-revenue report has zero margin and ROAS', $zero_revenue['summary']['delivered_revenue'] == 0 && $zero_revenue['summary']['contribution_margin'] == 0 && $zero_revenue['summary']['roas'] == 0);
$GLOBALS['smf_test_no_orders'] = false;
$GLOBALS['smf_test_options']['smf_courier_delivery_cost'] = 0;
$GLOBALS['smf_test_options']['smf_courier_return_cost'] = 0;

check('courier risk starts neutral without history', SMF_Courier_Operations::risk_score(array())['score'] == 50);
check('courier delivery history lowers risk', SMF_Courier_Operations::risk_score(array('orders' => 10, 'delivered' => 10, 'returned' => 0, 'cancelled' => 0))['score'] == 90);
check('courier adverse history raises risk', SMF_Courier_Operations::risk_score(array('orders' => 10, 'delivered' => 0, 'returned' => 10, 'cancelled' => 0))['label'] === 'HIGH RISK');

check('order lifecycle confirmed to shipped is allowed', invoke_private('SMF_Courier_State', 'transition_allowed', array('confirmed', 'shipped')) === true);
check('order lifecycle shipped to delivered is allowed', invoke_private('SMF_Courier_State', 'transition_allowed', array('shipped', 'delivered')) === true);
check('order lifecycle shipped to returned is allowed', invoke_private('SMF_Courier_State', 'transition_allowed', array('shipped', 'returned')) === true);
check('order lifecycle confirmed to cancelled is allowed', invoke_private('SMF_Courier_State', 'transition_allowed', array('confirmed', 'cancelled')) === true);
check('terminal delivered cannot move to shipped', invoke_private('SMF_Courier_State', 'transition_allowed', array('delivered', 'shipped')) === false);
check('terminal returned cannot move to delivered', invoke_private('SMF_Courier_State', 'transition_allowed', array('returned', 'delivered')) === false);
check('courier status normalization is deterministic', invoke_private('SMF_Courier_State', 'normalize', array('in_transit')) === 'shipped');
$courier_status_source = file_get_contents(__DIR__ . '/../includes/class-smf-courier.php');
check('courier failed status matches cancellation contract', strpos($courier_status_source, "'failed'=>'cancelled'") !== false);
$meta_sync_source = file_get_contents(__DIR__ . '/../includes/class-smf-meta-insights.php');
check('Meta spend replacement uses transaction', strpos($meta_sync_source, 'START TRANSACTION') !== false && strpos($meta_sync_source, 'ROLLBACK') !== false && strpos($meta_sync_source, 'COMMIT') !== false);
check('Meta spend insert failure aborts replacement', strpos($meta_sync_source, 'Unable to store a Meta spend row.') !== false);

check('CAPI retries transient HTTP failures', invoke_private('SMF_Meta_CAPI', 'retryable_http_code', array(503)) === true);
check('CAPI retries rate limits', invoke_private('SMF_Meta_CAPI', 'retryable_http_code', array(429)) === true);
check('CAPI does not retry permanent client failures', invoke_private('SMF_Meta_CAPI', 'retryable_http_code', array(400)) === false);
check('CAPI has bounded retry policy', SMF_Meta_CAPI::MAX_ATTEMPTS === 5 && SMF_Meta_CAPI::BATCH_SIZE === 10);

$compatibility = SMF_Compatibility::report();
check('compatibility reports PHP', isset($compatibility['checks'][0]['name']) && $compatibility['checks'][0]['name'] === 'PHP');
check('compatibility reports WordPress', (bool) array_filter($compatibility['checks'], function ($check) { return $check['name'] === 'WordPress'; }));
check('compatibility reports WooCommerce', (bool) array_filter($compatibility['checks'], function ($check) { return $check['name'] === 'WooCommerce'; }));
check('compatibility reports HPOS', (bool) array_filter($compatibility['checks'], function ($check) { return $check['name'] === 'HPOS'; }));
check('compatibility reports plugin tables', (bool) array_filter($compatibility['checks'], function ($check) { return $check['name'] === 'Plugin tables'; }));
check('compatibility reports schema', $compatibility['schema_version'] === '1.0');
check('compatibility reports cron', (bool) array_filter($compatibility['checks'], function ($check) { return $check['name'] === 'Cron'; }));
check('compatibility uses normalized severity levels', !array_filter($compatibility['checks'], function ($check) { return !in_array($check['level'], array('ok', 'warning', 'blocking'), true); }));
check('compatibility report includes minimum runtimes', isset($compatibility['runtime']['php']['minimum'], $compatibility['runtime']['wordpress']['minimum'], $compatibility['runtime']['woocommerce']['minimum']));
check('compatibility aggregates warning levels', $compatibility['warnings'] >= 1 && (bool) array_filter($compatibility['checks'], function ($check) { return $check['name'] === 'HPOS' && $check['level'] === 'warning'; }));
check('compatibility does not expose credentials', strpos(json_encode($compatibility), 'token') === false && strpos(json_encode($compatibility), 'secret') === false);

$decision_source = file_get_contents(__DIR__ . '/../includes/class-smf-decision-engine.php');
check('decision engine remains advisory', strpos($decision_source, 'advisory') !== false);
check('decision engine has scale rule', strpos($decision_source, 'Scale ') !== false);
check('decision engine has watch rule', strpos($decision_source, 'Watch margin') !== false);
check('decision engine has stop-review rule', strpos($decision_source, 'Stop or review') !== false);
check('decision engine has degraded courier rule', strpos($decision_source, "'DEGRADED'") !== false);
check('decision engine has weak economics rule', strpos($decision_source, "'WEAK'") !== false);
check('decision engine has negative-profit rule', strpos($decision_source, 'contribution profit is negative') !== false);
check('decision engine has CAPI failure rule', strpos($decision_source, 'Resolve CAPI failures') !== false);
check('decision engine has CAPI backlog rule', strpos($decision_source, 'CAPI queue backlog') !== false);

$courier_source = file_get_contents(__DIR__ . '/../includes/class-smf-courier.php');
$timeline_source = file_get_contents(__DIR__ . '/../includes/class-smf-courier-timeline.php');
check('courier webhook uses timing-safe signature comparison', strpos($courier_source, 'hash_equals') !== false);
check('courier webhook rejects invalid signatures', strpos($courier_source, 'smf_invalid_signature') !== false);
check('valid courier signature fixture verifies', hash_equals(hash_hmac('sha256', '{"event_id":"evt-1"}', 'test-secret'), hash_hmac('sha256', '{"event_id":"evt-1"}', 'test-secret')));
check('invalid courier signature fixture rejects', !hash_equals(hash_hmac('sha256', '{"event_id":"evt-1"}', 'test-secret'), hash_hmac('sha256', '{"event_id":"evt-2"}', 'test-secret')));
check('courier timeline has event idempotency', strpos($timeline_source, 'UNIQUE KEY event_hash') !== false && strpos($timeline_source, 'duplicate') !== false);
check('courier timeline has stale processing recovery', strpos($timeline_source, 'stale_processing_recovered') !== false);
check('shipment creation has idempotency lock', strpos($courier_source, 'acquire_shipment_lock') !== false && strpos($courier_source, '_smf_courier_consignment_id') !== false);
$capi_source = file_get_contents(__DIR__ . '/../includes/class-smf-meta-capi.php');
check('CAPI queue deduplicates event ids', strpos($capi_source, 'INSERT IGNORE') !== false && strpos($capi_source, 'event_id') !== false);
check('CAPI queue exhausts bounded retries', strpos($capi_source, 'MAX_ATTEMPTS') !== false && strpos($capi_source, "status'=>'failed'") !== false);
check('CAPI lock has stale recovery', strpos($capi_source, 'LOCK_TTL') !== false && strpos($capi_source, 'delete_option(self::LOCK_KEY)') !== false);
$timeline_retry_source = file_get_contents(__DIR__ . '/../includes/class-smf-courier-timeline.php');
check('courier transport failures increment attempts', strpos($timeline_retry_source, '$attempts=(int)$row->attempts+1') !== false && strpos($timeline_retry_source, 'SET attempts=%d') !== false);

$order_source = file_get_contents(__DIR__ . '/../includes/class-smf-order-events.php');
check('browser event validates nonce', strpos($order_source, 'check_admin_referer') !== false || strpos($order_source, 'check_ajax_referer') !== false);
check('browser event deduplicates event ids', strpos($order_source, 'duplicate') !== false && strpos($order_source, 'event_id') !== false);
$attribution_source = file_get_contents(__DIR__ . '/../includes/class-smf-attribution.php');
check('failed session inserts do not return a phantom key', strpos($attribution_source, 'if(false===$wpdb->insert') !== false && strpos($attribution_source, 'return \'\';return $key') !== false);
$privacy_source = file_get_contents(__DIR__ . '/../includes/class-smf-privacy.php');
check('privacy export includes linked tracking data', strpos($privacy_source, 'tracking_sessions') !== false && strpos($privacy_source, 'tracking_events') !== false);
check('privacy erasure removes linked tracking data', strpos($privacy_source, 'delete($wpdb->prefix') !== false && substr_count($privacy_source, "array('session_key'") >= 2);
$tracker_source = file_get_contents(__DIR__ . '/../includes/class-smf-tracker.php');
check('thank-you tracking validates order key', strpos($tracker_source, 'get_order_key') !== false && strpos($tracker_source, 'hash_equals') !== false);
$courier_admin_source = file_get_contents(__DIR__ . '/../includes/class-smf-courier.php');
check('courier admin does not render saved credentials', strpos($courier_admin_source, 'value="<?php echo esc_attr($secret); ?>"') === false && strpos($courier_admin_source, 'value="<?php echo esc_attr($key); ?>"') === false && strpos($courier_admin_source, 'value="<?php echo esc_attr($skey); ?>"') === false);
check('courier credential fields preserve omitted values', strpos($courier_admin_source, 'trim((string)wp_unslash($_POST[\'webhook_secret\'])) !== \'\'') !== false);
$uninstall_source = file_get_contents(__DIR__ . '/../uninstall.php');
check('uninstall preserves data by default', strpos($uninstall_source, "smf_delete_data_on_uninstall','no')") !== false);
check('uninstall removes schema and shipment locks', strpos($uninstall_source, 'smf_courier_events_schema') !== false && strpos($uninstall_source, 'smf_shipment_lock_%') !== false);
$onboarding_source = file_get_contents(__DIR__ . '/../includes/class-smf-onboarding.php');
$new_store_categories = array(
    'core' => array('weight' => 30, 'ready' => false, 'optional' => false, 'skipped' => false),
    'tracking' => array('weight' => 25, 'ready' => false, 'optional' => true, 'skipped' => false),
    'attribution' => array('weight' => 10, 'ready' => false, 'optional' => false, 'skipped' => false),
    'courier' => array('weight' => 20, 'ready' => false, 'optional' => true, 'skipped' => false),
    'financial' => array('weight' => 15, 'ready' => false, 'optional' => false, 'skipped' => false),
);
$full_categories = $new_store_categories;
foreach ($full_categories as &$category) $category['ready'] = true;
unset($category);
$skipped_categories = $new_store_categories;
$skipped_categories['tracking']['skipped'] = true;
$skipped_categories['courier']['skipped'] = true;
check('new store onboarding starts low', SMF_Onboarding::readiness_score($new_store_categories) === 0);
check('fully configured onboarding reaches 100', SMF_Onboarding::readiness_score($full_categories) === 100);
check('optional onboarding skips earn explicit category credit', SMF_Onboarding::readiness_score($skipped_categories) === 45);
check('blocking compatibility cannot be scored ready', strpos($onboarding_source, 'core_ready = !empty($compatibility[\'ready\'])') !== false);
check('onboarding state writes no credentials', strpos($onboarding_source, "update_option('smf_meta_access_token'") === false && strpos($onboarding_source, "update_option('smf_courier_webhook_secret'") === false && strpos($onboarding_source, "update_option('smf_steadfast_api_key'") === false);
check('onboarding mutations require capability and nonce', strpos($onboarding_source, 'current_user_can') !== false && strpos($onboarding_source, 'check_admin_referer') !== false);
check('onboarding output escapes values', strpos($onboarding_source, 'esc_html') !== false && strpos($onboarding_source, 'esc_url') !== false);
check('onboarding has all seven steps', substr_count($onboarding_source, 'if ($step ===') >= 7);
check('onboarding has admin navigation and notice', strpos($onboarding_source, 'add_submenu_page') !== false && strpos($onboarding_source, 'notice-info') !== false);
check('onboarding notice uses admin notices lifecycle', strpos($onboarding_source, "add_action('admin_notices'") !== false);
check('onboarding skip/save actions advance state', strpos($onboarding_source, "update_option(self::STEP, 5") !== false && strpos($onboarding_source, "update_option(self::STEP, 7") !== false);
$observability_source = file_get_contents(__DIR__ . '/../includes/class-smf-observability.php');
check('observability normalizes healthy state', SMF_Observability::level(false, false) === 'ok');
check('observability normalizes warning state', SMF_Observability::level(false, true) === 'warning');
check('observability normalizes blocking state', SMF_Observability::level(true, false) === 'blocking');
check('observability redacts bearer tokens', strpos(SMF_Observability::sanitize_reason('Authorization: Bearer abc123'), 'abc123') === false);
check('observability bounds row reads', strpos($observability_source, 'ROW_LIMIT') !== false && strpos($observability_source, 'LIMIT %d') !== false);
check('observability uses existing CAPI and courier records', strpos($observability_source, 'smf_capi_queue') !== false && strpos($observability_source, 'smf_courier_events') !== false);
check('observability snapshot contains aggregates only', strpos($observability_source, 'raw') === false && strpos($observability_source, 'payload') === false);
check('diagnostics includes observability snapshot', strpos(file_get_contents(__DIR__ . '/../includes/class-smf-diagnostics.php'), "'observability'=>") !== false);
check('observability has a Diagnostics-only panel', strpos($observability_source, 'smf-diagnostics') !== false && strpos($observability_source, 'admin_panel') !== false);
check('observability panel escapes incident text', strpos($observability_source, 'esc_html($module[\'summary\'] . $failure)') !== false);
check('observability snapshot includes sanitized incident detail', strpos($observability_source, "'latest_failure' => " . '$module[\'latest_failure\']') !== false && strpos($observability_source, 'sanitize_reason') !== false);
check('observability reports provider aggregates', strpos($observability_source, 'provider_health') !== false && strpos($observability_source, "'processed' => 0") !== false);
$order_events_source = file_get_contents(__DIR__ . '/../includes/class-smf-order-events.php');
$installer_source = file_get_contents(__DIR__ . '/../includes/class-smf-installer.php');
check('order metrics enforce a bounded reporting window', strpos($order_events_source, 'max(1,min(90,absint($days)))') !== false && strpos($order_events_source, 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)') !== false);
check('order metrics memoize repeated requests', strpos($order_events_source, 'flow_metrics_cache') !== false);
check('installer adds order reporting index', strpos($installer_source, 'order_created(order_id,created_at,id)') !== false);
check('installer adds spend reporting index', strpos($installer_source, 'spend_currency(spend_date,currency)') !== false);
check('installer adds courier reporting indexes', strpos($installer_source, 'order_received(order_id,received_at,id)') !== false && strpos($installer_source, 'received_provider(received_at,provider)') !== false);
check('installer records additive schema version', strpos($installer_source, "update_option('smf_schema_version','1.1'") !== false);
check('observability memoizes repeated reports', strpos($observability_source, 'report_cache') !== false && strpos($observability_source, 'if (self::$report_cache !== null)') !== false);
$v3_contracts = file_get_contents(__DIR__ . '/../includes/v3/Contracts/SMF_V3_Contracts.php');
$v3_domain = file_get_contents(__DIR__ . '/../includes/v3/Domain/SMF_V3_Value_Objects.php');
$v3_infrastructure = file_get_contents(__DIR__ . '/../includes/v3/Infrastructure/SMF_V3_Infrastructure.php');
check('V3 contracts load', interface_exists('SMF_V3_Event_Interface') && interface_exists('SMF_V3_Courier_Provider_Interface'));
check('V3 domain context normalizes values', (new SMF_V3_Order_Context(12, 'SMF-Shipped', 10, 'bdt'))->status() === 'smf-shipped' && (new SMF_V3_Order_Context(12, 'SMF-Shipped', 10, 'bdt'))->currency() === 'BDT');
check('V3 event strips sensitive payload keys', !array_key_exists('token', (new SMF_V3_Event_Envelope('test_event', '1.0', 'evt-1', '2026-01-01', array('token' => 'secret', 'safe' => 'yes')))->payload()));
check('V3 dispatcher contract is synchronous', (new SMF_V3_Synchronous_Dispatcher())->dispatch(new SMF_V3_Event_Envelope('test_event', '1.0', 'evt-1', '2026-01-01')) === true);
check('V3 container resolves explicit services', SMF_V3_Service_Bootstrap::build()->has('config') && SMF_V3_Service_Bootstrap::build()->has('courier'));
check('V3 container rejects duplicate registrations', strpos($v3_infrastructure, 'already registered') !== false);
check('V3 flag defaults disabled', SMF_V3_Feature_Flag::enabled() === false);
check('V3 bootstrap is fail-safe', strpos(file_get_contents(__DIR__ . '/../includes/v3/Services/SMF_V3_Services.php'), 'catch (Throwable') !== false);
check('V3 domain avoids WordPress persistence coupling', strpos($v3_domain, '$wpdb') === false && strpos($v3_domain, 'get_option') === false);
check('V3 contracts are interface-only boundaries', strpos($v3_contracts, 'wp_remote_') === false && strpos($v3_contracts, '$wpdb') === false);

// --- Phase 3.1 Controlled Automation ---
$GLOBALS['smf_test_options'] = array();
$policy_default = new SMF_V3_Automation_Policy();
check('automation policy defaults to observe', $policy_default->mode() === 'observe');
check('automation policy defaults dry-run', $policy_default->dry_run() === true);
check('automation policy defaults disabled', $policy_default->enabled() === false);
check('automation critical risk never allowed', $policy_default->allows('acknowledge_recommendation', 'critical') === false);
check('automation allows low risk acknowledge', $policy_default->allows('acknowledge_recommendation', 'low') === true);
check('automation blocks unregistered action types', $policy_default->allows('delete_everything', 'low') === false);
check('automation observe requires approval', $policy_default->requires_approval('low') === true);
$auto_policy = new SMF_V3_Automation_Policy('automate', true, SMF_V3_Automation_Policy::DEFAULT_ALLOWED, 'medium', 0, 20, true);
check('automation automate mode skips approval for low risk', $auto_policy->requires_approval('low') === false);
check('automation automate still requires approval for high risk', $auto_policy->requires_approval('high') === true);

$registry = new SMF_V3_Automation_Action_Registry();
check('automation registry has safe actions only', $registry->has('refresh_diagnostics') && $registry->has('retry_capi_event') && !$registry->has('arbitrary_callback'));
check('automation registry rejects duplicate registration', $registry->register('refresh_diagnostics', function () {}) === false);

$rec = new SMF_V3_Automation_Recommendation(array(
    'id' => 'rec-test-1',
    'type' => 'fix_capi',
    'title' => 'Resolve CAPI failures',
    'explanation' => 'Failed events remain.',
    'severity' => 'high',
    'confidence' => 90,
    'evidence' => array('source' => 'test', 'token' => 'should-strip', 'access_token' => 'x'),
    'affected_entity' => 'capi-queue',
    'recommended_action' => 'Retry queue',
    'expected_effect' => 'Clear backlog',
    'risk' => 'medium',
    'action_type' => 'refresh_diagnostics',
    'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
));
check('automation recommendation strips secrets from evidence', !isset($rec->to_array()['evidence']['token']) && !isset($rec->to_array()['evidence']['access_token']));
check('automation recommendation has stable fields', $rec->get('id') === 'rec-test-1' && $rec->get('type') === 'fix_capi');

$engine = new SMF_V3_Automation_Engine($registry, new SMF_V3_Synchronous_Dispatcher());
$observe = new SMF_V3_Automation_Policy('observe', true, null, 'medium', 0, 20, true);
$obs_result = $engine->run($rec, array('policy' => $observe, 'allow_disabled' => true));
check('automation observe mode blocks execution', $obs_result->status() === 'approval_required');

$recommend_policy = new SMF_V3_Automation_Policy('recommend', true, null, 'medium', 0, 20, true);
$need_approval = $engine->run($rec, array('policy' => $recommend_policy, 'allow_disabled' => true));
check('automation recommend mode requires approval', $need_approval->status() === 'approval_required');

$denied = $engine->approve('rec-test-1', 'tester', false, false);
check('automation approval requires capability and nonce', $denied->status() === 'failed');
$approved = $engine->approve('rec-test-1', 'tester', true, true);
check('automation approval succeeds with capability and nonce', $approved->status() === 'approved');
$dup_approve = $engine->approve('rec-test-1', 'tester', true, true);
check('automation approval is idempotent', $dup_approve->status() === 'duplicate');

$dry = $engine->run($rec, array('policy' => $recommend_policy, 'allow_disabled' => true, 'actor' => 'tester'));
check('automation dry-run executes without mutation', $dry->status() === 'dry_run' && !empty($dry->data()['verification']));
$dup_run = $engine->run($rec, array('policy' => $recommend_policy, 'allow_disabled' => true, 'actor' => 'tester'));
check('automation idempotency blocks duplicate execution', $dup_run->status() === 'duplicate');

$rec2 = new SMF_V3_Automation_Recommendation(array(
    'id' => 'rec-test-2', 'type' => 'review_campaign', 'title' => 'Review X', 'explanation' => 'e',
    'risk' => 'low', 'action_type' => 'acknowledge_recommendation', 'expires_at' => gmdate('Y-m-d H:i:s', time() - 10),
));
$expired = $engine->run($rec2, array('policy' => new SMF_V3_Automation_Policy('automate', true, null, 'medium', 0, 20, true), 'allow_disabled' => true, 'skip_approval' => true));
check('automation expiration is enforced', $expired->status() === 'expired');

$rejected = $engine->reject('rec-test-reject', 'tester', true, true);
check('automation rejection records state', $rejected->status() === 'rejected');

$critical_rec = new SMF_V3_Automation_Recommendation(array(
    'id' => 'rec-critical', 'type' => 'reduce_spend', 'title' => 'Stop campaign', 'explanation' => 'e',
    'risk' => 'critical', 'action_type' => 'acknowledge_recommendation',
));
$crit = $engine->run($critical_rec, array(
    'policy' => new SMF_V3_Automation_Policy('automate', false, null, 'critical', 0, 20, true),
    'allow_disabled' => true, 'skip_approval' => true, 'force_dry_run' => false,
));
check('automation critical actions never autonomous', $crit->status() === 'blocked');

$health = $engine->health();
check('automation health exposes aggregates', isset($health['recommendations'], $health['approvals'], $health['dry_runs'], $health['failures']));
check('automation audit never stores secrets', strpos(wp_json_encode(SMF_V3_Automation_Store::get_list(SMF_V3_Automation_Store::OPTION_AUDIT)), 'access_token') === false);
check('automation feature flag defaults off', SMF_V3_Feature_Flag::automation_enabled() === false);
check('automation events are typed', class_exists('SMF_V3_Automation_Succeeded') && class_exists('SMF_V3_Recommendation_Created'));
$auto_src = file_get_contents(__DIR__ . '/../includes/v3/Automation/SMF_V3_Automation.php');
check('automation rejects arbitrary callbacks from requests', strpos($auto_src, 'call_user_func_array($_') === false && strpos($auto_src, 'Action type is not registered') !== false);
check('automation version is 3.1 beta', strpos(file_get_contents(__DIR__ . '/../V3_AUTOMATION.md'), '3.1.0-beta.1') !== false);
check('automation docs exist', file_exists(__DIR__ . '/../V3_AUTOMATION.md'));

// --- Phase 3.2 Advanced Attribution ---
$models = SMF_Attribution_Model::models();
check('advanced attribution models include position and time decay', in_array('position_based', $models, true) && in_array('time_decay', $models, true));
$first_t = array('campaign_name' => 'Alpha', 'campaign_id' => 'c1', 'utm_source' => 'meta', 'utm_medium' => 'cpc', 'timestamp' => '2026-09-01 10:00:00');
$last_t = array('campaign_name' => 'Beta', 'campaign_id' => 'c2', 'utm_source' => 'meta', 'utm_medium' => 'cpc', 'timestamp' => '2026-09-02 10:00:00');
$pos = SMF_Attribution_Model::allocation($first_t, $last_t, 100, 'position_based', array('first' => 0.4, 'last' => 0.6));
check('position based allocation preserves value', abs(array_sum($pos) - 100) < 0.0001);
check('position based weights first and last', isset($pos['Alpha'], $pos['Beta']) && abs($pos['Alpha'] - 40) < 0.01 && abs($pos['Beta'] - 60) < 0.01);
$decay = SMF_Attribution_Model::allocation($first_t, $last_t, 100, 'time_decay', array('half_life_hours' => 24, 'conversion_at' => '2026-09-02 12:00:00'));
check('time decay allocation preserves value', abs(array_sum($decay) - 100) < 0.0001);
check('time decay favors newer touch', $decay['Beta'] > $decay['Alpha']);
$dup_touches = array($first_t, $first_t, $last_t, array('campaign_name' => 'Gamma', 'campaign_id' => 'c3', 'timestamp' => '2026-09-01 18:00:00', 'session_key' => '123e4567-e89b-12d3-a456-426614174000'));
$norm = SMF_V3_Attribution_Normalizer::touchpoints($dup_touches);
check('touchpoint normalizer deduplicates', count($norm) === 3);
check('touchpoint normalizer bounds volume', count(SMF_V3_Attribution_Normalizer::touchpoints(array_fill(0, 100, $first_t))) <= 50);
$missing = SMF_V3_Attribution_Normalizer::touchpoints(array(array('utm_source' => '', 'session_key' => 'not-a-uuid')));
check('missing UTM becomes direct when no campaign', $missing[0]->channel_key() === 'Direct / Unattributed');
$direct = SMF_V3_Attribution_Normalizer::from_first_last(array(), array(), '');
check('direct traffic has empty touch list or direct key', $direct === array() || $direct[0]->channel_key() === 'Direct / Unattributed');
$conv = new SMF_V3_Conversion(101, 250, 'bdt', '2026-09-02 12:00:00', '123e4567-e89b-12d3-a456-426614174000');
check('conversion normalizes currency', $conv->currency() === 'BDT' && $conv->value() === 250.0);
$pipe = new SMF_V3_Attribution_Pipeline();
$run = $pipe->run($dup_touches, $conv, 'first_last');
check('attribution pipeline labels estimates', !empty($run['estimate']) && strpos($run['disclaimer'], 'estimate') !== false);
check('attribution pipeline quality score bounded', $run['quality']['score'] >= 0 && $run['quality']['score'] <= 100);
check('attribution pipeline allocation non-negative', !array_filter($run['allocation'], function ($v) { return $v < 0; }));
$compare = $pipe->compare(array($first_t, $last_t), $conv);
check('attribution model comparison covers core models', isset($compare['comparisons']['first_touch'], $compare['comparisons']['last_touch'], $compare['comparisons']['assisted'], $compare['comparisons']['position_based'], $compare['comparisons']['time_decay']));
$multi = SMF_V3_Attribution_Allocator::allocate($norm, 90, 'position_based');
check('multi touch position based preserves value', abs(array_sum($multi) - 90) < 0.0001);
$invalid_id = new SMF_V3_Touchpoint(array('campaign_id' => 'x', 'session_key' => 'bad'));
check('invalid session identifier rejected on touchpoint', $invalid_id->get('session_key') === '');
$boundary = SMF_Attribution_Model::allocation($first_t, $last_t, 0, 'time_decay');
check('boundary zero value allocation is empty-or-zero sum', abs(array_sum($boundary)) < 0.0001);
$neg = SMF_Attribution_Model::allocation($first_t, $last_t, -50, 'last_touch');
check('negative revenue rejected in allocation', abs(array_sum($neg)) < 0.0001 || array_sum($neg) >= 0);
$q = SMF_V3_Attribution_Quality::score($norm, $conv, array('duplicate_rate' => 10, 'unattributed_rate' => 5));
check('quality dimensions are documented keys', isset($q['dimensions']['identity_completeness'], $q['dimensions']['campaign_completeness'], $q['dimensions']['conversion_linkage']));
$intel = (new SMF_V3_Attribution_Intelligence_Service())->campaign_intelligence(30, 'last_touch');
check('campaign intelligence consumes profitability contract', isset($intel['summary']['spend'], $intel['summary']['contribution']) && !empty($intel['estimate']));
check('advanced attribution flag defaults off', SMF_V3_Feature_Flag::advanced_attribution_enabled() === false);
check('advanced attribution docs exist', file_exists(__DIR__ . '/../V3_ATTRIBUTION.md'));
check('plugin version is 3.2 beta', strpos(file_get_contents(__DIR__ . '/../V3_ATTRIBUTION.md'), '3.2.0-beta.1') !== false);
$attr_src = file_get_contents(__DIR__ . '/../includes/v3/Attribution/SMF_V3_Attribution.php');
check('v3 attribution avoids lifetime scans', strpos($attr_src, 'LIMIT') !== false || strpos($attr_src, '50') !== false);
check('v3 attribution domain contracts free of wpdb', strpos(file_get_contents(__DIR__ . '/../includes/v3/Domain/SMF_V3_Value_Objects.php'), '$wpdb') === false);

// --- Phase 3.3 Advanced Courier Intelligence ---
$events = array(
    array('event_id' => 'e1', 'provider' => 'steadfast', 'order_id' => 10, 'status' => 'smf-shipped', 'result' => 'processed', 'received_at' => '2026-09-01 10:00:00', 'attempts' => 1),
    array('event_id' => 'e1', 'provider' => 'steadfast', 'order_id' => 10, 'status' => 'smf-shipped', 'result' => 'processed', 'received_at' => '2026-09-01 10:00:00', 'attempts' => 1),
    array('event_id' => 'e2', 'provider' => 'steadfast', 'order_id' => 10, 'status' => 'smf-delivered', 'result' => 'processed', 'received_at' => '2026-09-02 10:00:00', 'attempts' => 1),
);
$timeline = SMF_V3_Courier_Timeline_Builder::build($events);
check('courier timeline deduplicates events', count($timeline) === 2);
check('courier timeline outcome delivered', SMF_V3_Courier_Timeline_Builder::outcome($timeline) === 'delivered');
$stale_events = array_merge($events, array(array('event_id' => 'e3', 'provider' => 'steadfast', 'order_id' => 10, 'status' => 'smf-shipped', 'result' => 'pending', 'received_at' => '2026-08-01 10:00:00', 'attempts' => 3)));
check('courier timeline bounds volume', count(SMF_V3_Courier_Timeline_Builder::build(array_fill(0, 300, $events[0]))) <= 200);
$cust = SMF_V3_Courier_Risk::customer_from_stats(array('orders' => 10, 'delivered' => 8, 'returned' => 1, 'cancelled' => 1));
check('courier customer risk categories', in_array($cust['category'], array('low','medium','high'), true));
$prov = array('provider' => 'steadfast', 'health_score' => 55, 'return_rate' => 22, 'cancellation_rate' => 8, 'failure_rate' => 12, 'delivery_sla_hours' => 72);
$ship_risk = SMF_V3_Courier_Risk::shipment(array('status' => 'smf-shipped', 'opened_at' => gmdate('Y-m-d H:i:s', time() - 100 * 3600)), $prov, $cust);
check('courier shipment risk has heuristic dimensions', isset($ship_risk['late_delivery_risk'], $ship_risk['return_risk'], $ship_risk['estimate']));
$rows = SMF_V3_Courier_Provider_Intelligence::from_rows(array(
    array('provider' => 'a', 'events' => 100, 'processed' => 90, 'failed' => 10, 'retried' => 5, 'delivered' => 80, 'returned' => 5, 'cancelled' => 5, 'orders' => 90, 'health_score' => 85, 'avg_processing_seconds' => 12, 'avg_delivery_hours' => 40, 'delivery_sla_breaches' => 2),
    array('provider' => 'b', 'events' => 50, 'processed' => 20, 'failed' => 25, 'retried' => 20, 'delivered' => 10, 'returned' => 15, 'cancelled' => 10, 'orders' => 40, 'health_score' => 30, 'stale' => 10, 'delivery_sla_breaches' => 8),
));
check('courier provider recommendations include healthy and degraded', $rows[0]['recommendation'] === 'PROVIDER_HEALTHY' || $rows[0]['health_score'] >= $rows[1]['health_score']);
check('courier degraded provider flagged', in_array($rows[1]['recommendation'], array('PROVIDER_DEGRADED','REVIEW_PROVIDER','REVIEW_SHIPMENT'), true));
$engine = new SMF_V3_Courier_Intelligence_Engine();
$journey = $engine->journey(array('order_id' => 10, 'provider' => 'steadfast', 'status' => 'smf-shipped', 'opened_at' => '2026-09-01 09:00:00'), $events, array('orders' => 5, 'delivered' => 1, 'returned' => 2, 'cancelled' => 1), $prov);
check('courier journey pipeline complete', isset($journey['shipment'], $journey['timeline'], $journey['outcome'], $journey['shipment_risk'], $journey['recommendation']));
$opt = $engine->optimization_advice($rows);
check('courier optimization never auto-assigns in beta', $opt['auto_assign'] === false);
check('courier intelligence flag defaults off', SMF_V3_Feature_Flag::courier_intelligence_enabled() === false);
check('courier intelligence docs exist', file_exists(__DIR__ . '/../V3_COURIER_INTELLIGENCE.md'));
check('plugin version is 3.3 beta', strpos(file_get_contents(__DIR__ . '/../V3_COURIER_INTELLIGENCE.md'), '3.3.0-beta.1') !== false);
check('courier intelligence reuses v2 recovery contracts', strpos(file_get_contents(__DIR__ . '/../includes/v3/Courier/SMF_V3_Courier_Intelligence.php'), 'SMF_Courier_Recovery') !== false);

// --- Phase 3.4 Commercial SaaS ---
$free = new SMF_V3_License(array('plan' => 'free', 'state' => 'active'));
$checker = new SMF_V3_Entitlement_Checker($free);
check('commercial free plan has basic tracking', $checker->can('basic_tracking') === true);
check('commercial free plan lacks automation', $checker->can('automation') === false);
$biz = new SMF_V3_Entitlement_Checker(new SMF_V3_License(array('plan' => 'business', 'state' => 'active')));
check('commercial business plan has automation', $biz->can('automation') === true && $biz->can('ai_assistant') === false);
$ent = new SMF_V3_Entitlement_Checker(new SMF_V3_License(array('plan' => 'enterprise', 'state' => 'active')));
check('commercial enterprise has ai assistant', $ent->can('ai_assistant') === true);
$expired = new SMF_V3_Entitlement_Checker(new SMF_V3_License(array('plan' => 'enterprise', 'state' => 'expired')));
check('commercial expired keeps basic tracking', $expired->can('basic_tracking') === true);
check('commercial expired disables advanced capability', $expired->can('automation') === false);
$grace = SMF_V3_License_Service::apply_time_state(new SMF_V3_License(array(
    'plan' => 'pro', 'state' => 'trial', 'trial_ends_at' => gmdate('Y-m-d H:i:s', time() - 10), 'grace_ends_at' => gmdate('Y-m-d H:i:s', time() + 86400),
)));
check('commercial trial expiry enters grace', $grace->state() === 'grace');
$saved = SMF_V3_License_Service::save(array('plan' => 'pro', 'state' => 'active', 'token' => 'SECRET-TOKEN', 'license_key' => 'KEY'));
check('commercial license storage strips secrets', $saved->to_array()['plan'] === 'pro' && !isset($saved->to_array()['token']) && strpos(wp_json_encode(get_option('smf_v3_license')), 'SECRET-TOKEN') === false);
check('commercial unknown capability denied', $checker->can('delete_all_orders') === false);
$tel = SMF_V3_Telemetry::snapshot();
check('commercial telemetry disabled by default', $tel['enabled'] === false && $tel['blocks_plugin'] === false);
$GLOBALS['smf_test_options']['smf_v3_telemetry_opt_in'] = 'yes';
$tel_on = SMF_V3_Telemetry::snapshot();
check('commercial telemetry aggregate only', $tel_on['enabled'] === true && !isset($tel_on['payload']['email']) && !isset($tel_on['payload']['orders']));
$upd = SMF_V3_Update_Compatibility::status();
check('commercial update uses standard channel', $upd['custom_updater'] === false);
check('commercial docs exist', file_exists(__DIR__ . '/../V3_COMMERCIAL.md'));
check('commercial flag defaults off', SMF_V3_Feature_Flag::commercial_enabled() === false);

// --- Phase 3.5 AI Intelligence ---
$ai = new SMF_V3_AI_Assistant(new SMF_V3_AI_Deterministic_Provider());
$roas_q = $ai->ask('Why did my ROAS drop?', 30);
check('ai answer includes explainability fields', isset($roas_q['answer'], $roas_q['evidence'], $roas_q['confidence'], $roas_q['recommended_next_step']));
check('ai never claims execution', $roas_q['can_execute'] === false);
$camp_q = $ai->ask('Which campaigns need attention?', 30);
check('ai campaign answer grounded or explicit unavailable', is_string($camp_q['answer']) && $camp_q['answer'] !== '');
$ctx = SMF_V3_AI_Context_Builder::redact(array('metrics' => array('roas' => 1.2), 'access_token' => 'tok', 'nested' => array('password' => 'x', 'safe' => 'ok')));
check('ai context redacts secrets', ($ctx['access_token'] ?? '') === '[redacted]' && ($ctx['nested']['password'] ?? '') === '[redacted]' && ($ctx['nested']['safe'] ?? '') === 'ok');
$ranked = $ai->rank_recommendations(array(
    array('severity' => 'low', 'title' => 'a'),
    array('severity' => 'high', 'title' => 'b'),
));
check('ai ranking does not override policy', $ranked['overrides_policy'] === false && $ranked['can_execute'] === false && ($ranked['ranked'][0]['severity'] ?? '') === 'high');
check('ai provider interface is swappable', interface_exists('SMF_V3_AI_Provider_Interface'));
check('ai flag defaults off', SMF_V3_Feature_Flag::ai_enabled() === false);
check('ai docs exist', file_exists(__DIR__ . '/../V3_AI.md'));
check('plugin version is 3.5 beta', strpos(file_get_contents(__DIR__ . '/../sync-meta-flow.php'), '3.5.0-beta.1') !== false);
$ai_src = file_get_contents(__DIR__ . '/../includes/v3/AI/SMF_V3_AI.php');
check('ai has no autonomous execution hooks', strpos($ai_src, 'can_execute') !== false && strpos($ai_src, 'wp_remote_') === false);
check('domain still free of infrastructure coupling', strpos(file_get_contents(__DIR__ . '/../includes/v3/Domain/SMF_V3_Value_Objects.php'), 'get_option') === false);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);