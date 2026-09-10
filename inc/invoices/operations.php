<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_invoice_brand_label($brand)
{
    return $brand === 'official' ? 'فاکتور رسمی زیگورات' : 'فاکتور غیررسمی دیاموند';
}

function zigurat_invoice_document_label($type)
{
    return $type === 'invoice' ? 'فاکتور فروش کالا و خدمات' : 'پیش‌فاکتور';
}

function zigurat_invoice_status_label($status)
{
    return $status === 'draft' ? 'پیش‌نویس' : 'صادرشده';
}

function zigurat_invoice_payment_status_label($status)
{
    $labels = array(
        'not_applicable' => 'بدون وضعیت پرداخت',
        'unpaid' => 'پرداخت‌نشده',
        'partial' => 'پرداخت جزئی',
        'settled' => 'تسویه کامل',
    );
    return $labels[$status] ?? $labels['unpaid'];
}

function zigurat_invoice_tax_status_label($status)
{
    $labels = array(
        'not_submitted' => 'ثبت‌نشده',
        'ready' => 'آماده ارسال',
        'submitted' => 'ثبت‌شده',
        'confirmed' => 'تأییدشده در مؤدیان',
        'rejected' => 'خطا / ردشده',
        'corrected' => 'اصلاح‌شده',
        'voided' => 'باطل‌شده',
    );
    return $labels[$status] ?? $labels['not_submitted'];
}

function zigurat_invoice_tax_subject_label($subject)
{
    return $subject === 'correction' ? 'اصلاحیه' : 'اصلی';
}

function zigurat_invoice_is_locked($invoice)
{
    if (!$invoice || ($invoice->document_type ?? '') !== 'invoice' || ($invoice->status ?? '') !== 'issued') {
        return false;
    }
    return ($invoice->payment_status ?? '') === 'settled'
        || ((int) ($invoice->grand_total ?? 0) > 0 && (int) ($invoice->paid_amount ?? 0) >= (int) $invoice->grand_total);
}

function zigurat_invoice_lock_reason_label($invoice)
{
    if (!$invoice) {
        return '';
    }
    $reason = (string) ($invoice->locked_reason ?? '');
    if ($reason === 'settled' || ($invoice->payment_status ?? '') === 'settled') {
        return 'این فاکتور تسویه کامل شده و برای حفظ سابقه مالی قابل ویرایش نیست.';
    }
    return 'این سند قفل شده و قابل ویرایش نیست.';
}

function zigurat_invoice_default_seller($brand)
{
    if (function_exists('zigurat_invoice_get_brand_settings')) {
        $settings = zigurat_invoice_get_brand_settings($brand);
        return $settings['seller'];
    }
    if ($brand === 'official') {
        return array(
            'name' => 'سامان موثق (زیگورات)',
            'national_id' => '0067273602',
            'economic_no' => '00672736020002',
            'province' => 'البرز',
            'county' => 'کرج',
            'city' => 'کرج',
            'postal_code' => '3366135526',
            'address' => 'چهارباغ، قوهه، بلوار امام خمینی، پلاک ۹۱',
            'phone' => '09125606941',
        );
    }
    return array(
        'name' => 'فروشگاه دیاموند',
        'national_id' => '',
        'economic_no' => '',
        'province' => 'تهران',
        'county' => 'تهران',
        'city' => 'تهران',
        'postal_code' => '',
        'address' => '',
        'phone' => '',
    );
}

function zigurat_invoice_default_payment_info($brand)
{
    if (function_exists('zigurat_invoice_get_brand_settings')) {
        $settings = zigurat_invoice_get_brand_settings($brand);
        return (string) $settings['payment_info'];
    }
    return $brand === 'official'
        ? "شماره حساب: 80000611194000\nشماره کارت: 6221-0610-2723-6779\nشماره شبا: IR450540103480000611194000\nبانک پارسیان به نام سامان موثق"
        : '';
}

function zigurat_invoice_default_notes($brand)
{
    if (function_exists('zigurat_invoice_get_brand_settings')) {
        $settings = zigurat_invoice_get_brand_settings($brand);
        return (string) ($settings['notes'] ?? '');
    }
    return 'اعتبار قیمت تا ۴۸ ساعت است.';
}

function zigurat_invoice_normalize_digits($value)
{
    return strtr((string) $value, array(
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ));
}

function zigurat_invoice_money($value)
{
    $value = preg_replace('/[^0-9]/', '', zigurat_invoice_normalize_digits($value));
    return $value === '' ? 0 : min(PHP_INT_MAX, (int) $value);
}

function zigurat_invoice_quantity($value)
{
    $value = str_replace(array(',', '،', '٫'), '.', zigurat_invoice_normalize_digits($value));
    $value = preg_replace('/[^0-9.]/', '', $value);
    return max(0, round((float) $value, 3));
}

function zigurat_invoice_format_money($amount)
{
    return number_format_i18n((int) $amount) . ' ریال';
}

function zigurat_invoice_format_number($number, $suffix = 0)
{
    $formatted = str_pad((string) absint($number), 3, '0', STR_PAD_LEFT);
    $suffix = absint($suffix);
    return $suffix > 0 ? $formatted . '/' . $suffix : $formatted;
}

function zigurat_invoice_object_number($invoice)
{
    return zigurat_invoice_format_number(
        $invoice->document_number ?? 0,
        $invoice->number_suffix ?? 0
    );
}

function zigurat_invoice_today_jalali()
{
    $tehran = new DateTimeImmutable('now', new DateTimeZone('Asia/Tehran'));
    if (function_exists('zigurat_inventory_gregorian_to_jalali')) {
        $date = zigurat_inventory_gregorian_to_jalali((int) $tehran->format('Y'), (int) $tehran->format('m'), (int) $tehran->format('d'));
        return sprintf('%04d/%02d/%02d', $date[0], $date[1], $date[2]);
    }
    return $tehran->format('Y/m/d');
}

function zigurat_invoice_tax_period($brand, $type, $issue_date)
{
    if ($brand !== 'official' || $type !== 'invoice') {
        return array('year'=>0, 'quarter'=>0);
    }
    $issue_date = zigurat_invoice_normalize_digits($issue_date);
    if (!preg_match('/^(\d{4})\/(\d{2})\/\d{2}$/', $issue_date, $matches)) {
        return array('year'=>0, 'quarter'=>0);
    }
    $month = (int) $matches[2];
    if ($month < 1 || $month > 12) {
        return array('year'=>0, 'quarter'=>0);
    }
    return array(
        'year'=>(int) $matches[1],
        'quarter'=>(int) ceil($month / 3),
    );
}

function zigurat_invoice_tax_quarter_label($quarter)
{
    $labels = array(1=>'بهار', 2=>'تابستان', 3=>'پاییز', 4=>'زمستان');
    return $labels[absint($quarter)] ?? 'نامشخص';
}

function zigurat_invoice_tax_years()
{
    global $wpdb;
    $years = array_map('absint', $wpdb->get_col(
        "SELECT DISTINCT tax_year FROM " . zigurat_invoices_table_name() . "
         WHERE brand='official' AND document_type='invoice' AND tax_year > 0
         ORDER BY tax_year DESC"
    ));
    $current_year = (int) substr(zigurat_invoice_today_jalali(), 0, 4);
    if ($current_year > 0 && !in_array($current_year, $years, true)) {
        array_unshift($years, $current_year);
    }
    rsort($years, SORT_NUMERIC);
    return $years;
}

function zigurat_invoice_tax_summary($year)
{
    global $wpdb;
    $summary = array();
    for ($quarter = 1; $quarter <= 4; $quarter++) {
        $summary[$quarter] = (object) array(
            'tax_quarter'=>$quarter,
            'invoice_count'=>0,
            'grand_total'=>0,
            'tax_amount'=>0,
            'paid_amount'=>0,
            'balance'=>0,
        );
    }
    $year = absint($year);
    if (!$year) {
        return $summary;
    }
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT tax_quarter, COUNT(*) invoice_count,
                SUM(grand_total) grand_total, SUM(tax_amount) tax_amount,
                SUM(paid_amount) paid_amount, SUM(balance) balance
         FROM " . zigurat_invoices_table_name() . " i
         WHERE i.brand='official' AND i.document_type='invoice' AND i.status='issued'
           AND i.tax_status <> 'voided'
           AND NOT EXISTS (
               SELECT 1 FROM " . zigurat_invoices_table_name() . " child
               WHERE child.reference_invoice_id = i.id AND child.tax_subject = 'correction'
                 AND child.status = 'issued' AND child.tax_status IN ('submitted','confirmed','corrected')
           )
           AND i.tax_year=%d AND i.tax_quarter BETWEEN 1 AND 4
         GROUP BY tax_quarter",
        $year
    ));
    foreach ($rows as $row) {
        $summary[(int) $row->tax_quarter] = $row;
    }
    return $summary;
}

function zigurat_invoice_page_url($args = array())
{
    $page = get_page_by_path('invoices');
    $url = $page ? get_permalink($page) : home_url('/invoices/');
    if (!$args) {
        return $url;
    }

    // Generic query names such as `brand`, `type` and `view` may be claimed by
    // plugins/taxonomies and can make WordPress route away from this page.
    $route_keys = array(
        'brand' => 'invoice_brand',
        'view'  => 'invoice_view',
        'type'  => 'invoice_type',
        'edit'  => 'invoice_edit',
        'id'    => 'invoice_id',
        'from_proforma' => 'invoice_from_proforma',
        'branch_from' => 'invoice_branch_from',
        'correction_from' => 'invoice_correction_from',
    );
    foreach ($route_keys as $key => $invoice_key) {
        if (array_key_exists($key, $args)) {
            $args[$invoice_key] = $args[$key];
            unset($args[$key]);
        }
    }
    return add_query_arg($args, $url);
}

function zigurat_invoice_get($invoice_id)
{
    global $wpdb;
    $invoice = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . zigurat_invoices_table_name() . ' WHERE id = %d', absint($invoice_id)));
    if (!$invoice) {
        return null;
    }
    $invoice->items = $wpdb->get_results($wpdb->prepare(
        'SELECT * FROM ' . zigurat_invoice_items_table_name() . ' WHERE invoice_id = %d ORDER BY position ASC, id ASC',
        $invoice->id
    ));
    $seller = json_decode((string) $invoice->seller_json, true);
    $invoice->seller = is_array($seller) ? $seller : zigurat_invoice_default_seller($invoice->brand);
    return $invoice;
}

function zigurat_invoice_trash_get_record($trash_id)
{
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . zigurat_invoice_trash_table_name() . ' WHERE id = %d',
        absint($trash_id)
    ));
}

function zigurat_invoice_trash_get_invoice($trash_id)
{
    $record = zigurat_invoice_trash_get_record($trash_id);
    if (!$record) {
        return null;
    }
    $snapshot = json_decode((string) $record->snapshot_json, true);
    if (!is_array($snapshot) || empty($snapshot['invoice']) || !is_array($snapshot['invoice'])) {
        return null;
    }
    $invoice = (object) $snapshot['invoice'];
    $invoice->items = array_map(static function ($item) {
        return (object) $item;
    }, is_array($snapshot['items'] ?? null) ? $snapshot['items'] : array());
    $seller = json_decode((string) ($invoice->seller_json ?? ''), true);
    $invoice->seller = is_array($seller) ? $seller : zigurat_invoice_default_seller($invoice->brand ?? 'unofficial');
    $invoice->_trash_id = (int) $record->id;
    $invoice->_from_trash = true;
    return $invoice;
}

function zigurat_invoice_trash_list($args = array())
{
    global $wpdb;
    $args = wp_parse_args($args, array('brand'=>'', 'search'=>'', 'page'=>1, 'per_page'=>30));
    $where = array('1=1');
    $values = array();
    if (in_array($args['brand'], array('official','unofficial'), true)) {
        $where[] = 'brand=%s';
        $values[] = $args['brand'];
    }
    if ($args['search'] !== '') {
        $like = '%' . $wpdb->esc_like($args['search']) . '%';
        $where[] = "(customer_name LIKE %s OR subject LIKE %s OR CONCAT(document_number, IF(number_suffix > 0, CONCAT('/', number_suffix), '')) LIKE %s)";
        array_push($values, $like, $like, $like);
    }
    $table = zigurat_invoice_trash_table_name();
    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
    $total = (int) ($values ? $wpdb->get_var($wpdb->prepare($count_sql, $values)) : $wpdb->get_var($count_sql));
    $per_page = max(1, min(100, absint($args['per_page'])));
    $page = max(1, absint($args['page']));
    $offset = ($page - 1) * $per_page;
    $sql = "SELECT trash.*, users.display_name AS deleted_by_name
            FROM {$table} trash
            LEFT JOIN {$wpdb->users} users ON users.ID = trash.deleted_by
            WHERE {$where_sql}
            ORDER BY trash.deleted_at DESC, trash.id DESC
            LIMIT %d OFFSET %d";
    return array(
        'items' => $wpdb->get_results($wpdb->prepare($sql, array_merge($values, array($per_page, $offset)))),
        'total' => $total,
        'pages' => max(1, (int) ceil($total / $per_page)),
        'page' => $page,
    );
}

function zigurat_invoice_number_is_in_use($brand, $type, $number, $suffix = 0)
{
    global $wpdb;
    return (bool) $wpdb->get_var($wpdb->prepare(
        'SELECT id FROM ' . zigurat_invoices_table_name() . ' WHERE brand = %s AND document_type = %s AND document_number = %d AND number_suffix = %d LIMIT 1',
        $brand,
        $type,
        absint($number),
        absint($suffix)
    ));
}

function zigurat_invoice_restore_from_trash($trash_id, $use_new_number = false)
{
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'فقط مدیرکل اجازه بازیابی فاکتور را دارد.');
    }
    global $wpdb;
    $trash_table = zigurat_invoice_trash_table_name();
    $invoice_table = zigurat_invoices_table_name();
    $items_table = zigurat_invoice_items_table_name();
    $payments_table = zigurat_invoice_payments_table_name();
    $trash_id = absint($trash_id);
    $wpdb->query('START TRANSACTION');
    $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$trash_table} WHERE id = %d FOR UPDATE", $trash_id));
    $snapshot = $record ? json_decode((string) $record->snapshot_json, true) : null;
    if (!$record || !is_array($snapshot) || empty($snapshot['invoice']) || !is_array($snapshot['invoice'])) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('not_found', 'نسخه حذف‌شده فاکتور پیدا نشد.');
    }
    $invoice_data = $snapshot['invoice'];
    $original_id = absint($invoice_data['id'] ?? 0);
    $brand = sanitize_key((string) ($invoice_data['brand'] ?? ''));
    $type = sanitize_key((string) ($invoice_data['document_type'] ?? ''));
    $number = absint($invoice_data['document_number'] ?? 0);
    $suffix = absint($invoice_data['number_suffix'] ?? 0);
    $id_conflict = $original_id ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$invoice_table} WHERE id = %d", $original_id)) : 1;
    $number_conflict = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$invoice_table} WHERE brand = %s AND document_type = %s AND document_number = %d AND number_suffix = %d",
        $brand,
        $type,
        $number,
        $suffix
    ));
    $restore_as_new_record = $number_conflict > 0 && $use_new_number;
    if ($id_conflict > 0 && !$restore_as_new_record) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('restore_id_conflict', 'شناسه داخلی این فاکتور دوباره استفاده شده و بازیابی امن آن ممکن نیست.');
    }
    if ($number_conflict > 0 && !$use_new_number) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('restore_number_conflict', 'شماره اصلی این فاکتور دوباره استفاده شده است؛ بازیابی با شماره جدید را تأیید کنید.');
    }
    $restored_with_new_number = false;
    if ($number_conflict > 0) {
        $sequence_table = zigurat_invoice_sequences_table_name();
        $first_number = zigurat_invoice_next_number($brand, $type);
        $now = current_time('mysql', true);
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$sequence_table} (brand,document_type,last_number,updated_at) VALUES (%s,%s,%d,%s)",
            $brand,
            $type,
            max(0, $first_number - 1),
            $now
        ));
        $last_number = $wpdb->get_var($wpdb->prepare(
            "SELECT last_number FROM {$sequence_table} WHERE brand = %s AND document_type = %s FOR UPDATE",
            $brand,
            $type
        ));
        if ($last_number === null) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('database', 'شماره جدید برای بازیابی ساخته نشد.');
        }
        $number = (int) $last_number + 1;
        while ($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$invoice_table} WHERE brand = %s AND document_type = %s AND document_number = %d LIMIT 1 FOR UPDATE",
            $brand,
            $type,
            $number
        ))) {
            ++$number;
        }
        if ($wpdb->update(
            $sequence_table,
            array('last_number'=>$number, 'updated_at'=>$now),
            array('brand'=>$brand, 'document_type'=>$type),
            array('%d','%s'),
            array('%s','%s')
        ) === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('database', 'شماره جدید بازیابی ذخیره نشد.');
        }
        $invoice_data['document_number'] = $number;
        $invoice_data['number_suffix'] = 0;
        $invoice_data['parent_invoice_id'] = 0;
        $invoice_data['allow_branches'] = 0;
        unset($invoice_data['id']);
        $restored_with_new_number = true;
    }
    foreach (array('parent_invoice_id','reference_invoice_id','source_proforma_id') as $relation_key) {
        $relation_id = absint($invoice_data[$relation_key] ?? 0);
        if ($relation_id && !(int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$invoice_table} WHERE id = %d", $relation_id))) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('missing_relation', 'ابتدا سند اصلی یا مرتبط با این فاکتور را از سطل زباله بازیابی کنید.');
        }
    }
    $invoice_columns = array_flip($wpdb->get_col("SHOW COLUMNS FROM {$invoice_table}", 0));
    $invoice_data = array_intersect_key($invoice_data, $invoice_columns);
    if (!$wpdb->insert($invoice_table, $invoice_data)) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('database', 'بازیابی اطلاعات فاکتور انجام نشد.');
    }
    $restored_id = $restore_as_new_record ? (int) $wpdb->insert_id : $original_id;
    foreach ((array) ($snapshot['items'] ?? array()) as $item) {
        if (!is_array($item)) {
            continue;
        }
        unset($item['id']);
        $item['invoice_id'] = $restored_id;
        if (!$wpdb->insert($items_table, $item)) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('database', 'ردیف‌های فاکتور بازیابی نشدند.');
        }
    }
    foreach ((array) ($snapshot['payments'] ?? array()) as $payment) {
        if (!is_array($payment)) {
            continue;
        }
        unset($payment['id']);
        $payment['invoice_id'] = $restored_id;
        if (!$wpdb->insert($payments_table, $payment)) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('database', 'پرداخت‌های فاکتور بازیابی نشدند.');
        }
    }
    if ($wpdb->delete($trash_table, array('id'=>$trash_id), array('%d')) !== 1) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('database', 'فاکتور بازیابی شد اما از سطل زباله خارج نشد.');
    }
    $wpdb->query('COMMIT');
    $restored = zigurat_invoice_get($restored_id);
    if ($restored) {
        $restored->_restored_with_new_number = $restored_with_new_number;
    }
    return $restored;
}

function zigurat_invoice_delete_from_trash($trash_id)
{
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'فقط مدیرکل اجازه حذف دائمی فاکتور را دارد.');
    }
    $record = zigurat_invoice_trash_get_record($trash_id);
    if (!$record) {
        return new WP_Error('not_found', 'نسخه حذف‌شده فاکتور پیدا نشد.');
    }
    global $wpdb;
    if ($wpdb->delete(zigurat_invoice_trash_table_name(), array('id'=>absint($trash_id)), array('%d')) !== 1) {
        return new WP_Error('database', 'حذف دائمی فاکتور انجام نشد.');
    }
    return $record;
}

/**
 * Move an invoice and all of its own rows to the separate trash archive.
 * Related documents must be removed first so relationships never become orphaned.
 */
function zigurat_invoice_delete($invoice_id)
{
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'فقط مدیرکل اجازه حذف فاکتور را دارد.');
    }

    $invoice_id = absint($invoice_id);
    global $wpdb;
    $invoice_table = zigurat_invoices_table_name();
    $items_table = zigurat_invoice_items_table_name();
    $payments_table = zigurat_invoice_payments_table_name();
    $trash_table = zigurat_invoice_trash_table_name();
    $wpdb->query('START TRANSACTION');
    $invoice_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$invoice_table} WHERE id = %d FOR UPDATE", $invoice_id), ARRAY_A);
    if (!$invoice_row) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('not_found', 'فاکتور پیدا نشد.');
    }
    $related_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$invoice_table}
         WHERE parent_invoice_id = %d OR reference_invoice_id = %d OR source_proforma_id = %d",
        $invoice_id,
        $invoice_id,
        $invoice_id
    ));
    if ($related_count > 0) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('has_related_documents', 'این سند به فاکتور یا انشعاب دیگری متصل است؛ ابتدا اسناد وابسته را حذف کنید.');
    }
    $item_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$items_table} WHERE invoice_id = %d ORDER BY position ASC, id ASC", $invoice_id), ARRAY_A);
    $payment_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$payments_table} WHERE invoice_id = %d ORDER BY id ASC", $invoice_id), ARRAY_A);
    $snapshot = wp_json_encode(array(
        'version' => 1,
        'invoice' => $invoice_row,
        'items' => $item_rows,
        'payments' => $payment_rows,
    ), JSON_UNESCAPED_UNICODE);
    $trash_saved = $snapshot ? $wpdb->insert(
        $trash_table,
        array(
            'original_invoice_id' => $invoice_id,
            'brand' => $invoice_row['brand'],
            'document_type' => $invoice_row['document_type'],
            'document_number' => $invoice_row['document_number'],
            'number_suffix' => $invoice_row['number_suffix'],
            'issue_date' => $invoice_row['issue_date'],
            'customer_name' => $invoice_row['customer_name'],
            'subject' => $invoice_row['subject'],
            'grand_total' => $invoice_row['grand_total'],
            'snapshot_json' => $snapshot,
            'deleted_by' => get_current_user_id(),
            'deleted_at' => current_time('mysql', true),
        ),
        array('%d','%s','%s','%d','%d','%s','%s','%s','%d','%s','%d','%s')
    ) : false;
    if (!$trash_saved) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('database', 'فاکتور به سطل زباله منتقل نشد.');
    }
    $items_deleted = $wpdb->delete($items_table, array('invoice_id' => $invoice_id), array('%d'));
    $payments_deleted = $wpdb->delete($payments_table, array('invoice_id' => $invoice_id), array('%d'));
    $invoice_deleted = $wpdb->delete($invoice_table, array('id' => $invoice_id), array('%d'));
    if ($items_deleted === false || $payments_deleted === false || $invoice_deleted !== 1) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('database', 'حذف فاکتور کامل نشد؛ دوباره تلاش کنید.');
    }

    // اگر هیچ سند یا انشعاب دیگری این شماره را ندارد، شماره برای سند بعدی آزاد شود.
    $number_still_used = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$invoice_table} WHERE brand = %s AND document_type = %s AND document_number = %d",
        $invoice_row['brand'],
        $invoice_row['document_type'],
        $invoice_row['document_number']
    ));
    if ($number_still_used === 0) {
        $sequence_table = zigurat_invoice_sequences_table_name();
        $released_last_number = max(0, (int) $invoice_row['document_number'] - 1);
        $current_last_number = $wpdb->get_var($wpdb->prepare(
            "SELECT last_number FROM {$sequence_table} WHERE brand = %s AND document_type = %s FOR UPDATE",
            $invoice_row['brand'],
            $invoice_row['document_type']
        ));
        if ($current_last_number === null) {
            $sequence_saved = $wpdb->insert(
                $sequence_table,
                array(
                    'brand' => $invoice_row['brand'],
                    'document_type' => $invoice_row['document_type'],
                    'last_number' => $released_last_number,
                    'updated_at' => current_time('mysql', true),
                ),
                array('%s', '%s', '%d', '%s')
            );
        } elseif ((int) $current_last_number > $released_last_number) {
            $sequence_saved = $wpdb->update(
                $sequence_table,
                array('last_number' => $released_last_number, 'updated_at' => current_time('mysql', true)),
                array('brand' => $invoice_row['brand'], 'document_type' => $invoice_row['document_type']),
                array('%d', '%s'),
                array('%s', '%s')
            );
        } else {
            $sequence_saved = true;
        }
        if ($sequence_saved === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('database', 'فاکتور حذف شد اما شماره آن آزاد نشد؛ دوباره تلاش کنید.');
        }
    }

    $wpdb->query('COMMIT');
    $invoice = (object) $invoice_row;
    return $invoice;
}

function zigurat_invoice_get_latest_correction($invoice_id)
{
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . zigurat_invoices_table_name() . " WHERE reference_invoice_id = %d AND tax_subject = 'correction' ORDER BY id DESC LIMIT 1",
        absint($invoice_id)
    ));
}

function zigurat_invoice_set_payment_status($invoice_id, $status)
{
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'فقط مدیر کل می‌تواند وضعیت پرداخت را تغییر دهد.');
    }
    $status = sanitize_key($status);
    if (!in_array($status, array('unpaid','settled'), true)) {
        return new WP_Error('invalid_status', 'وضعیت پرداخت معتبر نیست.');
    }
    $invoice = zigurat_invoice_get($invoice_id);
    if (!$invoice || $invoice->document_type !== 'invoice') {
        return new WP_Error('invalid_invoice', 'این سند، فاکتور معتبر نیست.');
    }
    if ($invoice->status !== 'issued') {
        return new WP_Error('draft_invoice', 'ابتدا فاکتور را از حالت پیش‌نویس خارج کنید.');
    }
    $now = current_time('mysql', true);
    global $wpdb;
    $wpdb->query('START TRANSACTION');
    $locked_invoice = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . zigurat_invoices_table_name() . ' WHERE id = %d FOR UPDATE',
        absint($invoice_id)
    ));
    if (!$locked_invoice) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('not_found', 'فاکتور پیدا نشد.');
    }
    $update = array(
        'paid_amount'=>$status === 'settled' ? (int) $locked_invoice->grand_total : 0,
        'balance'=>$status === 'settled' ? 0 : (int) $locked_invoice->grand_total,
        'payment_status'=>$status,
        'settled_at'=>$status === 'settled' ? $now : null,
        'updated_by'=>get_current_user_id(),
        'updated_at'=>$now,
    );
    if ($status === 'settled') {
        $update['locked_at'] = $locked_invoice->locked_at ?: $now;
        $update['locked_reason'] = 'settled';
    } else {
        $update['locked_at'] = null;
        $update['locked_reason'] = '';
    }
    $saved = $wpdb->update(zigurat_invoices_table_name(), $update, array('id'=>absint($invoice_id)));
    if ($saved === false) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('database', 'وضعیت پرداخت ذخیره نشد.');
    }
    $wpdb->query('COMMIT');
    return zigurat_invoice_get($invoice_id);
}

function zigurat_invoice_set_tax_status($invoice_id, $status)
{
    if (!zigurat_is_manager()) {
        return new WP_Error('forbidden', 'دسترسی به وضعیت مؤدیان مجاز نیست.');
    }
    $status = sanitize_key($status);
    if (!in_array($status, array('not_submitted','submitted'), true)) {
        return new WP_Error('invalid_status', 'وضعیت مؤدیان معتبر نیست.');
    }
    $invoice = zigurat_invoice_get($invoice_id);
    if (!$invoice || $invoice->brand !== 'official' || $invoice->document_type !== 'invoice') {
        return new WP_Error('invalid_invoice', 'این سند، فاکتور رسمی معتبر نیست.');
    }
    if ($invoice->status !== 'issued') {
        return new WP_Error('draft_invoice', 'ابتدا فاکتور را از حالت پیش‌نویس خارج کنید.');
    }
    if (in_array(($invoice->tax_status ?? ''), array('confirmed','corrected','voided'), true)) {
        return new WP_Error('terminal_status', 'وضعیت این سند نهایی شده و قابل بازگشت نیست.');
    }
    $now = current_time('mysql', true);
    global $wpdb;
    $wpdb->query('START TRANSACTION');
    $locked_invoice = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . zigurat_invoices_table_name() . ' WHERE id = %d FOR UPDATE',
        absint($invoice_id)
    ));
    if (!$locked_invoice) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('not_found', 'فاکتور پیدا نشد.');
    }
    $update = array(
        'tax_status'=>$status,
        'tax_submitted_at'=>$status === 'submitted' ? $now : null,
        'updated_by'=>get_current_user_id(),
        'updated_at'=>$now,
    );
    if (($locked_invoice->locked_reason ?? '') === 'tax_submitted') {
        $payment_locked = ($locked_invoice->payment_status ?? '') === 'settled';
        $update['locked_at'] = $payment_locked ? ($locked_invoice->locked_at ?: $now) : null;
        $update['locked_reason'] = $payment_locked ? 'settled' : '';
    }
    $saved = $wpdb->update(zigurat_invoices_table_name(), $update, array('id'=>absint($invoice_id)));
    if ($saved === false) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('database', 'وضعیت مؤدیان ذخیره نشد.');
    }
    if (($locked_invoice->tax_subject ?? 'original') === 'correction' && !empty($locked_invoice->reference_invoice_id)) {
        $parent_status = $status === 'submitted' ? 'corrected' : 'submitted';
        $parent_saved = $wpdb->update(zigurat_invoices_table_name(), array(
            'tax_status'=>$parent_status,
            'updated_by'=>get_current_user_id(),
            'updated_at'=>$now,
        ), array('id'=>absint($locked_invoice->reference_invoice_id)), array('%s','%d','%s'), array('%d'));
        if ($parent_saved === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('database', 'وضعیت فاکتور مرجع اصلاحیه ذخیره نشد.');
        }
    }
    $wpdb->query('COMMIT');
    return zigurat_invoice_get($invoice_id);
}

function zigurat_invoice_can_branch($invoice)
{
    if (!$invoice) {
        return false;
    }
    if (empty($invoice->parent_invoice_id)) {
        return !empty($invoice->allow_branches);
    }
    $root = zigurat_invoice_get($invoice->parent_invoice_id);
    return $root && !empty($root->allow_branches);
}

function zigurat_invoice_next_branch_suffix($invoice)
{
    if (!$invoice) {
        return 1;
    }
    global $wpdb;
    $max_suffix = $wpdb->get_var($wpdb->prepare(
        'SELECT MAX(number_suffix) FROM ' . zigurat_invoices_table_name() . ' WHERE brand = %s AND document_type = %s AND document_number = %d',
        $invoice->brand,
        $invoice->document_type,
        $invoice->document_number
    ));
    return max(1, absint($max_suffix) + 1);
}

/** فاکتوری که قبلاً از یک پیش‌فاکتور ساخته شده است. */
function zigurat_invoice_get_conversion($proforma_id)
{
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        'SELECT id, brand, document_type, document_number, number_suffix, source_proforma_id FROM ' . zigurat_invoices_table_name() . ' WHERE document_type = %s AND source_proforma_id = %d ORDER BY id ASC LIMIT 1',
        'invoice',
        absint($proforma_id)
    ));
}

function zigurat_invoice_clean_seller($data, $brand)
{
    $defaults = zigurat_invoice_default_seller($brand);
    $settings = function_exists('zigurat_invoice_get_brand_settings')
        ? zigurat_invoice_get_brand_settings($brand)
        : array('stamp_id' => 0);
    $seller = array();
    foreach (array('name','national_id','economic_no','province','county','city','postal_code','address','phone') as $key) {
        $raw = $data['seller_' . $key] ?? $defaults[$key];
        $seller[$key] = $key === 'address' ? sanitize_textarea_field(wp_unslash((string) $raw)) : sanitize_text_field(wp_unslash((string) $raw));
    }
    $seller['stamp_id'] = absint($data['seller_stamp_id'] ?? ($settings['stamp_id'] ?? 0));
    $seller['include_stamp'] = !empty($data['include_stamp']) ? 1 : 0;
    return $seller;
}

function zigurat_invoice_clean_items($data)
{
    $descriptions = isset($data['item_description']) && is_array($data['item_description']) ? $data['item_description'] : array();
    $quantities = isset($data['item_quantity']) && is_array($data['item_quantity']) ? $data['item_quantity'] : array();
    $units = isset($data['item_unit']) && is_array($data['item_unit']) ? $data['item_unit'] : array();
    $prices = isset($data['item_unit_price']) && is_array($data['item_unit_price']) ? $data['item_unit_price'] : array();
    $discounts = isset($data['item_discount']) && is_array($data['item_discount']) ? $data['item_discount'] : array();
    $items = array();
    foreach ($descriptions as $index => $description) {
        $description = sanitize_textarea_field(wp_unslash((string) $description));
        if ($description === '') {
            continue;
        }
        $quantity = zigurat_invoice_quantity($quantities[$index] ?? 0);
        $unit_price = zigurat_invoice_money($prices[$index] ?? 0);
        $discount = zigurat_invoice_money($discounts[$index] ?? 0);
        if ($quantity <= 0) {
            continue;
        }
        $raw_total = (int) round($quantity * $unit_price);
        $discount = min($discount, $raw_total);
        $items[] = array(
            'description' => $description,
            'quantity' => $quantity,
            'unit' => sanitize_text_field(wp_unslash((string) ($units[$index] ?? ''))),
            'unit_price' => $unit_price,
            'discount' => $discount,
            'line_total' => max(0, $raw_total - $discount),
        );
    }
    return $items;
}

function zigurat_invoice_save($data)
{
    if (!zigurat_is_manager()) {
        return new WP_Error('forbidden', 'دسترسی به بخش فاکتور مجاز نیست.');
    }
    $invoice_id = absint($data['invoice_id'] ?? 0);
    $existing = $invoice_id ? zigurat_invoice_get($invoice_id) : null;
    $brand = $existing ? $existing->brand : sanitize_key($data['invoice_form_brand'] ?? ($data['brand'] ?? ''));
    $type = $existing ? $existing->document_type : sanitize_key($data['invoice_form_document_type'] ?? ($data['document_type'] ?? ''));
    $source_proforma_id = $existing
        ? absint($existing->source_proforma_id ?? 0)
        : absint($data['source_proforma_id'] ?? 0);
    $branch_source_id = $existing ? 0 : absint($data['branch_source_id'] ?? 0);
    $reference_invoice_id = $existing
        ? absint($existing->reference_invoice_id ?? 0)
        : absint($data['reference_invoice_id'] ?? 0);
    $tax_subject = $existing
        ? sanitize_key($existing->tax_subject ?? 'original')
        : sanitize_key($data['tax_subject'] ?? 'original');
    if (!in_array($tax_subject, array('original','correction'), true)) {
        $tax_subject = 'original';
    }
    if (!in_array($brand, array('official','unofficial'), true) || !in_array($type, array('proforma','invoice'), true)) {
        return new WP_Error('invalid_type', 'نوع فاکتور معتبر نیست.');
    }
    if ($invoice_id && !$existing) {
        return new WP_Error('not_found', 'فاکتور پیدا نشد.');
    }
    if ($existing && zigurat_invoice_is_locked($existing)) {
        return new WP_Error('invoice_locked', zigurat_invoice_lock_reason_label($existing));
    }
    if ($branch_source_id && $source_proforma_id) {
        return new WP_Error('invalid_branch', 'یک سند هم‌زمان نمی‌تواند تبدیل و انشعاب باشد.');
    }
    if ($tax_subject === 'correction') {
        $reference = zigurat_invoice_get($reference_invoice_id);
        if (!$reference || $brand !== 'official' || $type !== 'invoice'
            || $reference->brand !== 'official' || $reference->document_type !== 'invoice') {
            return new WP_Error('invalid_reference', 'فاکتور مرجع اصلاحیه معتبر نیست.');
        }
        $previous_correction = zigurat_invoice_get_latest_correction($reference_invoice_id);
        if (!$existing && $previous_correction) {
            return new WP_Error('correction_exists', 'برای این فاکتور قبلاً اصلاحیه شماره ' . zigurat_invoice_object_number($previous_correction) . ' ساخته شده است.');
        }
        if ($branch_source_id || $source_proforma_id) {
            return new WP_Error('invalid_correction', 'اصلاحیه نمی‌تواند هم‌زمان تبدیل یا انشعاب باشد.');
        }
    } else {
        $reference_invoice_id = 0;
    }
    if ($branch_source_id) {
        $branch_source = zigurat_invoice_get($branch_source_id);
        if (!$branch_source || $branch_source->brand !== $brand || $branch_source->document_type !== $type) {
            return new WP_Error('invalid_branch', 'سند مبدأ انشعاب معتبر نیست.');
        }
        $branch_root = !empty($branch_source->parent_invoice_id)
            ? zigurat_invoice_get($branch_source->parent_invoice_id)
            : $branch_source;
        if (!$branch_root || empty($branch_root->allow_branches)) {
            return new WP_Error('branch_disabled', 'برای این سند امکان ایجاد انشعاب فعال نشده است.');
        }
    }
    if ($source_proforma_id) {
        $source_proforma = zigurat_invoice_get($source_proforma_id);
        if (!$source_proforma || $source_proforma->document_type !== 'proforma') {
            return new WP_Error('invalid_source_proforma', 'پیش‌فاکتور مبدأ معتبر نیست.');
        }
        if ($type !== 'invoice' || $source_proforma->brand !== $brand) {
            return new WP_Error('invalid_conversion', 'پیش‌فاکتور فقط به فاکتور همان مجموعه قابل تبدیل است.');
        }
        $previous_conversion = zigurat_invoice_get_conversion($source_proforma_id);
        if ($previous_conversion && (int) $previous_conversion->id !== $invoice_id) {
            return new WP_Error(
                'already_converted',
                'این پیش‌فاکتور قبلاً به فاکتور شماره ' . zigurat_invoice_object_number($previous_conversion) . ' تبدیل شده است.'
            );
        }
    }
    $customer_name = sanitize_text_field(wp_unslash((string) ($data['customer_name'] ?? '')));
    $items = zigurat_invoice_clean_items($data);
    if ($customer_name === '') {
        return new WP_Error('invalid_customer', 'نام خریدار را وارد کنید.');
    }
    if (!$items) {
        return new WP_Error('invalid_items', 'حداقل یک ردیف کالا یا خدمت معتبر ثبت کنید.');
    }
    $issue_date = zigurat_invoice_normalize_digits($data['issue_date'] ?? '');
    if (!preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $issue_date)) {
        return new WP_Error('invalid_date', 'تاریخ را به‌شکل ۱۴۰۵/۰۵/۱۲ وارد کنید.');
    }
    $status = ($data['status'] ?? '') === 'draft' ? 'draft' : 'issued';
    $tax_period = zigurat_invoice_tax_period($brand, $type, $issue_date);
    $subtotal = array_sum(wp_list_pluck($items, 'line_total'));
    $discount = min(zigurat_invoice_money($data['discount'] ?? 0), $subtotal);
    $shipping = zigurat_invoice_money($data['shipping'] ?? 0);
    $overhead_rate = max(0, min(100, (float) zigurat_invoice_normalize_digits($data['overhead_rate'] ?? 0)));
    $insurance_rate = $brand === 'official'
        ? max(0, min(100, (float) zigurat_invoice_normalize_digits($data['insurance_rate'] ?? 0)))
        : 0;
    $base_amount = max(0, $subtotal - $discount + $shipping);
    $overhead_amount = (int) round($base_amount * $overhead_rate / 100);
    $amount_with_overhead = $base_amount + $overhead_amount;
    $insurance_amount = (int) round($amount_with_overhead * $insurance_rate / 100);
    $tax_rate = max(0, min(100, (float) zigurat_invoice_normalize_digits($data['tax_rate'] ?? 0)));
    $taxable = $amount_with_overhead + $insurance_amount;
    $tax_amount = (int) round($taxable * $tax_rate / 100);
    $grand_total = $taxable + $tax_amount;
    $paid_amount = 0;
    if ($type === 'invoice') {
        $paid_amount = current_user_can('manage_options')
            ? zigurat_invoice_money($data['paid_amount'] ?? 0)
            : (int) ($existing->paid_amount ?? 0);
    }
    if ($paid_amount > $grand_total) {
        return new WP_Error('invalid_paid_amount', 'مبلغ پرداخت‌شده نمی‌تواند بیشتر از جمع کل فاکتور باشد.');
    }
    $balance = max(0, $grand_total - $paid_amount);
    $payment_status = $type !== 'invoice'
        ? 'not_applicable'
        : ($grand_total > 0 && $paid_amount >= $grand_total ? 'settled' : ($paid_amount > 0 ? 'partial' : 'unpaid'));
    $seller = zigurat_invoice_clean_seller($data, $brand);
    $now = current_time('mysql', true);
    $user_id = get_current_user_id();

    global $wpdb;
    $invoice_table = zigurat_invoices_table_name();
    $items_table = zigurat_invoice_items_table_name();
    $sequence_table = zigurat_invoice_sequences_table_name();
    $wpdb->query('START TRANSACTION');
    $number_suffix = 0;
    $parent_invoice_id = 0;
    $allow_branches = !empty($data['allow_branches']) ? 1 : 0;
    if ($existing) {
        $locked = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$invoice_table} WHERE id = %d FOR UPDATE", $invoice_id));
        if (!$locked) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('not_found', 'فاکتور پیدا نشد.');
        }
        if (zigurat_invoice_is_locked($locked)) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('invoice_locked', zigurat_invoice_lock_reason_label($locked));
        }
        $document_number = (int) $locked->document_number;
        $number_suffix = absint($locked->number_suffix ?? 0);
        $parent_invoice_id = absint($locked->parent_invoice_id ?? 0);
        $allow_branches = $parent_invoice_id ? 1 : (!empty($data['allow_branches']) ? 1 : 0);
        if (!$parent_invoice_id && $allow_branches && $number_suffix === 0) {
            $children = $wpdb->get_results($wpdb->prepare(
                "SELECT id, number_suffix FROM {$invoice_table} WHERE parent_invoice_id = %d ORDER BY number_suffix DESC FOR UPDATE",
                $invoice_id
            ));
            foreach ($children as $child) {
                $shifted = $wpdb->update(
                    $invoice_table,
                    array('number_suffix' => absint($child->number_suffix) + 1),
                    array('id' => absint($child->id)),
                    array('%d'),
                    array('%d')
                );
                if ($shifted === false) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('database', 'شماره انشعاب‌های قبلی به‌روزرسانی نشد.');
                }
            }
            $number_suffix = 1;
        } elseif (!$parent_invoice_id && !$allow_branches && $number_suffix > 0) {
            $children = $wpdb->get_results($wpdb->prepare(
                "SELECT id, number_suffix FROM {$invoice_table} WHERE parent_invoice_id = %d ORDER BY number_suffix ASC FOR UPDATE",
                $invoice_id
            ));
            $released = $wpdb->update(
                $invoice_table,
                array('number_suffix' => 0),
                array('id' => $invoice_id),
                array('%d'),
                array('%d')
            );
            if ($released === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('database', 'پسوند سند حذف نشد.');
            }
            foreach ($children as $child) {
                $shifted = $wpdb->update(
                    $invoice_table,
                    array('number_suffix' => max(1, absint($child->number_suffix) - 1)),
                    array('id' => absint($child->id)),
                    array('%d'),
                    array('%d')
                );
                if ($shifted === false) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('database', 'شماره انشعاب‌های قبلی به‌روزرسانی نشد.');
                }
            }
            $number_suffix = 0;
        }
        $source_proforma_id = absint($locked->source_proforma_id ?? 0);
    } else {
        if ($source_proforma_id) {
            $locked_source = $wpdb->get_row($wpdb->prepare(
                "SELECT id, brand, document_type FROM {$invoice_table} WHERE id = %d FOR UPDATE",
                $source_proforma_id
            ));
            $converted_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$invoice_table} WHERE document_type = %s AND source_proforma_id = %d LIMIT 1 FOR UPDATE",
                'invoice',
                $source_proforma_id
            ));
            if (!$locked_source || $locked_source->document_type !== 'proforma' || $locked_source->brand !== $brand || $converted_id) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('already_converted', 'این پیش‌فاکتور معتبر نیست یا قبلاً به فاکتور تبدیل شده است.');
            }
        }
        if ($branch_source_id) {
            $locked_source = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$invoice_table} WHERE id = %d FOR UPDATE",
                $branch_source_id
            ));
            $root_id = $locked_source ? absint($locked_source->parent_invoice_id ?: $locked_source->id) : 0;
            $locked_root = $root_id ? $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$invoice_table} WHERE id = %d FOR UPDATE",
                $root_id
            )) : null;
            if (!$locked_source || !$locked_root || empty($locked_root->allow_branches)
                || $locked_root->brand !== $brand || $locked_root->document_type !== $type) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('branch_disabled', 'امکان ایجاد انشعاب برای این سند فعال نیست.');
            }
            $suffixes = $wpdb->get_col($wpdb->prepare(
                "SELECT number_suffix FROM {$invoice_table} WHERE brand = %s AND document_type = %s AND document_number = %d ORDER BY number_suffix ASC FOR UPDATE",
                $brand,
                $type,
                $locked_root->document_number
            ));
            $document_number = (int) $locked_root->document_number;
            $number_suffix = max(1, ($suffixes ? max(array_map('absint', $suffixes)) : 0) + 1);
            $parent_invoice_id = $root_id;
            $allow_branches = 1;
        } else {
            $first_number = function_exists('zigurat_invoice_next_number') ? zigurat_invoice_next_number($brand, $type) : 1;
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$sequence_table} (brand,document_type,last_number,updated_at) VALUES (%s,%s,%d,%s)",
                $brand, $type, max(0, $first_number - 1), $now
            ));
            $last_number = $wpdb->get_var($wpdb->prepare(
                "SELECT last_number FROM {$sequence_table} WHERE brand = %s AND document_type = %s FOR UPDATE",
                $brand, $type
            ));
            if ($last_number === null) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('database', 'شماره فاکتور ساخته نشد.');
            }
            $document_number = (int) $last_number + 1;
            while ($wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$invoice_table} WHERE brand = %s AND document_type = %s AND document_number = %d LIMIT 1 FOR UPDATE",
                $brand,
                $type,
                $document_number
            ))) {
                ++$document_number;
            }
            $number_suffix = $allow_branches ? 1 : 0;
            $sequence_updated = $wpdb->update($sequence_table, array('last_number'=>$document_number,'updated_at'=>$now), array('brand'=>$brand,'document_type'=>$type), array('%d','%s'), array('%s','%s'));
            if ($sequence_updated === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('database', 'شماره فاکتور ذخیره نشد.');
            }
        }
    }

    $invoice_data = array(
        'brand'=>$brand, 'document_type'=>$type, 'document_number'=>$document_number,
        'number_suffix'=>$number_suffix, 'parent_invoice_id'=>$parent_invoice_id,
        'allow_branches'=>$allow_branches,
        'issue_date'=>$issue_date, 'tax_year'=>$tax_period['year'],
        'tax_quarter'=>$tax_period['quarter'], 'status'=>$status,
        'subject'=>sanitize_text_field(wp_unslash((string) ($data['subject'] ?? ''))),
        'source_proforma_id'=>$source_proforma_id,
        'tax_subject'=>$tax_subject,
        'reference_invoice_id'=>$reference_invoice_id,
        'seller_json'=>wp_json_encode($seller, JSON_UNESCAPED_UNICODE),
        'customer_name'=>$customer_name,
        'customer_national_id'=>sanitize_text_field(wp_unslash((string) ($data['customer_national_id'] ?? ''))),
        'customer_economic_no'=>sanitize_text_field(wp_unslash((string) ($data['customer_economic_no'] ?? ''))),
        'customer_province'=>sanitize_text_field(wp_unslash((string) ($data['customer_province'] ?? ''))),
        'customer_county'=>sanitize_text_field(wp_unslash((string) ($data['customer_county'] ?? ''))),
        'customer_city'=>sanitize_text_field(wp_unslash((string) ($data['customer_city'] ?? ''))),
        'customer_postal_code'=>sanitize_text_field(wp_unslash((string) ($data['customer_postal_code'] ?? ''))),
        'customer_address'=>sanitize_textarea_field(wp_unslash((string) ($data['customer_address'] ?? ''))),
        'customer_phone'=>sanitize_text_field(wp_unslash((string) ($data['customer_phone'] ?? ''))),
        'subtotal'=>$subtotal, 'discount'=>$discount, 'shipping'=>$shipping,
        'overhead_rate'=>$overhead_rate, 'overhead_amount'=>$overhead_amount,
        'insurance_rate'=>$insurance_rate, 'insurance_amount'=>$insurance_amount,
        'tax_rate'=>$tax_rate, 'tax_amount'=>$tax_amount, 'grand_total'=>$grand_total,
        'paid_amount'=>$paid_amount, 'balance'=>$balance,
        'payment_status'=>$payment_status,
        'notes'=>sanitize_textarea_field(wp_unslash((string) ($data['notes'] ?? ''))),
        'payment_info'=>sanitize_textarea_field(wp_unslash((string) ($data['payment_info'] ?? ''))),
        'updated_by'=>$user_id, 'updated_at'=>$now,
    );
    if ($existing) {
        $saved = $wpdb->update($invoice_table, $invoice_data, array('id'=>$invoice_id));
    } else {
        $invoice_data['created_by'] = $user_id;
        $invoice_data['created_at'] = $now;
        $saved = $wpdb->insert($invoice_table, $invoice_data);
        $invoice_id = (int) $wpdb->insert_id;
    }
    if ($saved === false || !$invoice_id) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('database', 'ذخیره فاکتور انجام نشد.');
    }
    if ($existing) {
        $wpdb->delete($items_table, array('invoice_id'=>$invoice_id), array('%d'));
    }
    foreach ($items as $index => $item) {
        $inserted = $wpdb->insert($items_table, array(
            'invoice_id'=>$invoice_id, 'position'=>$index + 1, 'description'=>$item['description'],
            'quantity'=>$item['quantity'], 'unit'=>$item['unit'], 'unit_price'=>$item['unit_price'],
            'discount'=>$item['discount'], 'line_total'=>$item['line_total'],
        ), array('%d','%d','%s','%f','%s','%d','%d','%d'));
        if (!$inserted) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('database', 'ذخیره ردیف‌های فاکتور انجام نشد.');
        }
    }
    if ($status === 'issued' && $payment_status === 'settled') {
        $locked_saved = $wpdb->update($invoice_table, array(
            'settled_at'=>$now,
            'locked_at'=>$now,
            'locked_reason'=>'settled',
        ), array('id'=>$invoice_id), array('%s','%s','%s'), array('%d'));
        if ($locked_saved === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('database', 'وضعیت تسویه فاکتور ذخیره نشد.');
        }
    }
    $wpdb->query('COMMIT');
    return zigurat_invoice_get($invoice_id);
}

function zigurat_invoice_list($args = array())
{
    global $wpdb;
    $args = wp_parse_args($args, array('brand'=>'','type'=>'','status'=>'','payment_status'=>'','tax_status'=>'','search'=>'','tax_year'=>0,'tax_quarter'=>0,'page'=>1,'per_page'=>30));
    $where = array('1=1');
    $values = array();
    if (in_array($args['brand'], array('official','unofficial'), true)) { $where[]='brand=%s'; $values[]=$args['brand']; }
    if (in_array($args['type'], array('invoice','proforma'), true)) { $where[]='document_type=%s'; $values[]=$args['type']; }
    if (in_array($args['status'], array('draft','issued'), true)) { $where[]='status=%s'; $values[]=$args['status']; }
    if ($args['payment_status'] === 'unpaid') { $where[]="payment_status IN ('unpaid','partial')"; }
    elseif ($args['payment_status'] === 'settled') { $where[]='payment_status=%s'; $values[]='settled'; }
    if ($args['tax_status'] === 'not_submitted') { $where[]="tax_status IN ('not_submitted','ready','rejected')"; }
    elseif ($args['tax_status'] === 'submitted') { $where[]="tax_status IN ('submitted','confirmed','corrected','voided')"; }
    if (absint($args['tax_year'])) { $where[]='tax_year=%d'; $values[]=absint($args['tax_year']); }
    if (absint($args['tax_quarter']) >= 1 && absint($args['tax_quarter']) <= 4) { $where[]='tax_quarter=%d'; $values[]=absint($args['tax_quarter']); }
    if ($args['search'] !== '') {
        $like = '%' . $wpdb->esc_like($args['search']) . '%';
        $where[] = "(customer_name LIKE %s OR subject LIKE %s OR CONCAT(document_number, IF(number_suffix > 0, CONCAT('/', number_suffix), '')) LIKE %s)";
        array_push($values, $like, $like, $like);
    }
    $table = zigurat_invoices_table_name();
    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
    $total = (int) ($values ? $wpdb->get_var($wpdb->prepare($count_sql, $values)) : $wpdb->get_var($count_sql));
    $per_page = max(1, min(100, absint($args['per_page'])));
    $page = max(1, absint($args['page']));
    $offset = ($page - 1) * $per_page;
    $sql = "SELECT current_invoice.*,
            EXISTS(
                SELECT 1 FROM {$table} related_invoice
                WHERE related_invoice.parent_invoice_id = current_invoice.id
                   OR related_invoice.reference_invoice_id = current_invoice.id
                   OR related_invoice.source_proforma_id = current_invoice.id
            ) AS has_related_documents
            FROM {$table} current_invoice
            WHERE {$where_sql}
            ORDER BY COALESCE(NULLIF(current_invoice.parent_invoice_id,0),current_invoice.id) DESC,
                     current_invoice.number_suffix DESC,
                     current_invoice.id DESC
            LIMIT %d OFFSET %d";
    return array(
        'items'=>$wpdb->get_results($wpdb->prepare($sql, array_merge($values, array($per_page,$offset)))),
        'total'=>$total, 'pages'=>max(1,(int)ceil($total/$per_page)), 'page'=>$page,
    );
}
