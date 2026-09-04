<?php
// Static behavioral regression guard for Phase 2.11.
$root=dirname(__DIR__);$file=$root.'/includes/class-smf-profitability.php';$main=$root.'/sync-meta-flow.php';$installer=$root.'/includes/class-smf-installer.php';if(!is_file($file))exit(1);$src=file_get_contents($file);$main_src=file_get_contents($main);$ins=file_get_contents($installer);
$checks=array(
 'class SMF_Profitability','function report','smf_cogs_percent','smf_payment_fee_percent','smf_courier_delivery_cost','smf_courier_return_cost',
 'contribution_profit','contribution_margin','delivered_revenue','returned_revenue','campaign_spend','SMF_Profitability::init()',
 "event_type='purchase'",'smf-shipped','smf-delivered','smf-returned','first_touch','first_last','assisted','last_touch',
 'Revenue − operating assumptions − ad spend','Campaign spend is matched by campaign_id'
);foreach($checks as $needle)if(strpos($src,$needle)===false){fwrite(STDERR,"Missing profitability invariant: {$needle}\n");exit(1);}foreach(array('smf_profitability.php','SMF_Profitability::init()') as $needle)if(strpos($main_src,$needle)===false&&$needle!=='smf_profitability.php'){fwrite(STDERR,"Missing main bootstrap invariant: {$needle}\n");exit(1);}foreach(array('smf_cogs_percent','smf_payment_fee_percent','smf_courier_delivery_cost','smf_courier_return_cost') as $needle)if(strpos($ins,$needle)===false){fwrite(STDERR,"Missing installer option: {$needle}\n");exit(1);}echo "Phase 2.11 profitability intelligence regression checks passed.\n";
