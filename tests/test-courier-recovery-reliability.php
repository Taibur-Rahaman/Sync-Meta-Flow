<?php
$timeline=file_get_contents(__DIR__.'/../includes/class-smf-courier-timeline.php');
$recovery=file_get_contents(__DIR__.'/../includes/class-smf-courier-recovery.php');
$installer=file_get_contents(__DIR__.'/../includes/class-smf-installer.php');
$checks=array(
    'timeline max attempts'=>strpos($timeline,"const MAX_ATTEMPTS=5")!==false,
    'attempt counter'=>strpos($timeline,'attempts=attempts+1')!==false,
    'last attempt timestamp'=>strpos($timeline,'last_attempt_at')!==false,
    'failure reason'=>strpos($timeline,'last_error')!==false,
    'retry timestamp'=>strpos($timeline,'next_retry_at')!==false,
    'bounded backoff'=>strpos($timeline,'array(300,900,1800,3600,7200)')!==false,
    'atomic retry claim'=>strpos($timeline,"result='received'")!==false&&strpos($timeline,'attempts<%d')!==false,
    'stale processing recovery'=>strpos($timeline,'stale_processing_recovered')!==false,
    'health counters'=>strpos($timeline,'function health')!==false,
    'five minute cron'=>strpos($recovery,"'interval'=>300")!==false,
    'automatic retry worker'=>strpos($recovery,'process_retries')!==false,
    'manual retry capability'=>strpos($recovery,"current_user_can('manage_woocommerce')")!==false,
    'manual retry nonce'=>strpos($recovery,'check_admin_referer')!==false,
    'signed replay'=>strpos($recovery,"hash_hmac('sha256'")!==false,
    'transport failure persisted'=>strpos($recovery,'record_retry_failure')!==false,
    'schema retry columns'=>strpos($installer,'next_retry_at')!==false&&strpos($installer,'last_error')!==false,
    'schema retry indexes'=>strpos($installer,'retry_state(result,next_retry_at)')!==false
);
foreach($checks as $name=>$ok){if(!$ok){fwrite(STDERR,"FAIL: $name\n");exit(1);}echo "PASS: $name\n";}
echo "Courier recovery reliability tests passed.\n";
