<?php
if(!defined('ABSPATH'))define('ABSPATH',__DIR__.'/');
function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_-]/','',str_replace(' ','-',(string)$v)));}
function ok($e,$a,$n){if($e!==$a){fwrite(STDERR,"FAIL $n\nExpected: ".var_export($e,true)."\nActual: ".var_export($a,true)."\n");exit(1);}echo "PASS $n\n";}
require dirname(__DIR__).'/includes/class-smf-courier-operations.php';
$low=SMF_Courier_Operations::risk_score(array('orders'=>4,'delivered'=>4,'returned'=>0,'cancelled'=>0));
ok(90,$low['score'],'all delivered score');
ok('LOW RISK',$low['label'],'all delivered label');
$returned=SMF_Courier_Operations::risk_score(array('orders'=>4,'delivered'=>1,'returned'=>2,'cancelled'=>1));
ok(0,$returned['score'],'mixed failure score clamps');
ok('HIGH RISK',$returned['label'],'mixed failure label');
$medium=SMF_Courier_Operations::risk_score(array('orders'=>4,'delivered'=>2,'returned'=>0,'cancelled'=>0));
ok(70,$medium['score'],'half delivered score');
ok('MEDIUM RISK',$medium['label'],'half delivered label');
$empty=SMF_Courier_Operations::risk_score(array('orders'=>0));
ok(50,$empty['score'],'no history baseline');
ok('MEDIUM RISK',$empty['label'],'no history label');
echo "All courier risk tests passed.\n";
