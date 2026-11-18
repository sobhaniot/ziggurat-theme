<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_inventory_sync_manager_access()
{
    return current_user_can('manage_options')
        || current_user_can('zigurat_sync_inventory')
        || (function_exists('zigurat_user_can_access_manager_panel') && zigurat_user_can_access_manager_panel());
}

function zigurat_inventory_sync_user_profile_field($user)
{
    if (!current_user_can('manage_options') || !current_user_can('edit_user', $user->ID)) {
        return;
    }
    wp_nonce_field('zigurat_inventory_sync_' . $user->ID, 'zigurat_inventory_sync_nonce');
    ?>
    <h2>همگام‌سازی انبار از اکسل</h2>
    <label>
        <input type="checkbox" name="zigurat_sync_inventory_enabled" value="1" <?php checked(user_can($user, 'zigurat_sync_inventory')); ?>>
        اجازهٔ مشاهدهٔ فهرست کالاها و همگام‌سازی موجودی از نرم‌افزار زیگورات
    </label>
    <?php
}
add_action('show_user_profile', 'zigurat_inventory_sync_user_profile_field');
add_action('edit_user_profile', 'zigurat_inventory_sync_user_profile_field');

function zigurat_inventory_sync_save_user_profile($user_id)
{
    if (!current_user_can('manage_options') || !current_user_can('edit_user', $user_id)) {
        return;
    }
    $nonce = $_POST['zigurat_inventory_sync_nonce'] ?? '';
    if (!is_string($nonce) || !wp_verify_nonce(wp_unslash($nonce), 'zigurat_inventory_sync_' . $user_id)) {
        return;
    }
    $user = new WP_User($user_id);
    if (($_POST['zigurat_sync_inventory_enabled'] ?? '') === '1') {
        $user->add_cap('zigurat_sync_inventory');
    } else {
        $user->remove_cap('zigurat_sync_inventory');
    }
}
add_action('edit_user_profile_update', 'zigurat_inventory_sync_save_user_profile');
add_action('personal_options_update', 'zigurat_inventory_sync_save_user_profile');

add_action('rest_api_init', static function () {
    register_rest_route('zigurat-inventory/v1', '/catalog', array(
        'methods' => 'GET',
        'permission_callback' => 'zigurat_inventory_sync_manager_access',
        'callback' => static function () {
            $products = array();
            foreach (zigurat_inventory_get_catalog(false) as $category) {
                foreach ($category['products'] as $product) {
                    $products[] = array(
                        'product_id' => (int) $product['id'],
                        'inventory_id' => (int) $product['inventory_id'],
                        'name' => (string) $product['name'],
                        'category_id' => (int) $category['id'],
                        'category' => (string) $category['name'],
                        'quantity' => (string) $product['quantity'],
                    );
                }
            }
            return new WP_REST_Response(array('products' => $products), 200, array('Cache-Control' => 'private, no-store'));
        },
    ));
    register_rest_route('zigurat-inventory/v1', '/sync', array(
        'methods' => 'POST',
        'permission_callback' => 'zigurat_inventory_sync_manager_access',
        'callback' => 'zigurat_inventory_sync_from_excel',
    ));
});

function zigurat_inventory_sync_resolve_project($name)
{
    if (!zigurat_inventory_sync_manager_access()) {
        return new WP_Error('forbidden', 'دسترسی همگام‌سازی انبار ندارید.', array('status' => 403));
    }
    $name = sanitize_text_field((string) $name);
    if ($name === '') return new WP_Error('inventory_project_empty', 'نام پروژه مصرف‌کننده خالی است.', array('status' => 400));
    $matches = zigurat_inventory_find_projects_by_name($name);
    // Prefer a published site project; otherwise reuse an active internal one.
    foreach ($matches as $project) {
        if (!zigurat_inventory_is_internal_project($project) && zigurat_inventory_project_is_selectable($project)) return (int) $project->ID;
    }
    foreach ($matches as $project) {
        if (zigurat_inventory_project_is_selectable($project)) return (int) $project->ID;
    }
    if ($matches) return new WP_Error('inventory_project_inactive', 'پروژه هم‌نام غیرفعال است؛ ابتدا آن را در سایت فعال کنید.', array('status' => 409));
    $id = wp_insert_post(array(
        'post_type' => 'project', 'post_status' => 'draft', 'post_title' => wp_slash($name),
        'post_author' => get_current_user_id(),
        'meta_input' => array(
            '_zigurat_inventory_only' => '1',
            '_zigurat_inventory_project_status' => 'active',
        ),
    ), true);
    if (is_wp_error($id)) return $id;
    zigurat_inventory_internal_project_code($id);
    return (int) $id;
}

function zigurat_inventory_sync_from_excel(WP_REST_Request $request)
{
    global $wpdb;
    $input = $request->get_json_params();
    $snapshots_before = $input['snapshots_before'] ?? null;
    $movements = $input['movements'] ?? null;
    $snapshots_after = $input['snapshots_after'] ?? null;
    if (!is_array($snapshots_before) || !is_array($movements) || !is_array($snapshots_after)
        || array_values($snapshots_before) !== $snapshots_before
        || array_values($movements) !== $movements
        || array_values($snapshots_after) !== $snapshots_after
        || count($snapshots_before) + count($movements) + count($snapshots_after) > 5000) {
        return new WP_Error('inventory_sync_invalid', 'ساختار همگام‌سازی انبار معتبر نیست.', array('status' => 400));
    }
    $normalize_quantity = static function ($value) {
        if (!is_string($value) && !is_int($value) && !is_float($value)) return null;
        $value = strtr(trim((string) $value), array(
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            '٫'=>'.', ','=>'.', '،'=>'.',
        ));
        return preg_match('/^(?:0|[1-9][0-9]{0,18})(?:\.[0-9])?$/D', $value) ? $value : null;
    };
    $validate_snapshot = static function ($rows) use ($normalize_quantity) {
        $seen = array();
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['product_id']) || !ctype_digit((string) $row['product_id'])
                || (int) $row['product_id'] < 1 || !is_string($row['code'] ?? null)
                || trim($row['code']) === '' || !is_string($row['source_table'] ?? null)
                || trim($row['source_table']) === '') return false;
            $id = (int) $row['product_id'];
            if (isset($seen[$id])) return false;
            $seen[$id] = true;
            $quantity = $normalize_quantity($row['quantity'] ?? null);
            if ($quantity === null) return false;
        }
        return true;
    };
    if (!$validate_snapshot($snapshots_before) || !$validate_snapshot($snapshots_after)) {
        return new WP_Error('inventory_sync_snapshot_invalid', 'موجودی نهایی یا شناسهٔ کالای یکی از ردیف‌ها معتبر نیست.', array('status' => 400));
    }
    $movement_keys = array();
    foreach ($movements as $row) {
        if (!is_array($row) || empty($row['product_id']) || !ctype_digit((string) $row['product_id'])
            || (int) $row['product_id'] < 1 || !in_array($row['action'] ?? '', array('add', 'subtract'), true)
            || !is_string($row['code'] ?? null) || trim($row['code']) === ''
            || !is_string($row['source_table'] ?? null) || trim($row['source_table']) === ''
            || !preg_match('/^[a-f0-9]{64}$/D', (string) ($row['source_key'] ?? ''))
            || !preg_match('/^[a-f0-9]{64}$/D', (string) ($row['source_hash'] ?? ''))
            || $normalize_quantity($row['quantity'] ?? null) === null
            || (float) ($row['quantity'] ?? 0) <= 0) {
            return new WP_Error('inventory_sync_movement_invalid', 'مقدار، جهت گردش یا شناسهٔ یکی از ردیف‌های گردش معتبر نیست.', array('status' => 400));
        }
        if (isset($movement_keys[$row['source_key']])) {
            return new WP_Error('inventory_sync_duplicate_key', 'یک ردیف اکسل در بستهٔ همگام‌سازی تکراری است.', array('status' => 400));
        }
        $movement_keys[$row['source_key']] = true;
    }

    $inventory_table = zigurat_inventory_table_name();
    $transactions_table = zigurat_inventory_transactions_table_name();
    $products_table = zigurat_inventory_products_table_name();
    $categories_table = zigurat_inventory_categories_table_name();
    $current_user = wp_get_current_user();
    $now = current_time('mysql', true);
    $changed_products = array();
    $unchanged_products = array();
    $accepted_movement_keys = array();
    $wpdb->query('START TRANSACTION');

    $apply_snapshot = static function ($rows) use (
        $wpdb, $inventory_table, $products_table, $categories_table, $transactions_table,
        $current_user, $now, &$changed_products, &$unchanged_products
    ) {
        foreach ($rows as $row) {
            $product_id = (int) $row['product_id'];
            $item = $wpdb->get_row($wpdb->prepare(
                "SELECT i.id AS inventory_id, i.item_quantity, p.name AS item_name, c.name AS item_category
                FROM {$products_table} p INNER JOIN {$categories_table} c ON c.id = p.category_id
                INNER JOIN {$inventory_table} i ON i.product_id = p.id
                WHERE p.id = %d LIMIT 1 FOR UPDATE",
                $product_id
            ));
            if (!$item) return new WP_Error('inventory_sync_item_missing', 'یکی از کالاهای انتخاب‌شده در انبار سایت پیدا نشد.');
            $target = (float) $row['quantity'];
            $before = round((float) $item->item_quantity, 1);
            if (abs($target - $before) < 0.00001) {
                $unchanged_products[] = $product_id;
                continue;
            }
            $action = $target > $before ? 'add' : 'subtract';
            $delta = round(abs($target - $before), 1);
            if ($wpdb->update($inventory_table, array('item_quantity' => $target, 'updated_at' => $now), array('id' => (int) $item->inventory_id), array('%f', '%s'), array('%d')) === false) {
                return new WP_Error('inventory_sync_stock_failed', 'به‌روزرسانی موجودی یکی از کالاها انجام نشد.');
            }
            $note = 'همگام‌سازی موجودی نهایی از اکسل؛ کد ' . sanitize_text_field($row['code'])
                . '؛ تیبل ' . sanitize_text_field($row['source_table']);
            if (!$wpdb->insert($transactions_table, array(
                'inventory_id' => (int) $item->inventory_id,
                'action' => $action,
                'quantity' => $delta,
                'quantity_before' => $before,
                'quantity_after' => $target,
                'item_name' => $item->item_name,
                'item_category' => $item->item_category,
                'project_id' => null,
                'project_name' => '',
                'user_id' => (int) $current_user->ID,
                'user_name' => $current_user->display_name ?: $current_user->user_login,
                'notes' => $note,
                'created_at' => $now,
            ), array('%d', '%s', '%f', '%f', '%f', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s'))) {
                return new WP_Error('inventory_sync_transaction_failed', 'ثبت سابقهٔ تغییر موجودی انجام نشد.');
            }
            $changed_products[] = $product_id;
        }
        return true;
    };

    $snapshot_result = $apply_snapshot($snapshots_before);
    if (is_wp_error($snapshot_result)) {
        $wpdb->query('ROLLBACK');
        return $snapshot_result;
    }
    foreach ($movements as $row) {
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, source_hash, reversed_by_transaction_id FROM {$transactions_table} WHERE source_key = %s LIMIT 1 FOR UPDATE",
            $row['source_key']
        ));
        if ($existing && empty($existing->reversed_by_transaction_id)
            && !hash_equals((string) $existing->source_hash, (string) $row['source_hash'])) {
            $reversal = zigurat_inventory_reverse_transaction_internal(
                (int) $existing->id,
                'اصلاح خودکار ردیف اکسل؛ ابطال سند قبلی و ثبت سند جایگزین',
                true
            );
            if (is_wp_error($reversal)) {
                $wpdb->query('ROLLBACK');
                return $reversal;
            }
            $existing->reversed_by_transaction_id = $reversal['reversal_id'];
        }
        if ($existing && !empty($existing->reversed_by_transaction_id)) {
            // Preserve the cancelled document and its hash while freeing the
            // Excel row identity for a new, independently reversible movement.
            $archived_key = hash('sha256', 'cancelled:' . $existing->id . ':' . $row['source_key']);
            if ($wpdb->update($transactions_table, array('source_key' => $archived_key), array('id' => (int) $existing->id), array('%s'), array('%d')) !== 1) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('inventory_sync_reactivate_failed', 'آماده‌سازی ثبت مجدد سند باطل‌شده انجام نشد.', array('status' => 409));
            }
            $existing = null;
        }
        if ($existing) {
            if (!hash_equals((string) $existing->source_hash, (string) $row['source_hash'])) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('inventory_sync_row_changed', 'یک ردیف گردشِ قبلاً ثبت‌شده در اکسل تغییر کرده است؛ برای جلوگیری از ثبت دوباره، ابتدا آن را بررسی و در صورت نیاز از سایت ابطال کنید.', array('status' => 409));
            }
            if ($row['action'] === 'subtract') {
                $project_id = zigurat_inventory_sync_resolve_project($row['project_name'] ?? '');
                if (is_wp_error($project_id)) { $wpdb->query('ROLLBACK'); return $project_id; }
                if ($wpdb->update($transactions_table, array('project_id' => $project_id), array('id' => (int) $existing->id), array('%d'), array('%d')) === false) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('inventory_project_link_failed', 'اتصال پروژه به سند قبلی انجام نشد.');
                }
            }
            $accepted_movement_keys[] = $row['source_key'];
            continue;
        }
        $product_id = (int) $row['product_id'];
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT i.id AS inventory_id, i.item_quantity, p.name AS item_name, c.name AS item_category
            FROM {$products_table} p INNER JOIN {$categories_table} c ON c.id = p.category_id
            INNER JOIN {$inventory_table} i ON i.product_id = p.id
            WHERE p.id = %d LIMIT 1 FOR UPDATE",
            $product_id
        ));
        if (!$item) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('inventory_sync_item_missing', 'یکی از کالاهای انتخاب‌شده در انبار سایت پیدا نشد.');
        }
        $quantity = (float) $row['quantity'];
        $before = round((float) $item->item_quantity, 1);
        if ($row['action'] === 'subtract' && $before + 0.00001 < $quantity) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('inventory_sync_insufficient', 'موجودی سایت برای یکی از ردیف‌های مصرف کافی نیست؛ کل همگام‌سازی متوقف شد و چیزی ثبت نشد.', array('status' => 409));
        }
        $after = round($row['action'] === 'add' ? $before + $quantity : $before - $quantity, 1);
        if ($wpdb->update($inventory_table, array('item_quantity' => $after, 'updated_at' => $now), array('id' => (int) $item->inventory_id), array('%f', '%s'), array('%d')) === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('inventory_sync_stock_failed', 'تغییر موجودی یکی از کالاها انجام نشد.');
        }
        $note = 'ورود/مصرف از اکسل؛ کد ' . sanitize_text_field($row['code'])
            . '؛ تیبل ' . sanitize_text_field($row['source_table']);
        if (!empty($row['source_date'])) $note .= '؛ تاریخ اکسل ' . sanitize_text_field($row['source_date']);
        if (!empty($row['description'])) $note .= '؛ ' . sanitize_text_field($row['description']);
        $project_id = null;
        if ($row['action'] === 'subtract') {
            $project_id = zigurat_inventory_sync_resolve_project($row['project_name'] ?? '');
            if (is_wp_error($project_id)) { $wpdb->query('ROLLBACK'); return $project_id; }
        }
        if (!$wpdb->insert($transactions_table, array(
            'inventory_id' => (int) $item->inventory_id,
            'action' => $row['action'],
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'item_name' => $item->item_name,
            'item_category' => $item->item_category,
            'project_id' => $project_id,
            'project_name' => sanitize_text_field($row['project_name'] ?? ''),
            'user_id' => (int) $current_user->ID,
            'user_name' => $current_user->display_name ?: $current_user->user_login,
            'notes' => $note,
            'created_at' => $now,
            'source_key' => $row['source_key'],
            'source_hash' => $row['source_hash'],
        ), array('%d', '%s', '%f', '%f', '%f', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s'))) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('inventory_sync_transaction_failed', 'ثبت گردش انبار انجام نشد.');
        }
        $accepted_movement_keys[] = $row['source_key'];
        $changed_products[] = $product_id;
    }
    $snapshot_result = $apply_snapshot($snapshots_after);
    if (is_wp_error($snapshot_result)) {
        $wpdb->query('ROLLBACK');
        return $snapshot_result;
    }
    if ($wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('inventory_sync_commit_failed', 'ثبت نهایی همگام‌سازی انبار انجام نشد.');
    }
    foreach ($changed_products as $product_id) {
        if (function_exists('zigurat_activity_log')) zigurat_activity_log('موجودی کالا با شناسهٔ '.$product_id.' از اکسل همگام شد.');
    }
    return new WP_REST_Response(array(
        'changed_product_ids' => array_values(array_unique($changed_products)),
        'unchanged_product_ids' => array_values(array_unique($unchanged_products)),
        'accepted_movement_keys' => array_values(array_unique($accepted_movement_keys)),
    ), 200, array('Cache-Control' => 'private, no-store'));
}
