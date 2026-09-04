<?php
/**
 * Static release-hardening guard. Does not bootstrap WordPress/WooCommerce.
 */
$root=dirname(__DIR__);
$plugin=file_get_contents($root.'/sync-meta-flow.php');
$installer=file_get_contents($root.'/includes/class-smf-installer.php');
$uninstall=file_get_contents($root.'/uninstall.php');
$readme=file_get_contents($root.'/readme.txt');
$attrs=file_get_contents($root.'/.gitattributes');
$checks=array(
 'plugin version'=>strpos($plugin,"Version: 2.1.0")!==false && strpos($plugin,"define('SMF_VERSION','2.1.0')")!==false,
 'upgrade hook'=>strpos($plugin,'SMF_Installer::maybe_upgrade()')!==false,
 'deactivation recovery cron'=>strpos($installer,"wp_clear_scheduled_hook('smf_retry_courier_events')")!==false,
 'uninstall recovery cron'=>strpos($uninstall,"wp_clear_scheduled_hook('smf_retry_courier_events')")!==false,
 'preserve by default'=>strpos($uninstall,"smf_delete_data_on_uninstall','no'")!==false,
 'release documentation'=>strpos($readme,'Stable tag: 2.1.0')!==false && strpos($readme,'== v2.1.0 — Production release hardening ==')!==false,
 'source export exclusions'=>strpos($attrs,'tests/ export-ignore')!==false && strpos($attrs,'.github/ export-ignore')!==false,
);
$failed=array();foreach($checks as $name=>$ok){if(!$ok)$failed[]=$name;}
if($failed){fwrite(STDERR,"Release hardening test failed: ".implode(', ',$failed)."\n");exit(1);}
echo "Release hardening static checks passed.\n";
