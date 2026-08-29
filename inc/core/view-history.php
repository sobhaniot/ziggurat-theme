<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_view_history_table_name()
{
    global $wpdb;
    return $wpdb->prefix . 'zigurat_daily_views';
}

function zigurat_install_view_history_table()
{
    if (get_option('zigurat_view_history_version') === '1') {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = zigurat_view_history_table_name();
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$table} (
        view_date date NOT NULL,
        content_type varchar(20) NOT NULL,
        views bigint(20) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (view_date, content_type),
        KEY content_type (content_type)
    ) {$charset};");
    update_option('zigurat_view_history_version', '1', false);
}
add_action('init', 'zigurat_install_view_history_table', 5);
add_action('after_switch_theme', 'zigurat_install_view_history_table');

/** ثبت اتمیک یک بازدید روزانه؛ در هر روز فقط دو ردیف مطلب و پروژه ساخته می‌شود. */
function zigurat_record_daily_view($content_type)
{
    if (!in_array($content_type, array('article', 'project'), true)) {
        return;
    }
    global $wpdb;
    $table = zigurat_view_history_table_name();
    $date = current_time('Y-m-d');
    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$table} (view_date, content_type, views)
         VALUES (%s, %s, 1)
         ON DUPLICATE KEY UPDATE views = views + 1",
        $date,
        $content_type
    ));
}

/** تبدیل تاریخ جلالی به میلادی برای ساخت بازه‌های ماهانه و سالانه شمسی. */
function zigurat_views_jalali_to_gregorian($jy, $jm, $jd)
{
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

function zigurat_views_jalali_date_parts(DateTimeInterface $date)
{
    if (function_exists('zigurat_inventory_gregorian_to_jalali')) {
        return zigurat_inventory_gregorian_to_jalali((int) $date->format('Y'), (int) $date->format('m'), (int) $date->format('d'));
    }
    return array((int) $date->format('Y'), (int) $date->format('m'), (int) $date->format('d'));
}

function zigurat_views_date_from_jalali($year, $month, $day, DateTimeZone $timezone)
{
    [$gy, $gm, $gd] = zigurat_views_jalali_to_gregorian($year, $month, $day);
    return new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $gy, $gm, $gd), $timezone);
}

function zigurat_views_periods($range)
{
    $timezone = wp_timezone();
    $today = new DateTimeImmutable(current_time('Y-m-d') . ' 00:00:00', $timezone);
    $periods = array();
    if ($range === 'daily') {
        for ($index = 13; $index >= 0; $index--) {
            $start = $today->modify('-' . $index . ' days');
            $jalali = zigurat_views_jalali_date_parts($start);
            $periods[] = array('start' => $start, 'end' => $start, 'label' => sprintf('%02d/%02d', $jalali[1], $jalali[2]));
        }
        return $periods;
    }
    if ($range === 'weekly') {
        $days_since_saturday = ((int) $today->format('N') + 1) % 7;
        $current_start = $today->modify('-' . $days_since_saturday . ' days');
        for ($index = 11; $index >= 0; $index--) {
            $start = $current_start->modify('-' . $index . ' weeks');
            $end = $start->modify('+6 days');
            $jalali = zigurat_views_jalali_date_parts($start);
            $periods[] = array('start' => $start, 'end' => $end, 'label' => sprintf('%02d/%02d', $jalali[1], $jalali[2]));
        }
        return $periods;
    }
    $today_jalali = zigurat_views_jalali_date_parts($today);
    if ($range === 'monthly') {
        $month_index = ($today_jalali[0] * 12) + $today_jalali[1] - 1;
        for ($offset = 11; $offset >= 0; $offset--) {
            $value = $month_index - $offset;
            $year = (int) floor($value / 12);
            $month = ($value % 12) + 1;
            $next_value = $value + 1;
            $next_year = (int) floor($next_value / 12);
            $next_month = ($next_value % 12) + 1;
            $start = zigurat_views_date_from_jalali($year, $month, 1, $timezone);
            $end = zigurat_views_date_from_jalali($next_year, $next_month, 1, $timezone)->modify('-1 day');
            $periods[] = array('start' => $start, 'end' => $end, 'label' => sprintf('%04d/%02d', $year, $month));
        }
        return $periods;
    }
    for ($offset = 4; $offset >= 0; $offset--) {
        $year = $today_jalali[0] - $offset;
        $start = zigurat_views_date_from_jalali($year, 1, 1, $timezone);
        $end = zigurat_views_date_from_jalali($year + 1, 1, 1, $timezone)->modify('-1 day');
        $periods[] = array('start' => $start, 'end' => $end, 'label' => (string) $year);
    }
    return $periods;
}

function zigurat_get_views_chart_data($range = 'daily')
{
    global $wpdb;
    $range = in_array($range, array('daily', 'weekly', 'monthly', 'yearly'), true) ? $range : 'daily';
    $periods = zigurat_views_periods($range);
    if (!$periods) {
        return array();
    }
    $first = $periods[0]['start']->format('Y-m-d');
    $last = $periods[count($periods) - 1]['end']->format('Y-m-d');
    $table = zigurat_view_history_table_name();
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT view_date, content_type, views FROM {$table} WHERE view_date BETWEEN %s AND %s ORDER BY view_date ASC",
        $first,
        $last
    ));
    $result = array();
    foreach ($periods as $period) {
        $start = $period['start']->format('Y-m-d');
        $end = $period['end']->format('Y-m-d');
        $article = 0;
        $project = 0;
        foreach ($rows as $row) {
            if ($row->view_date < $start || $row->view_date > $end) {
                continue;
            }
            if ($row->content_type === 'article') {
                $article += (int) $row->views;
            } elseif ($row->content_type === 'project') {
                $project += (int) $row->views;
            }
        }
        $result[] = array(
            'label' => $period['label'],
            'article' => $article,
            'project' => $project,
            'total' => $article + $project,
        );
    }
    return $result;
}

function zigurat_get_all_views_chart_data()
{
    return array(
        'daily' => array('title' => '۱۴ روز اخیر', 'items' => zigurat_get_views_chart_data('daily')),
        'weekly' => array('title' => '۱۲ هفته اخیر', 'items' => zigurat_get_views_chart_data('weekly')),
        'monthly' => array('title' => '۱۲ ماه اخیر', 'items' => zigurat_get_views_chart_data('monthly')),
        'yearly' => array('title' => '۵ سال اخیر', 'items' => zigurat_get_views_chart_data('yearly')),
    );
}
