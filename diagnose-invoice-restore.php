<?php
require dirname(__DIR__, 3) . '/wp-load.php';

global $wpdb;
$admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
if ($admins) {
    wp_set_current_user((int) $admins[0]);
}

$trash_table = zigurat_invoice_trash_table_name();
$invoice_table = zigurat_invoices_table_name();
$rows = $wpdb->get_results("SELECT id, original_invoice_id, brand, document_type, document_number, number_suffix FROM {$trash_table} ORDER BY id DESC LIMIT 20");
$result = array();
foreach ($rows as $row) {
    $result[] = array(
        'trash_id' => (int) $row->id,
        'original_id' => (int) $row->original_invoice_id,
        'number' => zigurat_invoice_format_number($row->document_number, $row->number_suffix),
        'number_conflict' => (bool) zigurat_invoice_number_is_in_use($row->brand, $row->document_type, $row->document_number, $row->number_suffix),
        'id_conflict' => (bool) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$invoice_table} WHERE id = %d", $row->original_invoice_id)),
    );
}
echo wp_json_encode(array('admin' => get_current_user_id(), 'rows' => $result, 'db_error' => $wpdb->last_error), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
