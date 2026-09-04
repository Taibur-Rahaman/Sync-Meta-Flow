<?php
defined('ABSPATH') || exit;

class SMF_Spend {
    public static function init() {
        add_action('admin_post_smf_save_spend', array(__CLASS__, 'save'));
        add_action('admin_post_smf_delete_spend', array(__CLASS__, 'delete'));
    }

    public static function save() {
        if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized');
        check_admin_referer('smf_save_spend');
        global $wpdb;
        $table = $wpdb->prefix . 'smf_campaign_spend';
        $date = isset($_POST['spend_date']) ? sanitize_text_field(wp_unslash($_POST['spend_date'])) : '';
        $campaign_id = isset($_POST['campaign_id']) ? sanitize_text_field(wp_unslash($_POST['campaign_id'])) : '';
        $adset_id = isset($_POST['adset_id']) ? sanitize_text_field(wp_unslash($_POST['adset_id'])) : '';
        $ad_id = isset($_POST['ad_id']) ? sanitize_text_field(wp_unslash($_POST['ad_id'])) : '';
        $amount = isset($_POST['amount']) ? (float) wp_unslash($_POST['amount']) : 0;
        $currency = isset($_POST['currency']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['currency']))) : 'BDT';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $amount < 0) wp_die('Invalid spend data.');
        $wpdb->insert($table, array('spend_date'=>$date,'campaign_id'=>$campaign_id,'adset_id'=>$adset_id,'ad_id'=>$ad_id,'amount'=>$amount,'currency'=>$currency,'created_at'=>current_time('mysql')));
        wp_safe_redirect(admin_url('admin.php?page=smf-spend&updated=1')); exit;
    }

    public static function delete() {
        if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized');
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        check_admin_referer('smf_delete_spend_' . $id);
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'smf_campaign_spend', array('id'=>$id), array('%d'));
        wp_safe_redirect(admin_url('admin.php?page=smf-spend')); exit;
    }

    public static function total_spend($since = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'smf_campaign_spend';
        if ($since) return (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM $table WHERE spend_date >= %s", $since));
        return (float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM $table");
    }

    public static function rows($limit = 50) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}smf_campaign_spend ORDER BY spend_date DESC,id DESC LIMIT %d", max(1,min(200,absint($limit)))));
    }

    public static function render_page() {
        if (!current_user_can('manage_woocommerce')) return;
        $rows = self::rows();
        ?>
        <div class="wrap smf-wrap smf-settings"><div class="smf-header"><div><h1>Ad Spend & ROAS</h1><p>Enter Meta spend to turn attributed WooCommerce revenue into measurable ROAS.</p></div><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sync-meta-flow')); ?>">← Dashboard</a></div>
        <div class="smf-setup-grid"><div class="smf-panel"><h2>Add daily spend</h2><p class="smf-muted">Use campaign/ad-set/ad IDs from your Meta Ads Manager. Leave lower levels blank for campaign-level spend.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="smf_save_spend"><?php wp_nonce_field('smf_save_spend'); ?><p><label>Date<br><input class="smf-input" type="date" name="spend_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required></label></p><p><label>Campaign ID<br><input class="smf-input" name="campaign_id" placeholder="Meta campaign ID"></label></p><p><label>Ad Set ID<br><input class="smf-input" name="adset_id" placeholder="Optional"></label></p><p><label>Ad ID<br><input class="smf-input" name="ad_id" placeholder="Optional"></label></p><p><label>Spend<br><input class="smf-input" type="number" name="amount" min="0" step="0.01" required></label></p><p><label>Currency<br><input class="smf-input" name="currency" value="BDT" maxlength="3"></label></p><?php submit_button('Save Spend'); ?></form></div><div class="smf-panel"><h2>Recorded spend</h2><div class="smf-table-wrap"><table class="smf-table"><thead><tr><th>Date</th><th>Campaign</th><th>Ad Set</th><th>Ad</th><th>Spend</th><th></th></tr></thead><tbody><?php if($rows): foreach($rows as $row): ?><tr><td><?php echo esc_html($row->spend_date); ?></td><td><?php echo esc_html($row->campaign_id ?: '—'); ?></td><td><?php echo esc_html($row->adset_id ?: '—'); ?></td><td><?php echo esc_html($row->ad_id ?: '—'); ?></td><td><?php echo esc_html(number_format_i18n((float)$row->amount,2).' '.$row->currency); ?></td><td><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=smf_delete_spend&id='.(int)$row->id),'smf_delete_spend_'.(int)$row->id)); ?>" onclick="return confirm('Delete this spend entry?')">Delete</a></td></tr><?php endforeach; else: ?><tr><td colspan="6">No spend entries yet.</td></tr><?php endif; ?></tbody></table></div></div></div></div>
        <?php
    }
}
