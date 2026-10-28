<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_register_workflow_project_post_type()
{
    register_post_type('zig_work_project', array(
        'labels' => array(
            'name' => 'پروژه‌های مدیریتی',
            'singular_name' => 'پروژه مدیریتی',
        ),
        'public' => false,
        'show_ui' => false,
        'show_in_rest' => false,
        'supports' => array('title', 'author'),
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ));
}
add_action('init', 'zigurat_register_workflow_project_post_type', 12);

function zigurat_workflow_project_stages()
{
    return array(
        'lead' => 'پیگیری اولیه',
        'proforma' => 'پیش‌فاکتور',
        'approval' => 'تأیید و قرارداد',
        'production' => 'در حال اجرا',
        'delivery' => 'تحویل و فاکتور',
        'payment' => 'در انتظار پرداخت',
    );
}

/**
 * صدور فاکتور یعنی پروژه حداقل وارد اجرا شده است، نه اینکه تحویل شده باشد.
 * مراحل جلوتر را حفظ می‌کنیم تا ویرایش یا اتصال سند باعث عقب‌گرد کارت نشود.
 */
function zigurat_workflow_project_stage_with_invoice($stage)
{
    $stages = array_keys(zigurat_workflow_project_stages());
    $stage = sanitize_key((string) $stage);
    $stage_rank = array_search($stage, $stages, true);
    $production_rank = array_search('production', $stages, true);
    if ($stage_rank === false || $stage_rank < $production_rank) {
        return 'production';
    }
    return $stage;
}

function zigurat_workflow_project_url($args = array())
{
    return add_query_arg(array_merge(array('manager-section' => 'projects'), $args), zigurat_manager_login_url());
}

function zigurat_workflow_project_normalize_date($value)
{
    $value = sanitize_text_field((string) $value);
    if (function_exists('zigurat_invoice_normalize_digits')) {
        $value = zigurat_invoice_normalize_digits($value);
    }
    $value = str_replace(array('-', '.', '\\'), '/', $value);
    if (!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $value, $matches)) {
        return '';
    }
    $month = (int) $matches[2];
    $day = (int) $matches[3];
    if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
        return '';
    }
    return sprintf('%04d/%02d/%02d', (int) $matches[1], $month, $day);
}

function zigurat_workflow_project_money($value)
{
    if (function_exists('zigurat_invoice_money')) {
        return zigurat_invoice_money($value);
    }
    $value = preg_replace('/[^0-9]/', '', (string) $value);
    return $value === '' ? 0 : (int) $value;
}

function zigurat_workflow_project_document($document_id, $expected_type = '')
{
    $document_id = absint($document_id);
    if (!$document_id || !function_exists('zigurat_invoices_table_name')) {
        return null;
    }
    if (!isset($GLOBALS['zigurat_workflow_document_cache']) || !is_array($GLOBALS['zigurat_workflow_document_cache'])) {
        $GLOBALS['zigurat_workflow_document_cache'] = array();
    }
    if (!array_key_exists($document_id, $GLOBALS['zigurat_workflow_document_cache'])) {
        global $wpdb;
        $GLOBALS['zigurat_workflow_document_cache'][$document_id] = $wpdb->get_row($wpdb->prepare(
            'SELECT id, brand, document_type, document_number, number_suffix, customer_name, customer_phone, subject, status, payment_status, source_proforma_id
             FROM ' . zigurat_invoices_table_name() . ' WHERE id = %d',
            $document_id
        ));
    }
    $document = $GLOBALS['zigurat_workflow_document_cache'][$document_id];
    if (!$document || ($expected_type && $document->document_type !== $expected_type)) {
        return null;
    }
    return $document;
}

/** بارگذاری گروهی اسناد کارت‌ها؛ مانع دو کوئری جداگانه برای هر پروژه می‌شود. */
function zigurat_workflow_prime_documents($document_ids)
{
    global $wpdb;
    $document_ids = array_values(array_unique(array_filter(array_map('absint', (array) $document_ids))));
    if (!$document_ids || !function_exists('zigurat_invoices_table_name')) {
        return;
    }
    if (!isset($GLOBALS['zigurat_workflow_document_cache']) || !is_array($GLOBALS['zigurat_workflow_document_cache'])) {
        $GLOBALS['zigurat_workflow_document_cache'] = array();
    }
    $missing = array_values(array_filter($document_ids, static function ($document_id) {
        return !array_key_exists($document_id, $GLOBALS['zigurat_workflow_document_cache']);
    }));
    if (!$missing) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($missing), '%d'));
    $sql = 'SELECT id, brand, document_type, document_number, number_suffix, customer_name, customer_phone, subject, status, payment_status, source_proforma_id
            FROM ' . zigurat_invoices_table_name() . " WHERE id IN ({$placeholders})";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $missing));
    foreach ($missing as $document_id) {
        $GLOBALS['zigurat_workflow_document_cache'][$document_id] = null;
    }
    foreach ((array) $rows as $row) {
        $GLOBALS['zigurat_workflow_document_cache'][(int) $row->id] = $row;
    }
}

function zigurat_workflow_project_document_url($document)
{
    if (!$document || !function_exists('zigurat_invoice_page_url')) {
        return '';
    }
    return zigurat_invoice_page_url(array(
        'brand' => $document->brand,
        'view' => 'form',
        'type' => $document->document_type,
        'edit' => $document->id,
    ));
}

function zigurat_workflow_project_conversion_url($proforma)
{
    if (!$proforma || $proforma->document_type !== 'proforma' || !function_exists('zigurat_invoice_page_url')) {
        return '';
    }
    return zigurat_invoice_page_url(array(
        'brand' => $proforma->brand,
        'view' => 'form',
        'type' => 'invoice',
        'source_proforma_id' => (int) $proforma->id,
    ));
}

/**
 * شناسه همه اسناد متصل به یک کارت. کلیدهای تکی نسخه قدیمی همچنان خوانده
 * می‌شوند تا پروژه‌های موجود بدون مهاجرت دستی قابل استفاده باشند.
 */
function zigurat_workflow_project_document_ids($project_id, $document_type)
{
    $project_id = absint($project_id);
    $document_type = $document_type === 'invoice' ? 'invoice' : 'proforma';
    $plural_key = $document_type === 'invoice'
        ? '_zigurat_work_project_invoices'
        : '_zigurat_work_project_proformas';
    $legacy_key = $document_type === 'invoice'
        ? '_zigurat_work_project_invoice'
        : '_zigurat_work_project_proforma';
    $ids = array();
    foreach ((array) get_post_meta($project_id, $plural_key, false) as $stored) {
        foreach ((array) $stored as $document_id) {
            $document_id = absint($document_id);
            if ($document_id) {
                $ids[] = $document_id;
            }
        }
    }
    $legacy_id = absint(get_post_meta($project_id, $legacy_key, true));
    if ($legacy_id) {
        $ids[] = $legacy_id;
    }
    return array_values(array_unique($ids));
}

function zigurat_workflow_project_set_document_ids($project_id, $document_type, $document_ids)
{
    $project_id = absint($project_id);
    $document_type = $document_type === 'invoice' ? 'invoice' : 'proforma';
    $plural_key = $document_type === 'invoice'
        ? '_zigurat_work_project_invoices'
        : '_zigurat_work_project_proformas';
    $legacy_key = $document_type === 'invoice'
        ? '_zigurat_work_project_invoice'
        : '_zigurat_work_project_proforma';
    $document_ids = array_values(array_unique(array_filter(array_map('absint', (array) $document_ids))));
    delete_post_meta($project_id, $plural_key);
    foreach ($document_ids as $document_id) {
        add_post_meta($project_id, $plural_key, $document_id, false);
    }
    update_post_meta($project_id, $legacy_key, $document_ids ? (int) end($document_ids) : 0);
    return $document_ids;
}

function zigurat_workflow_project_add_document($project_id, $document_type, $document_id)
{
    $ids = zigurat_workflow_project_document_ids($project_id, $document_type);
    $document_id = absint($document_id);
    if ($document_id && !in_array($document_id, $ids, true)) {
        $ids[] = $document_id;
    }
    return zigurat_workflow_project_set_document_ids($project_id, $document_type, $ids);
}

function zigurat_workflow_project_documents($project_id, $document_type)
{
    $documents = array();
    foreach (zigurat_workflow_project_document_ids($project_id, $document_type) as $document_id) {
        $document = zigurat_workflow_project_document($document_id, $document_type);
        if ($document) {
            $documents[] = $document;
        }
    }
    return $documents;
}

function zigurat_workflow_project_find_by_document($meta_key, $document_id)
{
    $document_id = absint($document_id);
    if (!$document_id || !in_array($meta_key, array('_zigurat_work_project_proforma', '_zigurat_work_project_invoice'), true)) {
        return 0;
    }
    $plural_key = $meta_key === '_zigurat_work_project_invoice'
        ? '_zigurat_work_project_invoices'
        : '_zigurat_work_project_proformas';
    $projects = get_posts(array(
        'post_type' => 'zig_work_project',
        'post_status' => 'private',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => array(
            'relation' => 'OR',
            array('key' => $plural_key, 'value' => $document_id, 'compare' => '='),
            array('key' => $meta_key, 'value' => $document_id, 'compare' => '='),
        ),
    ));
    return $projects ? (int) $projects[0] : 0;
}

function zigurat_workflow_sync_saved_invoice($invoice)
{
    if (!$invoice || empty($invoice->id) || !in_array($invoice->document_type, array('proforma', 'invoice'), true)) {
        return;
    }

    if ($invoice->document_type === 'proforma') {
        $project_id = zigurat_workflow_project_find_by_document('_zigurat_work_project_proforma', $invoice->id);
        if ($project_id) {
            zigurat_workflow_project_add_document($project_id, 'proforma', $invoice->id);
            $old_stage = sanitize_key((string) get_post_meta($project_id, '_zigurat_work_project_stage', true));
            if ($old_stage === '' || $old_stage === 'lead') {
                update_post_meta($project_id, '_zigurat_work_project_stage', 'proforma');
                zigurat_workflow_project_add_history($project_id, $old_stage ?: 'lead', 'proforma', 'proforma');
            }
            return;
        }
        $title = trim((string) ($invoice->project_name ?? ''));
        if ($title === '') {
            $number = function_exists('zigurat_invoice_object_number') ? zigurat_invoice_object_number($invoice) : (string) $invoice->document_number;
            $title = 'پیش‌فاکتور ' . $number;
        }
        $project_id = wp_insert_post(array(
            'post_type' => 'zig_work_project',
            'post_status' => 'private',
            'post_title' => sanitize_text_field($title),
            'post_author' => get_current_user_id(),
        ), true);
        if (is_wp_error($project_id) || !$project_id) {
            return;
        }
        update_post_meta($project_id, '_zigurat_work_project_stage', 'proforma');
        update_post_meta($project_id, '_zigurat_work_project_client', sanitize_text_field((string) ($invoice->customer_name ?? '')));
        update_post_meta($project_id, '_zigurat_work_project_phone', sanitize_text_field((string) ($invoice->customer_phone ?? '')));
        zigurat_workflow_project_set_document_ids($project_id, 'proforma', array((int) $invoice->id));
        zigurat_workflow_project_set_document_ids($project_id, 'invoice', array());
        zigurat_workflow_project_add_history($project_id, '', 'proforma', 'created');
        return;
    }

    $source_proforma_id = absint($invoice->source_proforma_id ?? 0);
    $project_id = $source_proforma_id
        ? zigurat_workflow_project_find_by_document('_zigurat_work_project_proforma', $source_proforma_id)
        : zigurat_workflow_project_find_by_document('_zigurat_work_project_invoice', $invoice->id);
    if (!$project_id) {
        return;
    }
    $old_stage = sanitize_key((string) get_post_meta($project_id, '_zigurat_work_project_stage', true));
    zigurat_workflow_project_add_document($project_id, 'invoice', $invoice->id);
    $project_invoices = zigurat_workflow_project_documents($project_id, 'invoice');
    $all_invoices_settled = $project_invoices && !array_filter($project_invoices, static function ($project_invoice) {
        return ($project_invoice->payment_status ?? '') !== 'settled';
    });
    if ($all_invoices_settled) {
        zigurat_workflow_archive_project($project_id);
        return;
    }
    $has_settled_invoice = (bool) array_filter($project_invoices, static function ($project_invoice) {
        return ($project_invoice->payment_status ?? '') === 'settled';
    });
    $next_stage = ($old_stage === 'payment' || $has_settled_invoice)
        ? 'payment'
        : zigurat_workflow_project_stage_with_invoice($old_stage);
    update_post_meta($project_id, '_zigurat_work_project_stage', $next_stage);
    if ($old_stage !== $next_stage) {
        zigurat_workflow_project_add_history($project_id, $old_stage, $next_stage, 'invoice');
    }
    $project = get_post($project_id);
    if ($project) {
        wp_update_post(array('ID' => $project_id, 'post_title' => $project->post_title));
    }
}
add_action('zigurat_invoice_saved', 'zigurat_workflow_sync_saved_invoice', 10, 1);

function zigurat_workflow_create_invoice_from_proforma($proforma)
{
    if (!$proforma || $proforma->document_type !== 'proforma' || !function_exists('zigurat_invoice_save')) {
        return new WP_Error('invalid_proforma', 'پیش‌فاکتور مبدأ معتبر نیست.');
    }
    // کارت پروژه برای سرعت فقط خلاصه سند را نگه می‌دارد. برای تبدیل باید
    // پیش‌فاکتور کامل، به‌ویژه ردیف‌های کالا/خدمت و اطلاعات فروشنده، خوانده شود.
    $full_proforma = function_exists('zigurat_invoice_get') ? zigurat_invoice_get($proforma->id) : null;
    if (!$full_proforma || $full_proforma->document_type !== 'proforma') {
        return new WP_Error('invalid_proforma', 'اطلاعات کامل پیش‌فاکتور پیدا نشد.');
    }
    if (empty($full_proforma->items) || !is_array($full_proforma->items)) {
        return new WP_Error('invalid_proforma_items', 'پیش‌فاکتور انتخاب‌شده ردیف کالا یا خدمت ندارد.');
    }
    $proforma = $full_proforma;
    if (function_exists('zigurat_invoice_get_conversion')) {
        $existing = zigurat_invoice_get_conversion($proforma->id);
        if ($existing) {
            return zigurat_invoice_get($existing->id);
        }
    }
    $seller = is_array($proforma->seller ?? null) ? $proforma->seller : array();
    $data = array(
        'invoice_form_brand' => $proforma->brand,
        'invoice_form_document_type' => 'invoice',
        'source_proforma_id' => (int) $proforma->id,
        'customer_name' => (string) ($proforma->customer_name ?? ''),
        'customer_national_id' => (string) ($proforma->customer_national_id ?? ''),
        'customer_economic_no' => (string) ($proforma->customer_economic_no ?? ''),
        'customer_province' => (string) ($proforma->customer_province ?? ''),
        'customer_county' => (string) ($proforma->customer_county ?? ''),
        'customer_city' => (string) ($proforma->customer_city ?? ''),
        'customer_postal_code' => (string) ($proforma->customer_postal_code ?? ''),
        'customer_address' => (string) ($proforma->customer_address ?? ''),
        'customer_phone' => (string) ($proforma->customer_phone ?? ''),
        'issue_date' => function_exists('zigurat_invoice_today_jalali') ? zigurat_invoice_today_jalali() : (string) ($proforma->issue_date ?? ''),
        'contract_number' => (string) ($proforma->contract_number ?? ''),
        'contract_date' => (string) ($proforma->contract_date ?? ''),
        'subject' => (string) ($proforma->subject ?? ''),
        'project_name' => (string) ($proforma->project_name ?? ''),
        'status' => 'issued',
        'discount' => (int) ($proforma->discount ?? 0),
        'shipping_mode' => (string) ($proforma->shipping_mode ?? 'fixed'),
        'shipping_value' => (string) (($proforma->shipping_mode ?? 'fixed') === 'percent' ? ($proforma->shipping_rate ?? 0) : ($proforma->shipping ?? 0)),
        'overhead_rate' => (float) ($proforma->overhead_rate ?? 0),
        'insurance_rate' => (float) ($proforma->insurance_rate ?? 0),
        'tax_rate' => (float) ($proforma->tax_rate ?? 0),
        'deduction_amount' => 0,
        'deduction_note' => '',
        'paid_amount' => 0,
        'notes' => (string) ($proforma->notes ?? ''),
        'payment_info' => (string) ($proforma->payment_info ?? ''),
    );
    foreach (array('name','national_id','economic_no','province','city','postal_code','phone','address') as $seller_key) {
        $data['seller_' . $seller_key] = (string) ($seller[$seller_key] ?? '');
    }
    foreach ((array) ($proforma->items ?? array()) as $item) {
        $data['item_description'][] = (string) ($item->description ?? '');
        $data['item_quantity'][] = (string) ($item->quantity ?? 0);
        $data['item_unit'][] = (string) ($item->unit ?? '');
        $data['item_unit_price'][] = (string) ($item->unit_price ?? 0);
        $data['item_discount'][] = (string) ($item->discount ?? 0);
    }
    return zigurat_invoice_save($data);
}

function zigurat_workflow_project_document_label($document)
{
    if (!$document) {
        return '';
    }
    $number = function_exists('zigurat_invoice_object_number')
        ? zigurat_invoice_object_number($document)
        : (string) $document->document_number;
    $type = $document->document_type === 'proforma' ? 'پیش‌فاکتور' : 'فاکتور';
    return sprintf('%s %s — %s — %s', $type, $number, $document->customer_name, $document->subject ?: 'بدون موضوع');
}

function zigurat_workflow_project_recent_documents($document_type, $limit = 40)
{
    global $wpdb;
    if (!function_exists('zigurat_invoices_table_name')) {
        return array();
    }
    $document_type = $document_type === 'invoice' ? 'invoice' : 'proforma';
    $limit = max(20, min(500, absint($limit)));
    $payment_condition = $document_type === 'invoice' ? " AND payment_status <> 'settled'" : '';
    return $wpdb->get_results($wpdb->prepare(
        'SELECT id, brand, document_type, document_number, number_suffix, customer_name, subject, status, payment_status, grand_total
         FROM ' . zigurat_invoices_table_name() . '
         WHERE document_type = %s' . $payment_condition . '
         ORDER BY id DESC LIMIT %d',
        $document_type,
        $limit
    ));
}

function zigurat_workflow_project_partners()
{
    static $partners = null;
    if (is_array($partners)) {
        return $partners;
    }
    $partner_posts = get_posts(array(
        'post_type' => 'partner_application',
        'post_status' => 'private',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'meta_key' => '_application_application_type',
        'meta_value' => 'collaborator',
    ));
    $partners = array_map(static function ($partner) {
        $first_name = trim((string) get_post_meta($partner->ID, '_application_first_name', true));
        $last_name = trim((string) get_post_meta($partner->ID, '_application_last_name', true));
        $partner_name = trim($first_name . ' ' . $last_name);
        if ($partner_name === '') {
            $partner_name = preg_replace('/\s*[—–-]\s*(?:همکار اجرایی|تأمین‌کننده)\s*$/u', '', (string) $partner->post_title);
        }
        $business = trim((string) get_post_meta($partner->ID, '_application_business_name', true));
        $profession = trim((string) get_post_meta($partner->ID, '_application_profession', true));
        $location = function_exists('zigurat_application_location_label')
            ? zigurat_application_location_label(
                get_post_meta($partner->ID, '_application_province', true),
                get_post_meta($partner->ID, '_application_city', true)
            )
            : '';
        return array(
            'id' => (int) $partner->ID,
            'name' => $partner_name,
            'business' => $business,
            'profession' => $profession,
            'location' => $location,
        );
    }, $partner_posts);
    return $partners;
}

function zigurat_workflow_project_work_items($project_id)
{
    $stored = get_post_meta($project_id, '_zigurat_work_project_work_items', true);
    if (!is_array($stored)) {
        return array();
    }
    $partners = array();
    foreach (zigurat_workflow_project_partners() as $partner) {
        $partners[$partner['id']] = $partner;
    }
    $items = array();
    foreach ($stored as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = sanitize_text_field((string) ($item['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $partner_ids = array_values(array_filter(array_unique(array_map('absint', (array) ($item['partner_ids'] ?? array()))), static function ($partner_id) use ($partners) {
            return isset($partners[$partner_id]);
        }));
        $items[] = array(
            'id' => sanitize_key((string) ($item['id'] ?? '')),
            'title' => $title,
            'partner_ids' => $partner_ids,
            'other_partner' => sanitize_text_field((string) ($item['other_partner'] ?? '')),
            'partner_names' => array_values(array_filter(array_merge(array_map(static function ($partner_id) use ($partners) {
                return $partners[$partner_id]['name'];
            }, $partner_ids), array(sanitize_text_field((string) ($item['other_partner'] ?? '')))))),
            // این فیلدها مبنای تایم‌لاین و وابستگی فعالیت‌ها در مرحله بعد هستند.
            'start_date' => zigurat_workflow_project_normalize_date($item['start_date'] ?? ''),
            'end_date' => zigurat_workflow_project_normalize_date($item['end_date'] ?? ''),
            'dependencies' => array_values(array_filter(array_map('sanitize_key', (array) ($item['dependencies'] ?? array())))),
            'parallel_group' => sanitize_key((string) ($item['parallel_group'] ?? '')),
        );
    }
    return $items;
}

function zigurat_workflow_project_sanitize_work_items($project_id, $raw_items)
{
    $allowed_partners = array();
    foreach (zigurat_workflow_project_partners() as $partner) {
        $allowed_partners[$partner['id']] = true;
    }
    $existing = array();
    foreach (zigurat_workflow_project_work_items($project_id) as $item) {
        if ($item['id'] !== '') {
            $existing[$item['id']] = $item;
        }
    }
    $items = array();
    foreach ((array) $raw_items as $raw_item) {
        if (!is_array($raw_item)) {
            continue;
        }
        $title = sanitize_text_field(wp_unslash((string) ($raw_item['title'] ?? '')));
        $partner_ids = array_values(array_filter(array_unique(array_map('absint', (array) ($raw_item['partner_ids'] ?? array()))), static function ($partner_id) use ($allowed_partners) {
            return isset($allowed_partners[$partner_id]);
        }));
        $other_partner = sanitize_text_field(wp_unslash((string) ($raw_item['other_partner'] ?? '')));
        if ($title === '' && !$partner_ids && $other_partner === '') {
            continue;
        }
        if ($title === '') {
            $title = 'فعالیت اجرایی';
        }
        $item_id = sanitize_key(wp_unslash((string) ($raw_item['id'] ?? '')));
        if ($item_id === '') {
            $item_id = 'work-' . strtolower(wp_generate_password(10, false, false));
        }
        $old_item = $existing[$item_id] ?? array();
        $items[] = array(
            'id' => $item_id,
            'title' => $title,
            'partner_ids' => $partner_ids,
            'other_partner' => $other_partner,
            'start_date' => $old_item['start_date'] ?? '',
            'end_date' => $old_item['end_date'] ?? '',
            'dependencies' => $old_item['dependencies'] ?? array(),
            'parallel_group' => $old_item['parallel_group'] ?? '',
        );
    }
    return $items;
}

function zigurat_workflow_partner_assignment_key($partner_id = 0, $other_partner = '')
{
    $partner_id = absint($partner_id);
    if ($partner_id) {
        return 'partner-' . $partner_id;
    }
    $other_partner = trim(sanitize_text_field((string) $other_partner));
    return $other_partner === '' ? '' : 'other-' . md5($other_partner);
}

function zigurat_workflow_project_assignment_keys($work_items)
{
    $keys = array();
    foreach ((array) $work_items as $item) {
        if (!is_array($item)) {
            continue;
        }
        foreach ((array) ($item['partner_ids'] ?? array()) as $partner_id) {
            $key = zigurat_workflow_partner_assignment_key($partner_id);
            if ($key !== '') {
                $keys[$key] = true;
            }
        }
        $other_key = zigurat_workflow_partner_assignment_key(0, $item['other_partner'] ?? '');
        if ($other_key !== '') {
            $keys[$other_key] = true;
        }
    }
    return array_keys($keys);
}

function zigurat_workflow_active_collaborators($records = null)
{
    $records = is_array($records) ? $records : zigurat_workflow_project_records();
    $partners = array();
    foreach (zigurat_workflow_project_partners() as $partner) {
        $partners[$partner['id']] = $partner;
    }
    $columns = array();
    foreach ($records as $record) {
        $closed = get_post_meta($record['id'], '_zigurat_work_project_closed_partners', true);
        $closed = is_array($closed) ? $closed : array();
        $project_assignments = array();
        foreach ((array) $record['work_items'] as $item) {
            foreach ((array) $item['partner_ids'] as $partner_id) {
                $partner_id = absint($partner_id);
                if (!isset($partners[$partner_id])) {
                    continue;
                }
                $key = zigurat_workflow_partner_assignment_key($partner_id);
                if (isset($closed[$key])) {
                    continue;
                }
                if (!isset($columns[$key])) {
                    $columns[$key] = array(
                        'key' => $key,
                        'name' => $partners[$partner_id]['name'],
                        'profession' => $partners[$partner_id]['profession'],
                        'location' => $partners[$partner_id]['location'],
                        'projects' => array(),
                    );
                }
                $project_assignments[$key][] = $item['title'];
            }
            $other_name = trim((string) ($item['other_partner'] ?? ''));
            $other_key = zigurat_workflow_partner_assignment_key(0, $other_name);
            if ($other_key !== '' && !isset($closed[$other_key])) {
                if (!isset($columns[$other_key])) {
                    $columns[$other_key] = array(
                        'key' => $other_key,
                        'name' => $other_name,
                        'profession' => 'سایر همکاران',
                        'location' => '',
                        'projects' => array(),
                    );
                }
                $project_assignments[$other_key][] = $item['title'];
            }
        }
        foreach ($project_assignments as $key => $roles) {
            $columns[$key]['projects'][] = array(
                'id' => $record['id'],
                'title' => $record['title'],
                'stage' => $record['stage'],
                'stage_label' => $record['stage_label'],
                'roles' => array_values(array_unique(array_filter($roles))),
                'client' => $record['client'],
            );
        }
    }
    uasort($columns, static function ($first, $second) {
        return strnatcasecmp($first['name'], $second['name']);
    });
    return array_values($columns);
}

function zigurat_workflow_project_add_history($project_id, $from_stage, $to_stage, $action = 'stage')
{
    $history = get_post_meta($project_id, '_zigurat_work_project_history', true);
    $history = is_array($history) ? $history : array();
    $history[] = array(
        'action' => sanitize_key($action),
        'from' => sanitize_key($from_stage),
        'to' => sanitize_key($to_stage),
        'user_id' => get_current_user_id(),
        'time' => current_time('mysql'),
    );
    if (count($history) > 100) {
        $history = array_slice($history, -100);
    }
    update_post_meta($project_id, '_zigurat_work_project_history', $history);
}

function zigurat_workflow_project_record($project)
{
    $project = is_object($project) ? $project : get_post(absint($project));
    if (!$project || $project->post_type !== 'zig_work_project' || !in_array($project->post_status, array('private', 'trash'), true)) {
        return null;
    }
    $stages = zigurat_workflow_project_stages();
    $stage = sanitize_key((string) get_post_meta($project->ID, '_zigurat_work_project_stage', true));
    if (!isset($stages[$stage])) {
        $stage = 'lead';
    }
    $proformas = zigurat_workflow_project_documents($project->ID, 'proforma');
    $invoices = zigurat_workflow_project_documents($project->ID, 'invoice');
    $proforma = $proformas ? end($proformas) : null;
    $invoice = $invoices ? end($invoices) : null;
    $raw_history = get_post_meta($project->ID, '_zigurat_work_project_history', true);
    $history = array();
    foreach (array_reverse(array_slice(is_array($raw_history) ? $raw_history : array(), -12)) as $entry) {
        $history_user = !empty($entry['user_id']) ? get_userdata(absint($entry['user_id'])) : null;
        $history_time = sanitize_text_field((string) ($entry['time'] ?? ''));
        if ($history_time && function_exists('zigurat_inventory_format_jalali_datetime')) {
            $history_time = zigurat_inventory_format_jalali_datetime($history_time);
        }
        $from = sanitize_key((string) ($entry['from'] ?? ''));
        $to = sanitize_key((string) ($entry['to'] ?? ''));
        $history[] = array(
            'action' => sanitize_key((string) ($entry['action'] ?? 'stage')),
            'from_label' => $stages[$from] ?? '',
            'to_label' => $stages[$to] ?? '',
            'user_name' => $history_user ? ($history_user->display_name ?: $history_user->user_login) : 'کاربر حذف‌شده',
            'time' => $history_time,
        );
    }
    return array(
        'id' => (int) $project->ID,
        'archived' => $project->post_status === 'trash',
        'title' => (string) $project->post_title,
        'stage' => $stage,
        'stage_label' => $stages[$stage],
        'client' => (string) get_post_meta($project->ID, '_zigurat_work_project_client', true),
        'phone' => (string) get_post_meta($project->ID, '_zigurat_work_project_phone', true),
        'followup' => (string) get_post_meta($project->ID, '_zigurat_work_project_followup', true),
        'followup_due' => ($followup_time = zigurat_project_followup_timestamp(get_post_meta($project->ID, '_zigurat_work_project_followup', true))) && $followup_time <= time(),
        'repeat_daily' => (bool)get_post_meta($project->ID, '_zigurat_work_project_repeat_daily', true),
        'work_items' => zigurat_workflow_project_work_items($project->ID),
        'notes' => (string) get_post_meta($project->ID, '_zigurat_work_project_notes', true),
        'proforma_ids' => array_values(array_map(static function ($document) { return (int) $document->id; }, $proformas)),
        'proformas' => array_values(array_map(static function ($document) {
            return array(
                'id' => (int) $document->id,
                'number' => function_exists('zigurat_invoice_object_number') ? zigurat_invoice_object_number($document) : (string) $document->document_number,
                'label' => zigurat_workflow_project_document_label($document),
                'url' => zigurat_workflow_project_document_url($document),
                'conversion_url' => zigurat_workflow_project_conversion_url($document),
            );
        }, $proformas)),
        'proforma_id' => $proforma ? (int) $proforma->id : 0,
        'proforma_label' => zigurat_workflow_project_document_label($proforma),
        'proforma_url' => zigurat_workflow_project_document_url($proforma),
        'conversion_url' => zigurat_workflow_project_conversion_url($proforma),
        'invoice_ids' => array_values(array_map(static function ($document) { return (int) $document->id; }, $invoices)),
        'invoices' => array_values(array_map(static function ($document) {
            return array(
                'id' => (int) $document->id,
                'number' => function_exists('zigurat_invoice_object_number') ? zigurat_invoice_object_number($document) : (string) $document->document_number,
                'label' => zigurat_workflow_project_document_label($document),
                'url' => zigurat_workflow_project_document_url($document),
                'payment_status' => sanitize_key((string) ($document->payment_status ?? '')),
            );
        }, $invoices)),
        'invoice_id' => $invoice ? (int) $invoice->id : 0,
        'invoice_label' => zigurat_workflow_project_document_label($invoice),
        'invoice_url' => zigurat_workflow_project_document_url($invoice),
        'history' => $history,
        'modified' => $project->post_modified,
    );
}

/** کارت‌هایی که یک سند مشترک دارند، همان پروژه‌اند و پیش از نمایش یکی می‌شوند. */
function zigurat_workflow_merge_duplicate_document_projects()
{
    static $done = false;
    if ($done || !zigurat_is_manager()) {
        return;
    }
    $done = true;
    $safety = 0;
    do {
        $merged = false;
        $owners = array();
        $projects = get_posts(array(
            'post_type' => 'zig_work_project',
            'post_status' => 'private',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'ASC',
            'no_found_rows' => true,
        ));
        foreach ($projects as $project) {
            foreach (array('proforma', 'invoice') as $document_type) {
                foreach (zigurat_workflow_project_document_ids($project->ID, $document_type) as $document_id) {
                    $key = $document_type . ':' . $document_id;
                    if (!isset($owners[$key])) {
                        $owners[$key] = (int) $project->ID;
                        continue;
                    }
                    if (zigurat_workflow_merge_projects((int) $owners[$key], (int) $project->ID)) {
                        $merged = true;
                        break 3;
                    }
                }
            }
        }
        $safety++;
    } while ($merged && $safety < 250);
}

function zigurat_workflow_project_records()
{
    if (!zigurat_is_manager()) {
        return array();
    }
    zigurat_workflow_merge_duplicate_document_projects();
    $query = new WP_Query(array(
        'post_type' => 'zig_work_project',
        'post_status' => 'private',
        'posts_per_page' => -1,
        'orderby' => 'modified',
        'order' => 'DESC',
        'no_found_rows' => true,
    ));
    $document_ids = array();
    foreach ($query->posts as $project) {
        $document_ids = array_merge(
            $document_ids,
            zigurat_workflow_project_document_ids($project->ID, 'proforma'),
            zigurat_workflow_project_document_ids($project->ID, 'invoice')
        );
    }
    zigurat_workflow_prime_documents($document_ids);
    return array_values(array_filter(array_map('zigurat_workflow_project_record', $query->posts)));
}

function zigurat_workflow_archived_records()
{
    if (!zigurat_is_manager()) {
        return array();
    }
    $query = new WP_Query(array(
        'post_type' => 'zig_work_project',
        'post_status' => 'trash',
        'posts_per_page' => -1,
        'orderby' => 'modified',
        'order' => 'DESC',
        'no_found_rows' => true,
    ));
    $document_ids = array();
    foreach ($query->posts as $project) {
        $document_ids = array_merge(
            $document_ids,
            zigurat_workflow_project_document_ids($project->ID, 'proforma'),
            zigurat_workflow_project_document_ids($project->ID, 'invoice')
        );
    }
    zigurat_workflow_prime_documents($document_ids);
    $records = array();
    foreach ($query->posts as $project) {
        $record = zigurat_workflow_project_record($project);
        if (!$record) {
            continue;
        }
        $record['created_at'] = function_exists('zigurat_inventory_format_jalali_datetime')
            ? zigurat_inventory_format_jalali_datetime($project->post_date)
            : $project->post_date;
        $record['timeline_start_ts'] = strtotime($project->post_date) ?: 0;
        $record['archived_at'] = (string) get_post_meta($project->ID, '_zigurat_work_project_archived_at', true);
        if ($record['archived_at'] === '') {
            $record['archived_at'] = $project->post_modified;
        }
        $record['timeline_end_ts'] = strtotime($record['archived_at']) ?: $record['timeline_start_ts'];
        $record['timeline_events'] = array();
        $raw_history = get_post_meta($project->ID, '_zigurat_work_project_history', true);
        $stages = zigurat_workflow_project_stages();
        foreach (is_array($raw_history) ? $raw_history : array() as $history_item) {
            $event_ts = strtotime((string) ($history_item['time'] ?? ''));
            $to = sanitize_key((string) ($history_item['to'] ?? ''));
            if ($event_ts && $to && isset($stages[$to])) {
                $record['timeline_events'][] = array('ts' => $event_ts, 'label' => $stages[$to], 'stage' => $to);
            }
        }
        $record['timeline_events'][] = array('ts' => $record['timeline_end_ts'], 'label' => 'تسویه کامل', 'stage' => 'settled');
        usort($record['timeline_events'], static function ($first, $second) { return $first['ts'] <=> $second['ts']; });
        $records[] = $record;
    }
    return $records;
}

function zigurat_ajax_load_workflow_archive()
{
    check_ajax_referer('zigurat_workflow_projects', 'nonce');
    if (!zigurat_is_manager()) {
        wp_send_json_error(array('message' => 'دسترسی مجاز نیست.'), 403);
    }
    wp_send_json_success(array('records' => zigurat_workflow_archived_records()));
}
add_action('wp_ajax_zigurat_load_workflow_archive', 'zigurat_ajax_load_workflow_archive');

function zigurat_ajax_get_workflow_project_by_document()
{
    check_ajax_referer('zigurat_workflow_projects', 'nonce');
    if (!zigurat_is_manager()) {
        wp_send_json_error(array('message' => 'دسترسی مجاز نیست.'), 403);
    }
    $document_id = absint($_POST['document_id'] ?? 0);
    $document_type = sanitize_key(wp_unslash((string) ($_POST['document_type'] ?? '')));
    $meta_key = $document_type === 'invoice' ? '_zigurat_work_project_invoice' : '_zigurat_work_project_proforma';
    $project_id = zigurat_workflow_project_find_by_document($meta_key, $document_id);
    $record = $project_id ? zigurat_workflow_project_record(get_post($project_id)) : null;
    if (!$record) {
        wp_send_json_error(array('message' => 'کارت مرتبط پیدا نشد.'), 404);
    }
    wp_send_json_success(array('record' => $record));
}
add_action('wp_ajax_zigurat_get_workflow_project_by_document', 'zigurat_ajax_get_workflow_project_by_document');

function zigurat_workflow_archive_project($project_id)
{
    $project_id = absint($project_id);
    if (!$project_id) {
        return false;
    }
    update_post_meta($project_id, '_zigurat_work_project_archived_at', current_time('mysql'));
    return (bool) wp_trash_post($project_id);
}

function zigurat_workflow_project_valid_document_ids($raw_ids, $document_type)
{
    $ids = array_values(array_unique(array_filter(array_map('absint', (array) $raw_ids))));
    return array_values(array_filter($ids, static function ($document_id) use ($document_type) {
        return (bool) zigurat_workflow_project_document($document_id, $document_type);
    }));
}

/** ادغام کارت خودکار/تکراری در کارت اصلی بدون حذف اسناد و تاریخچه. */
function zigurat_workflow_merge_projects($target_id, $source_id)
{
    $target_id = absint($target_id);
    $source_id = absint($source_id);
    if (!$target_id || !$source_id || $target_id === $source_id) {
        return false;
    }
    $target = get_post($target_id);
    $source = get_post($source_id);
    if (!$target || !$source || $target->post_type !== 'zig_work_project' || $source->post_type !== 'zig_work_project' || $source->post_status !== 'private') {
        return false;
    }

    foreach (array('proforma', 'invoice') as $document_type) {
        $ids = array_merge(
            zigurat_workflow_project_document_ids($target_id, $document_type),
            zigurat_workflow_project_document_ids($source_id, $document_type)
        );
        zigurat_workflow_project_set_document_ids($target_id, $document_type, $ids);
    }

    $stage_order = array_keys(zigurat_workflow_project_stages());
    $target_stage = sanitize_key((string) get_post_meta($target_id, '_zigurat_work_project_stage', true));
    $source_stage = sanitize_key((string) get_post_meta($source_id, '_zigurat_work_project_stage', true));
    $target_rank = array_search($target_stage, $stage_order, true);
    $source_rank = array_search($source_stage, $stage_order, true);
    $merged_stage = ($source_rank !== false && ($target_rank === false || $source_rank > $target_rank)) ? $source_stage : $target_stage;
    if ($merged_stage === '' || !isset(zigurat_workflow_project_stages()[$merged_stage])) {
        $merged_stage = 'lead';
    }
    if (zigurat_workflow_project_document_ids($target_id, 'invoice')) {
        $merged_stage = zigurat_workflow_project_stage_with_invoice($merged_stage);
    } elseif (zigurat_workflow_project_document_ids($target_id, 'proforma') && $merged_stage === 'lead') {
        $merged_stage = 'proforma';
    }
    update_post_meta($target_id, '_zigurat_work_project_stage', $merged_stage);

    foreach (array('client', 'phone', 'followup', 'notes') as $field) {
        $key = '_zigurat_work_project_' . $field;
        if (trim((string) get_post_meta($target_id, $key, true)) === '') {
            $source_value = get_post_meta($source_id, $key, true);
            if ($source_value !== '') {
                update_post_meta($target_id, $key, $source_value);
            }
        }
    }

    $target_items = zigurat_workflow_project_work_items($target_id);
    $source_items = zigurat_workflow_project_work_items($source_id);
    $known_items = array();
    foreach ($target_items as $item) {
        $known_items[md5(wp_json_encode(array($item['title'], $item['partner_ids'], $item['other_partner'])))] = true;
    }
    foreach ($source_items as $item) {
        $key = md5(wp_json_encode(array($item['title'], $item['partner_ids'], $item['other_partner'])));
        if (!isset($known_items[$key])) {
            $target_items[] = $item;
            $known_items[$key] = true;
        }
    }
    update_post_meta($target_id, '_zigurat_work_project_work_items', $target_items);

    $target_closed = get_post_meta($target_id, '_zigurat_work_project_closed_partners', true);
    $source_closed = get_post_meta($source_id, '_zigurat_work_project_closed_partners', true);
    update_post_meta($target_id, '_zigurat_work_project_closed_partners', array_merge(
        is_array($source_closed) ? $source_closed : array(),
        is_array($target_closed) ? $target_closed : array()
    ));

    $target_history = get_post_meta($target_id, '_zigurat_work_project_history', true);
    $source_history = get_post_meta($source_id, '_zigurat_work_project_history', true);
    $history = array_merge(is_array($target_history) ? $target_history : array(), is_array($source_history) ? $source_history : array());
    usort($history, static function ($first, $second) {
        return strcmp((string) ($first['time'] ?? ''), (string) ($second['time'] ?? ''));
    });
    update_post_meta($target_id, '_zigurat_work_project_history', array_slice($history, -99));
    zigurat_workflow_project_add_history($target_id, '', '', 'merged');
    wp_delete_post($source_id, true);
    return true;
}

function zigurat_workflow_project_save_from_request($data)
{
    if (!zigurat_is_manager()) {
        return new WP_Error('forbidden', 'دسترسی مجاز نیست.');
    }
    $project_id = absint($data['project_id'] ?? 0);
    $existing = $project_id ? get_post($project_id) : null;
    if ($project_id && (!$existing || $existing->post_type !== 'zig_work_project' || $existing->post_status !== 'private')) {
        return new WP_Error('not_found', 'پروژه موردنظر پیدا نشد.');
    }
    $title = sanitize_text_field(wp_unslash((string) ($data['title'] ?? '')));
    if ($title === '') {
        return new WP_Error('missing_title', 'نام پروژه را وارد کنید.');
    }
    $followup_raw = trim((string)wp_unslash($data['followup'] ?? ''));
    $followup_at = zigurat_project_followup_timestamp($followup_raw);
    if ($followup_raw !== '' && !$followup_at) return new WP_Error('invalid_followup', 'تاریخ و ساعت پیگیری معتبر نیست؛ از تقویم شمسی انتخاب کنید.');
    $stages = zigurat_workflow_project_stages();
    $stage = sanitize_key(wp_unslash((string) ($data['stage'] ?? 'lead')));
    if (!isset($stages[$stage])) {
        $stage = 'lead';
    }
    $proforma_ids = zigurat_workflow_project_valid_document_ids($data['proforma_ids'] ?? ($data['proforma_id'] ?? array()), 'proforma');
    $invoice_ids = zigurat_workflow_project_valid_document_ids($data['invoice_ids'] ?? ($data['invoice_id'] ?? array()), 'invoice');
    if ($invoice_ids) {
        $stage = zigurat_workflow_project_stage_with_invoice($stage);
    } elseif ($proforma_ids && $stage === 'lead') {
        $stage = 'proforma';
    }
    $post_data = array(
        'post_type' => 'zig_work_project',
        'post_status' => 'private',
        'post_title' => $title,
    );
    if ($project_id) {
        $post_data['ID'] = $project_id;
        $result = wp_update_post($post_data, true);
    } else {
        $post_data['post_author'] = get_current_user_id();
        $result = wp_insert_post($post_data, true);
    }
    if (is_wp_error($result)) {
        return $result;
    }
    $old_stage = $existing ? sanitize_key((string) get_post_meta($result, '_zigurat_work_project_stage', true)) : '';
    update_post_meta($result, '_zigurat_work_project_stage', $stage);
    update_post_meta($result, '_zigurat_work_project_client', sanitize_text_field(wp_unslash((string) ($data['client'] ?? ''))));
    update_post_meta($result, '_zigurat_work_project_phone', sanitize_text_field(wp_unslash((string) ($data['phone'] ?? ''))));
    delete_post_meta($result, '_zigurat_work_project_source');
    delete_post_meta($result, '_zigurat_work_project_value');
    if (!$existing || isset($data['repeat_daily_present'])) update_post_meta($result, '_zigurat_work_project_repeat_daily', empty($data['repeat_daily']) ? 0 : 1);
    update_post_meta($result, '_zigurat_work_project_followup', $followup_at ? zigurat_crm_datetime_input($followup_at) : '');
    $old_work_items = zigurat_workflow_project_work_items($result);
    $new_work_items = zigurat_workflow_project_sanitize_work_items($result, $data['work_items'] ?? array());
    update_post_meta($result, '_zigurat_work_project_work_items', $new_work_items);
    $old_assignment_keys = zigurat_workflow_project_assignment_keys($old_work_items);
    $new_assignment_keys = zigurat_workflow_project_assignment_keys($new_work_items);
    $closed_partners = get_post_meta($result, '_zigurat_work_project_closed_partners', true);
    $closed_partners = is_array($closed_partners) ? $closed_partners : array();
    foreach (array_diff($new_assignment_keys, $old_assignment_keys) as $new_key) {
        unset($closed_partners[$new_key]);
    }
    update_post_meta($result, '_zigurat_work_project_closed_partners', $closed_partners);
    delete_post_meta($result, '_zigurat_work_project_owner');
    update_post_meta($result, '_zigurat_work_project_notes', sanitize_textarea_field(wp_unslash((string) ($data['notes'] ?? ''))));
    $duplicate_project_ids = array();
    foreach ($proforma_ids as $document_id) {
        $duplicate_id = zigurat_workflow_project_find_by_document('_zigurat_work_project_proforma', $document_id);
        if ($duplicate_id && $duplicate_id !== (int) $result) {
            $duplicate_project_ids[] = $duplicate_id;
        }
    }
    foreach ($invoice_ids as $document_id) {
        $duplicate_id = zigurat_workflow_project_find_by_document('_zigurat_work_project_invoice', $document_id);
        if ($duplicate_id && $duplicate_id !== (int) $result) {
            $duplicate_project_ids[] = $duplicate_id;
        }
    }
    zigurat_workflow_project_set_document_ids($result, 'proforma', $proforma_ids);
    zigurat_workflow_project_set_document_ids($result, 'invoice', $invoice_ids);
    foreach (array_unique($duplicate_project_ids) as $duplicate_project_id) {
        zigurat_workflow_merge_projects($result, $duplicate_project_id);
    }
    $effective_invoice_ids = zigurat_workflow_project_document_ids($result, 'invoice');
    $effective_proforma_ids = zigurat_workflow_project_document_ids($result, 'proforma');
    if ($effective_invoice_ids) {
        $stage = zigurat_workflow_project_stage_with_invoice($stage);
    } elseif (!$effective_invoice_ids && $effective_proforma_ids && $stage === 'lead') {
        $stage = 'proforma';
    }
    update_post_meta($result, '_zigurat_work_project_stage', $stage);
    if (!$existing || $old_stage !== $stage) {
        zigurat_workflow_project_add_history($result, $old_stage, $stage, $existing ? 'stage' : 'created');
    }
    return zigurat_workflow_project_record($result);
}

function zigurat_ajax_save_workflow_project()
{
    check_ajax_referer('zigurat_workflow_projects', 'nonce');
    $record = zigurat_workflow_project_save_from_request($_POST);
    if (is_wp_error($record)) {
        wp_send_json_error(array('message' => $record->get_error_message()), $record->get_error_code() === 'forbidden' ? 403 : 400);
    }
    wp_send_json_success(array(
        'message' => empty($_POST['project_id']) ? 'پروژه ایجاد شد.' : 'تغییرات پروژه ذخیره شد.',
        'project' => $record,
    ));
}
add_action('wp_ajax_zigurat_save_workflow_project', 'zigurat_ajax_save_workflow_project');

function zigurat_ajax_get_workflow_project()
{
    check_ajax_referer('zigurat_workflow_projects', 'nonce');
    if (!zigurat_is_manager()) {
        wp_send_json_error(array('message' => 'دسترسی مجاز نیست.'), 403);
    }
    $record = zigurat_workflow_project_record(absint($_POST['project_id'] ?? 0));
    if (!$record) {
        wp_send_json_error(array('message' => 'پروژه پیدا نشد.'), 404);
    }
    wp_send_json_success(array('project' => $record));
}
add_action('wp_ajax_zigurat_get_workflow_project', 'zigurat_ajax_get_workflow_project');

function zigurat_ajax_move_workflow_project()
{
    check_ajax_referer('zigurat_workflow_projects', 'nonce');
    if (!zigurat_is_manager()) {
        wp_send_json_error(array('message' => 'دسترسی مجاز نیست.'), 403);
    }
    $project_id = absint($_POST['project_id'] ?? 0);
    $stage = sanitize_key(wp_unslash((string) ($_POST['stage'] ?? '')));
    $stages = zigurat_workflow_project_stages();
    $project = get_post($project_id);
    if (!$project || $project->post_type !== 'zig_work_project' || $project->post_status !== 'private') {
        wp_send_json_error(array('message' => 'پروژه پیدا نشد.'), 404);
    }
    if (!isset($stages[$stage])) {
        wp_send_json_error(array('message' => 'مرحله انتخاب‌شده معتبر نیست.'), 400);
    }
    $created_invoice = null;
    $proforma_ids = zigurat_workflow_project_document_ids($project_id, 'proforma');
    $invoice_ids = zigurat_workflow_project_document_ids($project_id, 'invoice');
    $proforma_id = $proforma_ids ? (int) end($proforma_ids) : 0;
    $invoice_id = $invoice_ids ? (int) end($invoice_ids) : 0;
    if ($stage === 'delivery' && $proforma_id && !$invoice_ids) {
        $proforma = zigurat_workflow_project_document($proforma_id, 'proforma');
        $created_invoice = zigurat_workflow_create_invoice_from_proforma($proforma);
        if (is_wp_error($created_invoice)) {
            wp_send_json_error(array('message' => $created_invoice->get_error_message()), 400);
        }
        $invoice_id = absint($created_invoice->id ?? 0);
        if ($invoice_id) {
            zigurat_workflow_project_add_document($project_id, 'invoice', $invoice_id);
            $invoice_ids[] = $invoice_id;
            // ساخت فاکتور شروع اجرای پروژه است؛ تحویل باید بعداً و آگاهانه انجام شود.
            $stage = 'production';
        }
    }
    if ($stage === 'payment' && !$invoice_ids) {
        wp_send_json_error(array('message' => 'برای ورود به انتظار پرداخت، ابتدا فاکتور نهایی را بسازید.'), 400);
    }
    if ($stage === 'delivery') {
        if ($proforma_id && !$invoice_ids) {
            wp_send_json_error(array('message' => 'ابتدا فاکتور نهایی را از پیش‌فاکتور بسازید و ذخیره کنید.'), 409);
        }
    }
    $old_stage = sanitize_key((string) get_post_meta($project_id, '_zigurat_work_project_stage', true));
    if ($old_stage !== $stage) {
        update_post_meta($project_id, '_zigurat_work_project_stage', $stage);
        zigurat_workflow_project_add_history($project_id, $old_stage, $stage, 'stage');
        wp_update_post(array('ID' => $project_id, 'post_title' => $project->post_title));
    }
    $project_record = zigurat_workflow_project_record($project_id);
    wp_send_json_success(array(
        'message' => $created_invoice ? 'فاکتور ساخته شد و کارت به «در حال اجرا» منتقل شد.' : 'مرحله پروژه به «' . $stages[$stage] . '» تغییر کرد.',
        'stage' => $stage,
        'stage_label' => $stages[$stage],
        'invoice_id' => $invoice_id,
        'invoice_url' => $created_invoice ? zigurat_workflow_project_document_url($created_invoice) : '',
        'can_undo' => !$created_invoice && $old_stage !== $stage,
        'project' => $project_record,
    ));
}
add_action('wp_ajax_zigurat_move_workflow_project', 'zigurat_ajax_move_workflow_project');

function zigurat_ajax_archive_workflow_project()
{
    check_ajax_referer('zigurat_workflow_projects', 'nonce');
    if (!zigurat_is_manager()) {
        wp_send_json_error(array('message' => 'دسترسی مجاز نیست.'), 403);
    }
    $project_id = absint($_POST['project_id'] ?? 0);
    $project = get_post($project_id);
    if (!$project || $project->post_type !== 'zig_work_project' || $project->post_status !== 'private') {
        wp_send_json_error(array('message' => 'پروژه پیدا نشد.'), 404);
    }
    $deleted = zigurat_workflow_archive_project($project_id);
    if (!$deleted) {
        wp_send_json_error(array('message' => 'بایگانی پروژه انجام نشد.'), 500);
    }
    wp_send_json_success(array('message' => 'پروژه بایگانی شد.'));
}
add_action('wp_ajax_zigurat_archive_workflow_project', 'zigurat_ajax_archive_workflow_project');

function zigurat_ajax_delete_workflow_project()
{
    check_ajax_referer('zigurat_workflow_projects', 'nonce');
    if (!zigurat_is_manager()) {
        wp_send_json_error(array('message' => 'دسترسی مجاز نیست.'), 403);
    }
    $project_id = absint($_POST['project_id'] ?? 0);
    $project = get_post($project_id);
    if (!$project || $project->post_type !== 'zig_work_project' || $project->post_status !== 'private') {
        wp_send_json_error(array('message' => 'پروژه پیدا نشد.'), 404);
    }
    if (!wp_delete_post($project_id, true)) {
        wp_send_json_error(array('message' => 'حذف کارت انجام نشد.'), 500);
    }
    wp_send_json_success(array('message' => 'کارت پروژه برای همیشه حذف شد.'));
}
add_action('wp_ajax_zigurat_delete_workflow_project', 'zigurat_ajax_delete_workflow_project');

function zigurat_ajax_close_workflow_partner_assignment()
{
    check_ajax_referer('zigurat_workflow_projects', 'nonce');
    if (!zigurat_is_manager()) {
        wp_send_json_error(array('message' => 'دسترسی مجاز نیست.'), 403);
    }
    $project_id = absint($_POST['project_id'] ?? 0);
    $assignment_key = sanitize_key(wp_unslash((string) ($_POST['assignment_key'] ?? '')));
    $project = get_post($project_id);
    $record = zigurat_workflow_project_record($project);
    if (!$record) {
        wp_send_json_error(array('message' => 'پروژه پیدا نشد.'), 404);
    }
    if ($assignment_key === '' || !in_array($assignment_key, zigurat_workflow_project_assignment_keys($record['work_items']), true)) {
        wp_send_json_error(array('message' => 'همکار انتخاب‌شده در این پروژه پیدا نشد.'), 400);
    }
    $closed = get_post_meta($project_id, '_zigurat_work_project_closed_partners', true);
    $closed = is_array($closed) ? $closed : array();
    $closed[$assignment_key] = array(
        'closed_at' => current_time('mysql'),
        'closed_by' => get_current_user_id(),
    );
    update_post_meta($project_id, '_zigurat_work_project_closed_partners', $closed);
    wp_send_json_success(array('message' => 'پروژه از فهرست فعال این همکار خارج شد.'));
}
add_action('wp_ajax_zigurat_close_workflow_partner_assignment', 'zigurat_ajax_close_workflow_partner_assignment');
