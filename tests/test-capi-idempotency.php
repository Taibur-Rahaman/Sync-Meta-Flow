<?php
/**
 * Static behavioral guard for CAPI queue idempotency invariants.
 * Run from repository root with: php tests/test-capi-idempotency.php
 */

$installer = file_get_contents(__DIR__ . '/../includes/class-smf-installer.php');
$capi = file_get_contents(__DIR__ . '/../includes/class-smf-meta-capi.php');

if ($installer === false || $capi === false) {
    fwrite(STDERR, "Unable to read CAPI source files.\n");
    exit(1);
}

$checks = array(
    'queue has unique event_id index' => (bool) preg_match('/UNIQUE\s+KEY\s+event_id\s*\(event_id\)/i', $installer),
    'queue insert is idempotent' => strpos($capi, 'INSERT IGNORE INTO $table') !== false,
    'purchase event id is persisted' => strpos($capi, "_smf_purchase_event_id") !== false,
    'delivered event id is deterministic' => strpos($capi, "smf-delivered-'.\$order->get_id()") !== false,
    'queue has retry state' => strpos($installer, "status varchar(20) NOT NULL DEFAULT 'pending'") !== false,
    'queue has attempt counter' => strpos($installer, 'attempts smallint(5) unsigned') !== false,
);

$failed = array();
foreach ($checks as $name => $passed) {
    if ($passed) {
        echo "PASS: {$name}\n";
    } else {
        echo "FAIL: {$name}\n";
        $failed[] = $name;
    }
}

if ($failed) {
    fwrite(STDERR, "CAPI idempotency invariants failed: " . implode(', ', $failed) . "\n");
    exit(1);
}

echo "All CAPI idempotency invariants passed.\n";
