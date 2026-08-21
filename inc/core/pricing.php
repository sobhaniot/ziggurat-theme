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
    $normalized = str_replace(',', '.', zigurat_pricing_normalize_digits($value));
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
