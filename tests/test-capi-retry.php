<?php
/**
 * Static behavioral guard for Meta CAPI retry and response handling.
 */
$source = file_get_contents(__DIR__ . '/../includes/class-smf-meta-capi.php');
if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read CAPI source.\n");
    exit(1);
}

$checks = array(
    'private static function retryable_http_code' => 'retry classifier exists',
    '$code===408' => '408 retries',
    '$code===409' => '409 retries',
    '$code===425' => '425 retries',
    '$code===429' => '429 retries',
    '$code>=500' => '5xx retries',
    "'retryable'=>true,'error'=>$r->get_error_message()" => 'network errors retry',
    "'retryable'=>false,'error'=>'Meta Pixel ID or access token is missing.'" => 'configuration errors fail permanently',
    "self::fail_row($row,$result['error'],$result['retryable'])" => 'queue uses retry classification',
    "self::fail_row($row,'Invalid queued payload.',false)" => 'invalid payload fails permanently',
    "'error'=>!empty($body['error']['message'])?$body['error']['message']:'Meta returned HTTP '.$code.'.'" => 'Meta error payload is surfaced',
    "if($code>=200&&$code<300)return array('success'=>true,'retryable'=>false,'error'=>'');" => '2xx responses are successful',
);

$failed = false;
foreach ($checks as $needle => $label) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, "FAIL: {$label}\n");
        $failed = true;
    } else {
        echo "PASS: {$label}\n";
    }
}

if ($failed) {
    exit(1);
}

echo "All CAPI retry and response handling invariants passed.\n";
