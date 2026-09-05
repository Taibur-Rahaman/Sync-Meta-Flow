<?php
/**
 * Attribution and ROAS reporting for Sync Meta Flow.
 *
 * @package Sync_Meta_Flow
 */
defined('ABSPATH') || exit;

class SMF_Attribution_Report {
    public static function init(){
        add_action('admin_menu',array(__CLASS__,'menu'));
    }
    public static function menu(){
        add_submenu_page('sync-meta-flow','Attribution & ROAS','Attribution & ROAS','manage_woocommerce','smf-attribution',array(__CLASS__,'render'));
    }
    private static function money($v){return number_format_i18n((float)$v,2);}
    private static function empty_campaign(){
        return array('campaign_id'=>'','first_orders'=>0,'last_orders'=>0,'assisted_orders'=>0,'first_revenue'=>0,'last_revenue'=>0,'assisted_revenue'=>0,'delivered_first'=>0,'delivered_last'=>0,'delivered_assisted'=>0,'spend'=>0);
    }
    private static function data($since,$until,$currency='BDT'){
        global $wpdb;
        $events=$wpdb->prefix.'smf_order_events';
        $spend=$wpdb->prefix.'smf_campaign_spend';
        $rows=$wpdb->get_results($wpdb->prepare("SELECT order_id,event_type,new_status,metadata,created_at,id FROM $events WHERE created_at >= %s AND created_at < DATE_ADD(%s,INTERVAL 1 DAY) ORDER BY order_id ASC,created_at ASC,id ASC",$since.' 00:00:00',$until.' 00:00:00'));
        $orders=array();
        foreach($rows as $r){
            $id=(int)$r->order_id;if(!$id)continue;
            if(!isset($orders[$id]))$orders[$id]=array('total'=>0,'currency'=>'','purchase'=>false,'delivered'=>false,'returned'=>false,'cancelled'=>false,'first_campaign_id'=>'','last_campaign_id'=>'','first_campaign'=>'','last_campaign'=>'');
            $m=$r->metadata?json_decode($r->metadata,true):array();
            if(isset($m['order_total']))$orders[$id]['total']=(float)$m['order_total'];
            if(!empty($m['currency']))$orders[$id]['currency']=sanitize_text_field($m['currency']);
            foreach(array('first_campaign_id','last_campaign_id','first_campaign','last_campaign') as $f)if(!empty($m[$f]))$orders[$id][$f]=sanitize_text_field($m[$f]);
            if($r->event_type==='purchase')$orders[$id]['purchase']=true;
            $s=(string)$r->new_status;
            if(in_array($s,array('smf-delivered','completed'),true))$orders[$id]['delivered']=true;
            if(in_array($s,array('smf-returned','refunded'),true))$orders[$id]['returned']=true;
            if($s==='cancelled')$orders[$id]['cancelled']=true;
        }
        $sp=$wpdb->get_results($wpdb->prepare("SELECT campaign_id,adset_id,ad_id,amount,currency FROM $spend WHERE spend_date BETWEEN %s AND %s AND currency=%s",$since,$until,$currency));
        $spend_map=array('campaign'=>array(),'adset'=>array(),'ad'=>array());
        foreach($sp as $r){
            foreach(array('campaign','adset','ad') as $level){
                $id=$level==='campaign'?$r->campaign_id:($level==='adset'?$r->adset_id:$r->ad_id);
                if($id!=='')$spend_map[$level][$id]=($spend_map[$level][$id]??0)+(float)$r->amount;
            }
        }
        $campaigns=array();
        $summary=array('spend'=>0,'purchase_revenue'=>0,'delivered_revenue'=>0,'first_touch_revenue'=>0,'last_touch_revenue'=>0,'assisted_revenue'=>0,'orders'=>0,'delivered_orders'=>0,'assisted_orders'=>0);
        foreach($orders as $o){
            if(!$o['purchase']||strtoupper($o['currency'])!==strtoupper($currency))continue;
            $summary['orders']++;$summary['purchase_revenue']+=(float)$o['total'];
            if($o['delivered']){$summary['delivered_orders']++;$summary['delivered_revenue']+=(float)$o['total'];}
            $fc=$o['first_campaign_id']?:($o['first_campaign']?:'unattributed');
            $lc=$o['last_campaign_id']?:($o['last_campaign']?:$fc);
            if(!isset($campaigns[$fc])){$campaigns[$fc]=self::empty_campaign();$campaigns[$fc]['campaign_id']=$fc;}
            if(!isset($campaigns[$lc])){$campaigns[$lc]=self::empty_campaign();$campaigns[$lc]['campaign_id']=$lc;}
            $campaigns[$fc]['first_orders']++;$campaigns[$fc]['first_revenue']+=(float)$o['total'];
            if($o['delivered']){$campaigns[$fc]['delivered_first']+=(float)$o['total'];$summary['first_touch_revenue']+=(float)$o['total'];}
            $campaigns[$lc]['last_orders']++;$campaigns[$lc]['last_revenue']+=(float)$o['total'];
            if($o['delivered']){$campaigns[$lc]['delivered_last']+=(float)$o['total'];$summary['last_touch_revenue']+=(float)$o['total'];}
            if($fc!==$lc){$summary['assisted_orders']++;$campaigns[$fc]['assisted_orders']++;if($o['delivered']){$summary['assisted_revenue']+=(float)$o['total'];$campaigns[$fc]['assisted_revenue']+=(float)$o['total'];$campaigns[$fc]['delivered_assisted']+=(float)$o['total'];}}
        }
        foreach($campaigns as &$c){
            $id=$c['campaign_id'];
            $c['spend']=$spend_map['campaign'][$id]??0;
            if($c['spend']<=0&&isset($spend_map['adset'][$id]))$c['spend']=$spend_map['adset'][$id];
            if($c['spend']<=0&&isset($spend_map['ad'][$id]))$c['spend']=$spend_map['ad'][$id];
            $summary['spend']+=(float)$c['spend'];
            $c['first_roas']=$c['spend']>0?$c['delivered_first']/$c['spend']:0;
            $c['last_roas']=$c['spend']>0?$c['delivered_last']/$c['spend']:0;
            $c['assisted_share']=$c['delivered_first']>0?($c['delivered_assisted']/$c['delivered_first'])*100:0;
        }
        unset($c);
        $summary['first_roas']=$summary['spend']>0?$summary['first_touch_revenue']/$summary['spend']:0;
        $summary['last_roas']=$summary['spend']>0?$summary['last_touch_revenue']/$summary['spend']:0;
        uasort($campaigns,function($a,$b){return $b['last_roas']<=>$a['last_roas'];});
        return array('summary'=>$summary,'campaigns'=>array_values($campaigns));
    }
    public static function render(){
        if(!current_user_can('manage_woocommerce'))return;
        $days=isset($_GET['period'])&&in_array(absint($_GET['period']),array(7,30,90),true)?absint($_GET['period']):30;
        $currency=isset($_GET['currency'])?strtoupper(sanitize_text_field(wp_unslash($_GET['currency']))):'BDT';
        $model=isset($_GET['model'])?SMF_Attribution_Model::normalize_model(wp_unslash($_GET['model'])):get_option('smf_attribution_model','last_touch');
        $until=current_time('Y-m-d');$since=wp_date('Y-m-d',strtotime('-'.($days-1).' days',current_time('timestamp')));
        $d=self::data($since,$until,$currency);$s=$d['summary'];
        $model_revenue=$model==='first_touch'?$s['first_touch_revenue']:$s['last_touch_revenue'];
        if($model==='assisted')$model_revenue=$s['first_touch_revenue'];
        if($model==='first_last')$model_revenue=$s['last_touch_revenue'];
        $model_roas=$s['spend']>0?$model_revenue/$s['spend']:0;
        ?>
        <div class="wrap smf-wrap"><div class="smf-header"><div><h1>Attribution & ROAS</h1><p>Compare acquisition, conversion and assisted revenue against advertising spend.</p></div><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sync-meta-flow'));?>">← Dashboard</a></div>
        <form method="get" class="smf-period-bar"><input type="hidden" name="page" value="smf-attribution"><strong><?php echo esc_html($currency);?> · <?php echo esc_html($since.' → '.$until);?></strong><label>Model <select name="model"><option value="last_touch" <?php selected($model,'last_touch');?>>Last Touch</option><option value="first_touch" <?php selected($model,'first_touch');?>>First Touch</option><option value="first_last" <?php selected($model,'first_last');?>>First + Last</option><option value="assisted" <?php selected($model,'assisted');?>>Assisted</option></select></label><?php foreach(array(7,30,90) as $p):?><a class="button <?php echo $days===$p?'button-primary':'';?>" href="<?php echo esc_url(admin_url('admin.php?page=smf-attribution&period='.$p.'&currency='.rawurlencode($currency).'&model='.rawurlencode($model)));?>"><?php echo esc_html($p);?> days</a><?php endforeach;?><button class="button">Apply</button></form>
        <div class="smf-cards"><div class="smf-card"><span>Ad Spend</span><strong><?php echo esc_html(self::money($s['spend']));?></strong><small>Selected period</small></div><div class="smf-card"><span>Delivered Revenue</span><strong><?php echo esc_html(self::money($s['delivered_revenue']));?></strong><small><?php echo esc_html($s['delivered_orders']);?> delivered orders</small></div><div class="smf-card"><span><?php echo esc_html(SMF_Attribution_Model::label($model));?> ROAS</span><strong><?php echo esc_html(number_format_i18n($model_roas,2));?>×</strong><small>Selected attribution model</small></div><div class="smf-card"><span>Assisted Conversions</span><strong><?php echo esc_html($s['assisted_orders']);?></strong><small><?php echo esc_html(self::money($s['assisted_revenue']));?> delivered revenue</small></div></div>
        <div class="smf-revenue-grid"><div><span>First-touch delivered revenue</span><strong><?php echo esc_html(self::money($s['first_touch_revenue']));?></strong></div><div><span>Last-touch delivered revenue</span><strong><?php echo esc_html(self::money($s['last_touch_revenue']));?></strong></div><div><span>Assisted delivered revenue</span><strong><?php echo esc_html(self::money($s['assisted_revenue']));?></strong></div><div><span>Purchase revenue</span><strong><?php echo esc_html(self::money($s['purchase_revenue']));?></strong></div></div>
        <div class="smf-panel"><h2>Campaign Model Comparison</h2><p class="smf-muted">The selected model controls the headline ROAS. First-touch identifies acquisition campaigns; last-touch identifies the final converting campaign; assisted highlights customers whose first and last campaign differ. Attribution models are alternatives and must not be added together.</p><div class="smf-table-wrap"><table class="smf-table"><thead><tr><th>Campaign</th><th>Spend</th><th>First orders</th><th>Last orders</th><th>Assisted</th><th>First delivered</th><th>Last delivered</th><th>First ROAS</th><th>Last ROAS</th></tr></thead><tbody><?php if($d['campaigns']):foreach($d['campaigns'] as $c):?><tr><td><strong><?php echo esc_html($c['campaign_id']);?></strong></td><td><?php echo esc_html(self::money($c['spend']).' '.$currency);?></td><td><?php echo esc_html($c['first_orders']);?></td><td><?php echo esc_html($c['last_orders']);?></td><td><?php echo esc_html($c['assisted_orders']);?></td><td><?php echo esc_html(self::money($c['delivered_first']));?></td><td><?php echo esc_html(self::money($c['delivered_last']));?></td><td><strong><?php echo esc_html(number_format_i18n($c['first_roas'],2));?>×</strong></td><td><strong><?php echo esc_html(number_format_i18n($c['last_roas'],2));?>×</strong></td></tr><?php endforeach;else:?><tr><td colspan="9">No attributed purchase data found for this period.</td></tr><?php endif;?></tbody></table></div></div>
        <div class="smf-panel"><h2>Model guidance</h2><p class="smf-muted"><strong>First Touch</strong> is best for acquisition analysis. <strong>Last Touch</strong> is best for conversion/operational optimization. <strong>First + Last</strong> keeps both views visible without double-counting store revenue. <strong>Assisted</strong> focuses on acquisition campaigns that influenced customers who later converted through another campaign.</p></div></div><?php
    }
}
