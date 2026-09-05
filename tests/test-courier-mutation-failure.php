<?php
/**
 * Static behavioral guard for courier webhook mutation failure handling.
 */
$source = file_get_contents(__DIR__ . '/../includes/class-smf-courier.php');
$timeline = file_get_contents(__DIR__ . '/../includes/class-smf-courier-timeline.php');
if ($source === false || $timeline === false) {
    fwrite(STDERR, "FAIL: unable to read courier sources.\n");
    exit(1);
}

$checks = array(
    'try {\n            $order->update_meta_data' => 'order mutation is protected by exception handling',
    '} catch (Throwable $e) {' => 'mutation exceptions are caught',
    "new WP_Error('smf_webhook_mutation_failed'" => 'mutation failure becomes a retryable REST error',
    "'status'=>500" => 'mutation failure returns HTTP 500',
    'the event will remain retryable' => 'failure response documents retry semantics',
    "WHERE event_hash=%s AND result='processing'" => 'timeline result changes only the claimed processing event',
    "($code>=200&&$code<300)?'processed':'failed'" => 'only successful REST responses become processed',
);

$failed = false;
foreach ($checks as $needle => $label) {
    if (strpos($source, $needle) === false && strpos($timeline, $needle) === false) {
        fwrite(STDERR, "FAIL: {$label}\n");
        $failed = true;
    } else {
        echo "PASS: {$label}\n";
    }
}

if ($failed) exit(1);
echo "All courier mutation failure invariants passed.\n";
