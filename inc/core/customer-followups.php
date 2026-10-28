<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_crm_register_post_type()
{
    register_post_type('zig_crm_customer', array(
        'labels' => array('name' => 'پیگیری مشتریان', 'singular_name' => 'مشتری'),
        'public' => false,
        'show_ui' => false,
        'show_in_rest' => false,
        'supports' => array('title', 'author'),
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ));
}
add_action('init', 'zigurat_crm_register_post_type', 12);

function zigurat_crm_url($args = array())
{
    return add_query_arg(array_merge(array('manager-section' => 'customers'), $args), zigurat_manager_login_url());
}

function zigurat_crm_statuses()
{
    return array(
        'new' => 'تماس جدید',
        'contacting' => 'در حال مذاکره',
        'waiting' => 'منتظر پاسخ',
        'won' => 'تبدیل‌شده به پروژه',
        'lost' => 'ناموفق',
    );
}

function zigurat_crm_priorities()
{
    return array('normal' => 'عادی', 'important' => 'مهم', 'urgent' => 'فوری');
}

function zigurat_crm_active_statuses()
{
    return array('new', 'contacting', 'waiting');
}

function zigurat_crm_timezone()
{
    $timezone_string = (string) get_option('timezone_string');
    $gmt_offset = (float) get_option('gmt_offset', 0);
    if ($timezone_string === '' && $gmt_offset === 0.0) {
        return new DateTimeZone('Asia/Tehran');
    }
    return wp_timezone();
}

function zigurat_crm_legacy_tehran_timestamp($timestamp)
{
    $timestamp = (int) $timestamp;
    $implicit_utc = (string) get_option('timezone_string') === '' && (float) get_option('gmt_offset', 0) === 0.0;
    if (!$timestamp || !$implicit_utc) {
        return $timestamp;
    }
    $wall_time = (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $corrected = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $wall_time, new DateTimeZone('Asia/Tehran'));
    return $corrected ? $corrected->getTimestamp() : $timestamp;
}

function zigurat_crm_managers()
{
    $users = get_users(array(
        'role__in' => array('administrator', 'contributor'),
        'orderby' => 'display_name',
        'order' => 'ASC',
        'fields' => array('ID', 'display_name', 'user_login'),
    ));
    return array_map(static function ($user) {
        $name = trim((string) $user->display_name);
        return array('id' => (int) $user->ID, 'name' => $name !== '' ? $name : (string) $user->user_login);
    }, $users);
}

function zigurat_crm_jalali_to_gregorian($jy, $jm, $jd)
{
    if (function_exists('zigurat_views_jalali_to_gregorian')) {
        return zigurat_views_jalali_to_gregorian((int) $jy, (int) $jm, (int) $jd);
    }
    $jy = (int) $jy + 1595;
    $days = -355668 + (365 * $jy) + ((int) ($jy / 33) * 8) + (int) ((($jy % 33) + 3) / 4) + (int) $jd;
    $days += $jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186;
    $gy = 400 * (int) ($days / 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * (int) (--$days / 36524);
        $days %= 36524;
        if ($days >= 365) {
            $days++;
        }
    }
    $gy += 4 * (int) ($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += (int) (($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $month_days = array(0, 31, (($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    for ($gm = 1; $gm <= 12 && $gd > $month_days[$gm]; $gm++) {
        $gd -= $month_days[$gm];
    }
    return array($gy, $gm, $gd);
}

function zigurat_crm_jalali_parts($timestamp)
{
    $date = (new DateTimeImmutable('@' . (int) $timestamp))->setTimezone(zigurat_crm_timezone());
    if (function_exists('zigurat_inventory_gregorian_to_jalali')) {
        return zigurat_inventory_gregorian_to_jalali((int) $date->format('Y'), (int) $date->format('m'), (int) $date->format('d'));
    }
    return array((int) $date->format('Y'), (int) $date->format('m'), (int) $date->format('d'));
}

function zigurat_crm_parse_datetime($value)
{
    $value = sanitize_text_field((string) $value);
    if (function_exists('zigurat_invoice_normalize_digits')) {
        $value = zigurat_invoice_normalize_digits($value);
    } else {
        $value = strtr($value, array(
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ));
    }
    if ($value === '') {
        return 0;
    }
    $value = trim(str_replace(array('T', '-', '.', '٫'), array(' ', '/', '/', '/'), $value));
    $value = preg_replace('/\s+/u', ' ', $value);
    if (!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})(?:\s+(\d{1,2}):(\d{1,2}))?$/', $value, $matches)) {
        return 0;
    }
    $year = (int) $matches[1];
    $month = (int) $matches[2];
    $day = (int) $matches[3];
    $hour = isset($matches[4]) ? (int) $matches[4] : 9;
    $minute = isset($matches[5]) ? (int) $matches[5] : 0;
    if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || $hour > 23 || $minute > 59) {
        return 0;
    }
    if ($year < 1700) {
        [$gregorian_year, $gregorian_month, $gregorian_day] = zigurat_crm_jalali_to_gregorian($year, $month, $day);
        if (function_exists('zigurat_inventory_gregorian_to_jalali')) {
            $roundtrip = zigurat_inventory_gregorian_to_jalali($gregorian_year, $gregorian_month, $gregorian_day);
            if ($roundtrip !== array($year, $month, $day)) {
                return 0;
            }
        }
    } else {
        $gregorian_year = $year;
        $gregorian_month = $month;
        $gregorian_day = $day;
    }
    if (!checkdate($gregorian_month, $gregorian_day, $gregorian_year)) {
        return 0;
    }
    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i',
        sprintf('%04d-%02d-%02d %02d:%02d', $gregorian_year, $gregorian_month, $gregorian_day, $hour, $minute),
        zigurat_crm_timezone()
    );
    return $date ? $date->getTimestamp() : 0;
}

function zigurat_crm_datetime_input($timestamp)
{
    if (!$timestamp) {
        return '';
    }
    $date = (new DateTimeImmutable('@' . (int) $timestamp))->setTimezone(zigurat_crm_timezone());
    $jalali = zigurat_crm_jalali_parts($timestamp);
    return sprintf('%04d/%02d/%02d %s', $jalali[0], $jalali[1], $jalali[2], $date->format('H:i'));
}

function zigurat_crm_datetime_label($timestamp)
{
    if (!$timestamp) {
        return 'بدون زمان';
    }
    $date = (new DateTimeImmutable('@' . (int) $timestamp))->setTimezone(zigurat_crm_timezone());
    $jalali = zigurat_crm_jalali_parts($timestamp);
    return sprintf('%04d/%02d/%02d ساعت %s', $jalali[0], $jalali[1], $jalali[2], $date->format('H:i'));
}

function zigurat_crm_history($customer_id)
{
    $history = get_post_meta($customer_id, '_zigurat_crm_history', true);
    return is_array($history) ? array_values($history) : array();
}

function zigurat_crm_add_history($customer_id, $type, $note = '')
{
    $history = zigurat_crm_history($customer_id);
    $user = wp_get_current_user();
    $history[] = array(
        'type' => sanitize_key($type),
        'note' => sanitize_textarea_field($note),
        'time' => current_time('mysql'),
        'user' => $user->exists() ? ($user->display_name ?: $user->user_login) : 'سیستم',
    );
    update_post_meta($customer_id, '_zigurat_crm_history', array_slice($history, -100));
    update_post_meta($customer_id, '_zigurat_crm_last_activity', time());
}

function zigurat_crm_record($post, $full_history = false)
{
    $post = is_numeric($post) ? get_post((int) $post) : $post;
    if (!$post || $post->post_type !== 'zig_crm_customer' || $post->post_status !== 'private') {
        return null;
    }
    $status = sanitize_key((string) get_post_meta($post->ID, '_zigurat_crm_status', true));
    $priority = sanitize_key((string) get_post_meta($post->ID, '_zigurat_crm_priority', true));
    $next_at = (int) get_post_meta($post->ID, '_zigurat_crm_next_at', true);
    $next_timezone = (string) get_post_meta($post->ID, '_zigurat_crm_timezone', true);
    if ($next_at && $next_timezone === '') {
        $next_at = zigurat_crm_legacy_tehran_timestamp($next_at);
    }
    $owner_id = absint(get_post_meta($post->ID, '_zigurat_crm_owner', true));
    $owner = $owner_id ? get_userdata($owner_id) : null;
    $now = time();
    $day_end = (new DateTime('tomorrow', zigurat_crm_timezone()))->getTimestamp() - 1;
    $due_state = 'none';
    if ($next_at && in_array($status, zigurat_crm_active_statuses(), true)) {
        $due_state = $next_at < $now ? 'overdue' : ($next_at <= $day_end ? 'today' : 'upcoming');
    }
    $history = array_reverse(zigurat_crm_history($post->ID));
    $last_activity = max((int)get_post_meta($post->ID, '_zigurat_crm_last_activity', true), !empty($post->post_modified_gmt) ? (strtotime($post->post_modified_gmt . ' UTC') ?: 0) : 0);
    foreach ($history as $entry) {
        $activity_date = !empty($entry['time']) ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$entry['time'], zigurat_crm_timezone()) : false;
        if ($activity_date) $last_activity = max($last_activity, $activity_date->getTimestamp());
    }
    return array(
        'id' => (int) $post->ID,
        'last_activity' => $last_activity,
        'name' => (string) get_post_meta($post->ID, '_zigurat_crm_name', true),
        'prefix' => (string) get_post_meta($post->ID, '_zigurat_crm_prefix', true),
        'company' => (string) get_post_meta($post->ID, '_zigurat_crm_company', true),
        'phone' => (string) get_post_meta($post->ID, '_zigurat_crm_phone', true),
        'topic' => (string) get_post_meta($post->ID, '_zigurat_crm_topic', true),
        'status' => isset(zigurat_crm_statuses()[$status]) ? $status : 'new',
        'status_label' => zigurat_crm_statuses()[$status] ?? zigurat_crm_statuses()['new'],
        'priority' => isset(zigurat_crm_priorities()[$priority]) ? $priority : 'normal',
        'priority_label' => zigurat_crm_priorities()[$priority] ?? zigurat_crm_priorities()['normal'],
        'owner_id' => $owner_id,
        'owner_name' => $owner ? ($owner->display_name ?: $owner->user_login) : 'همه مدیران',
        'next_at' => $next_at,
        'next_input' => zigurat_crm_datetime_input($next_at),
        'repeat_daily' => (bool)get_post_meta($post->ID, '_zigurat_crm_repeat_daily', true),
        'next_label' => zigurat_crm_datetime_label($next_at),
        'due_state' => $due_state,
        'notes' => (string) get_post_meta($post->ID, '_zigurat_crm_notes', true),
        'project_id' => absint(get_post_meta($post->ID, '_zigurat_crm_project_id', true)),
        'history' => array_map(static function ($item) {
            $history_date = !empty($item['time'])
                ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $item['time'], zigurat_crm_timezone())
                : false;
            $timestamp = $history_date ? $history_date->getTimestamp() : 0;
            return array(
                'type' => sanitize_key($item['type'] ?? ''),
                'note' => (string) ($item['note'] ?? ''),
                'user' => (string) ($item['user'] ?? ''),
                'time' => $timestamp ? zigurat_crm_datetime_input($timestamp) : '',
            );
        }, $full_history ? $history : array_slice($history, 0, 20)),
    );
}

function zigurat_crm_records()
{
    static $cached_records = null;
    if (is_array($cached_records)) {
        return $cached_records;
    }
    $query = new WP_Query(array(
        'post_type' => 'zig_crm_customer',
        'post_status' => 'private',
        'posts_per_page' => 500,
        'orderby' => 'meta_value_num',
        'meta_key' => '_zigurat_crm_next_at',
        'order' => 'ASC',
        'no_found_rows' => true,
    ));
    $records = array_values(array_filter(array_map('zigurat_crm_record', $query->posts)));
    usort($records, static function ($first, $second) {
        $rank = array('overdue' => 0, 'today' => 1, 'upcoming' => 2, 'none' => 3);
        $state_compare = ($rank[$first['due_state']] ?? 9) <=> ($rank[$second['due_state']] ?? 9);
        if ($state_compare !== 0) {
            return $state_compare;
        }
        return ($first['next_at'] ?: PHP_INT_MAX) <=> ($second['next_at'] ?: PHP_INT_MAX);
    });
    $cached_records = $records;
    return $cached_records;
}

function zigurat_crm_summary($records = null)
{
    $records = is_array($records) ? $records : zigurat_crm_records();
    $summary = array('overdue' => 0, 'today' => 0, 'upcoming' => 0, 'active' => 0);
    foreach ($records as $record) {
        if (in_array($record['status'], zigurat_crm_active_statuses(), true)) {
            $summary['active']++;
        }
        if (isset($summary[$record['due_state']])) {
            $summary[$record['due_state']]++;
        }
    }
    return $summary;
}

function zigurat_crm_due_count($user_id = 0)
{
    $user_id = $user_id ?: get_current_user_id();
    static $counts = array();
    if (isset($counts[$user_id])) {
        return $counts[$user_id];
    }
    $day_end = (new DateTime('tomorrow', wp_timezone()))->getTimestamp() - 1;
    $meta_query = array(
        'relation' => 'AND',
        array('key' => '_zigurat_crm_status', 'value' => zigurat_crm_active_statuses(), 'compare' => 'IN'),
        array('key' => '_zigurat_crm_next_at', 'value' => array(1, $day_end), 'compare' => 'BETWEEN', 'type' => 'NUMERIC'),
    );
    if (!user_can($user_id, 'manage_options')) {
        $meta_query[] = array(
            'relation' => 'OR',
            array('key' => '_zigurat_crm_owner', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC'),
            array('key' => '_zigurat_crm_owner', 'value' => (int) $user_id, 'compare' => '=', 'type' => 'NUMERIC'),
        );
    }
    $query = new WP_Query(array(
        'post_type' => 'zig_crm_customer',
        'post_status' => 'private',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => $meta_query,
        'no_found_rows' => false,
    ));
    $counts[$user_id] = (int) $query->found_posts;
    return $counts[$user_id];
}

function zigurat_crm_schedule_reminder($customer_id, $next_at)
{
    $customer_id = absint($customer_id);
    $old_at = (int) get_post_meta($customer_id, '_zigurat_crm_scheduled_at', true);
    if ($old_at === (int)$next_at) return;
    if ($old_at) {
        wp_clear_scheduled_hook('zigurat_crm_send_reminder', array($customer_id, $old_at));
    }
    delete_post_meta($customer_id, '_zigurat_crm_notified_at');
    delete_post_meta($customer_id, '_zigurat_crm_devices');
    delete_post_meta($customer_id, '_zigurat_crm_scheduled_at');
    $next_at = (int) $next_at;
    if (!$next_at) {
        return;
    }
    $send_at = max(time() + 10, $next_at);
    wp_schedule_single_event($send_at, 'zigurat_crm_send_reminder', array($customer_id, $next_at));
    update_post_meta($customer_id, '_zigurat_crm_scheduled_at', $next_at);
}

function zigurat_crm_ensure_legacy_reminder_schedules()
{
    foreach (zigurat_crm_records() as $record) {
        if (!$record['next_at'] || !in_array($record['status'], zigurat_crm_active_statuses(), true)) {
            continue;
        }
        if ((string) get_post_meta($record['id'], '_zigurat_crm_timezone', true) !== '') {
            continue;
        }
        if ((int) get_post_meta($record['id'], '_zigurat_crm_notified_at', true) === (int) $record['next_at']) {
            continue;
        }
        $args = array((int) $record['id'], (int) $record['next_at']);
        if (!wp_next_scheduled('zigurat_crm_send_reminder', $args)) {
            wp_schedule_single_event(max(time() + 10, (int) $record['next_at']), 'zigurat_crm_send_reminder', $args);
        }
    }
}
add_action('init', 'zigurat_crm_ensure_legacy_reminder_schedules', 25);

function zigurat_crm_save_from_request($data)
{
    if (!zigurat_is_manager()) {
        return new WP_Error('forbidden', 'دسترسی مجاز نیست.');
    }
    $customer_id = absint($data['customer_id'] ?? 0);
    $existing = $customer_id ? get_post($customer_id) : null;
    if ($customer_id && (!$existing || $existing->post_type !== 'zig_crm_customer' || $existing->post_status !== 'private')) {
        return new WP_Error('not_found', 'مشتری پیدا نشد.');
    }
    $name = sanitize_text_field(wp_unslash((string) ($data['name'] ?? '')));
    $company = sanitize_text_field(wp_unslash((string) ($data['company'] ?? '')));
    $phone = sanitize_text_field(wp_unslash((string) ($data['phone'] ?? '')));
    $topic = sanitize_text_field(wp_unslash((string) ($data['topic'] ?? '')));
    if ($name === '' && $company === '') {
        return new WP_Error('missing_name', 'نام مشتری یا شرکت را وارد کنید.');
    }
    if ($phone === '') {
        return new WP_Error('missing_phone', 'شماره تماس را وارد کنید.');
    }
    $status = sanitize_key(wp_unslash((string) ($data['status'] ?? 'new')));
    $priority = sanitize_key(wp_unslash((string) ($data['priority'] ?? 'normal')));
    $status = isset(zigurat_crm_statuses()[$status]) ? $status : 'new';
    $convert_to_project = $status === 'won';
    if ($convert_to_project && (!$existing || !get_post_meta($customer_id, '_zigurat_crm_project_id', true))) {
        $status = 'waiting'; // Mark won only after successful project creation.
    }
    $priority = isset(zigurat_crm_priorities()[$priority]) ? $priority : 'normal';
    $owner_id = absint($data['owner_id'] ?? 0);
    if ($owner_id) {
        $owner_user = get_userdata($owner_id);
        if (!$owner_user || !zigurat_user_can_access_manager_panel($owner_user)) {
            $owner_id = 0;
        }
    }
    $next_at_raw = wp_unslash((string) ($data['next_at'] ?? ''));
    $next_at = zigurat_crm_parse_datetime($next_at_raw);
    if (trim($next_at_raw) !== '' && !$next_at) {
        return new WP_Error('invalid_next_at', 'تاریخ پیگیری را به‌صورت شمسی وارد کنید؛ مثلاً ۱۴۰۵/۰۷/۲۱ ۱۴:۳۰.');
    }
    $title = trim(($company !== '' ? $company : $name) . ($topic !== '' ? ' — ' . $topic : ''));
    $post_data = array('post_type' => 'zig_crm_customer', 'post_status' => 'private', 'post_title' => $title);
    if ($existing) {
        $post_data['ID'] = $customer_id;
        $result = wp_update_post($post_data, true);
    } else {
        $post_data['post_author'] = get_current_user_id();
        $result = wp_insert_post($post_data, true);
    }
    if (is_wp_error($result)) {
        return $result;
    }
    $old_next = $existing ? (int) get_post_meta($result, '_zigurat_crm_next_at', true) : 0;
    $old_status = $existing ? (string)get_post_meta($result, '_zigurat_crm_status', true) : '';
    update_post_meta($result, '_zigurat_crm_name', $name);
    update_post_meta($result, '_zigurat_crm_prefix', sanitize_text_field(wp_unslash((string) ($data['prefix'] ?? ''))));
    update_post_meta($result, '_zigurat_crm_company', $company);
    update_post_meta($result, '_zigurat_crm_phone', $phone);
    update_post_meta($result, '_zigurat_crm_topic', $topic);
    update_post_meta($result, '_zigurat_crm_status', $status);
    update_post_meta($result, '_zigurat_crm_priority', $priority);
    update_post_meta($result, '_zigurat_crm_owner', $owner_id);
    update_post_meta($result, '_zigurat_crm_next_at', $next_at);
    if (!$existing || isset($data['repeat_daily_present'])) update_post_meta($result, '_zigurat_crm_repeat_daily', empty($data['repeat_daily']) ? 0 : 1);
    update_post_meta($result, '_zigurat_crm_timezone', 'Asia/Tehran');
    update_post_meta($result, '_zigurat_crm_notes', sanitize_textarea_field(wp_unslash((string) ($data['notes'] ?? ''))));
    if (!$existing) {
        zigurat_crm_add_history($result, 'created', 'مشتری برای پیگیری ثبت شد.');
    } elseif ($old_next !== $next_at) {
        zigurat_crm_add_history($result, 'scheduled', $next_at ? 'پیگیری بعدی: ' . zigurat_crm_datetime_label($next_at) : 'زمان پیگیری حذف شد.');
    }
    zigurat_crm_schedule_reminder($result, in_array($status, zigurat_crm_active_statuses(), true) ? $next_at : 0);
    if ($existing && in_array($old_status, array('won', 'lost'), true) && in_array($status, zigurat_crm_active_statuses(), true)) {
        zigurat_crm_add_history($result, 'reopened', 'پیگیری مشتری دوباره آغاز شد؛ وضعیت جدید: ' . zigurat_crm_statuses()[$status]);
    }
    if ($convert_to_project) {
        $converted = zigurat_crm_convert_to_project($result);
        if (is_wp_error($converted)) return $converted;
        $converted['record']['_project_created'] = $converted['created'];
        return $converted['record'];
    }
    return zigurat_crm_record($result);
}

function zigurat_crm_ajax_guard()
{
    check_ajax_referer('zigurat_crm', 'nonce');
    if (!zigurat_is_manager()) {
        wp_send_json_error(array('message' => 'دسترسی مجاز نیست.'), 403);
    }
}

function zigurat_ajax_crm_list()
{
    zigurat_crm_ajax_guard();
    $records = zigurat_crm_records();
    wp_send_json_success(array('records' => $records, 'summary' => zigurat_crm_summary($records)));
}
add_action('wp_ajax_zigurat_crm_list', 'zigurat_ajax_crm_list');

/** Paged directory: filter before LIMIT, never load all customer records. */
function zigurat_crm_backfill_activity() {
    global $wpdb;
    $ids = $wpdb->get_col("SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type='zig_crm_customer' AND p.post_status='private' AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id=p.ID AND m.meta_key='_zigurat_crm_last_activity') ORDER BY p.ID LIMIT 100");
    if ($ids) _prime_post_caches($ids, false, true);
    foreach ($ids as $id) {
        $record = zigurat_crm_record($id);
        if ($record) add_post_meta($id, '_zigurat_crm_last_activity', $record['last_activity'], true);
    }
    if (count($ids) === 100 && !wp_next_scheduled('zigurat_crm_backfill_activity')) wp_schedule_single_event(time()+10, 'zigurat_crm_backfill_activity');
}
add_action('zigurat_crm_backfill_activity', 'zigurat_crm_backfill_activity');

function zigurat_crm_directory_page($data) {
    if (!zigurat_is_manager()) return new WP_Error('forbidden', 'دسترسی مجاز نیست.');
    zigurat_crm_backfill_activity();
    global $wpdb;
    $page = max(1, absint($data['page'] ?? 1));
    $term = sanitize_text_field(wp_unslash((string)($data['search'] ?? '')));
    $status = sanitize_key($data['status'] ?? '');
    $due = sanitize_key($data['due'] ?? 'all');
    $where = "p.post_type = 'zig_crm_customer' AND p.post_status = 'private'";
    if ($term !== '') {
        $like = '%' . $wpdb->esc_like($term) . '%';
        $where .= $wpdb->prepare(" AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} s WHERE s.post_id=p.ID AND s.meta_key IN ('_zigurat_crm_name','_zigurat_crm_company','_zigurat_crm_phone','_zigurat_crm_topic') AND s.meta_value LIKE %s)", $like);
    }
    if ($status !== '' && isset(zigurat_crm_statuses()[$status])) {
        $where .= $wpdb->prepare(" AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} st WHERE st.post_id=p.ID AND st.meta_key='_zigurat_crm_status' AND st.meta_value=%s)", $status);
    }
    if (in_array($due, array('active','overdue','today','upcoming'), true)) {
        $where .= " AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} a WHERE a.post_id=p.ID AND a.meta_key='_zigurat_crm_status' AND a.meta_value IN ('new','contacting','waiting'))";
        $now = time();
        $end = (new DateTimeImmutable('tomorrow', zigurat_crm_timezone()))->getTimestamp() - 1;
        if ($due === 'overdue') $condition = $wpdb->prepare('CAST(d.meta_value AS UNSIGNED)>0 AND CAST(d.meta_value AS UNSIGNED)<%d', $now);
        elseif ($due === 'today') $condition = $wpdb->prepare('CAST(d.meta_value AS UNSIGNED)>=%d AND CAST(d.meta_value AS UNSIGNED)<=%d', $now, $end);
        elseif ($due === 'upcoming') $condition = $wpdb->prepare('CAST(d.meta_value AS UNSIGNED)>%d', $end);
        if ($due !== 'active') $where .= " AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} d WHERE d.post_id=p.ID AND d.meta_key='_zigurat_crm_next_at' AND $condition)";
    }
    $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p WHERE $where");
    $pages = max(1, (int)ceil($total / 50));
    $page = min($page, $pages);
    $ids = $wpdb->get_col($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p WHERE $where ORDER BY GREATEST(COALESCE((SELECT MAX(CAST(v.meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} v WHERE v.post_id=p.ID AND v.meta_key='_zigurat_crm_last_activity'),0),COALESCE(UNIX_TIMESTAMP(p.post_modified_gmt),0)) DESC,p.ID DESC LIMIT 50 OFFSET %d", ($page-1)*50));
    if ($ids) _prime_post_caches($ids, false, true);
    return array('records'=>array_values(array_filter(array_map('zigurat_crm_record', $ids))), 'page'=>$page, 'pages'=>$pages, 'total'=>$total);
}
function zigurat_ajax_crm_directory() {
    zigurat_crm_ajax_guard();
    nocache_headers();
    $result = zigurat_crm_directory_page($_POST);
    if (is_wp_error($result)) wp_send_json_error(array('message'=>$result->get_error_message()), 403);
    wp_send_json_success($result);
}
add_action('wp_ajax_zigurat_crm_directory', 'zigurat_ajax_crm_directory');

function zigurat_crm_contact_suggestions($data) {
    if (!zigurat_is_manager()) return new WP_Error('forbidden', 'دسترسی مجاز نیست.');
    global $wpdb;
    $term = sanitize_text_field(wp_unslash((string)($data['term'] ?? '')));
    $field = ($data['field'] ?? '') === 'company' ? 'company' : 'name';
    if (mb_strlen($term) < 2) return array();
    $ids = $wpdb->get_col($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type='zig_crm_customer' AND p.post_status='private' AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id=p.ID AND m.meta_key=%s AND m.meta_value LIKE %s) ORDER BY p.post_modified_gmt DESC,p.ID DESC LIMIT 50", '_zigurat_crm_'.$field, '%'.$wpdb->esc_like($term).'%'));
    if ($ids) _prime_post_caches($ids, false, true);
    $items = array(); $seen = array();
    foreach ($ids as $id) {
        $contact = array();
        foreach (array('prefix','name','company','phone') as $key) $contact[$key] = (string)get_post_meta($id, '_zigurat_crm_'.$key, true);
        $identity = wp_json_encode(array($contact['name'], $contact['company'], $contact['phone']));
        if (isset($seen[$identity])) continue;
        $seen[$identity] = true;
        $items[] = $contact;
        if (count($items) >= 10) break;
    }
    return $items;
}
function zigurat_ajax_crm_contacts() {
    zigurat_crm_ajax_guard();
    nocache_headers();
    $contacts = zigurat_crm_contact_suggestions($_POST);
    if (is_wp_error($contacts)) wp_send_json_error(array('message'=>$contacts->get_error_message()),403);
    wp_send_json_success(array('contacts'=>$contacts));
}
add_action('wp_ajax_zigurat_crm_contacts', 'zigurat_ajax_crm_contacts');

function zigurat_ajax_crm_profile() {
    zigurat_crm_ajax_guard();
    $post = get_post(absint($_POST['customer_id'] ?? 0));
    if (!$post || $post->post_type !== 'zig_crm_customer' || $post->post_status !== 'private') {
        wp_send_json_error(array('message' => 'مشتری پیدا نشد.'), 404);
    }
    nocache_headers();
    wp_send_json_success(array('record' => zigurat_crm_record($post, true)));
}
add_action('wp_ajax_zigurat_crm_profile', 'zigurat_ajax_crm_profile');

function zigurat_ajax_crm_save()
{
    zigurat_crm_ajax_guard();
    $record = zigurat_crm_save_from_request($_POST);
    if (is_wp_error($record)) {
        wp_send_json_error(array('message' => $record->get_error_message()), 400);
    }
    wp_send_json_success(array('message' => empty($_POST['customer_id']) ? 'مشتری ثبت شد.' : 'اطلاعات مشتری ذخیره شد.', 'record' => $record, 'project_id'=>!empty($record['_project_created']) ? $record['project_id'] : 0));
}
add_action('wp_ajax_zigurat_crm_save', 'zigurat_ajax_crm_save');

function zigurat_ajax_crm_log()
{
    zigurat_crm_ajax_guard();
    $customer_id = absint($_POST['customer_id'] ?? 0);
    $record = zigurat_crm_record($customer_id);
    if (!$record) {
        wp_send_json_error(array('message' => 'مشتری پیدا نشد.'), 404);
    }
    $note = sanitize_textarea_field(wp_unslash((string) ($_POST['note'] ?? '')));
    if ($note === '') {
        wp_send_json_error(array('message' => 'نتیجه تماس را بنویسید.'), 400);
    }
    $status = sanitize_key(wp_unslash((string) ($_POST['status'] ?? $record['status'])));
    $status = isset(zigurat_crm_statuses()[$status]) ? $status : $record['status'];
    $next_at_raw = wp_unslash((string) ($_POST['next_at'] ?? ''));
    $next_at = zigurat_crm_parse_datetime($next_at_raw);
    if (trim($next_at_raw) !== '' && !$next_at) {
        wp_send_json_error(array('message' => 'تاریخ پیگیری را به‌صورت شمسی وارد کنید؛ مثلاً ۱۴۰۵/۰۷/۲۱ ۱۴:۳۰.'), 400);
    }
    update_post_meta($customer_id, '_zigurat_crm_status', $status);
    update_post_meta($customer_id, '_zigurat_crm_next_at', $next_at);
    if (isset($_POST['repeat_daily_present'])) update_post_meta($customer_id, '_zigurat_crm_repeat_daily', empty($_POST['repeat_daily']) ? 0 : 1);
    update_post_meta($customer_id, '_zigurat_crm_timezone', 'Asia/Tehran');
    zigurat_crm_add_history($customer_id, 'contact', $note);
    zigurat_crm_schedule_reminder($customer_id, in_array($status, zigurat_crm_active_statuses(), true) ? $next_at : 0);
    wp_send_json_success(array('message' => 'نتیجه تماس ثبت شد.', 'record' => zigurat_crm_record($customer_id)));
}
add_action('wp_ajax_zigurat_crm_log', 'zigurat_ajax_crm_log');

function zigurat_ajax_crm_delete()
{
    zigurat_crm_ajax_guard();
    $customer_id = absint($_POST['customer_id'] ?? 0);
    $record = zigurat_crm_record($customer_id);
    if (!$record || !wp_delete_post($customer_id, true)) {
        wp_send_json_error(array('message' => 'حذف مشتری انجام نشد.'), 400);
    }
    wp_send_json_success(array('message' => 'مشتری از فهرست پیگیری حذف شد.'));
}
add_action('wp_ajax_zigurat_crm_delete', 'zigurat_ajax_crm_delete');

function zigurat_crm_convert_to_project($customer_id)
{
    if (!zigurat_is_manager()) return new WP_Error('forbidden', 'دسترسی مجاز نیست.');
    global $wpdb;
    $customer_id = absint($customer_id);
    $lock = 'zig_crm_' . md5($wpdb->prefix . $customer_id);
    if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) {
        return new WP_Error('busy', 'تبدیل این پیگیری در حال انجام است؛ کمی بعد دوباره تلاش کنید.');
    }
    try {
        wp_cache_delete($customer_id, 'post_meta');
    $record = zigurat_crm_record($customer_id);
    if (!$record) {
        return new WP_Error('not_found', 'مشتری پیدا نشد.');
    }
    if ($record['project_id']) {
        $linked = get_post($record['project_id']);
        if (!$linked || $linked->post_type !== 'zig_work_project') return new WP_Error('missing_project', 'پروژهٔ متصل پیدا نشد؛ اتصال قبلی را بررسی کنید.');
        return array('project_id'=>$record['project_id'], 'record'=>$record, 'created'=>false);
    }
    $project = zigurat_workflow_project_save_from_request(array(
        'title' => $record['topic'] ?: ($record['company'] ?: $record['name']),
        'client' => trim($record['prefix'] . ' ' . $record['name'] . ($record['company'] ? ' — ' . $record['company'] : '')),
        'phone' => $record['phone'],
        'stage' => 'lead',
        'notes' => "ایجادشده از بخش پیگیری مشتریان.\n" . $record['notes'],
    ));
    if (is_wp_error($project)) {
        return $project;
    }
    update_post_meta($customer_id, '_zigurat_crm_project_id', (int) $project['id']);
    update_post_meta($customer_id, '_zigurat_crm_status', 'won');
    update_post_meta($customer_id, '_zigurat_crm_next_at', 0);
    zigurat_crm_schedule_reminder($customer_id, 0);
    zigurat_crm_add_history($customer_id, 'converted', 'به پروژه «' . $project['title'] . '» تبدیل شد.');
    return array('project_id'=>(int)$project['id'], 'record'=>zigurat_crm_record($customer_id), 'created'=>true);
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
}

function zigurat_ajax_crm_convert_project()
{
    zigurat_crm_ajax_guard();
    $converted = zigurat_crm_convert_to_project(absint($_POST['customer_id'] ?? 0));
    if (is_wp_error($converted)) wp_send_json_error(array('message'=>$converted->get_error_message()), 400);
    wp_send_json_success(array_merge($converted, array('message'=>'کارت پروژه آماده است.')));
}
add_action('wp_ajax_zigurat_crm_convert_project', 'zigurat_ajax_crm_convert_project');

function zigurat_crm_base64url_encode($value)
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function zigurat_crm_base64url_decode($value)
{
    return base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
}

function zigurat_crm_openssl_config()
{
    $configured = getenv('OPENSSL_CONF');
    $php_dir = dirname(PHP_BINARY);
    $ini_file = php_ini_loaded_file();
    $ini_dir = $ini_file ? dirname($ini_file) : '';
    $candidates = array_filter(array(
        $configured,
        $php_dir . '/extras/ssl/openssl.cnf',
        $php_dir . '/../conf/openssl.cnf',
        $php_dir . '/../apache/conf/openssl.cnf',
        $php_dir . '/../../apache/conf/openssl.cnf',
        $ini_dir !== '' ? $ini_dir . '/extras/ssl/openssl.cnf' : '',
        $ini_dir !== '' ? $ini_dir . '/../apache/conf/openssl.cnf' : '',
        '/etc/ssl/openssl.cnf',
        '/usr/local/ssl/openssl.cnf',
    ));
    foreach ($candidates as $candidate) {
        $candidate = wp_normalize_path((string) $candidate);
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    return '';
}

function zigurat_crm_vapid_raw_public_key($details)
{
    if (
        !is_array($details)
        || (int) ($details['type'] ?? -1) !== OPENSSL_KEYTYPE_EC
        || (int) ($details['bits'] ?? 0) !== 256
        || empty($details['ec']['x'])
        || empty($details['ec']['y'])
    ) {
        return '';
    }
    $x = ltrim((string) $details['ec']['x'], "\0");
    $y = ltrim((string) $details['ec']['y'], "\0");
    if (strlen($x) > 32 || strlen($y) > 32) {
        return '';
    }
    return "\x04" . str_pad($x, 32, "\0", STR_PAD_LEFT) . str_pad($y, 32, "\0", STR_PAD_LEFT);
}

function zigurat_crm_normalize_vapid_keys($keys)
{
    if (!is_array($keys) || empty($keys['private'])) {
        return array();
    }
    $resource = @openssl_pkey_get_private((string) $keys['private']);
    $details = $resource ? openssl_pkey_get_details($resource) : false;
    $raw_public = zigurat_crm_vapid_raw_public_key($details);
    if (!$resource || strlen($raw_public) !== 65 || ord($raw_public[0]) !== 4) {
        return array();
    }
    return array(
        'private' => (string) $keys['private'],
        'public' => zigurat_crm_base64url_encode($raw_public),
    );
}

function zigurat_crm_new_p256_key()
{
    $options = array('private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1');
    $config = zigurat_crm_openssl_config();
    if ($config !== '') {
        $options['config'] = $config;
    }
    return @openssl_pkey_new($options);
}

function zigurat_crm_generate_vapid_keys()
{
    $config = zigurat_crm_openssl_config();
    $resource = zigurat_crm_new_p256_key();
    $export_options = $config !== '' ? array('config' => $config) : array();
    if (!$resource || !@openssl_pkey_export($resource, $private, null, $export_options)) {
        return array();
    }
    return zigurat_crm_normalize_vapid_keys(array('private' => $private));
}

function zigurat_crm_vapid_keys()
{
    $stored = get_option('zigurat_crm_vapid_keys');
    $keys = zigurat_crm_normalize_vapid_keys($stored);
    if ($keys) {
        if (!is_array($stored) || ($stored['public'] ?? '') !== $keys['public']) {
            update_option('zigurat_crm_vapid_keys', $keys, false);
        }
        return $keys;
    }
    $keys = zigurat_crm_generate_vapid_keys();
    if (!$keys) {
        return array('private' => '', 'public' => '');
    }
    update_option('zigurat_crm_vapid_keys', $keys, false);
    return $keys;
}

function zigurat_crm_ajax_push_subscribe()
{
    zigurat_crm_ajax_guard();
    $subscription = json_decode(wp_unslash((string) ($_POST['subscription'] ?? '')), true);
    $endpoint = esc_url_raw((string) ($subscription['endpoint'] ?? ''));
    $p256dh = sanitize_text_field((string) ($subscription['keys']['p256dh'] ?? ''));
    $auth = sanitize_text_field((string) ($subscription['keys']['auth'] ?? ''));
    if (!$endpoint || !$p256dh || !$auth || strpos($endpoint, 'https://') !== 0) {
        wp_send_json_error(array('message' => 'اشتراک اعلان معتبر نیست.'), 400);
    }
    $subscriptions = get_user_meta(get_current_user_id(), '_zigurat_crm_push_subscriptions', true);
    $subscriptions = is_array($subscriptions) ? $subscriptions : array();
    $subscriptions[md5($endpoint)] = array('endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth, 'created_at' => time());
    update_user_meta(get_current_user_id(), '_zigurat_crm_push_subscriptions', $subscriptions);
    wp_send_json_success(array('message' => 'اعلان برای این دستگاه فعال شد.'));
}
add_action('wp_ajax_zigurat_crm_push_subscribe', 'zigurat_crm_ajax_push_subscribe');

function zigurat_crm_public_key_pem($raw_key)
{
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw_key;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function zigurat_crm_hkdf_expand($prk, $info, $length)
{
    $output = '';
    $last = '';
    for ($counter = 1; strlen($output) < $length; $counter++) {
        $last = hash_hmac('sha256', $last . $info . chr($counter), $prk, true);
        $output .= $last;
    }
    return substr($output, 0, $length);
}

function zigurat_crm_ecdsa_der_to_raw($signature)
{
    $offset = 2;
    if (ord($signature[1]) & 0x80) {
        $offset = 2 + (ord($signature[1]) & 0x7f);
    }
    if (ord($signature[$offset]) !== 2) {
        return '';
    }
    $r_length = ord($signature[$offset + 1]);
    $r = substr($signature, $offset + 2, $r_length);
    $offset += 2 + $r_length;
    if (!isset($signature[$offset]) || ord($signature[$offset]) !== 2) {
        return '';
    }
    $s_length = ord($signature[$offset + 1]);
    $s = substr($signature, $offset + 2, $s_length);
    return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT) . str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
}

function zigurat_crm_vapid_header($endpoint, $keys)
{
    $parts = wp_parse_url($endpoint);
    if (empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }
    $audience = $parts['scheme'] . '://' . $parts['host'] . (!empty($parts['port']) ? ':' . $parts['port'] : '');
    $header = zigurat_crm_base64url_encode(wp_json_encode(array('typ' => 'JWT', 'alg' => 'ES256')));
    $email = sanitize_email(get_option('admin_email'));
    $payload = zigurat_crm_base64url_encode(wp_json_encode(array('aud' => $audience, 'exp' => time() + 43200, 'sub' => 'mailto:' . $email)));
    $input = $header . '.' . $payload;
    if (!openssl_sign($input, $signature, $keys['private'], OPENSSL_ALGO_SHA256)) {
        return '';
    }
    $raw = zigurat_crm_ecdsa_der_to_raw($signature);
    return $raw ? 'vapid t=' . $input . '.' . zigurat_crm_base64url_encode($raw) . ', k=' . $keys['public'] : '';
}

function zigurat_crm_push_payload($subscription, $payload)
{
    $client_public = zigurat_crm_base64url_decode($subscription['p256dh']);
    $auth_secret = zigurat_crm_base64url_decode($subscription['auth']);
    if (strlen($client_public) !== 65 || strlen($auth_secret) !== 16) {
        return false;
    }
    $client_key = openssl_pkey_get_public(zigurat_crm_public_key_pem($client_public));
    $server_key = zigurat_crm_new_p256_key();
    $details = $server_key ? openssl_pkey_get_details($server_key) : false;
    if (!$client_key || !$server_key || empty($details['ec']['x']) || empty($details['ec']['y'])) {
        return false;
    }
    $server_public = zigurat_crm_vapid_raw_public_key($details);
    if (strlen($server_public) !== 65) {
        return false;
    }
    $shared = openssl_pkey_derive($client_key, $server_key, 32);
    if (!$shared) {
        return false;
    }
    $prk_key = hash_hmac('sha256', $shared, $auth_secret, true);
    $ikm = zigurat_crm_hkdf_expand($prk_key, "WebPush: info\0" . $client_public . $server_public, 32);
    $salt = random_bytes(16);
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $cek = zigurat_crm_hkdf_expand($prk, "Content-Encoding: aes128gcm\0", 16);
    $nonce = zigurat_crm_hkdf_expand($prk, "Content-Encoding: nonce\0", 12);
    $ciphertext = openssl_encrypt(wp_json_encode($payload, JSON_UNESCAPED_UNICODE) . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($ciphertext === false) {
        return false;
    }
    return $salt . pack('N', 4096) . chr(strlen($server_public)) . $server_public . $ciphertext . $tag;
}

function zigurat_crm_send_push($subscription, $payload)
{
    $keys = zigurat_crm_vapid_keys();
    $body = zigurat_crm_push_payload($subscription, $payload);
    $authorization = zigurat_crm_vapid_header($subscription['endpoint'], $keys);
    if (!$body || !$authorization) {
        error_log('Ziggurat CRM push: payload or VAPID authorization could not be generated.');
        return 0;
    }
    $response = wp_remote_post($subscription['endpoint'], array(
        'timeout' => 15,
        'headers' => array(
            'Authorization' => $authorization,
            'Content-Encoding' => 'aes128gcm',
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => strlen($body),
            'TTL' => 86400,
            'Urgency' => 'high',
        ),
        'body' => $body,
    ));
    if (is_wp_error($response)) {
        error_log('Ziggurat CRM push transport error: ' . $response->get_error_message());
        return 0;
    }
    $response_code = (int) wp_remote_retrieve_response_code($response);
    if (!in_array($response_code, array(200, 201, 202), true)) {
        error_log('Ziggurat CRM push rejected with HTTP status ' . $response_code . '.');
    }
    return $response_code;
}

function zigurat_crm_send_reminder($customer_id, $scheduled_at)
{
    $record = zigurat_crm_record($customer_id);
    $repeat = !empty($record['repeat_daily']);
    $cycle = zigurat_followup_delivery_cycle($scheduled_at, $repeat);
    if (!$record || !$cycle || !in_array($record['status'], zigurat_crm_active_statuses(), true) || (int)$record['next_at'] !== (int)$scheduled_at) return;
    if ((int)get_post_meta($customer_id, '_zigurat_crm_notified_at', true) >= $cycle) return;
    global $wpdb;
    $lock = 'zig_crm_push_'.md5($wpdb->prefix.$customer_id);
    if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== 1) return;
    try {
        wp_cache_delete($customer_id, 'post_meta');
        if ((int)get_post_meta($customer_id, '_zigurat_crm_notified_at', true) >= $cycle) return;
        $current_record = zigurat_crm_record($customer_id);
        if (!$current_record || !in_array($current_record['status'], zigurat_crm_active_statuses(), true) || (int)$current_record['next_at'] !== (int)$scheduled_at) return;
        $delivered = get_post_meta($customer_id, '_zigurat_crm_devices', true);
        $delivered = is_array($delivered) ? $delivered : array();
        $user_ids = $record['owner_id'] ? array($record['owner_id']) : get_users(array('role__in'=>array('administrator','contributor'), 'fields'=>'ids'));
        $sent = false; $failed = false;
        foreach ($user_ids as $user_id) {
            $subscriptions = get_user_meta($user_id, '_zigurat_crm_push_subscriptions', true);
            $subscriptions = is_array($subscriptions) ? $subscriptions : array();
            foreach ($subscriptions as $key=>$subscription) {
                $device = md5((string)($subscription['endpoint'] ?? ''));
                if (($delivered[$device] ?? 0) >= $cycle) { $sent = true; continue; }
                $code = zigurat_crm_send_push($subscription, array(
                    'title'=>'پیگیری '.($record['company'] ?: $record['name']),
                    'body'=>$record['topic'].' — '.$record['next_label'],
                    'url'=>zigurat_crm_url(array('customer'=>$record['id'])),
                    'tag'=>'zigurat-crm-'.$record['id'].'-'.$cycle,
                ));
                if (in_array($code,array(200,201,202),true)) { $sent=true; $delivered[$device]=$cycle; }
                elseif (in_array($code,array(404,410),true)) unset($subscriptions[$key]);
                else $failed=true;
            }
            update_user_meta($user_id, '_zigurat_crm_push_subscriptions', $subscriptions);
        }
        wp_cache_delete($customer_id, 'post_meta');
        if ((int)get_post_meta($customer_id, '_zigurat_crm_next_at', true) !== (int)$scheduled_at) return;
        update_post_meta($customer_id, '_zigurat_crm_devices', $delivered);
        if ($sent && !$failed) update_post_meta($customer_id, '_zigurat_crm_notified_at', $cycle);
    } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
}
add_action('zigurat_crm_send_reminder', 'zigurat_crm_send_reminder', 10, 2);

function zigurat_crm_cron_schedules($schedules)
{
    $schedules['zigurat_five_minutes'] = array('interval' => 300, 'display' => 'هر پنج دقیقه');
    return $schedules;
}
add_filter('cron_schedules', 'zigurat_crm_cron_schedules');

function zigurat_crm_ensure_cron()
{
    if (!wp_next_scheduled('zigurat_crm_check_due_reminders')) {
        wp_schedule_event(time() + 60, 'zigurat_five_minutes', 'zigurat_crm_check_due_reminders');
    }
}
add_action('init', 'zigurat_crm_ensure_cron', 20);

function zigurat_crm_check_due_reminders()
{
    $now = time();
    foreach (zigurat_crm_records() as $record) {
        if ($record['next_at'] && $record['next_at'] <= $now && (!empty($record['repeat_daily']) || $record['next_at'] >= $now - DAY_IN_SECONDS)) {
            zigurat_crm_send_reminder($record['id'], $record['next_at']);
        }
    }
}
add_action('zigurat_crm_check_due_reminders', 'zigurat_crm_check_due_reminders');

function zigurat_crm_serve_service_worker()
{
    if (isset($_GET['zigurat_manager_manifest'])) {
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo wp_json_encode(array(
            'name' => 'مدیریت زیگورات',
            'short_name' => 'زیگورات',
            'start_url' => zigurat_crm_url(),
            'scope' => home_url('/'),
            'display' => 'standalone',
            'background_color' => '#f8f5ee',
            'theme_color' => '#211e19',
            'dir' => 'rtl',
            'lang' => 'fa',
            'icons' => array(array(
                'src' => get_template_directory_uri() . '/assets/images/zigurat-logo.svg',
                'sizes' => 'any',
                'type' => 'image/svg+xml',
                'purpose' => 'any maskable',
            )),
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if (!isset($_GET['zigurat_manager_sw'])) {
        return;
    }
    header('Content-Type: application/javascript; charset=utf-8');
    header('Service-Worker-Allowed: /');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $icon_url = esc_js(get_template_directory_uri() . '/assets/images/zigurat-logo.svg');
    $manager_url = esc_js(zigurat_crm_url());
    echo "self.addEventListener('push',function(event){var data={};try{data=event.data.json();}catch(e){data={title:'پیگیری مشتری',body:event.data?event.data.text():''};}event.waitUntil(self.registration.showNotification(data.title||'پیگیری مشتری',{body:data.body||'',icon:'{$icon_url}',badge:'{$icon_url}',tag:data.tag||'zigurat-crm',renotify:true,data:{url:data.url||'{$manager_url}'}}));});self.addEventListener('notificationclick',function(event){event.notification.close();var url=event.notification.data&&event.notification.data.url?event.notification.data.url:'{$manager_url}';event.waitUntil(clients.matchAll({type:'window',includeUncontrolled:true}).then(function(items){for(var i=0;i<items.length;i++){if('focus' in items[i]){items[i].navigate(url);return items[i].focus();}}return clients.openWindow(url);}));});";
    exit;
}
add_action('template_redirect', 'zigurat_crm_serve_service_worker', 0);

function zigurat_crm_manifest_head()
{
    if (!(is_page('login') || is_page_template('page-login.php'))) {
        return;
    }
    echo '<link rel="manifest" href="' . esc_url(add_query_arg('zigurat_manager_manifest', '1', home_url('/'))) . '">';
    echo '<meta name="theme-color" content="#211e19">';
    echo '<meta name="apple-mobile-web-app-capable" content="yes">';
    echo '<meta name="apple-mobile-web-app-title" content="زیگورات">';
}
add_action('wp_head', 'zigurat_crm_manifest_head', 5);
