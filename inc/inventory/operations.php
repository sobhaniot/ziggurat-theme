<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_inventory_clean_text($value, $limit = 191)
{
    $value = sanitize_text_field(is_string($value) ? wp_unslash($value) : '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
}

function zigurat_inventory_gregorian_to_jalali($gy, $gm, $gd)
{
    $g_day_no = 365 * ($gy - 1600) + (int) floor(($gy - 1600 + 3) / 4)
        - (int) floor(($gy - 1600 + 99) / 100) + (int) floor(($gy - 1600 + 399) / 400);
    $g_month_days = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    for ($month = 0; $month < $gm - 1; $month++) {
        $g_day_no += $g_month_days[$month];
    }
    if ($gm > 2 && (($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0)) {
        $g_day_no++;
    }
    $g_day_no += $gd - 1;
    $j_day_no = $g_day_no - 79;
    $j_np = (int) floor($j_day_no / 12053);
    $j_day_no %= 12053;
    $jy = 979 + 33 * $j_np + 4 * (int) floor($j_day_no / 1461);
    $j_day_no %= 1461;
    if ($j_day_no >= 366) {
        $jy += (int) floor(($j_day_no - 1) / 365);
        $j_day_no = ($j_day_no - 1) % 365;
    }
    if ($j_day_no < 186) {
        $jm = 1 + (int) floor($j_day_no / 31);
        $jd = 1 + ($j_day_no % 31);
    } else {
        $jm = 7 + (int) floor(($j_day_no - 186) / 30);
        $jd = 1 + (($j_day_no - 186) % 30);
    }
    return array($jy, $jm, $jd);
}

function zigurat_inventory_format_jalali_datetime($mysql_datetime)
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $mysql_datetime, new DateTimeZone('UTC'));
    if (!$date) {
        return (string) $mysql_datetime;
    }
    $date = $date->setTimezone(new DateTimeZone('Asia/Tehran'));
    $jalali = zigurat_inventory_gregorian_to_jalali((int) $date->format('Y'), (int) $date->format('m'), (int) $date->format('d'));
    return sprintf('%04d/%02d/%02d %s', $jalali[0], $jalali[1], $jalali[2], $date->format('H:i'));
}

function zigurat_inventory_create_category($name)
{
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'فقط مدیرکل می‌تواند دسته تعریف کند.');
    }
    $name = zigurat_inventory_clean_text($name);
    if ($name === '') {
        return new WP_Error('invalid_category', 'نام دسته را وارد کنید.');
    }
    global $wpdb;
    $table = zigurat_inventory_categories_table_name();
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE name = %s LIMIT 1", $name));
    if ($exists) {
        return new WP_Error('duplicate_category', 'این دسته قبلاً تعریف شده است.');
    }
    $inserted = $wpdb->insert($table, array('name' => $name, 'created_at' => current_time('mysql', true)), array('%s', '%s'));
    return $inserted ? (int) $wpdb->insert_id : new WP_Error('database', 'ثبت دسته انجام نشد.');
}

function zigurat_inventory_create_product($category_id, $name)
{
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'فقط مدیرکل می‌تواند کالا تعریف کند.');
    }
    $category_id = absint($category_id);
    $name = zigurat_inventory_clean_text($name);
    global $wpdb;
    $categories_table = zigurat_inventory_categories_table_name();
    $products_table = zigurat_inventory_products_table_name();
    $inventory_table = zigurat_inventory_table_name();
    $category = $category_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$categories_table} WHERE id = %d", $category_id)) : null;
    if (!$category || $name === '') {
        return new WP_Error('invalid_product', 'دسته و نام کالا را کامل وارد کنید.');
    }
    if ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$products_table} WHERE category_id = %d AND name = %s", $category_id, $name))) {
        return new WP_Error('duplicate_product', 'این کالا قبلاً در دسته انتخاب‌شده تعریف شده است.');
    }
    $now = current_time('mysql', true);
    $wpdb->query('START TRANSACTION');
    $created = $wpdb->insert($products_table, array('category_id' => $category_id, 'name' => $name, 'created_at' => $now), array('%d', '%s', '%s'));
    if (!$created) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('database', 'ثبت کالا انجام نشد.');
    }
    $product_id = (int) $wpdb->insert_id;
    $created_stock = $wpdb->insert($inventory_table, array(
        'product_id' => $product_id,
        'item_name' => $name,
        'item_category' => $category->name,
        'item_quantity' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ), array('%d', '%s', '%s', '%d', '%s', '%s'));
    if (!$created_stock) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('database', 'ثبت موجودی اولیه کالا انجام نشد.');
    }
    $wpdb->query('COMMIT');
    return $product_id;
}

function zigurat_inventory_get_catalog($available_only = false)
{
    global $wpdb;
    $categories_table = zigurat_inventory_categories_table_name();
    $products_table = zigurat_inventory_products_table_name();
    $inventory_table = zigurat_inventory_table_name();
    $availability = $available_only ? ' AND i.item_quantity > 0' : '';
    $rows = $wpdb->get_results("SELECT c.id AS category_id, c.name AS category_name,
        p.id AS product_id, p.name AS product_name, i.id AS inventory_id, COALESCE(i.item_quantity, 0) AS item_quantity
        FROM {$categories_table} c
        LEFT JOIN {$products_table} p ON p.category_id = c.id
        LEFT JOIN {$inventory_table} i ON i.product_id = p.id
        WHERE (p.id IS NULL OR 1=1){$availability}
        ORDER BY c.name ASC, p.name ASC");
    $catalog = array();
    foreach ($rows as $row) {
        $category_id = (int) $row->category_id;
        if (!isset($catalog[$category_id])) {
            $catalog[$category_id] = array('id' => $category_id, 'name' => $row->category_name, 'products' => array());
        }
        if ($row->product_id) {
            $catalog[$category_id]['products'][] = array(
                'id' => (int) $row->product_id,
                'name' => $row->product_name,
                'inventory_id' => (int) $row->inventory_id,
                'quantity' => (int) $row->item_quantity,
            );
        }
    }
    return array_values($catalog);
}

function zigurat_inventory_find_catalog_selection($category_id, $product_id)
{
    global $wpdb;
    $categories_table = zigurat_inventory_categories_table_name();
    $products_table = zigurat_inventory_products_table_name();
    return $wpdb->get_row($wpdb->prepare(
        "SELECT c.id AS category_id, c.name AS category_name, p.id AS product_id, p.name AS product_name
        FROM {$products_table} p INNER JOIN {$categories_table} c ON c.id = p.category_id
        WHERE c.id = %d AND p.id = %d LIMIT 1",
        absint($category_id), absint($product_id)
    ));
}

function zigurat_inventory_adjust_stock($action, $data)
{
    if (!zigurat_is_manager()) {
        return new WP_Error('forbidden', 'دسترسی به انبار مجاز نیست.');
    }
    if (!in_array($action, array('add', 'subtract'), true)) {
        return new WP_Error('invalid_action', 'نوع عملیات معتبر نیست.');
    }
    $quantity = isset($data['quantity']) && is_numeric($data['quantity']) ? (int) $data['quantity'] : 0;
    if ($quantity <= 0) {
        return new WP_Error('invalid_quantity', 'تعداد باید بیشتر از صفر باشد.');
    }
    $notes = isset($data['notes']) ? sanitize_textarea_field(wp_unslash((string) $data['notes'])) : '';
    $project_id = isset($data['project_id']) ? absint($data['project_id']) : 0;
    $project_name = '';
    if ($action === 'subtract') {
        $project = $project_id ? get_post($project_id) : null;
        if (!$project || !function_exists('zigurat_inventory_project_is_selectable') || !zigurat_inventory_project_is_selectable($project)) {
            return new WP_Error('invalid_project', 'پروژه را انتخاب کنید.');
        }
        $project_name = get_the_title($project);
    }

    global $wpdb;
    $inventory_table = zigurat_inventory_table_name();
    $transactions_table = zigurat_inventory_transactions_table_name();
    $products_table = zigurat_inventory_products_table_name();
    $categories_table = zigurat_inventory_categories_table_name();
    $now = current_time('mysql', true);
    $wpdb->query('START TRANSACTION');

    if ($action === 'add') {
        $product_id = isset($data['product_id']) ? absint($data['product_id']) : 0;
        $product = $product_id ? $wpdb->get_row($wpdb->prepare(
            "SELECT p.id, p.name AS item_name, c.name AS item_category FROM {$products_table} p
            INNER JOIN {$categories_table} c ON c.id = p.category_id WHERE p.id = %d LIMIT 1",
            $product_id
        )) : null;
        if (!$product) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('invalid_item', 'کالای انتخاب‌شده معتبر نیست.');
        }
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$inventory_table} WHERE product_id = %d LIMIT 1 FOR UPDATE", $product_id));
        if (!$item) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('invalid_item', 'ردیف موجودی این کالا پیدا نشد.');
        }
        $inventory_id = (int) $item->id;
        $item_name = $product->item_name;
        $item_category = $product->item_category;
        $before = (int) $item->item_quantity;
        $after = $before + $quantity;
    } else {
        $inventory_id = isset($data['inventory_id']) ? absint($data['inventory_id']) : 0;
        $item = $inventory_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$inventory_table} WHERE id = %d LIMIT 1 FOR UPDATE", $inventory_id)) : null;
        if (!$item) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('invalid_item', 'کالای انتخاب‌شده پیدا نشد.');
        }
        $item_name = $item->item_name;
        $item_category = $item->item_category;
        $before = (int) $item->item_quantity;
        if ($before < $quantity) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('insufficient', 'موجودی کالا برای این کسر کافی نیست.');
        }
        $after = $before - $quantity;
    }
    $updated = $wpdb->update($inventory_table, array('item_quantity' => $after, 'updated_at' => $now), array('id' => $inventory_id), array('%d', '%s'), array('%d'));
    if ($updated === false) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('database', 'به‌روزرسانی موجودی انجام نشد.');
    }

    $current_user = wp_get_current_user();
    $inserted = $wpdb->insert($transactions_table, array(
        'inventory_id' => $inventory_id, 'action' => $action, 'quantity' => $quantity,
        'quantity_before' => $before, 'quantity_after' => $after, 'item_name' => $item_name,
        'item_category' => $item_category, 'project_id' => $project_id ?: null,
        'project_name' => $project_name, 'user_id' => $current_user->ID,
        'user_name' => $current_user->display_name ?: $current_user->user_login,
        'notes' => $notes, 'created_at' => $now,
    ), array('%d', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s'));
    if (!$inserted) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('database', 'گردش انبار ثبت نشد و موجودی تغییر نکرد.');
    }
    $transaction_id = (int) $wpdb->insert_id;
    $wpdb->query('COMMIT');
    return array('transaction_id' => $transaction_id, 'quantity_before' => $before, 'quantity_after' => $after);
}

/** Reverse a wrong transaction without erasing the audit trail. */
function zigurat_inventory_reverse_transaction($transaction_id, $reason)
{
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'فقط مدیرکل می‌تواند تراکنش را ابطال کند.');
    }
    $transaction_id = absint($transaction_id);
    $reason = sanitize_textarea_field(wp_unslash((string) $reason));
    if (!$transaction_id) {
        return new WP_Error('invalid_transaction', 'تراکنش معتبر نیست.');
    }
    if ($reason === '') {
        return new WP_Error('invalid_reason', 'دلیل ابطال را وارد کنید.');
    }

    global $wpdb;
    $inventory_table = zigurat_inventory_table_name();
    $transactions_table = zigurat_inventory_transactions_table_name();
    $now = current_time('mysql', true);
    $wpdb->query('START TRANSACTION');

    $transaction = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$transactions_table} WHERE id = %d LIMIT 1 FOR UPDATE",
        $transaction_id
    ));
    if (!$transaction) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('invalid_transaction', 'تراکنش پیدا نشد.');
    }
    if (!empty($transaction->reverses_transaction_id)) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('cannot_reverse_reversal', 'سند ابطال را نمی‌توان دوباره ابطال کرد.');
    }
    if (!empty($transaction->reversed_by_transaction_id)) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('already_reversed', 'این تراکنش قبلاً ابطال شده است.');
    }
    if (!in_array($transaction->action, array('add', 'subtract'), true)) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('invalid_transaction', 'نوع تراکنش برای ابطال معتبر نیست.');
    }

    $inventory = $transaction->inventory_id ? $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$inventory_table} WHERE id = %d LIMIT 1 FOR UPDATE",
        $transaction->inventory_id
    )) : null;
    if (!$inventory) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('invalid_item', 'ردیف موجودی مربوط به این تراکنش پیدا نشد.');
    }

    $before = (int) $inventory->item_quantity;
    $quantity = (int) $transaction->quantity;
    $reverse_action = $transaction->action === 'add' ? 'subtract' : 'add';
    if ($reverse_action === 'subtract' && $before < $quantity) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('insufficient_reversal', 'موجودی فعلی برای ابطال این ورود کافی نیست؛ بخشی از کالا قبلاً مصرف شده است.');
    }
    $after = $reverse_action === 'subtract' ? $before - $quantity : $before + $quantity;
    $stock_updated = $wpdb->update(
        $inventory_table,
        array('item_quantity' => $after, 'updated_at' => $now),
        array('id' => (int) $inventory->id),
        array('%d', '%s'),
        array('%d')
    );
    if ($stock_updated === false) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('database', 'برگرداندن موجودی انجام نشد.');
    }

    $current_user = wp_get_current_user();
    $notes = sprintf('ابطال تراکنش شماره %d — دلیل: %s', $transaction_id, $reason);
    $inserted = $wpdb->insert($transactions_table, array(
        'inventory_id' => (int) $inventory->id,
        'action' => $reverse_action,
        'quantity' => $quantity,
        'quantity_before' => $before,
        'quantity_after' => $after,
        'item_name' => $transaction->item_name,
        'item_category' => $transaction->item_category,
        'project_id' => $transaction->project_id ?: null,
        'project_name' => $transaction->project_name,
        'user_id' => $current_user->ID,
        'user_name' => $current_user->display_name ?: $current_user->user_login,
        'notes' => $notes,
        'created_at' => $now,
        'reverses_transaction_id' => $transaction_id,
    ), array('%d', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%d'));
    if (!$inserted) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('database', 'سند ابطال ثبت نشد و موجودی تغییر نکرد.');
    }
    $reversal_id = (int) $wpdb->insert_id;
    $marked = $wpdb->update($transactions_table, array(
        'reversed_by_transaction_id' => $reversal_id,
        'reversed_at' => $now,
        'reversed_by_user_id' => $current_user->ID,
    ), array('id' => $transaction_id), array('%d', '%s', '%d'), array('%d'));
    if ($marked === false) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('database', 'اتصال سند ابطال به تراکنش اصلی انجام نشد.');
    }
    $wpdb->query('COMMIT');
    return array('reversal_id' => $reversal_id, 'quantity_before' => $before, 'quantity_after' => $after);
}

function zigurat_get_inventory_categories()
{
    return array_map(static function ($category) { return $category['name']; }, zigurat_inventory_get_catalog(false));
}

function zigurat_get_inventory_item_names()
{
    $names = array();
    foreach (zigurat_inventory_get_catalog(false) as $category) {
        $names = array_merge($names, wp_list_pluck($category['products'], 'name'));
    }
    sort($names);
    return $names;
}

function zigurat_get_available_inventory_items()
{
    global $wpdb;
    $table = zigurat_inventory_table_name();
    $products = zigurat_inventory_products_table_name();
    return $wpdb->get_results("SELECT i.*, p.category_id FROM {$table} i INNER JOIN {$products} p ON p.id = i.product_id WHERE i.item_quantity > 0 ORDER BY i.item_category ASC, i.item_name ASC");
}

function zigurat_get_inventory_summary()
{
    global $wpdb;
    $table = zigurat_inventory_table_name();
    $row = $wpdb->get_row("SELECT COUNT(*) AS item_count, COALESCE(SUM(item_quantity), 0) AS total_quantity, SUM(item_quantity > 0) AS available_count FROM {$table}");
    return array('item_count' => (int) ($row->item_count ?? 0), 'total_quantity' => (int) ($row->total_quantity ?? 0), 'available_count' => (int) ($row->available_count ?? 0));
}

function zigurat_get_inventory_items($args = array())
{
    global $wpdb;
    $inventory = zigurat_inventory_table_name();
    $products = zigurat_inventory_products_table_name();
    $args = wp_parse_args($args, array('search' => '', 'category_id' => 0, 'product_id' => 0, 'available' => false, 'page' => 1, 'per_page' => 50));
    $where = array('1=1'); $values = array();
    if ($args['search'] !== '') {
        $like = '%' . $wpdb->esc_like($args['search']) . '%';
        $where[] = '(i.item_name LIKE %s OR i.item_category LIKE %s)'; array_push($values, $like, $like);
    }
    if ($args['category_id']) { $where[] = 'p.category_id = %d'; $values[] = absint($args['category_id']); }
    if ($args['product_id']) { $where[] = 'i.product_id = %d'; $values[] = absint($args['product_id']); }
    if ($args['available']) { $where[] = 'i.item_quantity > 0'; }
    $from = " FROM {$inventory} i INNER JOIN {$products} p ON p.id = i.product_id WHERE " . implode(' AND ', $where);
    $count_sql = 'SELECT COUNT(*)' . $from;
    $total = (int) ($values ? $wpdb->get_var($wpdb->prepare($count_sql, $values)) : $wpdb->get_var($count_sql));
    $per_page = min(100, max(1, absint($args['per_page']))); $page = max(1, absint($args['page'])); $offset = ($page - 1) * $per_page;
    $data_sql = 'SELECT i.*, p.category_id' . $from . ' ORDER BY i.item_category ASC, i.item_name ASC LIMIT %d OFFSET %d';
    return array('items' => $wpdb->get_results($wpdb->prepare($data_sql, array_merge($values, array($per_page, $offset)))), 'total' => $total, 'pages' => max(1, (int) ceil($total / $per_page)), 'page' => $page);
}

function zigurat_get_inventory_transactions($args = array())
{
    global $wpdb;
    $table = zigurat_inventory_transactions_table_name();
    $args = wp_parse_args($args, array('action' => '', 'project_id' => 0, 'project_name' => '', 'item_category' => '', 'item_name' => '', 'page' => 1, 'per_page' => 50));
    $where = array('1=1'); $values = array();
    if (in_array($args['action'], array('add', 'subtract'), true)) { $where[] = 'action = %s'; $values[] = $args['action']; }
    if ($args['project_id']) { $where[] = '(project_id = %d OR project_name = %s)'; $values[] = absint($args['project_id']); $values[] = $args['project_name']; }
    if ($args['item_category'] !== '') { $where[] = 'item_category = %s'; $values[] = $args['item_category']; }
    if ($args['item_name'] !== '') { $where[] = 'item_name = %s'; $values[] = $args['item_name']; }
    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
    $total = (int) ($values ? $wpdb->get_var($wpdb->prepare($count_sql, $values)) : $wpdb->get_var($count_sql));
    $per_page = min(100, max(1, absint($args['per_page']))); $page = max(1, absint($args['page'])); $offset = ($page - 1) * $per_page;
    $data_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
    return array('transactions' => $wpdb->get_results($wpdb->prepare($data_sql, array_merge($values, array($per_page, $offset)))), 'total' => $total, 'pages' => max(1, (int) ceil($total / $per_page)), 'page' => $page);
}

function zigurat_inventory_page_url($slug)
{
    $page = get_page_by_path($slug);
    return $page ? get_permalink($page) : home_url('/' . trim($slug, '/') . '/');
}
