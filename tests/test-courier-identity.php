<?php
if(!defined('ABSPATH'))define('ABSPATH',__DIR__.'/');
function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_-]/','',str_replace(' ','-',(string)$v)));}
function ok($e,$a,$n){if($e!==$a){fwrite(STDERR,"FAIL $n\nExpected: ".var_export($e,true)."\nActual: ".var_export($a,true)."\n");exit(1);}echo "PASS $n\n";}

class FakeCourierOrder {
    private $meta;
    public function __construct($meta){$this->meta=$meta;}
    public function get_meta($key){return isset($this->meta[$key])?$this->meta[$key]:'';}
}

require dirname(__DIR__).'/includes/class-smf-courier-state.php';
$ref=new ReflectionClass('SMF_Courier_State');
$method=$ref->getMethod('identity_matches');
$method->setAccessible(true);
$matches=function($meta,$data)use($method){return $method->invoke(null,new FakeCourierOrder($meta),$data);};

$meta=array('_smf_courier_invoice'=>'SMF-100','_smf_courier_consignment_id'=>'C-100','_smf_tracking_number'=>'TRK-100');
ok(true,$matches($meta,array('invoice'=>'SMF-100','consignment_id'=>'C-100','tracking_number'=>'TRK-100')),'matching courier identity');
ok(false,$matches($meta,array('invoice'=>'SMF-999')),'invoice mismatch rejected');
ok(false,$matches($meta,array('consignment_id'=>'C-999')),'consignment mismatch rejected');
ok(false,$matches($meta,array('tracking_number'=>'TRK-999')),'tracking mismatch rejected');
ok(true,$matches($meta,array('status'=>'delivered')),'status-only webhook remains compatible');
ok(true,$matches(array('_smf_courier_invoice'=>'SMF-100'),array('invoice'=>'smf-100')),'case-insensitive identity');

echo "All courier identity tests passed.\n";
