<?php
defined('ABSPATH') || exit;

class SMF_Onboarding {
    const PAGE = 'smf-onboarding';
    const COMPLETED = 'smf_onboarding_completed';
    const DISMISSED = 'smf_onboarding_dismissed';
    const STEP = 'smf_onboarding_step';
    const ATTRIBUTION_REVIEWED = 'smf_onboarding_attribution_reviewed';
    const PROFITABILITY_REVIEWED = 'smf_onboarding_profitability_reviewed';
    const META_SKIPPED = 'smf_onboarding_meta_skipped';
    const COURIER_SKIPPED = 'smf_onboarding_courier_skipped';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_notices', array(__CLASS__, 'notice'));
        add_action('admin_post_smf_onboarding', array(__CLASS__, 'handle'));
    }

    public static function menu() {
        add_submenu_page('sync-meta-flow', 'Setup Assistant', 'Setup Assistant', 'manage_woocommerce', self::PAGE, array(__CLASS__, 'page'));
    }

    public static function status() {
        $compatibility = class_exists('SMF_Compatibility') ? SMF_Compatibility::report() : array('ready' => false, 'checks' => array(), 'blocking_failures' => 1, 'warnings' => 0);
        $meta_enabled = get_option('smf_meta_enabled', 'no') === 'yes';
        $pixel = trim((string) get_option('smf_meta_pixel_id', ''));
        $provider = sanitize_key((string) get_option('smf_courier_provider', 'generic'));
        $courier_secret = trim((string) get_option('smf_courier_webhook_secret', ''));
        $meta_skipped = get_option(self::META_SKIPPED, 'no') === 'yes';
        $courier_skipped = get_option(self::COURIER_SKIPPED, 'no') === 'yes';
        $attribution_reviewed = get_option(self::ATTRIBUTION_REVIEWED, 'no') === 'yes';
        $profitability_reviewed = get_option(self::PROFITABILITY_REVIEWED, 'no') === 'yes';
        $meta_ready = $meta_enabled && $pixel !== '';
        $courier_ready = $provider !== 'generic' && $courier_secret !== '';
        $core_ready = !empty($compatibility['ready']);

        $categories = array(
            'core' => array('label' => 'Core', 'weight' => 30, 'ready' => $core_ready, 'optional' => false, 'skipped' => false, 'detail' => $core_ready ? 'Store compatibility and plugin services are ready.' : 'Resolve blocking compatibility checks before relying on reports.'),
            'tracking' => array('label' => 'Tracking', 'weight' => 25, 'ready' => $meta_ready, 'optional' => true, 'skipped' => $meta_skipped, 'detail' => $meta_ready ? 'Meta tracking is enabled and a Pixel is configured.' : ($meta_skipped ? 'Skipped - Meta is optional.' : 'Enable tracking and configure a Pixel, or skip Meta for now.')),
            'attribution' => array('label' => 'Attribution', 'weight' => 10, 'ready' => $attribution_reviewed, 'optional' => false, 'skipped' => false, 'detail' => $attribution_reviewed ? 'Attribution model reviewed: ' . SMF_Attribution_Model::label(get_option('smf_attribution_model', 'last_touch')) . '.' : 'Review the attribution model used in reporting.'),
            'courier' => array('label' => 'Courier', 'weight' => 20, 'ready' => $courier_ready, 'optional' => true, 'skipped' => $courier_skipped, 'detail' => $courier_ready ? 'Provider and signed webhook secret are configured.' : ($courier_skipped ? 'Skipped - courier integration is optional.' : 'Configure a provider and signed webhook secret, or skip courier setup.')),
            'financial' => array('label' => 'Financial', 'weight' => 15, 'ready' => $profitability_reviewed, 'optional' => false, 'skipped' => false, 'detail' => $profitability_reviewed ? 'Contribution-profit assumptions were reviewed.' : 'Review the contribution-profit assumptions before using financial recommendations.'),
        );

        return array(
            'completed' => get_option(self::COMPLETED, 'no') === 'yes',
            'dismissed' => get_option(self::DISMISSED, 'no') === 'yes',
            'step' => max(1, min(7, absint(get_option(self::STEP, 1)))),
            'existing_store' => self::existing_store(),
            'compatibility' => $compatibility,
            'meta' => array('enabled' => $meta_enabled, 'pixel' => $pixel !== '', 'capi' => trim((string) get_option('smf_meta_access_token', '')) !== '', 'account' => trim((string) get_option('smf_meta_ad_account_id', '')) !== '', 'currency' => (string) get_option('smf_meta_account_currency', ''), 'skipped' => $meta_skipped),
            'attribution_model' => SMF_Attribution_Model::normalize_model(get_option('smf_attribution_model', 'last_touch')),
            'courier' => array('provider' => $provider, 'configured' => $courier_ready, 'webhook' => $courier_secret !== '', 'skipped' => $courier_skipped),
            'profitability' => array('cogs' => (float) get_option('smf_cogs_percent', 0), 'fee' => (float) get_option('smf_payment_fee_percent', 0), 'delivery' => (float) get_option('smf_courier_delivery_cost', 0), 'return' => (float) get_option('smf_courier_return_cost', 0), 'reviewed' => $profitability_reviewed),
            'categories' => $categories,
            'score' => self::readiness_score($categories),
        );
    }

    public static function readiness_score($categories) {
        $score = 0;
        foreach ((array) $categories as $category) {
            if (!empty($category['ready']) || (!empty($category['skipped']) && !empty($category['optional']))) {
                $score += (int) $category['weight'];
            }
        }
        return min(100, max(0, $score));
    }

    public static function is_complete() {
        return get_option(self::COMPLETED, 'no') === 'yes';
    }

    private static function existing_store() {
        if (get_option('smf_meta_enabled', 'no') === 'yes' || get_option('smf_attribution_model', '') !== '' || get_option('smf_courier_provider', 'generic') !== 'generic') return true;
        global $wpdb;
        $table = $wpdb->prefix . 'smf_order_events';
        return (bool) $wpdb->get_var("SELECT id FROM $table LIMIT 1");
    }

    public static function notice() {
        if (!current_user_can('manage_woocommerce') || self::is_complete() || get_option(self::DISMISSED, 'no') === 'yes') return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && strpos((string) $screen->id, 'smf-onboarding') !== false) return;
        echo '<div class="notice notice-info is-dismissible"><p><strong>Sync Meta Flow is ready.</strong> Complete setup to review compatibility, tracking, attribution, courier intelligence, and profitability.</p><p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=' . self::PAGE . '&step=1')) . '">Start setup</a> <a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=smf_onboarding&task=dismiss'), 'smf_onboarding_dismiss')) . '">Dismiss</a></p></div>';
    }

    public static function handle() {
        if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized');
        $task = isset($_POST['task']) ? sanitize_key(wp_unslash($_POST['task'])) : (isset($_GET['task']) ? sanitize_key(wp_unslash($_GET['task'])) : '');
        check_admin_referer('smf_onboarding_' . $task);
        if ($task === 'dismiss') update_option(self::DISMISSED, 'yes', false);
        if ($task === 'start') { update_option(self::DISMISSED, 'no', false); update_option(self::STEP, 1, false); }
        if ($task === 'attribution') { update_option('smf_attribution_model', SMF_Attribution_Model::normalize_model(isset($_POST['model']) ? wp_unslash($_POST['model']) : 'last_touch'), false); update_option(self::ATTRIBUTION_REVIEWED, 'yes', false); update_option(self::STEP, 5, false); }
        if ($task === 'meta_skip') { update_option(self::META_SKIPPED, 'yes', false); update_option(self::STEP, 4, false); }
        if ($task === 'courier_skip') { update_option(self::COURIER_SKIPPED, 'yes', false); update_option(self::STEP, 6, false); }
        if ($task === 'financial') { update_option(self::PROFITABILITY_REVIEWED, 'yes', false); update_option(self::STEP, 7, false); }
        if ($task === 'next') update_option(self::STEP, max(1, min(7, absint(isset($_POST['step']) ? $_POST['step'] : 1) + 1)), false);
        if ($task === 'complete') { update_option(self::COMPLETED, 'yes', false); update_option(self::DISMISSED, 'no', false); update_option(self::STEP, 7, false); }
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&step=' . max(1, min(7, absint(get_option(self::STEP, 1))))));
        exit;
    }

    public static function page() {
        if (!current_user_can('manage_woocommerce')) return;
        $status = self::status();
        $step = isset($_GET['step']) ? max(1, min(7, absint($_GET['step']))) : $status['step'];
        $status['step'] = $step;
        $score = $status['score'];
        ?>
        <div class="wrap smf-wrap smf-settings"><div class="smf-header"><div><h1>Setup Assistant</h1><p>Review your store readiness without changing existing settings.</p></div><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sync-meta-flow')); ?>">Dashboard</a></div>
        <div class="smf-panel"><p><strong>Step <?php echo esc_html($step); ?> of 7</strong> · Readiness <?php echo esc_html($score); ?>%</p><div class="smf-onboarding-steps"><?php foreach (array('Welcome','Compatibility','Meta','Attribution','Courier','Profitability','Readiness') as $index => $label): ?><span class="<?php echo ($index + 1) === $step ? 'is-current' : (($index + 1) < $step ? 'is-done' : ''); ?>"><?php echo esc_html(($index + 1) . '. ' . $label); ?></span><?php endforeach; ?></div></div>
        <?php self::render_step($step, $status); ?></div>
        <?php
    }

    private static function render_step($step, $status) {
        $next = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="smf_onboarding"><input type="hidden" name="task" value="next"><input type="hidden" name="step" value="' . esc_attr($step) . '">' . wp_nonce_field('smf_onboarding_next', '_wpnonce', true, false) . '<button class="button button-primary" type="submit">Continue</button></form>';
        if ($step === 1) echo '<div class="smf-panel"><h2>Welcome to Sync Meta Flow</h2><p>Track the journey from ad click to WooCommerce order and delivery, understand campaign profitability, monitor courier outcomes, and recover failed Meta events. Reports are estimates and recommendations remain advisory.</p><p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=' . self::PAGE . '&step=2')) . '">Start setup</a> <a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=smf_onboarding&task=dismiss'), 'smf_onboarding_dismiss')) . '">Skip for now</a></p></div>';
        if ($step === 2) { echo '<div class="smf-panel"><h2>Store compatibility</h2>'; self::render_checks($status['compatibility']['checks']); echo '<p>' . $next . '</p></div>'; }
        if ($step === 3) echo '<div class="smf-panel"><h2>Meta tracking</h2><p>Meta is optional. Tokens and secrets are never shown here.</p><p>Tracking: <strong>' . ($status['meta']['enabled'] && $status['meta']['pixel'] ? 'Ready' : 'Needs setup') . '</strong> · CAPI: <strong>' . ($status['meta']['capi'] ? 'Configured' : 'Not configured') . '</strong> · Ad account: <strong>' . ($status['meta']['account'] ? 'Configured' : 'Not configured') . '</strong>' . ($status['meta']['currency'] ? ' · Currency: ' . esc_html($status['meta']['currency']) : '') . '</p><p><a class="button" href="' . esc_url(admin_url('admin.php?page=smf-settings')) . '">Configure Meta</a> <a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=smf_onboarding&task=meta_skip'), 'smf_onboarding_meta_skip')) . '">Skip Meta setup</a> ' . $next . '</p></div>';
        if ($step === 4) { echo '<div class="smf-panel"><h2>Attribution</h2><p>Choose how reporting assigns campaign credit. This does not rewrite historical events.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="smf_onboarding"><input type="hidden" name="task" value="attribution">' . wp_nonce_field('smf_onboarding_attribution', '_wpnonce', true, false) . '<select name="model">'; foreach (array('last_touch' => 'Last touch - most recent campaign gets the main credit.', 'first_touch' => 'First touch - the campaign that introduced the customer gets credit.', 'first_last' => 'First + Last - split credit between the first and last campaigns.', 'assisted' => 'Assisted - show first-touch influence separately.') as $model => $label) echo '<option value="' . esc_attr($model) . '" ' . selected($status['attribution_model'], $model, false) . '>' . esc_html($label) . '</option>'; echo '</select> <button class="button button-primary" type="submit">Save and continue</button></form></div>'; }
        if ($step === 5) echo '<div class="smf-panel"><h2>Courier intelligence</h2><p>Provider: <strong>' . esc_html($status['courier']['provider']) . '</strong> · Provider and signed webhook: <strong>' . ($status['courier']['configured'] ? 'Configured' : 'Not configured') . '</strong></p><p>Courier intelligence becomes more useful after delivery, return, and cancellation events exist. Do not select a provider unless its documented integration is configured.</p><p><a class="button" href="' . esc_url(admin_url('admin.php?page=smf-courier')) . '">Configure courier</a> <a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=smf_onboarding&task=courier_skip'), 'smf_onboarding_courier_skip')) . '">Skip courier setup</a> ' . $next . '</p></div>';
        if ($step === 6) echo '<div class="smf-panel"><h2>Profitability assumptions</h2><p>These values estimate contribution profit; they are not accounting-grade net profit.</p><p>COGS: ' . esc_html($status['profitability']['cogs']) . '% · Payment fee: ' . esc_html($status['profitability']['fee']) . '% · Delivery: ' . esc_html($status['profitability']['delivery']) . ' · Return: ' . esc_html($status['profitability']['return']) . '</p><p><a class="button" href="' . esc_url(admin_url('admin.php?page=smf-profitability')) . '">Review profitability</a></p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="smf_onboarding"><input type="hidden" name="task" value="financial">' . wp_nonce_field('smf_onboarding_financial', '_wpnonce', true, false) . '<button class="button button-primary" type="submit">Mark reviewed and continue</button></form></div>'; 
        if ($step === 7) { echo '<div class="smf-panel"><h2>' . ($score >= 85 && empty($status['compatibility']['blocking_failures']) ? 'Your store is ready' : 'Your store needs a few more steps') . '</h2><p>Readiness score: <strong>' . esc_html($score) . '%</strong>. Optional steps are marked as skipped rather than silently treated as configured.</p>'; foreach ($status['categories'] as $category) echo '<p><strong>' . esc_html($category['label']) . '</strong>: ' . esc_html($category['detail']) . '</p>'; echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=smf-executive')) . '">Go to Executive Dashboard</a> <a class="button" href="' . esc_url(admin_url('admin.php?page=smf-diagnostics')) . '">Open Diagnostics</a></p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="smf_onboarding"><input type="hidden" name="task" value="complete">' . wp_nonce_field('smf_onboarding_complete', '_wpnonce', true, false) . '<button class="button button-primary" type="submit">Finish setup</button></form></div>'; }
    }

    private static function render_checks($checks) { foreach ((array) $checks as $check) echo '<p><strong>' . esc_html($check['name']) . '</strong>: ' . esc_html(strtoupper($check['level'])) . ' - ' . esc_html($check['detail']) . '</p>'; }
}
