<?php
/**
 * Plugin Name: Sync Meta Flow
 * Plugin URI: https://github.com/Taibur-Rahaman/Sync-Meta-Flow
 * Description: WooCommerce Meta revenue intelligence with first/last-touch attribution, order-flow, customer quality, ROAS, profitability, decision intelligence, diagnostics, privacy controls and courier intelligence.
 * Version: 2.1.0
 * Author: Taibur Rahaman
 * License: GPL-2.0-or-later
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */
defined('ABSPATH') || exit;
define('SMF_VERSION','2.1.0'); define('SMF_FILE',__FILE__); define('SMF_DIR',plugin_dir_path(__FILE__)); define('SMF_URL',plugin_dir_url(__FILE__));
require_once SMF_DIR.'includes/class-smf-installer.php'; require_once SMF_DIR.'includes/class-smf-security.php'; require_once SMF_DIR.'includes/class-smf-attribution.php'; require_once SMF_DIR.'includes/class-smf-attribution-model.php'; require_once SMF_DIR.'includes/class-smf-tracker.php'; require_once SMF_DIR.'includes/class-smf-order-events.php'; require_once SMF_DIR.'includes/class-smf-order-status.php'; require_once SMF_DIR.'includes/class-smf-meta-capi.php'; require_once SMF_DIR.'includes/class-smf-spend.php'; require_once SMF_DIR.'includes/class-smf-meta-insights.php'; require_once SMF_DIR.'includes/class-smf-courier.php'; require_once SMF_DIR.'includes/class-smf-courier-timeline.php'; require_once SMF_DIR.'includes/class-smf-courier-state.php'; require_once SMF_DIR.'includes/class-smf-courier-operations.php'; require_once SMF_DIR.'includes/class-smf-courier-recovery.php'; require_once SMF_DIR.'includes/class-smf-attribution-report.php'; require_once SMF_DIR.'includes/class-smf-attribution-intelligence.php'; require_once SMF_DIR.'includes/class-smf-profitability.php'; require_once SMF_DIR.'includes/class-smf-executive.php'; require_once SMF_DIR.'includes/class-smf-decision-engine.php'; require_once SMF_DIR.'includes/class-smf-quality.php'; require_once SMF_DIR.'includes/class-smf-diagnostics.php'; require_once SMF_DIR.'includes/class-smf-privacy.php'; require_once SMF_DIR.'includes/class-smf-order-journey.php'; require_once SMF_DIR.'includes/class-smf-admin.php';
register_activation_hook(__FILE__,array('SMF_Installer','activate')); register_deactivation_hook(__FILE__,array('SMF_Installer','deactivate'));
add_action('plugins_loaded',function(){SMF_Installer::maybe_upgrade();SMF_Security::init();if(!class_exists('WooCommerce')){add_action('admin_notices',function(){if(!current_user_can('activate_plugins'))return;echo '<div class="notice notice-warning"><p><strong>Sync Meta Flow</strong> requires WooCommerce to be installed and active.</p></div>';});return;}SMF_Attribution::init();SMF_Tracker::init();SMF_Order_Status::init();SMF_Order_Events::init();SMF_Meta_CAPI::init();SMF_Spend::init();SMF_Meta_Insights::init();SMF_Courier::init();SMF_Courier_Timeline::init();SMF_Courier_State::init();SMF_Courier_Operations::init();SMF_Courier_Recovery::init();SMF_Attribution_Report::init();SMF_Attribution_Intelligence::init();SMF_Profitability::init();SMF_Executive::init();SMF_Decision_Engine::init();SMF_Quality::init();SMF_Diagnostics::init();SMF_Privacy::init();SMF_Order_Journey::init();SMF_Admin::init();});
