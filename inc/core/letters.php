<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_register_letter_post_type()
{
    register_post_type('zig_letter', array(
        'labels' => array(
            'name' => 'نامه‌ها',
            'singular_name' => 'نامه',
        ),
        'public' => false,
        'show_ui' => false,
        'show_in_menu' => false,
        'supports' => array('title', 'editor', 'author'),
        'rewrite' => false,
        'query_var' => false,
    ));
}
add_action('init', 'zigurat_register_letter_post_type', 16);

function zigurat_letters_page_url($args = array())
{
    $args = array_merge(array('manager-section' => 'letters'), $args);
    return add_query_arg($args, zigurat_manager_login_url());
}

function zigurat_letter_type_label($type)
{
    return $type === 'unofficial' ? 'نامه غیررسمی دیاموند' : 'نامه رسمی زیگورات';
}

function zigurat_letter_brand_defaults($type)
{
    if ($type === 'unofficial') {
        return array(
            'header_title' => 'فروشگاه دیاموند',
            'header_subtitle' => 'DIAMOND SIGN',
            'font_size' => 13,
        );
    }

    return array(
        'header_title' => 'گروه معماری زیگورات',
        'header_subtitle' => 'ZIGGURAT Architecture Group',
        'font_size' => 13,
    );
}

function zigurat_letter_stamp_layout_defaults($type)
{
    $type = $type === 'unofficial' ? 'unofficial' : 'official';
    return array(
        'stamp_size_mm' => 34,
        'stamp_x_percent' => 50,
        'stamp_bottom_mm' => 0,
    );
}

function zigurat_letter_stamp_number($value, $minimum, $maximum, $fallback)
{
    $number = is_numeric($value) ? (float) $value : (float) $fallback;
    $number = max((float) $minimum, min((float) $maximum, $number));
    return round($number, 1);
}

function zigurat_letter_get_settings($type)
{
    $type = $type === 'unofficial' ? 'unofficial' : 'official';
    $all_settings = get_option('zigurat_letter_settings', array());
    $settings = isset($all_settings[$type]) && is_array($all_settings[$type]) ? $all_settings[$type] : array();
    return wp_parse_args($settings, array_merge(array('stamp_id' => 0), zigurat_letter_stamp_layout_defaults($type)));
}

function zigurat_letter_stamp_layout($type)
{
    $type = $type === 'unofficial' ? 'unofficial' : 'official';
    $defaults = zigurat_letter_stamp_layout_defaults($type);
    $settings = zigurat_letter_get_settings($type);
    return array(
        'size_mm' => zigurat_letter_stamp_number($settings['stamp_size_mm'] ?? '', 18, 55, $defaults['stamp_size_mm']),
        'x_percent' => zigurat_letter_stamp_number($settings['stamp_x_percent'] ?? '', 10, 90, $defaults['stamp_x_percent']),
        'bottom_mm' => zigurat_letter_stamp_number($settings['stamp_bottom_mm'] ?? '', 0, 25, $defaults['stamp_bottom_mm']),
    );
}

function zigurat_letter_save_settings($data)
{
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'فقط مدیر کل می‌تواند تنظیمات نامه‌ها را تغییر دهد.');
    }

    $all_settings = get_option('zigurat_letter_settings', array());
    foreach (array('official', 'unofficial') as $type) {
        $settings = zigurat_letter_get_settings($type);
        $layout_defaults = zigurat_letter_stamp_layout_defaults($type);
        $settings['stamp_size_mm'] = zigurat_letter_stamp_number(
            $data['letter_' . $type . '_stamp_size_mm'] ?? ($settings['stamp_size_mm'] ?? ''),
            18,
            55,
            $layout_defaults['stamp_size_mm']
        );
        $settings['stamp_x_percent'] = zigurat_letter_stamp_number(
            $data['letter_' . $type . '_stamp_x_percent'] ?? ($settings['stamp_x_percent'] ?? ''),
            10,
            90,
            $layout_defaults['stamp_x_percent']
        );
        $settings['stamp_bottom_mm'] = zigurat_letter_stamp_number(
            $data['letter_' . $type . '_stamp_bottom_mm'] ?? ($settings['stamp_bottom_mm'] ?? ''),
            0,
            25,
            $layout_defaults['stamp_bottom_mm']
        );
        if (!empty($data['letter_' . $type . '_remove_stamp'])) {
            $settings['stamp_id'] = 0;
        }

        $upload_key = 'letter_' . $type . '_stamp_file';
        if (!empty($_FILES[$upload_key]['name']) && (int) ($_FILES[$upload_key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attachment_id = media_handle_upload($upload_key, 0);
            if (is_wp_error($attachment_id)) {
                return new WP_Error('stamp_upload', 'آپلود مهر انجام نشد: ' . $attachment_id->get_error_message());
            }
            if (strpos((string) get_post_mime_type($attachment_id), 'image/') !== 0) {
                wp_delete_attachment($attachment_id, true);
                return new WP_Error('stamp_type', 'فایل مهر باید تصویر باشد.');
            }
            $settings['stamp_id'] = (int) $attachment_id;
        }
        $all_settings[$type] = $settings;
    }
    update_option('zigurat_letter_settings', $all_settings, false);
    return true;
}

function zigurat_letter_persian_digits($value)
{
    return strtr((string) $value, array(
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        '٠' => '۰', '١' => '۱', '٢' => '۲', '٣' => '۳', '٤' => '۴',
        '٥' => '۵', '٦' => '۶', '٧' => '۷', '٨' => '۸', '٩' => '۹',
    ));
}

function zigurat_letter_persian_digits_html($html)
{
    $parts = preg_split('/(<[^>]+>)/u', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) {
        return zigurat_letter_persian_digits($html);
    }
    foreach ($parts as $index => $part) {
        if ($part === '' || $part[0] === '<') {
            continue;
        }
        $parts[$index] = zigurat_letter_persian_digits($part);
    }
    return implode('', $parts);
}

function zigurat_letter_get($letter_id)
{
    $post = get_post(absint($letter_id));
    if (!$post || $post->post_type !== 'zig_letter' || $post->post_status === 'trash') {
        return null;
    }

    $type = get_post_meta($post->ID, '_zigurat_letter_type', true);
    if (!in_array($type, array('official', 'unofficial'), true)) {
        $type = 'official';
    }
    $defaults = zigurat_letter_brand_defaults($type);
    $header_title = trim((string) get_post_meta($post->ID, '_zigurat_letter_header_title', true));
    $header_subtitle = trim((string) get_post_meta($post->ID, '_zigurat_letter_header_subtitle', true));
    $letter_settings = zigurat_letter_get_settings($type);
    $stamp_id = absint(get_post_meta($post->ID, '_zigurat_letter_stamp_id', true));
    if (!$stamp_id) {
        $stamp_id = absint($letter_settings['stamp_id'] ?? 0);
    }
    $stored_signer_name = (string) get_post_meta($post->ID, '_zigurat_letter_signer_name', true);
    $stored_signer_title = (string) get_post_meta($post->ID, '_zigurat_letter_signer_title', true);
    $signature_schema = (int) get_post_meta($post->ID, '_zigurat_letter_signature_schema', true);
    if ($signature_schema < 2) {
        $stored_signer_title = $stored_signer_name;
        $stored_signer_name = 'با احترام';
    }
    $font_size = (float) get_post_meta($post->ID, '_zigurat_letter_font_size', true);
    if ($font_size < 9 || $font_size > 22) {
        $font_size = (float) $defaults['font_size'];
    }

    return (object) array(
        'id' => (int) $post->ID,
        'type' => $type,
        'number' => (string) get_post_meta($post->ID, '_zigurat_letter_number', true),
        'issue_date' => (string) get_post_meta($post->ID, '_zigurat_letter_date', true),
        'recipient' => (string) get_post_meta($post->ID, '_zigurat_letter_recipient', true),
        'subject' => (string) get_post_meta($post->ID, '_zigurat_letter_subject', true),
        'attachment' => (string) get_post_meta($post->ID, '_zigurat_letter_attachment', true),
        'greeting' => (string) get_post_meta($post->ID, '_zigurat_letter_greeting', true),
        'body' => (string) $post->post_content,
        'signer_name' => $stored_signer_name,
        'signer_title' => $stored_signer_title,
        'stamp_id' => $stamp_id,
        'include_stamp' => (bool) get_post_meta($post->ID, '_zigurat_letter_include_stamp', true),
        'header_title' => $header_title !== '' ? $header_title : $defaults['header_title'],
        'header_subtitle' => $header_subtitle !== '' ? $header_subtitle : $defaults['header_subtitle'],
        'font_size' => $font_size,
        'author_id' => (int) $post->post_author,
        'created_at' => (string) $post->post_date,
        'updated_at' => (string) $post->post_modified,
    );
}

function zigurat_handle_manager_letter_settings_save()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['zigurat_save_letter_settings'])) {
        return;
    }
    $section = isset($_REQUEST['manager-section']) ? sanitize_key(wp_unslash($_REQUEST['manager-section'])) : '';
    if ($section !== 'letters' || !(is_page('login') || is_page_template('page-login.php'))) {
        return;
    }
    $nonce = isset($_POST['zigurat_letter_settings_nonce']) ? sanitize_text_field(wp_unslash($_POST['zigurat_letter_settings_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'zigurat_save_letter_settings')) {
        wp_die('درخواست ذخیره تنظیمات معتبر نیست. صفحه را تازه‌سازی کنید.', 'خطا', array('response' => 403));
    }
    $saved = zigurat_letter_save_settings($_POST);
    $status = is_wp_error($saved) ? 'settings-error' : 'settings-saved';
    $args = array('letter-view' => 'settings', 'letter-status' => $status);
    if (is_wp_error($saved)) {
        $args['letter-error'] = $saved->get_error_message();
    }
    wp_safe_redirect(zigurat_letters_page_url($args));
    exit;
}
add_action('template_redirect', 'zigurat_handle_manager_letter_settings_save', 7);

function zigurat_letter_ajax_save_stamp_layout()
{
    check_ajax_referer('zigurat_letter_stamp_layout', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'فقط مدیر کل می‌تواند جای مهر نامه را تغییر دهد.'), 403);
    }

    $type = isset($_POST['letter_type']) ? sanitize_key(wp_unslash($_POST['letter_type'])) : 'official';
    $type = $type === 'unofficial' ? 'unofficial' : 'official';
    $defaults = zigurat_letter_stamp_layout_defaults($type);
    $layout = array(
        'stamp_size_mm' => zigurat_letter_stamp_number($_POST['size_mm'] ?? '', 18, 55, $defaults['stamp_size_mm']),
        'stamp_x_percent' => zigurat_letter_stamp_number($_POST['x_percent'] ?? '', 10, 90, $defaults['stamp_x_percent']),
        'stamp_bottom_mm' => zigurat_letter_stamp_number($_POST['bottom_mm'] ?? '', 0, 25, $defaults['stamp_bottom_mm']),
    );

    $all_settings = get_option('zigurat_letter_settings', array());
    $settings = zigurat_letter_get_settings($type);
    $settings = array_merge($settings, $layout);
    $all_settings[$type] = $settings;
    update_option('zigurat_letter_settings', $all_settings, false);

    wp_send_json_success(array(
        'layout' => array(
            'size_mm' => $layout['stamp_size_mm'],
            'x_percent' => $layout['stamp_x_percent'],
            'bottom_mm' => $layout['stamp_bottom_mm'],
        ),
        'message' => 'اندازه و جای مهر نامه ذخیره شد.',
    ));
}
add_action('wp_ajax_zigurat_letter_save_stamp_layout', 'zigurat_letter_ajax_save_stamp_layout');

function zigurat_letter_today()
{
    if (function_exists('zigurat_invoice_today_jalali')) {
        return zigurat_invoice_today_jalali();
    }
    return current_time('Y/m/d');
}

function zigurat_letter_next_number($type)
{
    $type = $type === 'unofficial' ? 'unofficial' : 'official';
    $option_key = 'zigurat_letter_sequence_' . $type;
    $last_number = max(0, (int) get_option($option_key, 0));
    $latest_ids = get_posts(array(
        'post_type' => 'zig_letter',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => array(
            array('key' => '_zigurat_letter_type', 'value' => $type),
        ),
        'meta_key' => '_zigurat_letter_number',
        'orderby' => 'meta_value_num',
        'order' => 'DESC',
        'no_found_rows' => true,
    ));
    if ($latest_ids) {
        $last_number = max($last_number, (int) get_post_meta((int) $latest_ids[0], '_zigurat_letter_number', true));
    }
    $next = $last_number + 1;
    update_option($option_key, $next, false);
    return str_pad((string) $next, 3, '0', STR_PAD_LEFT);
}

function zigurat_letter_number_exists($type, $number, $exclude_id = 0)
{
    $ids = get_posts(array(
        'post_type' => 'zig_letter',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'post__not_in' => $exclude_id ? array(absint($exclude_id)) : array(),
        'meta_query' => array(
            'relation' => 'AND',
            array('key' => '_zigurat_letter_type', 'value' => $type),
            array('key' => '_zigurat_letter_number', 'value' => $number),
        ),
        'no_found_rows' => true,
    ));
    return !empty($ids);
}

function zigurat_handle_manager_letter_save()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['zigurat_save_letter'])) {
        return;
    }
    $section = isset($_REQUEST['manager-section']) ? sanitize_key(wp_unslash($_REQUEST['manager-section'])) : '';
    if ($section !== 'letters' || !(is_page('login') || is_page_template('page-login.php'))) {
        return;
    }
    if (!zigurat_is_manager()) {
        wp_die('برای ثبت نامه دسترسی ندارید.', 'خطا', array('response' => 403));
    }
    $nonce = isset($_POST['zigurat_letter_nonce']) ? sanitize_text_field(wp_unslash($_POST['zigurat_letter_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'zigurat_save_letter')) {
        wp_die('درخواست ثبت نامه معتبر نیست. صفحه را تازه‌سازی کنید.', 'خطا', array('response' => 403));
    }

    $letter_id = isset($_POST['letter_id']) ? absint($_POST['letter_id']) : 0;
    $existing = $letter_id ? zigurat_letter_get($letter_id) : null;
    if ($letter_id && !$existing) {
        wp_safe_redirect(zigurat_letters_page_url(array('letter-status' => 'missing')));
        exit;
    }

    $type = isset($_POST['letter_type']) ? sanitize_key(wp_unslash($_POST['letter_type'])) : 'official';
    if (!in_array($type, array('official', 'unofficial'), true)) {
        $type = 'official';
    }
    if ($existing) {
        $type = $existing->type;
    }

    $number = isset($_POST['letter_number']) ? sanitize_text_field(wp_unslash($_POST['letter_number'])) : '';
    if (function_exists('zigurat_invoice_normalize_digits')) {
        $number = zigurat_invoice_normalize_digits($number);
    }
    $number = preg_replace('/[^0-9A-Za-z\x{0600}-\x{06FF}\/\-_]/u', '', $number);
    if ($number === '') {
        $number = $existing ? $existing->number : zigurat_letter_next_number($type);
    }
    if (zigurat_letter_number_exists($type, $number, $letter_id)) {
        wp_safe_redirect(zigurat_letters_page_url(array(
            'letter-view' => $letter_id ? 'edit' : 'new',
            'letter-type' => $type,
            'letter-id' => $letter_id,
            'letter-status' => 'duplicate',
        )));
        exit;
    }

    $issue_date = isset($_POST['letter_date']) ? sanitize_text_field(wp_unslash($_POST['letter_date'])) : zigurat_letter_today();
    if (function_exists('zigurat_invoice_normalize_digits')) {
        $issue_date = zigurat_invoice_normalize_digits($issue_date);
    }
    if (!preg_match('/^[0-9]{4}\/[0-9]{1,2}\/[0-9]{1,2}$/', $issue_date)) {
        $issue_date = zigurat_letter_today();
    }
    $recipient = isset($_POST['letter_recipient']) ? sanitize_text_field(wp_unslash($_POST['letter_recipient'])) : '';
    $subject = isset($_POST['letter_subject']) ? sanitize_text_field(wp_unslash($_POST['letter_subject'])) : '';
    $attachment = isset($_POST['letter_attachment']) ? sanitize_text_field(wp_unslash($_POST['letter_attachment'])) : 'ندارد';
    $greeting = isset($_POST['letter_greeting']) ? sanitize_text_field(wp_unslash($_POST['letter_greeting'])) : 'با سلام و احترام';
    $body = isset($_POST['letter_body']) ? wp_kses_post(wp_unslash($_POST['letter_body'])) : '';
    $signer_name = isset($_POST['letter_signer_name']) ? sanitize_text_field(wp_unslash($_POST['letter_signer_name'])) : '';
    $signer_title = isset($_POST['letter_signer_title']) ? sanitize_text_field(wp_unslash($_POST['letter_signer_title'])) : '';
    $letter_settings = zigurat_letter_get_settings($type);
    $stamp_id = absint($letter_settings['stamp_id'] ?? 0);
    $include_stamp = !empty($_POST['include_stamp']) && $stamp_id ? 1 : 0;
    $brand_defaults = zigurat_letter_brand_defaults($type);
    $header_title = isset($_POST['letter_header_title']) ? sanitize_text_field(wp_unslash($_POST['letter_header_title'])) : $brand_defaults['header_title'];
    $header_subtitle = isset($_POST['letter_header_subtitle']) ? sanitize_text_field(wp_unslash($_POST['letter_header_subtitle'])) : $brand_defaults['header_subtitle'];
    $font_size = isset($_POST['letter_font_size']) ? (float) wp_unslash($_POST['letter_font_size']) : (float) $brand_defaults['font_size'];
    $font_size = max(9, min(22, round($font_size * 2) / 2));

    if ($recipient === '' || $subject === '' || trim(wp_strip_all_tags($body)) === '') {
        wp_safe_redirect(zigurat_letters_page_url(array(
            'letter-view' => $letter_id ? 'edit' : 'new',
            'letter-type' => $type,
            'letter-id' => $letter_id,
            'letter-status' => 'required',
        )));
        exit;
    }

    $post_data = array(
        'post_type' => 'zig_letter',
        'post_status' => 'publish',
        'post_title' => implode('-', array_filter(array($number, $recipient, $subject))),
        'post_content' => $body,
        'post_author' => $existing ? $existing->author_id : get_current_user_id(),
    );
    if ($letter_id) {
        $post_data['ID'] = $letter_id;
    }
    $saved_id = wp_insert_post(wp_slash($post_data), true);
    if (is_wp_error($saved_id)) {
        wp_safe_redirect(zigurat_letters_page_url(array('letter-status' => 'error')));
        exit;
    }

    $meta = array(
        '_zigurat_letter_type' => $type,
        '_zigurat_letter_number' => $number,
        '_zigurat_letter_date' => $issue_date,
        '_zigurat_letter_recipient' => $recipient,
        '_zigurat_letter_subject' => $subject,
        '_zigurat_letter_attachment' => $attachment,
        '_zigurat_letter_greeting' => $greeting,
        '_zigurat_letter_signer_name' => $signer_name,
        '_zigurat_letter_signer_title' => $signer_title,
        '_zigurat_letter_signature_schema' => 2,
        '_zigurat_letter_stamp_id' => $stamp_id,
        '_zigurat_letter_include_stamp' => $include_stamp,
        '_zigurat_letter_header_title' => $header_title !== '' ? $header_title : $brand_defaults['header_title'],
        '_zigurat_letter_header_subtitle' => $header_subtitle !== '' ? $header_subtitle : $brand_defaults['header_subtitle'],
        '_zigurat_letter_font_size' => $font_size,
    );
    foreach ($meta as $key => $value) {
        update_post_meta($saved_id, $key, $value);
    }

    wp_safe_redirect(zigurat_letters_page_url(array(
        'letter-view' => 'edit',
        'letter-id' => (int) $saved_id,
        'letter-status' => 'saved',
    )));
    exit;
}
add_action('template_redirect', 'zigurat_handle_manager_letter_save', 8);

function zigurat_maybe_render_letter_print()
{
    $section = isset($_GET['manager-section']) ? sanitize_key(wp_unslash($_GET['manager-section'])) : '';
    $view = isset($_GET['letter-view']) ? sanitize_key(wp_unslash($_GET['letter-view'])) : '';
    if ($section !== 'letters' || $view !== 'print' || !(is_page('login') || is_page_template('page-login.php'))) {
        return;
    }
    if (!zigurat_is_manager()) {
        wp_safe_redirect(zigurat_manager_login_url());
        exit;
    }
    $letter = zigurat_letter_get(isset($_GET['letter-id']) ? absint($_GET['letter-id']) : 0);
    if (!$letter) {
        wp_die('نامه موردنظر پیدا نشد.', 'خطا', array('response' => 404));
    }

    nocache_headers();
    $template = locate_template('template-parts/letter-print.php');
    if (!$template) {
        wp_die('قالب چاپ نامه پیدا نشد.', 'خطا', array('response' => 500));
    }
    include $template;
    exit;
}
add_action('template_redirect', 'zigurat_maybe_render_letter_print', 12);
