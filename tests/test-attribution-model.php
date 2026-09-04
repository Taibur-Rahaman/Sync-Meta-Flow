<?php
function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_-]/','',str_replace(' ','-',(string)$v)));}
function sanitize_text_field($v){return trim((string)$v);}
require dirname(__DIR__).'/includes/class-smf-attribution-model.php';
function ok($e,$a,$n){if($e!==$a){fwrite(STDERR,"FAIL $n\nExpected: ".var_export($e,true)."\nActual: ".var_export($a,true)."\n");exit(1);}echo "PASS $n\n";}
$f=array('campaign_id'=>'A');$l=array('campaign_id'=>'B');
$direct=array();
$same=array('campaign_id'=>'A');
ok(array('A'=>100.0),SMF_Attribution_Model::allocation($f,$l,100,'first_touch'),'first-touch');
ok(array('B'=>100.0),SMF_Attribution_Model::allocation($f,$l,100,'last_touch'),'last-touch');
ok(array('A'=>50.0,'B'=>50.0),SMF_Attribution_Model::allocation($f,$l,100,'first_last'),'first-last split');
ok(array('A'=>100.0),SMF_Attribution_Model::allocation($f,$l,100,'assisted'),'assisted influence');
ok(array('A'=>100.0),SMF_Attribution_Model::allocation($same,$same,100,'first_last'),'same-touch no split');
ok(array('A'=>100.0),SMF_Attribution_Model::allocation($same,$same,100,'assisted'),'same-touch assisted empty fallback');
ok(array('Direct / Unattributed'=>100.0),SMF_Attribution_Model::allocation($direct,$direct,100,'last_touch'),'direct fallback');
ok(true,SMF_Attribution_Model::is_different_touch($f,$l),'different touches');
ok(false,SMF_Attribution_Model::is_different_touch($f,$same),'same campaign touch');
ok('B',SMF_Attribution_Model::touch_id($l),'touch id');
ok('A',SMF_Attribution_Model::display_name($f),'display name');
ok('last_touch',SMF_Attribution_Model::normalize_model('invalid'),'invalid model fallback');
echo "All attribution tests passed.\n";
