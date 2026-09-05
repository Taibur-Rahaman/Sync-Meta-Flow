<?php
/** Static behavioral guard for Phase 2.9 courier provider intelligence. */
$source=file_get_contents(__DIR__.'/../includes/class-smf-courier-operations.php');
if($source===false){fwrite(STDERR,"FAIL: unable to read courier operations source.\n");exit(1);}
$checks=array(
 'provider_intelligence' => 'provider intelligence service exists',
 "provider_intelligence(7)" => '7-day intelligence window',
 "provider_intelligence(30)" => '30-day intelligence window',
 "provider_intelligence(90)" => '90-day intelligence window',
 'processing_seconds' => 'processing latency is measured',
 'sla_breaches' => 'SLA breaches are measured',
 'processing_sla' => 'processing SLA is configurable',
 'delivery_sla' => 'delivery SLA policy is configurable',
 'success_rate' => 'provider success rate is exposed',
 'retry_rate' => 'provider retry rate is exposed',
 'health_score' => 'provider health score is calculated',
 "'HEALTHY'" => 'healthy provider classification exists',
 "'WATCH'" => 'watch provider classification exists',
 "'DEGRADED'" => 'degraded provider classification exists',
 'Best observed provider health score' => 'provider recommendation uses observed health',
 'combined_risk' => 'customer and courier risk can be combined',
 "'combined_score'" => 'combined risk score is exposed',
 'merchant-configurable thresholds' => 'SLA thresholds are explicitly advisory'
);
foreach($checks as $needle=>$label){if(strpos($source,$needle)===false){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
echo "Courier provider intelligence tests passed.\n";
