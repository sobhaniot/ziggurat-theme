<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_invoice_builtin_brand_settings($brand)
{
    if ($brand === 'official') {
        return array(
            'seller' => array(
                'name' => 'سامان موثق (زیگورات)',
                'national_id' => '0067273602',
                'economic_no' => '00672736020002',
                'province' => 'البرز',
                'county' => 'کرج',
                'city' => 'کرج',
                'postal_code' => '3366135526',
                'address' => 'چهارباغ، قوهه، بلوار امام خمینی، پلاک ۹۱',
                'phone' => '09125606941',
            ),
            'payment_info' => "شماره حساب: 80000611194000\nشماره کارت: 6221-0610-2723-6779\nشماره شبا: IR450540103480000611194000\nبانک پارسیان به نام سامان موثق",
            'tax_rate' => 10,
        );
    }

    return array(
        'seller' => array(
            'name' => 'فروشگاه دیاموند',
            'national_id' => '',
            'economic_no' => '',
            'province' => 'تهران',
            'county' => 'تهران',
            'city' => 'تهران',
            'postal_code' => '',
            'address' => '',
            'phone' => '',
        ),
        'payment_info' => '',
        'tax_rate' => 0,
    );
}

function zigurat_invoice_get_brand_settings($brand)
{
    $brand = $brand === 'official' ? 'official' : 'unofficial';
    $defaults = zigurat_invoice_builtin_brand_settings($brand);
    $all = get_option('zigurat_invoice_defaults', array());
    $saved = isset($all[$brand]) && is_array($all[$brand]) ? $all[$brand] : array();
    $seller = isset($saved['seller']) && is_array($saved['seller']) ? $saved['seller'] : array();
    $saved['seller'] = wp_parse_args($seller, $defaults['seller']);
    return wp_parse_args($saved, $defaults);
}

function zigurat_invoice_default_tax_rate($brand)
{
    $settings = zigurat_invoice_get_brand_settings($brand);
    return max(0, min(100, (float) $settings['tax_rate']));
}

function zigurat_invoice_next_number($brand, $type)
{
    global $wpdb;
    if (!in_array($brand, array('official', 'unofficial'), true) || !in_array($type, array('proforma', 'invoice'), true)) {
        return 1;
    }
    $last = $wpdb->get_var($wpdb->prepare(
        'SELECT last_number FROM ' . zigurat_invoice_sequences_table_name() . ' WHERE brand = %s AND document_type = %s',
        $brand,
        $type
    ));
    $max = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT MAX(document_number) FROM ' . zigurat_invoices_table_name() . ' WHERE brand = %s AND document_type = %s',
        $brand,
        $type
    ));
    return max(1, $max + 1, $last === null ? 1 : ((int) $last + 1));
}

function zigurat_invoice_save_initial_settings($data)
{
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'فقط مدیر کل می‌تواند تنظیمات اولیه فاکتورها را تغییر دهد.');
    }

    $settings = array();
    $seller_keys = array('name', 'national_id', 'economic_no', 'province', 'county', 'city', 'postal_code', 'address', 'phone');
    foreach (array('official', 'unofficial') as $brand) {
        $seller = array();
        foreach ($seller_keys as $key) {
            $raw = $data['setting_' . $brand . '_' . $key] ?? '';
            $seller[$key] = $key === 'address'
                ? sanitize_textarea_field(wp_unslash((string) $raw))
                : sanitize_text_field(wp_unslash((string) $raw));
        }
        if ($seller['name'] === '') {
            return new WP_Error('seller_name', 'نام فروشنده برای هر دو نوع فاکتور الزامی است.');
        }
        $tax_rate = (float) zigurat_invoice_normalize_digits($data['setting_' . $brand . '_tax_rate'] ?? 0);
        $settings[$brand] = array(
            'seller' => $seller,
            'payment_info' => sanitize_textarea_field(wp_unslash((string) ($data['setting_' . $brand . '_payment_info'] ?? ''))),
            'tax_rate' => max(0, min(100, $tax_rate)),
        );
    }

    global $wpdb;
    $requested_numbers = array();
    foreach (array('official', 'unofficial') as $brand) {
        foreach (array('proforma', 'invoice') as $type) {
            $key = $brand . '_' . $type;
            $next = max(1, absint(zigurat_invoice_normalize_digits($data['next_' . $key] ?? 1)));
            $max = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT MAX(document_number) FROM ' . zigurat_invoices_table_name() . ' WHERE brand = %s AND document_type = %s',
                $brand,
                $type
            ));
            if ($next <= $max) {
                return new WP_Error(
                    'number_conflict',
                    sprintf('شماره بعدی %s باید بزرگ‌تر از %s باشد.', zigurat_invoice_brand_label($brand) . ' / ' . zigurat_invoice_document_label($type), zigurat_invoice_format_number($max))
                );
            }
            $requested_numbers[$key] = array('brand' => $brand, 'type' => $type, 'next' => $next);
        }
    }

    update_option('zigurat_invoice_defaults', $settings, false);
    $now = current_time('mysql', true);
    foreach ($requested_numbers as $number) {
        $wpdb->replace(
            zigurat_invoice_sequences_table_name(),
            array(
                'brand' => $number['brand'],
                'document_type' => $number['type'],
                'last_number' => $number['next'] - 1,
                'updated_at' => $now,
            ),
            array('%s', '%s', '%d', '%s')
        );
    }
    return true;
}

function zigurat_invoice_customer_matches($term, $limit = 8)
{
    global $wpdb;
    $term = sanitize_text_field($term);
    if (mb_strlen($term) < 2) {
        return array();
    }
    $table = zigurat_invoices_table_name();
    $like = '%' . $wpdb->esc_like($term) . '%';
    $limit = max(1, min(20, absint($limit)));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT customer_name, customer_national_id, customer_economic_no,
                customer_province, customer_county, customer_city,
                customer_postal_code, customer_address, customer_phone
         FROM {$table}
         WHERE customer_name LIKE %s
         ORDER BY (customer_name = %s) DESC, id DESC
         LIMIT %d",
        $like,
        $term,
        min(200, $limit * 20)
    ));
    $fields = array('customer_national_id','customer_economic_no','customer_province','customer_county','customer_city','customer_postal_code','customer_address','customer_phone');
    $customers = array();
    foreach ($rows as $row) {
        $name = (string) $row->customer_name;
        if (!isset($customers[$name])) {
            $customers[$name] = (object) array_merge(array('customer_name'=>$name), array_fill_keys($fields, ''));
        }
        foreach ($fields as $field) {
            if ($customers[$name]->{$field} === '' && (string) $row->{$field} !== '') {
                $customers[$name]->{$field} = (string) $row->{$field};
            }
        }
    }
    return array_slice(array_values($customers), 0, $limit);
}

function zigurat_invoice_ajax_customer_lookup()
{
    check_ajax_referer('zigurat_invoice_customer_lookup', 'nonce');
    if (!zigurat_is_manager()) {
        wp_send_json_error(array('message' => 'دسترسی مجاز نیست.'), 403);
    }
    $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
    wp_send_json_success(zigurat_invoice_customer_matches($term));
}
add_action('wp_ajax_zigurat_invoice_customer_lookup', 'zigurat_invoice_ajax_customer_lookup');

function zigurat_invoice_excel_column($index)
{
    $column = '';
    for ($index++; $index > 0; $index = (int) (($index - 1) / 26)) {
        $column = chr(65 + (($index - 1) % 26)) . $column;
    }
    return $column;
}

function zigurat_invoice_xml_value($value)
{
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function zigurat_invoice_export_rows($args)
{
    global $wpdb;
    $where = array('1=1');
    $values = array();
    if (in_array($args['brand'] ?? '', array('official', 'unofficial'), true)) {
        $where[] = 'brand = %s';
        $values[] = $args['brand'];
    }
    if (in_array($args['type'] ?? '', array('invoice', 'proforma'), true)) {
        $where[] = 'document_type = %s';
        $values[] = $args['type'];
    }
    if (in_array($args['status'] ?? '', array('issued', 'draft'), true)) {
        $where[] = 'status = %s';
        $values[] = $args['status'];
    }
    $search = (string) ($args['search'] ?? '');
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(customer_name LIKE %s OR subject LIKE %s OR CAST(document_number AS CHAR) LIKE %s)';
        array_push($values, $like, $like, $like);
    }
    $sql = 'SELECT * FROM ' . zigurat_invoices_table_name() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 10000';
    return $values ? $wpdb->get_results($wpdb->prepare($sql, $values)) : $wpdb->get_results($sql);
}

function zigurat_invoice_build_xlsx($args)
{
    if (!class_exists('ZipArchive')) {
        return new WP_Error('zip_unavailable', 'امکان ساخت فایل اکسل روی سرور فعال نیست.');
    }

    $rows = zigurat_invoice_export_rows($args);
    $headers = array('نوع مجموعه', 'نوع سند', 'شماره', 'تاریخ', 'وضعیت', 'موضوع', 'خریدار', 'شناسه/ثبت', 'شماره اقتصادی', 'استان', 'شهر', 'تلفن', 'جمع اقلام', 'تخفیف', 'حمل', 'مالیات', 'جمع کل', 'پرداختی', 'مانده');
    $sheet_rows = array($headers);
    foreach ($rows as $row) {
        $sheet_rows[] = array(
            $row->brand === 'official' ? 'زیگورات (رسمی)' : 'فروشگاه دیاموند (غیررسمی)',
            zigurat_invoice_document_label($row->document_type),
            zigurat_invoice_format_number($row->document_number),
            $row->issue_date,
            zigurat_invoice_status_label($row->status),
            $row->subject,
            $row->customer_name,
            $row->customer_national_id,
            $row->customer_economic_no,
            $row->customer_province,
            $row->customer_city,
            $row->customer_phone,
            (int) $row->subtotal,
            (int) $row->discount,
            (int) $row->shipping,
            (int) $row->tax_amount,
            (int) $row->grand_total,
            (int) $row->paid_amount,
            (int) $row->balance,
        );
    }

    $numeric_columns = array(12, 13, 14, 15, 16, 17, 18);
    $sheet_data = '';
    foreach ($sheet_rows as $row_index => $cells) {
        $excel_row = $row_index + 1;
        $sheet_data .= '<row r="' . $excel_row . '">';
        foreach ($cells as $cell_index => $value) {
            $reference = zigurat_invoice_excel_column($cell_index) . $excel_row;
            $style = $row_index === 0 ? ' s="1"' : (in_array($cell_index, $numeric_columns, true) ? ' s="2"' : '');
            if ($row_index > 0 && in_array($cell_index, $numeric_columns, true)) {
                $sheet_data .= '<c r="' . $reference . '"' . $style . '><v>' . (int) $value . '</v></c>';
            } else {
                $sheet_data .= '<c r="' . $reference . '" t="inlineStr"' . $style . '><is><t>' . zigurat_invoice_xml_value($value) . '</t></is></c>';
            }
        }
        $sheet_data .= '</row>';
    }

    $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0" rightToLeft="1"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="20"/><cols>'
        . '<col min="1" max="1" width="22" customWidth="1"/><col min="2" max="2" width="24" customWidth="1"/>'
        . '<col min="3" max="5" width="14" customWidth="1"/><col min="6" max="6" width="30" customWidth="1"/>'
        . '<col min="7" max="7" width="24" customWidth="1"/><col min="8" max="9" width="18" customWidth="1"/>'
        . '<col min="10" max="12" width="16" customWidth="1"/><col min="13" max="19" width="16" customWidth="1"/>'
        . '</cols><sheetData>' . $sheet_data . '</sheetData>'
        . '<autoFilter ref="A1:S' . max(1, count($sheet_rows)) . '"/><pageSetup orientation="landscape"/></worksheet>';
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView rightToLeft="1"/></bookViews><sheets><sheet name="فاکتورها" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF2D2D2D"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs></styleSheet>';

    if (!function_exists('wp_tempnam')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $temp = wp_tempnam('zigurat-invoices.xlsx');
    $zip = new ZipArchive();
    if (!$temp || $zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return new WP_Error('xlsx_failed', 'ساخت فایل اکسل انجام نشد.');
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', $worksheet);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->close();

    return $temp;
}

function zigurat_invoice_download_xlsx($args)
{
    if (!zigurat_is_manager()) {
        wp_die('دسترسی به خروجی فاکتورها مجاز نیست.', 'خطا', array('response' => 403));
    }
    $temp = zigurat_invoice_build_xlsx($args);
    if (is_wp_error($temp)) {
        wp_die(esc_html($temp->get_error_message()), 'خطا', array('response' => 500));
    }

    $filename = 'zigurat-invoices-' . wp_date('Ymd-His') . '.xlsx';
    nocache_headers();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($temp));
    readfile($temp);
    unlink($temp);
    exit;
}
