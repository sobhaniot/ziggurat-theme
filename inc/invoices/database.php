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

function zigurat_install_invoice_tables()
{
    global $wpdb;
    $version = '2';
    $invoices = zigurat_invoices_table_name();
    $items = zigurat_invoice_items_table_name();
    $sequences = zigurat_invoice_sequences_table_name();
    if (get_option('zigurat_invoice_schema_version') === $version
        && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $invoices)) === $invoices
        && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $items)) === $items
        && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $sequences)) === $sequences) {
        return;
    }
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$invoices} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        brand varchar(20) NOT NULL,
        document_type varchar(20) NOT NULL,
        document_number bigint(20) unsigned NOT NULL,
        issue_date varchar(10) NOT NULL,
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
        tax_rate decimal(5,2) NOT NULL DEFAULT 0,
        tax_amount bigint(20) unsigned NOT NULL DEFAULT 0,
        grand_total bigint(20) unsigned NOT NULL DEFAULT 0,
        paid_amount bigint(20) unsigned NOT NULL DEFAULT 0,
        balance bigint(20) unsigned NOT NULL DEFAULT 0,
        notes text NULL,
        payment_info text NULL,
        created_by bigint(20) unsigned NOT NULL DEFAULT 0,
        updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY brand_type_number (brand,document_type,document_number),
        KEY brand (brand),
        KEY document_type (document_type),
        KEY status (status),
        KEY source_proforma_id (source_proforma_id),
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
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $invoices)) === $invoices
        && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $items)) === $items
        && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $sequences)) === $sequences) {
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
