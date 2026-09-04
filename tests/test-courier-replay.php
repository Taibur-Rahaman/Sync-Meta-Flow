<?php
/**
 * Static behavioral guard for courier webhook replay/conflict protection.
 */
$source = file_get_contents(__DIR__ . '/../includes/class-smf-courier-timeline.php');
if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read courier timeline source.\n");
    exit(1);
}

$checks = array(
    'private static function identity_conflict' => 'identity conflict detector exists',
    'private static function conflict_response' => 'conflict response helper exists',
    'if($existing&&self::identity_conflict($existing,$identity))return self::conflict_response($identity,$existing);' => 'conflicting replay is rejected',
    "'conflict'=>true" => 'conflict is explicitly reported',
    "'reason'=>'event_identity_conflict'" => 'conflict has stable reason',
    "'duplicate'=>true" => 'duplicate responses remain explicit',
    "UNIQUE KEY event_hash(event_hash)" => 'database replay uniqueness remains enforced',
    "WHERE event_hash=%s AND result='processing'" => 'result mutation is processing-state scoped',
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
echo "All courier replay/conflict invariants passed.\n";
