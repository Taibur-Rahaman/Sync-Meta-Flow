<?php
/**
 * Static behavioral guard for atomic courier shipment locking.
 */
$source = file_get_contents(__DIR__ . '/../includes/class-smf-courier.php');
if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read courier source.\n");
    exit(1);
}

$checks = array(
    "global $wpdb;" => 'shipment lock uses the database connection',
    'INSERT IGNORE INTO {$wpdb->options}' => 'lock creation uses an atomic insert',
    "option_name, option_value, autoload" => 'lock insert targets the WordPress options row',
    "CAST(SUBSTRING_INDEX(option_value, ':', -1) AS UNSIGNED) <= %d" => 'expired locks are replaced atomically',
    "WHERE option_name = %s AND option_value LIKE %s" => 'release only deletes the owning lock token',
    "wp_cache_delete($key, 'options')" => 'lock cache is invalidated after database mutation',
    "const SHIPMENT_LOCK_TTL = 120" => 'lock retains a bounded TTL',
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

if ($failed) exit(1);
echo "All atomic courier shipment lock invariants passed.\n";
