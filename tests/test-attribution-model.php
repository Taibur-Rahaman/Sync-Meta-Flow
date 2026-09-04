<?php
function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_-]/','',str_replace(' ','-',(string)$v)));}
function sanitize_text_field($v){return trim((string)$v);}
require dirname(__DIR__).'/includes/class-smf-attribution-model.php';
function ok($e,$a,$n){if($e!==$a){fwrite(STDERR,"FAIL $n\n");exit(1);}echo "PASS $n\n";}
$f=array('campaign_id'=>'A');$l=array('campaign_id'=>'B');
ok(array('A'=>100.0),SMF_Attribution_Model::allocation($f,$l,100,'first_touch'),'first');
ok(array('B'=>100.0),SMF_Attribution_Model::allocation($f,$l,100,'last_touch'),'last');
ok(array('A'=>50.0,'B'=>50.0),SMF_Attribution_Model::allocation($f,$l,100,'first_last'),'split');
ok(array('A'=>100.0),SMF_Attribution_Model::allocation($f,$l,100,'assisted'),'assisted');
ok(true,SMF_Attribution_Model::is_different_touch($f,$l),'different');
ok(false,SMF_Attribution_Model::is_different_touch($f,$f),'same');
echo "All attribution tests passed.\n";
