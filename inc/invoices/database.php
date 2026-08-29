<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_invoices_table_name()
{
    global $wpdb;
    return $wpdb->prefix . 'zigurat_invoices';
}

function zigurat_invoice_items_table_name()
{
    global $wpdb;
    return $wpdb->prefix . 'zigurat_invoice_items';
}

function zigurat_invoice_sequences_table_name()
{
    global $wpdb;
    return $wpdb->prefix . 'zigurat_invoice_sequences';
}

function zigurat_invoice_payments_table_name()
{
    global $wpdb;
    return $wpdb->prefix . 'zigurat_invoice_payments';
}

function zigurat_install_invoice_tables()
{
    global $wpdb;
    $version = '7';
    $invoices = zigurat_invoices_table_name();
    $items = zigurat_invoice_items_table_name();
    $sequences = zigurat_invoice_sequences_table_name();
    $payments = zigurat_invoice_payments_table_name();
    // در حالت عادی نسخه ذخیره‌شده کافی است. بررسی سه جدول در هر درخواست
    // باعث کندشدن همه صفحات، حتی برای بازدیدکنندگان عمومی، می‌شد.
    if (get_option('zigurat_invoice_schema_version') === $version) {
        return;
    }
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$invoices} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        brand varchar(20) NOT NULL,
        document_type varchar(20) NOT NULL,
        document_number bigint(20) unsigned NOT NULL,
        number_suffix smallint(5) unsigned NOT NULL DEFAULT 0,
        parent_invoice_id bigint(20) unsigned NOT NULL DEFAULT 0,
        allow_branches tinyint(1) unsigned NOT NULL DEFAULT 0,
        issue_date varchar(10) NOT NULL,
        tax_year smallint(5) unsigned NOT NULL DEFAULT 0,
        tax_quarter tinyint(1) unsigned NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'issued',
        subject varchar(191) NOT NULL DEFAULT '',
        source_proforma_id bigint(20) unsigned NOT NULL DEFAULT 0,
        seller_json longtext NULL,
        customer_name varchar(191) NOT NULL,
        customer_national_id varchar(50) NOT NULL DEFAULT '',
        customer_economic_no varchar(50) NOT NULL DEFAULT '',
        customer_province varchar(100) NOT NULL DEFAULT '',
        customer_county varchar(100) NOT NULL DEFAULT '',
        customer_city varchar(100) NOT NULL DEFAULT '',
        customer_postal_code varchar(30) NOT NULL DEFAULT '',
        customer_address text NULL,
        customer_phone varchar(50) NOT NULL DEFAULT '',
        subtotal bigint(20) unsigned NOT NULL DEFAULT 0,
        discount bigint(20) unsigned NOT NULL DEFAULT 0,
        shipping bigint(20) unsigned NOT NULL DEFAULT 0,
        overhead_rate decimal(5,2) NOT NULL DEFAULT 0,
        overhead_amount bigint(20) unsigned NOT NULL DEFAULT 0,
        insurance_rate decimal(5,2) NOT NULL DEFAULT 0,
        insurance_amount bigint(20) unsigned NOT NULL DEFAULT 0,
        tax_rate decimal(5,2) NOT NULL DEFAULT 0,
        tax_amount bigint(20) unsigned NOT NULL DEFAULT 0,
        grand_total bigint(20) unsigned NOT NULL DEFAULT 0,
        paid_amount bigint(20) unsigned NOT NULL DEFAULT 0,
        balance bigint(20) unsigned NOT NULL DEFAULT 0,
        payment_status varchar(20) NOT NULL DEFAULT 'unpaid',
        settled_at datetime NULL DEFAULT NULL,
        locked_at datetime NULL DEFAULT NULL,
        locked_reason varchar(30) NOT NULL DEFAULT '',
        tax_status varchar(30) NOT NULL DEFAULT 'not_submitted',
        tax_uid varchar(64) NOT NULL DEFAULT '',
        tax_tracking_code varchar(100) NOT NULL DEFAULT '',
        tax_submitted_at datetime NULL DEFAULT NULL,
        tax_subject varchar(20) NOT NULL DEFAULT 'original',
        reference_invoice_id bigint(20) unsigned NOT NULL DEFAULT 0,
        tax_note text NULL,
        notes text NULL,
        payment_info text NULL,
        created_by bigint(20) unsigned NOT NULL DEFAULT 0,
        updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY brand_type_number_suffix (brand,document_type,document_number,number_suffix),
        KEY brand (brand),
        KEY document_type (document_type),
        KEY status (status),
        KEY payment_status (payment_status),
        KEY tax_status (tax_status),
        KEY reference_invoice_id (reference_invoice_id),
        KEY source_proforma_id (source_proforma_id),
        KEY parent_invoice_id (parent_invoice_id),
        KEY tax_period (brand,document_type,tax_year,tax_quarter),
        KEY issue_date (issue_date),
        KEY customer_name (customer_name(100))
    ) {$charset};");
    dbDelta("CREATE TABLE {$items} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        invoice_id bigint(20) unsigned NOT NULL,
        position smallint(5) unsigned NOT NULL DEFAULT 1,
        description text NOT NULL,
        quantity decimal(18,3) NOT NULL DEFAULT 1,
        unit varchar(50) NOT NULL DEFAULT '',
        unit_price bigint(20) unsigned NOT NULL DEFAULT 0,
        discount bigint(20) unsigned NOT NULL DEFAULT 0,
        line_total bigint(20) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY invoice_id (invoice_id),
        KEY position (position)
    ) {$charset};");
    dbDelta("CREATE TABLE {$sequences} (
        brand varchar(20) NOT NULL,
        document_type varchar(20) NOT NULL,
        last_number bigint(20) unsigned NOT NULL DEFAULT 0,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (brand,document_type)
    ) {$charset};");
    dbDelta("CREATE TABLE {$payments} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        invoice_id bigint(20) unsigned NOT NULL,
        amount bigint(20) unsigned NOT NULL DEFAULT 0,
        payment_date varchar(10) NOT NULL,
        method varchar(30) NOT NULL DEFAULT '',
        reference_no varchar(100) NOT NULL DEFAULT '',
        notes text NULL,
        created_by bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY invoice_id (invoice_id),
        KEY payment_date (payment_date)
    ) {$charset};");
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $invoices)) === $invoices) {
        $old_number_index = $wpdb->get_var("SHOW INDEX FROM {$invoices} WHERE Key_name = 'brand_type_number'");
        if ($old_number_index) {
            $wpdb->query("ALTER TABLE {$invoices} DROP INDEX brand_type_number");
        }
        $new_number_index = $wpdb->get_var("SHOW INDEX FROM {$invoices} WHERE Key_name = 'brand_type_number_suffix'");
        if (!$new_number_index) {
            $wpdb->query("ALTER TABLE {$invoices} ADD UNIQUE KEY brand_type_number_suffix (brand,document_type,document_number,number_suffix)");
        }
        $has_tax_year = $wpdb->get_var("SHOW COLUMNS FROM {$invoices} LIKE 'tax_year'");
        $has_tax_quarter = $wpdb->get_var("SHOW COLUMNS FROM {$invoices} LIKE 'tax_quarter'");
        if ($has_tax_year && $has_tax_quarter) {
            $wpdb->query("UPDATE {$invoices}
                SET tax_year = CAST(SUBSTRING(issue_date,1,4) AS UNSIGNED),
                    tax_quarter = CASE
                        WHEN CAST(SUBSTRING(issue_date,6,2) AS UNSIGNED) BETWEEN 1 AND 3 THEN 1
                        WHEN CAST(SUBSTRING(issue_date,6,2) AS UNSIGNED) BETWEEN 4 AND 6 THEN 2
                        WHEN CAST(SUBSTRING(issue_date,6,2) AS UNSIGNED) BETWEEN 7 AND 9 THEN 3
                        WHEN CAST(SUBSTRING(issue_date,6,2) AS UNSIGNED) BETWEEN 10 AND 12 THEN 4
                        ELSE 0 END
                WHERE brand = 'official' AND document_type = 'invoice'
                  AND issue_date REGEXP '^[0-9]{4}/[0-9]{2}/[0-9]{2}$'");
        }
        if ($wpdb->get_var("SHOW COLUMNS FROM {$invoices} LIKE 'payment_status'")) {
            $wpdb->query("UPDATE {$invoices}
                SET payment_status = CASE
                    WHEN document_type <> 'invoice' THEN 'not_applicable'
                    WHEN grand_total > 0 AND paid_amount >= grand_total THEN 'settled'
                    WHEN paid_amount > 0 THEN 'partial'
                    ELSE 'unpaid' END");
            $wpdb->query("UPDATE {$invoices}
                SET settled_at = COALESCE(settled_at, updated_at),
                    locked_at = COALESCE(locked_at, updated_at),
                    locked_reason = IF(locked_reason = '', 'settled', locked_reason)
                WHERE document_type = 'invoice' AND status = 'issued'
                  AND grand_total > 0 AND paid_amount >= grand_total");
        }
    }
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $payments)) === $payments) {
        // پرداخت‌های قبلی را یک‌بار به‌عنوان مانده اولیه وارد می‌کنیم تا سابقه
        // و جمع پرداخت‌ها از این پس از جدول مستقل قابل محاسبه باشد.
        $wpdb->query("INSERT INTO {$payments}
            (invoice_id, amount, payment_date, method, reference_no, notes, created_by, created_at)
            SELECT i.id, i.paid_amount, i.issue_date, 'opening', '', 'مانده اولیه از اطلاعات قبلی', i.updated_by, i.updated_at
            FROM {$invoices} i
            WHERE i.document_type = 'invoice' AND i.paid_amount > 0
              AND NOT EXISTS (SELECT 1 FROM {$payments} p WHERE p.invoice_id = i.id)");
    }
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $invoices)) === $invoices
        && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $items)) === $items
        && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $sequences)) === $sequences
        && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $payments)) === $payments
        && $wpdb->get_var("SHOW INDEX FROM {$invoices} WHERE Key_name = 'brand_type_number_suffix'")
        && $wpdb->get_var("SHOW INDEX FROM {$invoices} WHERE Key_name = 'tax_period'")
        && $wpdb->get_var("SHOW COLUMNS FROM {$invoices} LIKE 'tax_year'")
        && $wpdb->get_var("SHOW COLUMNS FROM {$invoices} LIKE 'tax_quarter'")
        && $wpdb->get_var("SHOW COLUMNS FROM {$invoices} LIKE 'payment_status'")
        && $wpdb->get_var("SHOW COLUMNS FROM {$invoices} LIKE 'tax_status'")) {
        update_option('zigurat_invoice_schema_version', $version, false);
    }
}
add_action('init', 'zigurat_install_invoice_tables', 22);

function zigurat_ensure_invoices_page()
{
    if (get_option('zigurat_invoices_page_version') === '1') {
        return;
    }
    $page = get_page_by_path('invoices');
    $page_id = $page ? $page->ID : wp_insert_post(array(
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'بخش فاکتور',
        'post_name' => 'invoices',
    ));
    if ($page_id && !is_wp_error($page_id)) {
        update_post_meta($page_id, '_wp_page_template', 'page-invoices.php');
        update_option('zigurat_invoices_page_version', '1', false);
    }
}
add_action('init', 'zigurat_ensure_invoices_page', 32);
