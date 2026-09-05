<?php
defined('ABSPATH') || exit;

class SMF_Order_Status {
    public static function init() {
        add_action('init', array(__CLASS__, 'register_statuses'));
        add_filter('wc_order_statuses', array(__CLASS__, 'add_statuses'));
    }

    public static function register_statuses() {
        register_post_status('wc-smf-confirmed', array('label' => 'Confirmed', 'public' => false, 'show_in_admin_status_list' => true, 'show_in_admin_all_list' => true, 'label_count' => _n_noop('Confirmed <span class="count">(%s)</span>', 'Confirmed <span class="count">(%s)</span>')));
        register_post_status('wc-smf-shipped', array('label' => 'Shipped', 'public' => false, 'show_in_admin_status_list' => true, 'show_in_admin_all_list' => true, 'label_count' => _n_noop('Shipped <span class="count">(%s)</span>', 'Shipped <span class="count">(%s)</span>')));
        register_post_status('wc-smf-delivered', array('label' => 'Delivered', 'public' => false, 'show_in_admin_status_list' => true, 'show_in_admin_all_list' => true, 'label_count' => _n_noop('Delivered <span class="count">(%s)</span>', 'Delivered <span class="count">(%s)</span>')));
        register_post_status('wc-smf-returned', array('label' => 'Returned', 'public' => false, 'show_in_admin_status_list' => true, 'show_in_admin_all_list' => true, 'label_count' => _n_noop('Returned <span class="count">(%s)</span>', 'Returned <span class="count">(%s)</span>')));
    }

    public static function add_statuses($statuses) {
        $statuses['wc-smf-confirmed'] = 'Confirmed';
        $statuses['wc-smf-shipped'] = 'Shipped';
        $statuses['wc-smf-delivered'] = 'Delivered';
        $statuses['wc-smf-returned'] = 'Returned';
        return $statuses;
    }
}
