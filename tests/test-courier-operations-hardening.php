<?php
/**
 * Static behavioral guard for courier shipment safety.
 */
$source = file_get_contents(__DIR__ . '/../includes/class-smf-courier.php');
if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read courier source.\n");
    exit(1);
}

$checks = array(
    "private static function create_steadfast_order($order)" => 'shipment creation helper exists',
    "get_meta('_smf_courier_consignment_id')" => 'existing consignment is checked',
    "new WP_Error('smf_shipment_exists'" => 'duplicate shipment is rejected',
    "if (isset(\$_POST['steadfast_api_key']) && trim((string)wp_unslash(\$_POST['steadfast_api_key'])) !== '')" => 'blank API key cannot erase stored credential',
    "if (isset(\$_POST['steadfast_secret_key']) && trim((string)wp_unslash(\$_POST['steadfast_secret_key'])) !== '')" => 'blank secret key cannot erase stored credential',
    "check_admin_referer('smf_create_shipment_'.$order_id)" => 'shipment action remains nonce protected',
    "current_user_can('manage_woocommerce')" => 'shipment action requires WooCommerce capability',
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
echo "All courier shipment hardening invariants passed.\n";
