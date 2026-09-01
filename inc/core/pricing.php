<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_pricing_normalize_digits($value)
{
    return strtr((string) $value, array(
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ));
}

function zigurat_pricing_money($value)
{
    $normalized = preg_replace('/[^0-9]/', '', zigurat_pricing_normalize_digits($value));
    return max(0, (int) $normalized);
}

function zigurat_pricing_decimal($value)
{
    $normalized = str_replace(array(',', '،', '٫'), '.', zigurat_pricing_normalize_digits($value));
    $normalized = preg_replace('/[^0-9.]/', '', $normalized);
    return max(0, (float) $normalized);
}

function zigurat_get_lightbox_pricing_settings()
{
    $defaults = array(
        'perimeter_rate' => 0,
        'square_rate' => 0,
        'pvc_rate' => 0,
        'updated_at' => '',
        'updated_by' => 0,
    );
    return wp_parse_args(get_option('zigurat_lightbox_pricing', array()), $defaults);
}

function zigurat_save_lightbox_pricing_settings($data)
{
    if (!zigurat_is_manager()) {
        return new WP_Error('forbidden', 'دسترسی به تنظیمات محاسبه قیمت مجاز نیست.');
    }
    $settings = array(
        'perimeter_rate' => zigurat_pricing_money($data['perimeter_rate'] ?? 0),
        'square_rate' => zigurat_pricing_money($data['square_rate'] ?? 0),
        'pvc_rate' => zigurat_pricing_money($data['pvc_rate'] ?? 0),
        'updated_at' => current_time('mysql'),
        'updated_by' => get_current_user_id(),
    );
    update_option('zigurat_lightbox_pricing', $settings, false);
    return $settings;
}

function zigurat_ajax_save_lightbox_pricing_settings()
{
    check_ajax_referer('zigurat_lightbox_rates', 'nonce');
    $result = zigurat_save_lightbox_pricing_settings($_POST);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 403);
    }
    wp_send_json_success($result);
}
add_action('wp_ajax_zigurat_save_lightbox_rates', 'zigurat_ajax_save_lightbox_pricing_settings');

function zigurat_get_lightbox_last_costs()
{
    $defaults = array(
        'installation' => 0,
        'travel' => 0,
        'supplies' => 0,
        'transformer' => 0,
    );
    return wp_parse_args(get_option('zigurat_lightbox_last_costs', array()), $defaults);
}

function zigurat_save_lightbox_last_costs($data)
{
    if (!zigurat_is_manager()) {
        return new WP_Error('forbidden', 'دسترسی به ذخیره هزینه‌ها مجاز نیست.');
    }
    $costs = array(
        'installation' => zigurat_pricing_money($data['installation'] ?? 0),
        'travel' => zigurat_pricing_money($data['travel'] ?? 0),
        'supplies' => zigurat_pricing_money($data['supplies'] ?? 0),
        'transformer' => zigurat_pricing_money($data['transformer'] ?? 0),
    );
    update_option('zigurat_lightbox_last_costs', $costs, false);
    return $costs;
}

function zigurat_ajax_save_lightbox_last_costs()
{
    check_ajax_referer('zigurat_lightbox_last_costs', 'nonce');
    $result = zigurat_save_lightbox_last_costs($_POST);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 403);
    }
    wp_send_json_success($result);
}
add_action('wp_ajax_zigurat_save_lightbox_last_costs', 'zigurat_ajax_save_lightbox_last_costs');

/** محاسبه مرجع سمت سرور؛ رابط کاربری همین فرمول را به‌صورت لحظه‌ای اجرا می‌کند. */
function zigurat_calculate_lightbox_price($data, $settings = null)
{
    $settings = is_array($settings) ? $settings : zigurat_get_lightbox_pricing_settings();
    $length = zigurat_pricing_decimal($data['length'] ?? 0);
    $width = zigurat_pricing_decimal($data['width'] ?? 0);
    if ($length <= 0 || $width <= 0) {
        return new WP_Error('invalid_dimensions', 'طول و عرض باید بیشتر از صفر باشند.');
    }

    $use_perimeter = $length < 1.5 || $width < 1.5;
    $measure = $use_perimeter ? 2 * ($length + $width) : $length * $width;
    $rate = $use_perimeter
        ? max(0, (int) ($settings['perimeter_rate'] ?? 0))
        : max(0, (int) ($settings['square_rate'] ?? 0));
    $base_price = (int) round($measure * $rate);
    $use_pvc = !empty($data['use_pvc']);
    $pvc_rate = $use_pvc ? max(0, (int) ($settings['pvc_rate'] ?? 0)) : 0;
    $pvc_cost = (int) round($measure * $pvc_rate);
    $installation = zigurat_pricing_money($data['installation'] ?? 0);
    $travel = zigurat_pricing_money($data['travel'] ?? 0);
    $supplies = zigurat_pricing_money($data['supplies'] ?? 0);
    $transformer = zigurat_pricing_money($data['transformer'] ?? 0);
    $profit_percent = min(1000, zigurat_pricing_decimal($data['profit_percent'] ?? 0));
    $extra_costs = $installation + $travel + $supplies + $transformer;
    $subtotal = $base_price + $pvc_cost + $extra_costs;
    $profit_amount = (int) round($subtotal * $profit_percent / 100);

    return array(
        'method' => $use_perimeter ? 'perimeter' : 'square',
        'measure' => $measure,
        'rate' => $rate,
        'base_price' => $base_price,
        'use_pvc' => $use_pvc,
        'pvc_rate' => $pvc_rate,
        'pvc_cost' => $pvc_cost,
        'transformer' => $transformer,
        'extra_costs' => $extra_costs,
        'subtotal' => $subtotal,
        'profit_percent' => $profit_percent,
        'profit_amount' => $profit_amount,
        'final_price' => $subtotal + $profit_amount,
    );
}

function zigurat_get_composite_pricing_settings()
{
    $defaults = array(
        'iron_rate' => 0,
        'composite_rate' => 0,
        'installer_rate' => 0,
        'supplies_rate' => 0,
        'updated_at' => '',
        'updated_by' => 0,
    );
    return wp_parse_args(get_option('zigurat_composite_pricing', array()), $defaults);
}

function zigurat_save_composite_pricing_settings($data)
{
    if (!zigurat_is_manager()) {
        return new WP_Error('forbidden', 'دسترسی به تنظیمات محاسبه قیمت مجاز نیست.');
    }
    $settings = array(
        'iron_rate' => zigurat_pricing_money($data['iron_rate'] ?? 0),
        'composite_rate' => zigurat_pricing_money($data['composite_rate'] ?? 0),
        'installer_rate' => zigurat_pricing_money($data['installer_rate'] ?? 0),
        'supplies_rate' => zigurat_pricing_money($data['supplies_rate'] ?? 0),
        'updated_at' => current_time('mysql'),
        'updated_by' => get_current_user_id(),
    );
    update_option('zigurat_composite_pricing', $settings, false);
    return $settings;
}

function zigurat_ajax_save_composite_pricing_settings()
{
    check_ajax_referer('zigurat_composite_rates', 'nonce');
    $result = zigurat_save_composite_pricing_settings($_POST);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 403);
    }
    wp_send_json_success($result);
}
add_action('wp_ajax_zigurat_save_composite_rates', 'zigurat_ajax_save_composite_pricing_settings');

function zigurat_get_composite_last_values()
{
    $defaults = array(
        'freight' => 0,
        'bracing_cost' => 0,
        'profit_percent' => 0,
        'insurance_percent' => 0,
        'tax_percent' => 0,
    );
    return wp_parse_args(get_option('zigurat_composite_last_values', array()), $defaults);
}

function zigurat_save_composite_last_values($data)
{
    if (!zigurat_is_manager()) {
        return new WP_Error('forbidden', 'دسترسی به ذخیره مقادیر محاسبه مجاز نیست.');
    }
    $values = array(
        'freight' => zigurat_pricing_money($data['freight'] ?? 0),
        'bracing_cost' => zigurat_pricing_money($data['bracing_cost'] ?? 0),
        'profit_percent' => min(1000, zigurat_pricing_decimal($data['profit_percent'] ?? 0)),
        'insurance_percent' => min(1000, zigurat_pricing_decimal($data['insurance_percent'] ?? 0)),
        'tax_percent' => min(1000, zigurat_pricing_decimal($data['tax_percent'] ?? 0)),
    );
    update_option('zigurat_composite_last_values', $values, false);
    return $values;
}

function zigurat_ajax_save_composite_last_values()
{
    check_ajax_referer('zigurat_composite_last_values', 'nonce');
    $result = zigurat_save_composite_last_values($_POST);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 403);
    }
    wp_send_json_success($result);
}
add_action('wp_ajax_zigurat_save_composite_last_values', 'zigurat_ajax_save_composite_last_values');

/** محاسبه مرجع قیمت کامپوزیت؛ ترتیب سود، بیمه و مالیات در رابط کاربری نیز عیناً رعایت می‌شود. */
function zigurat_calculate_composite_price($data, $settings = null)
{
    $settings = is_array($settings) ? $settings : zigurat_get_composite_pricing_settings();
    $length = zigurat_pricing_decimal($data['length'] ?? 0);
    $width = zigurat_pricing_decimal($data['width'] ?? 0);
    if ($length <= 0 || $width <= 0) {
        return new WP_Error('invalid_dimensions', 'طول و عرض باید بیشتر از صفر باشند.');
    }

    $area = $length * $width;
    $iron_cost = (int) round($area * max(0, (int) ($settings['iron_rate'] ?? 0)));
    $composite_cost = (int) round($area * max(0, (int) ($settings['composite_rate'] ?? 0)));
    $installer_cost = (int) round($area * max(0, (int) ($settings['installer_rate'] ?? 0)));
    $supplies_cost = (int) round($area * max(0, (int) ($settings['supplies_rate'] ?? 0)));
    $freight = zigurat_pricing_money($data['freight'] ?? 0);
    $bracing_cost = zigurat_pricing_money($data['bracing_cost'] ?? 0);
    $base_total = $iron_cost + $composite_cost + $installer_cost + $supplies_cost + $freight + $bracing_cost;

    $profit_percent = min(1000, zigurat_pricing_decimal($data['profit_percent'] ?? 0));
    $profit_amount = (int) round($base_total * $profit_percent / 100);
    $after_profit = $base_total + $profit_amount;

    $insurance_percent = min(1000, zigurat_pricing_decimal($data['insurance_percent'] ?? 0));
    $use_insurance = $insurance_percent > 0;
    $insurance_amount = (int) round($after_profit * $insurance_percent / 100);
    $after_insurance = $after_profit + $insurance_amount;

    $tax_percent = min(1000, zigurat_pricing_decimal($data['tax_percent'] ?? 0));
    $use_tax = $tax_percent > 0;
    $tax_amount = (int) round($after_insurance * $tax_percent / 100);
    $final_price = $after_insurance + $tax_amount;

    return array(
        'length' => $length,
        'width' => $width,
        'area' => $area,
        'iron_cost' => $iron_cost,
        'composite_cost' => $composite_cost,
        'installer_cost' => $installer_cost,
        'supplies_cost' => $supplies_cost,
        'freight' => $freight,
        'bracing_cost' => $bracing_cost,
        'base_total' => $base_total,
        'profit_percent' => $profit_percent,
        'profit_amount' => $profit_amount,
        'after_profit' => $after_profit,
        'use_insurance' => $use_insurance,
        'insurance_percent' => $insurance_percent,
        'insurance_amount' => $insurance_amount,
        'use_tax' => $use_tax,
        'tax_percent' => $tax_percent,
        'tax_amount' => $tax_amount,
        'price_per_square_meter' => $area > 0 ? (int) round($final_price / $area) : 0,
        'final_price' => $final_price,
    );
}

function zigurat_flexi_roll_widths($value)
{
    $normalized = zigurat_pricing_normalize_digits($value);
    $normalized = str_replace(array('،', ';', '؛', '|', "\r", "\n", "\t"), ',', $normalized);
    $parts = preg_split('/[\s,]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);
    $widths = array();
    foreach ((array) $parts as $part) {
        $width = zigurat_pricing_decimal($part);
        if ($width > 0 && $width <= 10) {
            $widths[number_format($width, 3, '.', '')] = $width;
        }
    }
    $widths = array_values($widths);
    sort($widths, SORT_NUMERIC);
    return $widths;
}

function zigurat_flexi_roll_widths_text($value)
{
    $widths = zigurat_flexi_roll_widths($value);
    return implode(',', array_map(static function ($width) {
        return rtrim(rtrim(number_format($width, 3, '.', ''), '0'), '.');
    }, $widths));
}

function zigurat_get_flexi_pricing_settings()
{
    $defaults = array(
        'flex_rate'               => 0,
        'roll_widths'             => '1,1.5,2,2.5,3,3.2',
        'separator_rate'          => 0,
        'core_branch_rate'        => 0,
        'tape_rate'               => 0,
        'clip_rate'               => 0,
        'cover_branch_rate'       => 0,
        'installer_rate'          => 0,
        'iron_price_per_kg'       => 0,
        'updated_at'              => '',
        'updated_by'              => 0,
    );
    return wp_parse_args(get_option('zigurat_flexi_pricing', array()), $defaults);
}

function zigurat_save_flexi_pricing_settings($data)
{
    if (!zigurat_is_manager()) {
        return new WP_Error('forbidden', 'دسترسی به تنظیمات محاسبه قیمت مجاز نیست.');
    }
    $roll_widths = zigurat_flexi_roll_widths_text($data['roll_widths'] ?? '');
    if ($roll_widths === '') {
        return new WP_Error('invalid_roll_widths', 'حداقل یک عرض معتبر برای رول فلکسی وارد کنید.');
    }
    $settings = array(
        'flex_rate'               => zigurat_pricing_money($data['flex_rate'] ?? 0),
        'roll_widths'             => $roll_widths,
        'separator_rate'          => zigurat_pricing_money($data['separator_rate'] ?? 0),
        'core_branch_rate'        => zigurat_pricing_money($data['core_branch_rate'] ?? 0),
        'tape_rate'               => zigurat_pricing_money($data['tape_rate'] ?? 0),
        'clip_rate'               => zigurat_pricing_money($data['clip_rate'] ?? 0),
        'cover_branch_rate'       => zigurat_pricing_money($data['cover_branch_rate'] ?? 0),
        'installer_rate'          => zigurat_pricing_money($data['installer_rate'] ?? 0),
        'iron_price_per_kg'       => zigurat_pricing_money($data['iron_price_per_kg'] ?? 0),
        'updated_at'              => current_time('mysql'),
        'updated_by'              => get_current_user_id(),
    );
    update_option('zigurat_flexi_pricing', $settings, false);
    return $settings;
}

function zigurat_ajax_save_flexi_pricing_settings()
{
    check_ajax_referer('zigurat_flexi_rates', 'nonce');
    $result = zigurat_save_flexi_pricing_settings($_POST);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 400);
    }
    wp_send_json_success($result);
}
add_action('wp_ajax_zigurat_save_flexi_rates', 'zigurat_ajax_save_flexi_pricing_settings');

function zigurat_flexi_iron_types()
{
    return array(
        '20x20'  => array('label' => 'قوطی ۲۰×۲۰', 'branch_weight' => 7),
        '25x25'  => array('label' => 'قوطی ۲۵×۲۵', 'branch_weight' => 9),
        '20x30'  => array('label' => 'قوطی ۲۰×۳۰', 'branch_weight' => 9),
        '30x30'  => array('label' => 'قوطی ۳۰×۳۰', 'branch_weight' => 11),
        '40x40'  => array('label' => 'قوطی ۴۰×۴۰', 'branch_weight' => 15),
        'custom' => array('label' => 'سایز دیگر / سفارشی', 'branch_weight' => 1),
    );
}

function zigurat_flexi_bracing_iron_types()
{
    return array(
        '20x30'  => array('label' => 'قوطی ۲۰×۳۰', 'branch_weight' => 9),
        '40x40'  => array('label' => 'قوطی ۴۰×۴۰', 'branch_weight' => 15),
        '40x80'  => array('label' => 'قوطی ۴۰×۸۰', 'branch_weight' => 39),
        'custom' => array('label' => 'سایز دیگر / سفارشی', 'branch_weight' => 1),
    );
}

function zigurat_get_flexi_last_values()
{
    $defaults = array(
        'iron_type'          => '25x25',
        'iron_branch_weight' => 9,
        'bracing_iron_type'          => '40x40',
        'bracing_iron_branch_weight' => 15,
        'flex_margin_cm'      => 10,
        'freight'            => 0,
        'profit_percent'     => 0,
        'insurance_percent'  => 0,
        'tax_percent'        => 0,
    );
    $stored = get_option('zigurat_flexi_last_values', array());
    $stored = is_array($stored) ? $stored : array();
    $values = wp_parse_args($stored, $defaults);
    if (!array_key_exists('iron_branch_weight', $stored)) {
        $types = zigurat_flexi_iron_types();
        $type = isset($types[$values['iron_type']]) ? $values['iron_type'] : 'custom';
        $values['iron_branch_weight'] = $types[$type]['branch_weight'];
    }
    if (!array_key_exists('bracing_iron_branch_weight', $stored)) {
        $types = zigurat_flexi_bracing_iron_types();
        $type = isset($types[$values['bracing_iron_type']]) ? $values['bracing_iron_type'] : 'custom';
        $values['bracing_iron_branch_weight'] = $types[$type]['branch_weight'];
    }
    return $values;
}

function zigurat_save_flexi_last_values($data)
{
    if (!zigurat_is_manager()) {
        return new WP_Error('forbidden', 'دسترسی به ذخیره مقادیر محاسبه مجاز نیست.');
    }
    $types = zigurat_flexi_iron_types();
    $iron_type = sanitize_key($data['iron_type'] ?? '25x25');
    if (!isset($types[$iron_type])) {
        $iron_type = 'custom';
    }
    $bracing_types = zigurat_flexi_bracing_iron_types();
    $bracing_iron_type = sanitize_key($data['bracing_iron_type'] ?? '40x40');
    if (!isset($bracing_types[$bracing_iron_type])) {
        $bracing_iron_type = 'custom';
    }
    $values = array(
        'iron_type'          => $iron_type,
        'iron_branch_weight' => min(200, zigurat_pricing_decimal($data['iron_branch_weight'] ?? $types[$iron_type]['branch_weight'])),
        'bracing_iron_type'          => $bracing_iron_type,
        'bracing_iron_branch_weight' => min(200, zigurat_pricing_decimal($data['bracing_iron_branch_weight'] ?? $bracing_types[$bracing_iron_type]['branch_weight'])),
        'flex_margin_cm'      => min(10, max(7, zigurat_pricing_decimal($data['flex_margin_cm'] ?? 10))),
        'freight'            => zigurat_pricing_money($data['freight'] ?? 0),
        'profit_percent'     => min(1000, zigurat_pricing_decimal($data['profit_percent'] ?? 0)),
        'insurance_percent'  => min(1000, zigurat_pricing_decimal($data['insurance_percent'] ?? 0)),
        'tax_percent'        => min(1000, zigurat_pricing_decimal($data['tax_percent'] ?? 0)),
    );
    update_option('zigurat_flexi_last_values', $values, false);
    return $values;
}

function zigurat_ajax_save_flexi_last_values()
{
    check_ajax_referer('zigurat_flexi_last_values', 'nonce');
    $result = zigurat_save_flexi_last_values($_POST);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 403);
    }
    wp_send_json_success($result);
}
add_action('wp_ajax_zigurat_save_flexi_last_values', 'zigurat_ajax_save_flexi_last_values');

function zigurat_flexi_material_plan($length, $width, $roll_widths)
{
    $candidates = array();
    foreach (array(
        array('across' => $width, 'run' => $length, 'direction' => 'length'),
        array('across' => $length, 'run' => $width, 'direction' => 'width'),
    ) as $orientation) {
        foreach ($roll_widths as $roll_width) {
            $strips = max(1, (int) ceil(($orientation['across'] / $roll_width) - 0.0000001));
            $purchased_area = $strips * $roll_width * $orientation['run'];
            $candidates[] = array(
                'roll_width'    => $roll_width,
                'strips'        => $strips,
                'run_length'    => $orientation['run'],
                'direction'     => $orientation['direction'],
                'purchased_area'=> $purchased_area,
            );
        }
    }
    usort($candidates, static function ($first, $second) {
        $strip_compare = $first['strips'] <=> $second['strips'];
        return $strip_compare !== 0 ? $strip_compare : ($first['purchased_area'] <=> $second['purchased_area']);
    });
    return $candidates[0];
}

/** محاسبه مرجع فلکسی با خرید واقعی رول و شاخه‌ها و محاسبه پرت. */
function zigurat_calculate_flexi_price($data, $settings = null)
{
    $settings = is_array($settings) ? wp_parse_args($settings, zigurat_get_flexi_pricing_settings()) : zigurat_get_flexi_pricing_settings();
    $length = zigurat_pricing_decimal($data['length'] ?? 0);
    $width = zigurat_pricing_decimal($data['width'] ?? 0);
    if ($length <= 0 || $width <= 0) {
        return new WP_Error('invalid_dimensions', 'طول و ارتفاع باید بیشتر از صفر باشند.');
    }
    $roll_widths = zigurat_flexi_roll_widths($settings['roll_widths'] ?? '');
    if (!$roll_widths) {
        return new WP_Error('invalid_roll_widths', 'عرض رول‌های فلکسی تعریف نشده است.');
    }

    $area = $length * $width;
    $perimeter = 2 * ($length + $width);
    $flex_margin_cm = min(10, max(7, zigurat_pricing_decimal($data['flex_margin_cm'] ?? 10)));
    $flex_margin_m = $flex_margin_cm / 100;
    $flex_print_length = $length + (2 * $flex_margin_m);
    $flex_print_width = $width + (2 * $flex_margin_m);
    $flex_print_area = $flex_print_length * $flex_print_width;
    $flex_extra_area = max(0, $flex_print_area - $area);
    $material = zigurat_flexi_material_plan($flex_print_length, $flex_print_width, $roll_widths);
    $material['waste_area'] = max(0, $material['purchased_area'] - $flex_print_area);
    $flex_cost = (int) round($material['purchased_area'] * (int) $settings['flex_rate']);

    $separator_branch_length = 3.0;
    $separator_branches = max(1, (int) ceil(($perimeter / $separator_branch_length) - 0.0000001));
    $separator_purchased_length = $separator_branches * $separator_branch_length;
    $separator_waste = max(0, $separator_purchased_length - $perimeter);
    $separator_cost = (int) round($separator_purchased_length * (int) $settings['separator_rate']);

    $core_branch_length = 1.8;
    $core_branches = max(1, (int) ceil(($perimeter / $core_branch_length) - 0.0000001));
    $core_purchased_length = $core_branches * $core_branch_length;
    $core_waste = max(0, $core_purchased_length - $perimeter);
    $core_cost = $core_branches * (int) $settings['core_branch_rate'];

    $tape_length = $perimeter;
    $tape_cost = (int) round($tape_length * (int) $settings['tape_rate']);
    $clip_count = max(1, (int) ceil(($perimeter / 0.15) - 0.0000001));
    $clip_cost = $clip_count * (int) $settings['clip_rate'];

    $cover_branch_length = 2.5;
    $cover_branches = max(1, (int) ceil(($perimeter / $cover_branch_length) - 0.0000001));
    $cover_purchased_length = $cover_branches * $cover_branch_length;
    $cover_waste = max(0, $cover_purchased_length - $perimeter);
    $cover_cost = $cover_branches * (int) $settings['cover_branch_rate'];
    $installer_cost = (int) round($area * (int) $settings['installer_rate']);

    $types = zigurat_flexi_iron_types();
    $iron_type = sanitize_key($data['iron_type'] ?? '25x25');
    if (!isset($types[$iron_type])) {
        $iron_type = 'custom';
    }
    $iron_branch_length = 6.0;
    $iron_branch_weight = min(200, zigurat_pricing_decimal($data['iron_branch_weight'] ?? $types[$iron_type]['branch_weight']));
    $length_braces = $length > 1.5
        ? max(1, (int) ceil($length - 0.0000001) - 1)
        : 0;
    $width_braces = $width > 2
        ? max(1, (int) ceil(($width / 2) - 0.0000001) - 1)
        : 0;
    $iron_length = $perimeter + ($length_braces * $width) + ($width_braces * $length);
    $iron_branches = max(1, (int) ceil(($iron_length / $iron_branch_length) - 0.0000001));
    $iron_purchased_length = $iron_branches * $iron_branch_length;
    $iron_waste = max(0, $iron_purchased_length - $iron_length);
    $iron_weight = $iron_branches * $iron_branch_weight;
    $iron_cost = (int) round($iron_weight * (int) $settings['iron_price_per_kg']);

    $bracing_types = zigurat_flexi_bracing_iron_types();
    $bracing_iron_type = sanitize_key($data['bracing_iron_type'] ?? '40x40');
    if (!isset($bracing_types[$bracing_iron_type])) {
        $bracing_iron_type = 'custom';
    }
    $bracing_iron_length = min(10000, max(0, zigurat_pricing_decimal($data['bracing_iron_length'] ?? 0)));
    $bracing_iron_branch_weight = min(200, zigurat_pricing_decimal($data['bracing_iron_branch_weight'] ?? $bracing_types[$bracing_iron_type]['branch_weight']));
    $bracing_iron_branches = $bracing_iron_length > 0
        ? (int) ceil(($bracing_iron_length / $iron_branch_length) - 0.0000001)
        : 0;
    $bracing_iron_purchased_length = $bracing_iron_branches * $iron_branch_length;
    $bracing_iron_waste = max(0, $bracing_iron_purchased_length - $bracing_iron_length);
    $bracing_iron_weight = $bracing_iron_branches * $bracing_iron_branch_weight;
    $bracing_iron_cost = (int) round($bracing_iron_weight * (int) $settings['iron_price_per_kg']);

    $freight = zigurat_pricing_money($data['freight'] ?? 0);
    $base_total = $flex_cost + $separator_cost + $core_cost + $tape_cost + $clip_cost + $cover_cost + $installer_cost + $iron_cost + $bracing_iron_cost + $freight;
    $profit_percent = min(1000, zigurat_pricing_decimal($data['profit_percent'] ?? 0));
    $profit_amount = (int) round($base_total * $profit_percent / 100);
    $after_profit = $base_total + $profit_amount;
    $insurance_percent = min(1000, zigurat_pricing_decimal($data['insurance_percent'] ?? 0));
    $insurance_amount = (int) round($after_profit * $insurance_percent / 100);
    $after_insurance = $after_profit + $insurance_amount;
    $tax_percent = min(1000, zigurat_pricing_decimal($data['tax_percent'] ?? 0));
    $tax_amount = (int) round($after_insurance * $tax_percent / 100);
    $final_price = $after_insurance + $tax_amount;

    return array(
        'length' => $length, 'width' => $width, 'area' => $area, 'perimeter' => $perimeter,
        'flex_margin_cm' => $flex_margin_cm, 'flex_print_length' => $flex_print_length, 'flex_print_width' => $flex_print_width,
        'flex_print_area' => $flex_print_area, 'flex_extra_area' => $flex_extra_area,
        'material' => $material, 'flex_cost' => $flex_cost,
        'separator_branches' => $separator_branches, 'separator_purchased_length' => $separator_purchased_length, 'separator_waste' => $separator_waste, 'separator_cost' => $separator_cost,
        'core_branches' => $core_branches, 'core_purchased_length' => $core_purchased_length, 'core_waste' => $core_waste, 'core_cost' => $core_cost,
        'tape_length' => $tape_length, 'tape_cost' => $tape_cost, 'clip_count' => $clip_count, 'clip_cost' => $clip_cost,
        'cover_branches' => $cover_branches, 'cover_purchased_length' => $cover_purchased_length, 'cover_waste' => $cover_waste, 'cover_cost' => $cover_cost,
        'installer_cost' => $installer_cost,
        'iron_type' => $iron_type, 'iron_type_label' => $types[$iron_type]['label'], 'iron_branch_weight' => $iron_branch_weight,
        'length_braces' => $length_braces, 'width_braces' => $width_braces, 'iron_length' => $iron_length,
        'iron_branches' => $iron_branches, 'iron_purchased_length' => $iron_purchased_length, 'iron_waste' => $iron_waste, 'iron_weight' => $iron_weight,
        'iron_length_per_square_meter' => $iron_purchased_length / $area, 'iron_weight_per_square_meter' => $iron_weight / $area, 'iron_cost' => $iron_cost,
        'bracing_iron_type' => $bracing_iron_type, 'bracing_iron_type_label' => $bracing_types[$bracing_iron_type]['label'],
        'bracing_iron_length' => $bracing_iron_length, 'bracing_iron_branch_weight' => $bracing_iron_branch_weight,
        'bracing_iron_branches' => $bracing_iron_branches, 'bracing_iron_purchased_length' => $bracing_iron_purchased_length,
        'bracing_iron_waste' => $bracing_iron_waste, 'bracing_iron_weight' => $bracing_iron_weight, 'bracing_iron_cost' => $bracing_iron_cost,
        'freight' => $freight, 'base_total' => $base_total,
        'profit_percent' => $profit_percent, 'profit_amount' => $profit_amount,
        'insurance_percent' => $insurance_percent, 'insurance_amount' => $insurance_amount,
        'tax_percent' => $tax_percent, 'tax_amount' => $tax_amount,
        'price_per_square_meter' => (int) round($final_price / $area), 'final_price' => $final_price,
    );
}
