<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_inventory_table_name()
{
    global $wpdb;
    return $wpdb->prefix . 'zigurat_inventory';
}

function zigurat_inventory_transactions_table_name()
{
    global $wpdb;
    return $wpdb->prefix . 'zigurat_inventory_transactions';
}

function zigurat_inventory_categories_table_name()
{
    global $wpdb;
    return $wpdb->prefix . 'zigurat_inventory_categories';
}

function zigurat_inventory_products_table_name()
{
    global $wpdb;
    return $wpdb->prefix . 'zigurat_inventory_products';
}

function zigurat_inventory_table_exists($table_name)
{
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name;
}

/** ساخت جدول‌های اختصاصی و انتقال موجودی قدیمی بدون حذف منبع قبلی. */
function zigurat_install_inventory_tables()
{
    global $wpdb;
    $inventory_table = zigurat_inventory_table_name();
    $transactions_table = zigurat_inventory_transactions_table_name();
    $categories_table = zigurat_inventory_categories_table_name();
    $products_table = zigurat_inventory_products_table_name();
    $schema_version = '5';

    // نسخه جدول‌ها فقط هنگام تغییر ساختار بررسی می‌شود؛ اجرای چهار SHOW TABLES
    // در تمام بازدیدهای سایت، حتی صفحات عمومی، زمان پاسخ را بی‌دلیل افزایش می‌داد.
    if (get_option('zigurat_inventory_schema_version') === $schema_version) {
        return;
    }

    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("CREATE TABLE {$inventory_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        product_id bigint(20) unsigned DEFAULT NULL,
        item_name varchar(191) NOT NULL,
        item_category varchar(191) NOT NULL,
        item_quantity bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY product_id (product_id),
        UNIQUE KEY unique_item (item_category(80), item_name(100)),
        KEY item_category (item_category(100)),
        KEY item_quantity (item_quantity)
    ) {$charset_collate};");

    dbDelta("CREATE TABLE {$categories_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(191) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY category_name (name(100))
    ) {$charset_collate};");

    dbDelta("CREATE TABLE {$products_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        category_id bigint(20) unsigned NOT NULL,
        name varchar(191) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY category_product (category_id,name(100)),
        KEY category_id (category_id)
    ) {$charset_collate};");

    dbDelta("CREATE TABLE {$transactions_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        inventory_id bigint(20) unsigned DEFAULT NULL,
        action varchar(20) NOT NULL,
        quantity bigint(20) unsigned NOT NULL,
        quantity_before bigint(20) unsigned DEFAULT NULL,
        quantity_after bigint(20) unsigned DEFAULT NULL,
        item_name varchar(191) NOT NULL,
        item_category varchar(191) NOT NULL,
        project_id bigint(20) unsigned DEFAULT NULL,
        project_name varchar(191) NOT NULL DEFAULT '',
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        user_name varchar(191) NOT NULL DEFAULT '',
        notes text NULL,
        created_at datetime NOT NULL,
        legacy_post_id bigint(20) unsigned DEFAULT NULL,
        reverses_transaction_id bigint(20) unsigned DEFAULT NULL,
        reversed_by_transaction_id bigint(20) unsigned DEFAULT NULL,
        reversed_at datetime DEFAULT NULL,
        reversed_by_user_id bigint(20) unsigned DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY legacy_post_id (legacy_post_id),
        KEY inventory_id (inventory_id),
        KEY action (action),
        KEY project_id (project_id),
        KEY reverses_transaction_id (reverses_transaction_id),
        KEY reversed_by_transaction_id (reversed_by_transaction_id),
        KEY created_at (created_at)
    ) {$charset_collate};");

    if (
        !zigurat_inventory_table_exists($inventory_table)
        || !zigurat_inventory_table_exists($transactions_table)
        || !zigurat_inventory_table_exists($categories_table)
        || !zigurat_inventory_table_exists($products_table)
    ) {
        return;
    }

    if (get_option('zigurat_inventory_stock_migration_version') !== '1' && zigurat_inventory_table_exists('zigurat_inventory')) {
        $legacy_items = $wpdb->get_results('SELECT item_name, item_category, item_quantity FROM zigurat_inventory');
        $now = current_time('mysql', true);
        foreach ((array) $legacy_items as $legacy_item) {
            $name = sanitize_text_field($legacy_item->item_name);
            $category = sanitize_text_field($legacy_item->item_category);
            if ($name === '' || $category === '') {
                continue;
            }
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$inventory_table} (item_name, item_category, item_quantity, created_at, updated_at)
                VALUES (%s, %s, %d, %s, %s)
                ON DUPLICATE KEY UPDATE item_quantity = VALUES(item_quantity), updated_at = VALUES(updated_at)",
                $name,
                $category,
                max(0, (int) $legacy_item->item_quantity),
                $now,
                $now
            ));
        }
        update_option('zigurat_inventory_stock_migration_version', '1', false);
    }

    if (get_option('zigurat_inventory_catalog_migration_version') !== '1') {
        $items = $wpdb->get_results("SELECT id, item_name, item_category FROM {$inventory_table} ORDER BY id ASC");
        $now = current_time('mysql', true);
        foreach ((array) $items as $item) {
            $category_name = sanitize_text_field($item->item_category);
            $product_name = sanitize_text_field($item->item_name);
            if ($category_name === '' || $product_name === '') {
                continue;
            }
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$categories_table} (name, created_at) VALUES (%s, %s)
                ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)",
                $category_name,
                $now
            ));
            $category_id = (int) $wpdb->insert_id;
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$products_table} (category_id, name, created_at) VALUES (%d, %s, %s)
                ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)",
                $category_id,
                $product_name,
                $now
            ));
            $product_id = (int) $wpdb->insert_id;
            if ($product_id) {
                $wpdb->update($inventory_table, array('product_id' => $product_id), array('id' => (int) $item->id), array('%d'), array('%d'));
            }
        }
        update_option('zigurat_inventory_catalog_migration_version', '1', false);
    }

    update_option('zigurat_inventory_schema_version', $schema_version, false);
}
add_action('init', 'zigurat_install_inventory_tables', 20);

function zigurat_ensure_inventory_catalog_page()
{
    if (get_option('zigurat_inventory_catalog_page_version') === '1') {
        return;
    }
    $page = get_page_by_path('inventory-catalog');
    $page_id = $page ? $page->ID : wp_insert_post(array(
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'تعریف دسته و کالا',
        'post_name' => 'inventory-catalog',
    ));
    if ($page_id && !is_wp_error($page_id)) {
        update_post_meta($page_id, '_wp_page_template', 'page-inventory-catalog.php');
        update_option('zigurat_inventory_catalog_page_version', '1', false);
    }
}
add_action('init', 'zigurat_ensure_inventory_catalog_page', 30);

/** انتقال تراکنش‌های قدیمی از نوشته‌ها به جدول گردش اختصاصی. */
function zigurat_migrate_legacy_inventory_transactions()
{
    if (
        get_option('zigurat_inventory_transactions_migration_version') === '1'
        || !zigurat_inventory_table_exists(zigurat_inventory_transactions_table_name())
    ) {
        return;
    }

    global $wpdb;
    $transactions_table = zigurat_inventory_transactions_table_name();
    $inventory_table = zigurat_inventory_table_name();
    $legacy_ids = $wpdb->get_col(
        "SELECT DISTINCT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
        INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
        INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
        WHERE p.post_type = 'post' AND p.post_status = 'publish'
        AND tt.taxonomy = 'category' AND t.name IN ('Add', 'Remove')
        ORDER BY p.post_date ASC"
    );

    foreach ($legacy_ids as $legacy_id) {
        $legacy_id = absint($legacy_id);
        $action_terms = wp_get_post_terms($legacy_id, 'category');
        $action_names = $action_terms && !is_wp_error($action_terms) ? wp_list_pluck($action_terms, 'name') : array();
        $action = in_array('Remove', $action_names, true) ? 'subtract' : 'add';

        $item_terms = wp_get_post_terms($legacy_id, 'item_name');
        $item_name = '';
        $item_category = '';
        if ($item_terms && !is_wp_error($item_terms)) {
            foreach ($item_terms as $term) {
                if ((int) $term->parent === 0) {
                    $item_category = $term->name;
                } else {
                    $item_name = $term->name;
                    if ($item_category === '') {
                        $parent = get_term($term->parent, 'item_name');
                        $item_category = $parent && !is_wp_error($parent) ? $parent->name : '';
                    }
                }
            }
        }
        if ($item_name === '' || $item_category === '') {
            continue;
        }

        $quantity = absint(wp_strip_all_tags(get_post_field('post_content', $legacy_id)));
        if (!$quantity) {
            continue;
        }
        $employee_terms = wp_get_post_terms($legacy_id, 'employee');
        $project_terms = wp_get_post_terms($legacy_id, 'project_item');
        $user_name = $employee_terms && !is_wp_error($employee_terms) ? $employee_terms[0]->name : '';
        $project_name = $project_terms && !is_wp_error($project_terms) ? $project_terms[0]->name : '';
        $user = $user_name !== '' ? get_user_by('login', $user_name) : false;
        $inventory_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$inventory_table} WHERE item_name = %s AND item_category = %s LIMIT 1",
            $item_name,
            $item_category
        ));

        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$transactions_table}
            (inventory_id, action, quantity, quantity_before, quantity_after, item_name, item_category, project_id, project_name, user_id, user_name, notes, created_at, legacy_post_id)
            VALUES (%d, %s, %d, NULL, NULL, %s, %s, NULL, %s, %d, %s, %s, %s, %d)",
            $inventory_id,
            $action,
            $quantity,
            $item_name,
            $item_category,
            $project_name,
            $user ? $user->ID : 0,
            $user_name,
            'انتقال‌یافته از سیستم قدیمی',
            get_post_field('post_date', $legacy_id),
            $legacy_id
        ));
    }

    update_option('zigurat_inventory_transactions_migration_version', '1', false);
}
add_action('init', 'zigurat_migrate_legacy_inventory_transactions', 35);
