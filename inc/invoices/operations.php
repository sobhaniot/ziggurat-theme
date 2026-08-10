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
    $value = str_replace(',', '.', zigurat_invoice_normalize_digits($value));
    return max(0, round((float) $value, 3));
}

function zigurat_invoice_format_money($amount)
{
    return number_format_i18n((int) $amount) . ' ریال';
}

function zigurat_invoice_format_number($number)
{
    return str_pad((string) absint($number), 3, '0', STR_PAD_LEFT);
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

/** فاکتوری که قبلاً از یک پیش‌فاکتور ساخته شده است. */
function zigurat_invoice_get_conversion($proforma_id)
{
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        'SELECT id, brand, document_type, document_number, source_proforma_id FROM ' . zigurat_invoices_table_name() . ' WHERE document_type = %s AND source_proforma_id = %d ORDER BY id ASC LIMIT 1',
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
    if (!in_array($brand, array('official','unofficial'), true) || !in_array($type, array('proforma','invoice'), true)) {
        return new WP_Error('invalid_type', 'نوع فاکتور معتبر نیست.');
    }
    if ($invoice_id && !$existing) {
        return new WP_Error('not_found', 'فاکتور پیدا نشد.');
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
                'این پیش‌فاکتور قبلاً به فاکتور شماره ' . zigurat_invoice_format_number($previous_conversion->document_number) . ' تبدیل شده است.'
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
    $subtotal = array_sum(wp_list_pluck($items, 'line_total'));
    $discount = min(zigurat_invoice_money($data['discount'] ?? 0), $subtotal);
    $shipping = zigurat_invoice_money($data['shipping'] ?? 0);
    $tax_rate = max(0, min(100, (float) zigurat_invoice_normalize_digits($data['tax_rate'] ?? 0)));
    $taxable = max(0, $subtotal - $discount + $shipping);
    $tax_amount = (int) round($taxable * $tax_rate / 100);
    $grand_total = $taxable + $tax_amount;
    $paid_amount = zigurat_invoice_money($data['paid_amount'] ?? 0);
    $balance = max(0, $grand_total - $paid_amount);
    $seller = zigurat_invoice_clean_seller($data, $brand);
    $now = current_time('mysql', true);
    $user_id = get_current_user_id();

    global $wpdb;
    $invoice_table = zigurat_invoices_table_name();
    $items_table = zigurat_invoice_items_table_name();
    $sequence_table = zigurat_invoice_sequences_table_name();
    $wpdb->query('START TRANSACTION');
    if ($existing) {
        $locked = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$invoice_table} WHERE id = %d FOR UPDATE", $invoice_id));
        if (!$locked) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('not_found', 'فاکتور پیدا نشد.');
        }
        $document_number = (int) $locked->document_number;
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
        $sequence_updated = $wpdb->update($sequence_table, array('last_number'=>$document_number,'updated_at'=>$now), array('brand'=>$brand,'document_type'=>$type), array('%d','%s'), array('%s','%s'));
        if ($sequence_updated === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('database', 'شماره فاکتور ذخیره نشد.');
        }
    }

    $invoice_data = array(
        'brand'=>$brand, 'document_type'=>$type, 'document_number'=>$document_number,
        'issue_date'=>$issue_date, 'status'=>$status,
        'subject'=>sanitize_text_field(wp_unslash((string) ($data['subject'] ?? ''))),
        'source_proforma_id'=>$source_proforma_id,
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
        'tax_rate'=>$tax_rate, 'tax_amount'=>$tax_amount, 'grand_total'=>$grand_total,
        'paid_amount'=>$paid_amount, 'balance'=>$balance,
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
    $wpdb->query('COMMIT');
    return zigurat_invoice_get($invoice_id);
}

function zigurat_invoice_list($args = array())
{
    global $wpdb;
    $args = wp_parse_args($args, array('brand'=>'','type'=>'','status'=>'','search'=>'','page'=>1,'per_page'=>30));
    $where = array('1=1');
    $values = array();
    if (in_array($args['brand'], array('official','unofficial'), true)) { $where[]='brand=%s'; $values[]=$args['brand']; }
    if (in_array($args['type'], array('invoice','proforma'), true)) { $where[]='document_type=%s'; $values[]=$args['type']; }
    if (in_array($args['status'], array('draft','issued'), true)) { $where[]='status=%s'; $values[]=$args['status']; }
    if ($args['search'] !== '') {
        $like = '%' . $wpdb->esc_like($args['search']) . '%';
        $where[] = '(customer_name LIKE %s OR subject LIKE %s OR CAST(document_number AS CHAR) LIKE %s)';
        array_push($values, $like, $like, $like);
    }
    $table = zigurat_invoices_table_name();
    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
    $total = (int) ($values ? $wpdb->get_var($wpdb->prepare($count_sql, $values)) : $wpdb->get_var($count_sql));
    $per_page = max(1, min(100, absint($args['per_page'])));
    $page = max(1, absint($args['page']));
    $offset = ($page - 1) * $per_page;
    $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
    return array(
        'items'=>$wpdb->get_results($wpdb->prepare($sql, array_merge($values, array($per_page,$offset)))),
        'total'=>$total, 'pages'=>max(1,(int)ceil($total/$per_page)), 'page'=>$page,
    );
}
