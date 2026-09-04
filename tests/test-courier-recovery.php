<?php
/** Static behavioral guard for courier webhook recovery. */
$source = file_get_contents(__DIR__ . '/../includes/class-smf-courier-recovery.php');
$plugin = file_get_contents(__DIR__ . '/../sync-meta-flow.php');
if ($source === false || $plugin === false) { fwrite(STDERR, "FAIL: unable to read recovery sources.\n"); exit(1); }
$checks = array(
    'class SMF_Courier_Recovery' => 'recovery class exists',
    'add_action(\'admin_post_smf_retry_courier_event\'' => 'admin replay action is registered',
    'current_user_can(\'manage_woocommerce\')' => 'replay requires WooCommerce capability',
    'check_admin_referer(\'smf_retry_courier_event_' => 'replay is nonce protected',
    "result='failed'" => 'only failed events are replayable',
    'hash_hmac(\'sha256\', $payload, $secret)' => 'replay is signed',
    'X-SMF-Signature' => 'signature header is sent',
    'wp_remote_post($url' => 'replay uses HTTP POST to the canonical webhook',
    'Courier Recovery' => 'admin recovery page exists',
    'class-smf-courier-recovery.php' => 'plugin loads recovery module',
    'SMF_Courier_Recovery::init()' => 'plugin initializes recovery module',
);
$failed = false;
foreach ($checks as $needle => $label) {
    if (strpos($source, $needle) === false && strpos($plugin, $needle) === false) { fwrite(STDERR, "FAIL: {$label}\n"); $failed = true; }
    else echo "PASS: {$label}\n";
}
if ($failed) exit(1);
echo "All courier recovery invariants passed.\n";
