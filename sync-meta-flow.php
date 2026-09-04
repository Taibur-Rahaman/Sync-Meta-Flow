<?php
/**
 * Plugin Name: Sync Meta Flow
 * Plugin URI: https://github.com/Taibur-Rahaman/Sync-Meta-Flow
 * Description: WooCommerce order-flow tracking and Meta attribution foundation for F-commerce stores.
 * Version: 0.1.0
 * Author: Taibur Rahaman
 * License: GPL-2.0-or-later
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

define('SMF_VERSION', '0.1.0');
define('SMF_FILE', __FILE__);
define('SMF_DIR', plugin_dir_path(__FILE__));
define('SMF_URL', plugin_dir_url(__FILE__));

require_once SMF_DIR . 'includes/class-smf-installer.php';
require_once SMF_DIR . 'includes/class-smf-tracker.php';
require_once SMF_DIR . 'includes/class-smf-order-events.php';
require_once SMF_DIR . 'includes/class-smf-meta-capi.php';
require_once SMF_DIR . 'includes/class-smf-admin.php';

register_activation_hook(__FILE__, array('SMF_Installer', 'activate'));
register_deactivation_hook(__FILE__, array('SMF_Installer', 'deactivate'));

add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            if (!current_user_can('activate_plugins')) return;
            echo '<div class="notice notice-warning"><p><strong>Sync Meta Flow</strong> requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }

    SMF_Tracker::init();
    SMF_Order_Events::init();
    SMF_Meta_CAPI::init();
    SMF_Admin::init();
});
