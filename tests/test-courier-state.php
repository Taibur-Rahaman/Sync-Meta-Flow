<?php
if(!defined('ABSPATH'))define('ABSPATH',__DIR__.'/');
function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_-]/','',str_replace(' ','-',(string)$v)));}
function ok($e,$a,$n){if($e!==$a){fwrite(STDERR,"FAIL $n\nExpected: ".var_export($e,true)."\nActual: ".var_export($a,true)."\n");exit(1);}echo "PASS $n\n";}

require dirname(__DIR__).'/includes/class-smf-courier-state.php';

$ref = new ReflectionClass('SMF_Courier_State');
$method = $ref->getMethod('transition_allowed');
$method->setAccessible(true);
$allowed = function($current,$target) use ($method){return $method->invoke(null,$current,$target);};

ok(true,$allowed('confirmed','shipped'),'confirmed to shipped');
ok(true,$allowed('shipped','delivered'),'shipped to delivered');
ok(true,$allowed('delivered','returned'),'delivered to returned');
ok(false,$allowed('shipped','confirmed'),'shipped to confirmed rejected');
ok(false,$allowed('delivered','shipped'),'delivered to shipped rejected');
ok(false,$allowed('delivered','confirmed'),'delivered to confirmed rejected');
ok(false,$allowed('returned','delivered'),'returned to delivered rejected');
ok(false,$allowed('cancelled','shipped'),'cancelled to shipped rejected');
ok(true,$allowed('confirmed','delivered'),'confirmed to delivered');
ok(true,$allowed('confirmed','cancelled'),'confirmed to cancelled');
ok(true,$allowed('shipped','cancelled'),'shipped to cancelled');
ok(true,$allowed('delivered','delivered'),'same state is idempotent');
ok(true,$allowed('','confirmed'),'unknown current state is allowed');

echo "All courier state transition tests passed.\n";
