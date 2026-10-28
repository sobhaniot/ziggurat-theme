<?php
if (!defined('ABSPATH')) exit;

function zigurat_activity_table() {
    global $wpdb;
    return $wpdb->prefix . 'zigurat_activity_log';
}
function zigurat_activity_install() {
    if (get_option('zigurat_activity_schema') === '1') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = zigurat_activity_table();
    dbDelta("CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        created_at datetime NOT NULL,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        actor varchar(191) NOT NULL,
        message text NOT NULL,
        PRIMARY KEY  (id),
        KEY created_at (created_at)
    ) {$wpdb->get_charset_collate()};");
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) {
        update_option('zigurat_activity_schema', '1', false);
    }
}
add_action('init', 'zigurat_activity_install', 6);

/** Only server-generated descriptions, never request bodies, credentials or form contents. */
function zigurat_activity_log($message, $key = '', $user = null) {
    $user = $user ?: wp_get_current_user();
    if ($user->exists() && !zigurat_user_can_access_manager_panel($user)) return;
    $message = sanitize_text_field($message);
    if ($message === '') return;
    $entry = array('created_at'=>current_time('mysql', true), 'user_id'=>(int)$user->ID,
        'actor'=>$user->exists() ? ($user->display_name ?: $user->user_login) : 'سیستم', 'message'=>$message);
    // Collapse repeated metadata writes to one meaningful row per object in a request.
    if ($key === '') $GLOBALS['zigurat_activity_pending'][] = $entry;
    else $GLOBALS['zigurat_activity_pending'][$key] = $entry;
}
function zigurat_activity_flush() {
    global $wpdb;
    foreach ($GLOBALS['zigurat_activity_pending'] ?? array() as $entry) {
        if ($wpdb->insert(zigurat_activity_table(), $entry, array('%s','%d','%s','%s')) === false) {
            error_log('Ziggurat activity log: unable to persist an activity entry.');
        }
    }
    $GLOBALS['zigurat_activity_pending'] = array();
}
add_action('shutdown', 'zigurat_activity_flush', 5);

function zigurat_activity_posts() {
    return array('zig_work_project'=>'پروژه', 'zig_crm_customer'=>'پیگیری مشتری',
        'partner_application'=>'همکار', 'zig_letter'=>'نامه', 'zig_price_estimate'=>'برآورد قیمت');
}
function zigurat_activity_post_saved($id, $post, $update) {
    $types = zigurat_activity_posts();
    if (!isset($types[$post->post_type]) || in_array($post->post_status, array('auto-draft','inherit'), true)) return;
    unset($GLOBALS['zigurat_activity_pending']['meta-'.$id]);
    if (!isset($GLOBALS['zigurat_activity_pending']['history-'.$id]) && !isset($GLOBALS['zigurat_activity_pending']['status-'.$id])) {
        zigurat_activity_log($types[$post->post_type] . ' «' . $post->post_title . '» ' . ($update ? 'ویرایش شد.' : 'ایجاد شد.'), 'post-'.$id);
    }
}
add_action('wp_after_insert_post', 'zigurat_activity_post_saved', 10, 3);
add_action('transition_post_status', function ($new, $old, $post) {
    $types = zigurat_activity_posts();
    if (!isset($types[$post->post_type]) || $new === $old || ($new !== 'trash' && $old !== 'trash')) return;
    unset($GLOBALS['zigurat_activity_pending']['post-'.$post->ID], $GLOBALS['zigurat_activity_pending']['meta-'.$post->ID]);
    zigurat_activity_log($types[$post->post_type].' «'.$post->post_title.'» '.($new === 'trash' ? 'بایگانی شد.' : 'از بایگانی خارج شد.'), 'status-'.$post->ID);
}, 10, 3);
function zigurat_activity_post_deleted($id, $post) {
    $types = zigurat_activity_posts();
    if (isset($types[$post->post_type])) zigurat_activity_log($types[$post->post_type].' «'.$post->post_title.'» حذف دائمی شد.', 'post-'.$id);
}
add_action('deleted_post', 'zigurat_activity_post_deleted', 10, 2);
function zigurat_activity_meta($meta_id, $id, $key, $value) {
    $post = get_post($id);
    $types = zigurat_activity_posts();
    if (!$post || !isset($types[$post->post_type])) return;
    // Exclude read markers, reminder bookkeeping and subscription secrets.
    if (!preg_match('/^_(zigurat_(work_project|crm|letter)|partner|application)/', $key)
        || preg_match('/(view|read|notified|scheduled_at|subscription|devices|token|key)/', $key)) return;
    if ($key === '_zigurat_work_project_history' && is_array($value) && $value) {
        $event = end($value);
        $stages = zigurat_workflow_project_stages();
        $actions = array('created'=>'ایجاد شد', 'archive'=>'بایگانی شد', 'restore'=>'از بایگانی خارج شد', 'merged'=>'ادغام شد');
        $description = $actions[$event['action'] ?? ''] ?? 'تغییر کرد';
        if (($event['from'] ?? '') !== ($event['to'] ?? '') && isset($stages[$event['to'] ?? ''])) {
            $description = 'از «' . ($stages[$event['from'] ?? ''] ?? 'شروع') . '» به «'.$stages[$event['to']].'» منتقل شد';
        }
        unset($GLOBALS['zigurat_activity_pending']['meta-'.$id], $GLOBALS['zigurat_activity_pending']['post-'.$id]);
        zigurat_activity_log('کارت پروژه «'.$post->post_title.'» '.$description.'.', 'history-'.$id);
        return;
    }
    if ($key === '_zigurat_crm_history' && is_array($value) && $value) {
        $event = end($value);
        $actions = array('created'=>'ایجاد شد', 'scheduled'=>'زمان پیگیری تغییر کرد', 'contact'=>'تماس/پیگیری ثبت شد', 'converted'=>'به پروژه تبدیل شد');
        zigurat_activity_log('پیگیری مشتری «'.$post->post_title.'»: '.($actions[$event['type'] ?? ''] ?? 'به‌روز شد').'.', 'crm-'.$id);
        return;
    }
    if ($key === '_zigurat_work_project_archived_at') return; // Actual trash transition is logged after success.
    if (!isset($GLOBALS['zigurat_activity_pending']['post-'.$id]) && !isset($GLOBALS['zigurat_activity_pending']['history-'.$id])) {
        zigurat_activity_log('اطلاعات '.$types[$post->post_type].' «'.$post->post_title.'» ویرایش شد.', 'meta-'.$id);
    }
}
add_action('added_post_meta', 'zigurat_activity_meta', 10, 4);
add_action('updated_post_meta', 'zigurat_activity_meta', 10, 4);
add_action('deleted_post_meta', 'zigurat_activity_meta', 10, 4);

function zigurat_activity_invoice($invoice, $existing = null) {
    if (!$invoice) return;
    $type = $invoice->document_type === 'proforma' ? 'پیش‌فاکتور' : 'فاکتور';
    $brand = $invoice->brand === 'official' ? 'رسمی' : 'غیررسمی';
    zigurat_activity_log($type.' '.$brand.' شمارهٔ '.zigurat_invoice_object_number($invoice).' «'.($invoice->project_name ?? $invoice->subject ?? '').'» '.($existing ? 'ویرایش شد.' : 'ایجاد شد.'));
}
add_action('zigurat_invoice_saved', 'zigurat_activity_invoice', 10, 2);
function zigurat_activity_invoice_event($invoice, $action) {
    if ($invoice) zigurat_activity_log(($invoice->document_type === 'proforma' ? 'پیش‌فاکتور' : 'فاکتور').' '.($invoice->brand === 'official' ? 'رسمی' : 'غیررسمی').' شمارهٔ '.zigurat_invoice_object_number($invoice).' '.$action.'.');
}
function zigurat_activity_option($option) {
    if (!zigurat_is_manager() || !preg_match('/^(zigurat_|theme_mods_)/', $option)
        || preg_match('/(schema|version|stats|views|vapid|keys|transient|cache)/', $option)) return;
    $names = array('zigurat_invoice_defaults'=>'فاکتورها', 'zigurat_letter_settings'=>'نامه‌نگاری',
        'zigurat_lightbox_pricing'=>'قیمت‌گذاری لایت‌باکس', 'zigurat_composite_pricing'=>'قیمت‌گذاری کامپوزیت',
        'zigurat_letter_pricing'=>'قیمت‌گذاری حروف', 'zigurat_flexi_pricing'=>'قیمت‌گذاری فلکسی');
    $label = $names[$option] ?? (strpos($option, 'theme_mods_') === 0 ? 'قالب سایت' : 'پنل مدیران');
    zigurat_activity_log('تنظیمات «'.$label.'» تغییر کرد.', 'option-'.$option);
}
add_action('updated_option', 'zigurat_activity_option', 10, 1);
add_action('added_option', 'zigurat_activity_option', 10, 1);
add_action('deleted_option', 'zigurat_activity_option', 10, 1);
add_action('wp_login', function ($login, $user) { zigurat_activity_log('وارد پنل شد.', '', $user); }, 10, 2);
add_action('wp_logout', function ($id) { $user = get_userdata($id); if ($user) zigurat_activity_log('از حساب خارج شد.', '', $user); }, 10, 1);

function zigurat_activity_list($page = 1) {
    if (!current_user_can('manage_options')) return array('items'=>array(), 'more'=>false, 'page'=>1, 'pages'=>1, 'total'=>0);
    global $wpdb;
    $total = (int)$wpdb->get_var('SELECT COUNT(*) FROM '.zigurat_activity_table());
    $pages = max(1, (int)ceil($total/50));
    $page = min($pages, max(1, absint($page)));
    $items = $wpdb->get_results($wpdb->prepare('SELECT * FROM '.zigurat_activity_table().' ORDER BY id DESC LIMIT 50 OFFSET %d', ($page-1)*50));
    return array('items'=>$items, 'more'=>$page<$pages, 'page'=>$page, 'pages'=>$pages, 'total'=>$total);
}

/** CSV text must not be interpreted as a spreadsheet formula when opened in Excel. */
function zigurat_activity_csv_text($value) {
    $value = (string)$value;
    return preg_match('/^(?:\s*[=+@\-]|[\t\r\n])/u', $value) ? "'".$value : $value;
}
function zigurat_activity_export_range($from, $to) {
    $bounds = array('from'=>null, 'until'=>null);
    foreach (array('from'=>$from, 'until'=>$to) as $key=>$raw) {
        if (!is_scalar($raw)) return new WP_Error('invalid_range', 'بازهٔ زمانی معتبر نیست.');
        $raw = trim(strtr((string)$raw, array_combine(preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789'))));
        if ($raw === '') continue;
        if (!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $raw, $m) || (int)$m[1]<1200 || (int)$m[1]>1600) return new WP_Error('invalid_range', 'تاریخ‌ها را به‌صورت شمسی از تقویم انتخاب کنید.');
        $j = array((int)$m[1], (int)$m[2], (int)$m[3]);
        if ($j[1]<1 || $j[1]>12 || $j[2]<1 || $j[2]>31) return new WP_Error('invalid_range', 'تاریخ انتخاب‌شده معتبر نیست.');
        $g = zigurat_crm_jalali_to_gregorian(...$j);
        if (!checkdate($g[1], $g[2], $g[0]) || zigurat_inventory_gregorian_to_jalali(...$g) !== $j) return new WP_Error('invalid_range', 'تاریخ انتخاب‌شده معتبر نیست.');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('%04d-%02d-%02d', ...$g), new DateTimeZone('Asia/Tehran'));
        if ($key === 'until') $date = $date->modify('+1 day');
        $bounds[$key] = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    if ($bounds['from'] && $bounds['until'] && $bounds['from'] >= $bounds['until']) return new WP_Error('invalid_range', 'تاریخ شروع نباید بعد از تاریخ پایان باشد.');
    return $bounds;
}
function zigurat_activity_write_csv($stream, $range = array()) {
    global $wpdb;
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, array('شناسه','تاریخ و ساعت (شمسی، تهران)','کاربر','شرح رویداد'), ',', '"', '');
    // Freeze the upper ID and use bounded keyset batches, not an unbounded fetch.
    $cursor = (int)$wpdb->get_var('SELECT MAX(id) FROM '.zigurat_activity_table());
    $where = ''; $values = array();
    if (!empty($range['from'])) { $where .= ' AND created_at >= %s'; $values[]=$range['from']; }
    if (!empty($range['until'])) { $where .= ' AND created_at < %s'; $values[]=$range['until']; }
    while ($cursor > 0) {
        $rows = $wpdb->get_results($wpdb->prepare('SELECT id,created_at,actor,message FROM '.zigurat_activity_table().' WHERE id <= %d'.$where.' ORDER BY id DESC LIMIT 500', array_merge(array($cursor), $values)));
        if (!$rows) break;
        foreach ($rows as $row) {
            fputcsv($stream, array((int)$row->id,
                zigurat_inventory_format_jalali_datetime($row->created_at),
                zigurat_activity_csv_text($row->actor), zigurat_activity_csv_text($row->message)), ',', '"', '');
        }
        $cursor = (int)end($rows)->id - 1;
    }
}
function zigurat_activity_export() {
    if (!current_user_can('manage_options')) wp_die('فقط مدیرکل اجازهٔ خروجی گرفتن از گزارش فعالیت‌ها را دارد.', '', array('response'=>403));
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'zigurat_activity_export')) {
        wp_die('اعتبار درخواست تمام شده است؛ صفحه را تازه کنید.', '', array('response'=>403));
    }
    if (get_option('zigurat_activity_schema') !== '1') wp_die('گزارش فعالیت‌ها هنوز آماده نشده است.', '', array('response'=>503));
    $range = zigurat_activity_export_range(wp_unslash($_GET['date_from'] ?? ''), wp_unslash($_GET['date_to'] ?? ''));
    if (is_wp_error($range)) wp_die($range->get_error_message(), '', array('response'=>400));
    while (ob_get_level()) ob_end_clean();
    nocache_headers();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="zigurat-activity-'.wp_date('Ymd-His').'.csv"');
    header('X-Content-Type-Options: nosniff');
    $stream = fopen('php://output', 'wb');
    zigurat_activity_write_csv($stream, $range);
    fclose($stream);
    exit;
}
add_action('admin_post_zigurat_activity_export', 'zigurat_activity_export');
add_action('admin_post_nopriv_zigurat_activity_export', 'zigurat_activity_export');
