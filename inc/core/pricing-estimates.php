<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_register_pricing_estimate_post_type()
{
    register_post_type('zig_price_estimate', array(
        'labels' => array('name' => 'برآوردهای قیمت', 'singular_name' => 'برآورد قیمت'),
        'public' => false,
        'show_ui' => false,
        'show_in_rest' => false,
        'supports' => array('title', 'author'),
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ));
}
add_action('init', 'zigurat_register_pricing_estimate_post_type', 12);

function zigurat_pricing_estimate_date($post)
{
    if (function_exists('zigurat_inventory_format_jalali_datetime')) {
        return zigurat_inventory_format_jalali_datetime($post->post_modified);
    }
    return mysql2date('Y/m/d H:i', $post->post_modified);
}

function zigurat_get_pricing_estimate_records($limit = 100)
{
    $page = zigurat_get_pricing_estimate_page(1, $limit);
    return $page['records'];
}

function zigurat_get_pricing_estimate_page($page = 1, $per_page = 10, $search = '')
{
    if (!zigurat_is_manager()) {
        return array('records' => array(), 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 10);
    }
    $page = max(1, absint($page));
    $per_page = max(5, min(50, absint($per_page)));
    $search = sanitize_text_field((string) $search);
    $query = new WP_Query(array(
        'post_type' => 'zig_price_estimate',
        'post_status' => 'private',
        'posts_per_page' => $per_page,
        'paged' => $page,
        's' => $search,
        'orderby' => 'modified',
        'order' => 'DESC',
        'no_found_rows' => false,
    ));
    $pages = max(1, (int) $query->max_num_pages);
    if ($page > $pages) {
        return zigurat_get_pricing_estimate_page($pages, $per_page, $search);
    }
    $records = array_map(static function ($post) {
        $final_price = (int) get_post_meta($post->ID, '_zigurat_pricing_final', true);
        $perimeter_m = (float) get_post_meta($post->ID, '_zigurat_pricing_perimeter', true);
        $unit_price = (int) get_post_meta($post->ID, '_zigurat_pricing_unit_price', true);
        if ($perimeter_m <= 0 || $unit_price <= 0) {
            $snapshot = zigurat_get_pricing_estimate_snapshot($post->ID);
            if (is_array($snapshot)) {
                $perimeter_m = (float) ($snapshot['analysis']['rounded_perimeter_m'] ?? $snapshot['breakdown']['rounded_perimeter_m'] ?? 0);
                $unit_price = $perimeter_m > 0 ? (int) round($final_price / $perimeter_m) : 0;
            }
        }
        return array(
            'id' => (int) $post->ID,
            'project_name' => get_the_title($post),
            'calculator_type' => (string) get_post_meta($post->ID, '_zigurat_pricing_type', true),
            'final_price' => $final_price,
            'perimeter_m' => $perimeter_m,
            'unit_price' => $unit_price,
            'modified' => zigurat_pricing_estimate_date($post),
        );
    }, $query->posts);
    return array(
        'records' => $records,
        'total' => (int) $query->found_posts,
        'page' => $page,
        'pages' => $pages,
        'per_page' => $per_page,
    );
}

function zigurat_pricing_estimate_number($value, $maximum = 999999999999999)
{
    if (!is_numeric($value)) {
        return 0;
    }
    return min($maximum, max(0, (float) $value));
}

function zigurat_sanitize_pricing_svg($svg)
{
    $svg = is_string($svg) ? trim($svg) : '';
    if ($svg === '' || strlen($svg) > 5 * 1024 * 1024 || !class_exists('DOMDocument')) {
        return '';
    }
    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded || !$document->documentElement || strtolower($document->documentElement->localName) !== 'svg') {
        return '';
    }

    $class_styles = array();
    foreach ($document->getElementsByTagName('style') as $style_node) {
        $css = (string) $style_node->textContent;
        if (!preg_match_all('/([^{}]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($rules as $rule) {
            $declarations = array();
            foreach (explode(';', $rule[2]) as $declaration) {
                $separator = strpos($declaration, ':');
                if ($separator === false) {
                    continue;
                }
                $property = strtolower(trim(substr($declaration, 0, $separator)));
                $value = preg_replace('/\s*!important\s*$/i', '', trim(substr($declaration, $separator + 1)));
                if (in_array($property, array('fill', 'stroke', 'stroke-width', 'fill-opacity', 'stroke-opacity', 'opacity'), true)
                    && $value !== '' && stripos($value, 'url(') === false && !preg_match('/[<>]/', $value)) {
                    $declarations[$property] = $value;
                }
            }
            if (!$declarations) {
                continue;
            }
            foreach (explode(',', $rule[1]) as $selector) {
                if (preg_match('/^\.([a-zA-Z_][\w-]*)$/', trim($selector), $class_match)) {
                    $class_styles[$class_match[1]] = array_merge($class_styles[$class_match[1]] ?? array(), $declarations);
                }
            }
        }
    }

    $allowed_tags = array('svg', 'g', 'path', 'polygon', 'polyline', 'rect', 'circle', 'ellipse', 'line');
    $allowed_attributes = array(
        'xmlns', 'version', 'viewbox', 'width', 'height', 'transform', 'd', 'points',
        'x', 'y', 'x1', 'y1', 'x2', 'y2', 'rx', 'ry', 'cx', 'cy', 'r',
        'fill', 'stroke', 'stroke-width', 'fill-opacity', 'stroke-opacity', 'opacity',
        'fill-rule', 'clip-rule', 'id', 'class', 'xml:space',
    );
    $nodes = array();
    foreach ($document->getElementsByTagName('*') as $node) {
        $nodes[] = $node;
    }
    foreach (array_reverse($nodes) as $node) {
        if (!in_array(strtolower($node->localName), $allowed_tags, true)) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
            continue;
        }
        foreach (preg_split('/\s+/', trim((string) $node->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY) as $class_name) {
            foreach (($class_styles[$class_name] ?? array()) as $property => $value) {
                $node->setAttribute($property, $value);
            }
        }
        $style = (string) $node->getAttribute('style');
        if (preg_match('/(?:^|;)\s*fill-rule\s*:\s*(evenodd|nonzero)\b/i', $style, $match)) {
            $node->setAttribute('fill-rule', strtolower($match[1]));
        }
        if (preg_match('/(?:^|;)\s*clip-rule\s*:\s*(evenodd|nonzero)\b/i', $style, $match)) {
            $node->setAttribute('clip-rule', strtolower($match[1]));
        }
        foreach (array('fill', 'stroke', 'stroke-width', 'fill-opacity', 'stroke-opacity', 'opacity') as $property) {
            if (preg_match('/(?:^|;)\s*' . preg_quote($property, '/') . '\s*:\s*([^;]+)/i', $style, $match)) {
                $value = trim($match[1]);
                if ($value !== '' && stripos($value, 'url(') === false && !preg_match('/[<>]/', $value)) {
                    $node->setAttribute($property, $value);
                }
            }
        }
        $remove = array();
        foreach ($node->attributes as $attribute) {
            $name = strtolower($attribute->nodeName);
            if (!in_array($name, $allowed_attributes, true) || strpos($name, 'on') === 0 || stripos($attribute->nodeValue, 'url(') !== false) {
                $remove[] = $attribute->nodeName;
            }
        }
        foreach ($remove as $attribute_name) {
            $node->removeAttribute($attribute_name);
        }
    }
    $document->documentElement->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    return (string) $document->saveXML($document->documentElement);
}

function zigurat_sanitize_pricing_layout_previews($previews)
{
    if (!is_array($previews)) {
        return array();
    }
    $clean = array();
    $total_bytes = 0;
    foreach (array_slice($previews, 0, 24) as $preview) {
        if (!is_string($preview) || !preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#', $preview, $match)) {
            continue;
        }
        $binary = base64_decode($match[2], true);
        if ($binary === false || strlen($binary) > 350 * 1024) {
            continue;
        }
        $total_bytes += strlen($binary);
        if ($total_bytes > 3 * 1024 * 1024) {
            break;
        }
        $clean[] = 'data:image/' . strtolower($match[1]) . ';base64,' . base64_encode($binary);
    }
    return $clean;
}

function zigurat_sanitize_pricing_image_preview($preview)
{
    $clean = zigurat_sanitize_pricing_layout_previews(array($preview));
    return $clean ? $clean[0] : '';
}

function zigurat_sanitize_letter_estimate_snapshot($snapshot)
{
    if (!is_array($snapshot)) {
        return new WP_Error('invalid_snapshot', 'اطلاعات محاسبه معتبر نیست.');
    }
    $input_keys = array(
        'design_width_mm', 'design_height_mm', 'installation', 'travel',
        'wire_supplies', 'profit_percent', 'insurance_tax_percent', 'use_transformer', 'layout_trials',
    );
    $rate_keys = array(
        'sheet_width_mm', 'sheet_height_mm', 'plexi_sqm_rate', 'metal_sheet_07_sqm_rate', 'edge_swedish_material_rate',
        'edge_swedish_labor_rate', 'edge_plastic_material_rate', 'edge_plastic_labor_rate',
        'edge_channelium_material_rate', 'edge_channelium_labor_rate', 'edge_metal_material_rate',
        'edge_metal_labor_rate', 'metal_powder_coating_rate', 'double_layer_labor_rate', 'pvc_rate', 'plexi_cut_rate', 'pvc_cut_rate', 'glue_rate',
        'smd_block_rate', 'smd_lens_rate', 'smd_roll_rate',
        'smd_block_units_per_square_meter', 'smd_lens_units_per_square_meter',
        'smd_roll_units_per_square_meter', 'smd_block_width_mm', 'smd_block_height_mm', 'smd_block_led_count',
        'smd_lens_width_mm', 'smd_lens_height_mm', 'smd_lens_led_count',
        'smd_roll_strip_width_mm', 'smd_roll_watts_per_meter', 'smd_roll_length_m',
        'smd_roll_cut_interval_mm', 'smd_roll_waste_percent', 'smd_roll_transformer_reserve_percent',
        'smd_roll_wall_clearance_mm', 'smd_roll_row_spacing_mm',
        'transformer_60_rate', 'transformer_100_rate', 'transformer_120_rate',
        'transformer_200_rate', 'transformer_300_rate', 'transformer_400_rate',
        'transformer_60_capacity', 'transformer_100_capacity', 'transformer_120_capacity',
        'transformer_200_capacity', 'transformer_300_capacity', 'transformer_400_capacity',
        'cut_gap_mm', 'sheet_margin_mm',
    );
    $analysis_keys = array(
        'area_mm2', 'lighting_area_mm2', 'consumed_area_mm2', 'perimeter_mm', 'rounded_perimeter_m',
        'primary_perimeter_mm', 'double_perimeter_mm', 'pin_perimeter_mm', 'laser_perimeter_mm',
        'double_area_mm2', 'double_consumed_area_mm2',
        'parts', 'lighting_parts', 'sheets', 'unplaced_parts', 'design_width_mm', 'design_height_mm',
    );
    $breakdown_keys = array(
        'plexi', 'metal_sheet_07', 'powder_coating', 'edge', 'edge_labor', 'double_labor', 'plexi_cut', 'pvc', 'pvc_cut', 'glue', 'smd',
        'installation', 'travel', 'transformer', 'use_transformer', 'wire_supplies', 'base', 'profit',
        'insurance_tax_percent', 'insurance_tax',
        'final', 'smd_count', 'smd_density_count', 'smd_component_count', 'smd_length_m', 'smd_purchase_length_m',
        'smd_roll_count', 'smd_power_watts', 'smd_double_track_length_m', 'smd_multi_track_length_m',
        'smd_max_lane_count', 'transformer_count', 'transformer_capacity',
        'rounded_perimeter_m', 'double_perimeter_m', 'pin_perimeter_m', 'laser_perimeter_m',
    );
    $clean = array(
        'version' => 2,
        'calculator_type' => 'letters',
        'source_file' => sanitize_file_name((string) ($snapshot['source_file'] ?? 'طرح.svg')),
        'edge_type' => in_array(($snapshot['edge_type'] ?? ''), array('swedish', 'plastic', 'channelium', 'metal'), true) ? $snapshot['edge_type'] : 'swedish',
        'smd_type' => in_array(($snapshot['smd_type'] ?? ''), array('none', 'block', 'lens', 'roll'), true) ? $snapshot['smd_type'] : 'none',
        'installation_mode' => in_array(($snapshot['installation_mode'] ?? ''), array('fixed', 'perimeter'), true) ? $snapshot['installation_mode'] : 'fixed',
        'allow_rotation' => 1,
        'include_pvc' => 1,
        'include_metal_sheet_07' => (($snapshot['edge_type'] ?? '') === 'metal') ? 1 : 0,
        'inputs' => array(),
        'rates' => array(),
        'analysis' => array(),
        'breakdown' => array(),
        'materials' => array(),
        'layout_previews' => zigurat_sanitize_pricing_layout_previews($snapshot['layout_previews'] ?? array()),
        'smd_preview' => zigurat_sanitize_pricing_image_preview($snapshot['smd_preview'] ?? ''),
        'svg' => zigurat_sanitize_pricing_svg($snapshot['svg'] ?? ''),
        'consumption_modes' => array_values(array_filter(array_map(static function ($mode) {
            return $mode === 'tight' ? 'tight' : ($mode === 'full' ? 'full' : '');
        }, array_slice((array) ($snapshot['consumption_modes'] ?? array()), 0, 100)))),
    );
    // Retain this key only for estimates made before automatic transformer selection.
    if (isset($snapshot['transformer_type']) && in_array((string) $snapshot['transformer_type'], array('200', '300', '400'), true)) {
        $clean['transformer_type'] = (string) $snapshot['transformer_type'];
    }
    foreach ($input_keys as $key) {
        $clean['inputs'][$key] = zigurat_pricing_estimate_number($snapshot['inputs'][$key] ?? 0);
    }
    $layout_trials = (int) ($snapshot['inputs']['layout_trials'] ?? 10);
    $clean['inputs']['layout_trials'] = in_array($layout_trials, array(5, 10, 20, 30), true) ? $layout_trials : 10;
    foreach ($rate_keys as $key) {
        $clean['rates'][$key] = zigurat_pricing_estimate_number($snapshot['rates'][$key] ?? 0);
    }
    foreach ($analysis_keys as $key) {
        $clean['analysis'][$key] = zigurat_pricing_estimate_number($snapshot['analysis'][$key] ?? 0);
    }
    foreach ($breakdown_keys as $key) {
        $clean['breakdown'][$key] = zigurat_pricing_estimate_number($snapshot['breakdown'][$key] ?? 0);
    }
    foreach (array_slice((array) ($snapshot['materials'] ?? array()), 0, 50) as $material) {
        if (!is_array($material)) {
            continue;
        }
        $color = sanitize_hex_color((string) ($material['color'] ?? ''));
        $clean['materials'][] = array(
            'key' => sanitize_key((string) ($material['key'] ?? 'material')),
            'color' => $color ?: '#777777',
            'label' => sanitize_text_field((string) ($material['label'] ?? 'پلکسی')),
            'sheets' => min(100, absint($material['sheets'] ?? 0)),
            'parts' => min(2000, absint($material['parts'] ?? 0)),
            'area_mm2' => zigurat_pricing_estimate_number($material['area_mm2'] ?? 0),
            'consumed_area_mm2' => zigurat_pricing_estimate_number($material['consumed_area_mm2'] ?? 0),
        );
    }
    if ($clean['svg'] === '') {
        return new WP_Error('invalid_svg', 'فایل SVG برای ذخیره این برآورد معتبر نیست.');
    }
    return $clean;
}

/**
 * Read an estimate snapshot and repair records saved before JSON was wp_slash()ed.
 */
function zigurat_get_pricing_estimate_snapshot($estimate_id)
{
    $raw = get_post_meta($estimate_id, '_zigurat_pricing_snapshot', true);
    if (is_array($raw)) {
        return $raw;
    }

    $snapshot = json_decode((string) $raw, true);
    if (is_array($snapshot)) {
        return $snapshot;
    }

    // Older records lost JSON escaping around the embedded SVG when post meta was saved.
    $marker = ',"svg":"';
    $svg_start = strpos((string) $raw, $marker);
    $svg_close = strripos((string) $raw, '</svg>');
    if ($svg_start === false || $svg_close === false) {
        return null;
    }

    $svg_start += strlen($marker);
    $svg_end = $svg_close + strlen('</svg>');
    $svg = substr((string) $raw, $svg_start, $svg_end - $svg_start);
    $without_svg = substr((string) $raw, 0, $svg_start - strlen($marker)) . ',"svg":""}';
    $snapshot = json_decode($without_svg, true);
    if (!is_array($snapshot) || $svg === '') {
        return null;
    }

    $snapshot['svg'] = $svg;
    $snapshot = zigurat_sanitize_letter_estimate_snapshot($snapshot);
    if (is_wp_error($snapshot)) {
        return null;
    }
    update_post_meta($estimate_id, '_zigurat_pricing_snapshot', wp_slash(wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
    return $snapshot;
}

function zigurat_ajax_save_pricing_estimate()
{
    check_ajax_referer('zigurat_pricing_estimates', 'nonce');
    if (!zigurat_is_manager()) {
        wp_send_json_error(array('message' => 'دسترسی مجاز نیست.'), 403);
    }
    $project_name = sanitize_text_field(wp_unslash((string) ($_POST['project_name'] ?? '')));
    if ($project_name === '') {
        wp_send_json_error(array('message' => 'نام پروژه را وارد کنید.'), 400);
    }
    $decoded = json_decode(wp_unslash((string) ($_POST['snapshot'] ?? '')), true);
    $snapshot = zigurat_sanitize_letter_estimate_snapshot($decoded);
    if (is_wp_error($snapshot)) {
        wp_send_json_error(array('message' => $snapshot->get_error_message()), 400);
    }
    $estimate_id = absint($_POST['estimate_id'] ?? 0);
    if ($estimate_id) {
        $existing = get_post($estimate_id);
        if (!$existing || $existing->post_type !== 'zig_price_estimate') {
            wp_send_json_error(array('message' => 'رکورد موردنظر پیدا نشد.'), 404);
        }
    }
    $post_data = array(
        'post_type' => 'zig_price_estimate',
        'post_status' => 'private',
        'post_title' => $project_name,
        'post_author' => get_current_user_id(),
    );
    if ($estimate_id) {
        $post_data['ID'] = $estimate_id;
        $result = wp_update_post($post_data, true);
    } else {
        $result = wp_insert_post($post_data, true);
    }
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 500);
    }
    update_post_meta($result, '_zigurat_pricing_type', 'letters');
    update_post_meta($result, '_zigurat_pricing_final', (int) round($snapshot['breakdown']['final']));
    $perimeter_m = (float) ($snapshot['analysis']['rounded_perimeter_m'] ?? $snapshot['breakdown']['rounded_perimeter_m'] ?? 0);
    update_post_meta($result, '_zigurat_pricing_perimeter', $perimeter_m);
    update_post_meta($result, '_zigurat_pricing_unit_price', $perimeter_m > 0 ? (int) round($snapshot['breakdown']['final'] / $perimeter_m) : 0);
    update_post_meta($result, '_zigurat_pricing_snapshot', wp_slash(wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
    wp_send_json_success(array(
        'id' => (int) $result,
        'message' => $estimate_id ? 'تغییرات برآورد ذخیره شد.' : 'برآورد پروژه ذخیره شد.',
    ));
}
add_action('wp_ajax_zigurat_save_pricing_estimate', 'zigurat_ajax_save_pricing_estimate');

function zigurat_ajax_get_pricing_estimate()
{
    check_ajax_referer('zigurat_pricing_estimates', 'nonce');
    if (!zigurat_is_manager()) {
        wp_send_json_error(array('message' => 'دسترسی مجاز نیست.'), 403);
    }
    $post = get_post(absint($_POST['estimate_id'] ?? 0));
    if (!$post || $post->post_type !== 'zig_price_estimate' || $post->post_status !== 'private') {
        wp_send_json_error(array('message' => 'برآورد موردنظر پیدا نشد.'), 404);
    }
    $snapshot = zigurat_get_pricing_estimate_snapshot($post->ID);
    if (!is_array($snapshot)) {
        wp_send_json_error(array('message' => 'اطلاعات این برآورد کامل نیست.'), 422);
    }
    wp_send_json_success(array(
        'id' => (int) $post->ID,
        'project_name' => get_the_title($post),
        'snapshot' => $snapshot,
    ));
}
add_action('wp_ajax_zigurat_get_pricing_estimate', 'zigurat_ajax_get_pricing_estimate');

function zigurat_ajax_list_pricing_estimates()
{
    check_ajax_referer('zigurat_pricing_estimates', 'nonce');
    if (!zigurat_is_manager()) {
        wp_send_json_error(array('message' => 'دسترسی مجاز نیست.'), 403);
    }
    $page = absint($_POST['page'] ?? 1);
    $search = sanitize_text_field(wp_unslash((string) ($_POST['search'] ?? '')));
    wp_send_json_success(zigurat_get_pricing_estimate_page($page, 10, $search));
}
add_action('wp_ajax_zigurat_list_pricing_estimates', 'zigurat_ajax_list_pricing_estimates');
