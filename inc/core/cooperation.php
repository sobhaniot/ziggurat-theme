<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_register_partner_application_post_type()
{
    $labels = array(
        'name'          => 'درخواست‌های همکاری',
        'singular_name' => 'درخواست همکاری',
        'menu_name'     => 'درخواست‌های همکاری',
        'all_items'     => 'همه درخواست‌ها',
        'edit_item'     => 'مشاهده درخواست',
        'search_items'  => 'جستجوی درخواست‌ها',
        'not_found'     => 'درخواستی ثبت نشده است',
    );
    $manager_caps = array(
        'edit_post'              => 'manage_options',
        'read_post'              => 'manage_options',
        'delete_post'            => 'manage_options',
        'edit_posts'             => 'manage_options',
        'edit_others_posts'      => 'manage_options',
        'publish_posts'          => 'manage_options',
        'read_private_posts'     => 'manage_options',
        'delete_posts'           => 'manage_options',
        'delete_private_posts'   => 'manage_options',
        'delete_published_posts' => 'manage_options',
        'delete_others_posts'    => 'manage_options',
        'edit_private_posts'     => 'manage_options',
        'edit_published_posts'   => 'manage_options',
        'create_posts'           => 'do_not_allow',
    );
    register_post_type('partner_application', array(
        'labels'              => $labels,
        'public'              => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => false,
        'menu_icon'           => 'dashicons-groups',
        'supports'            => array('title'),
        'capabilities'        => $manager_caps,
        'map_meta_cap'        => false,
    ));
}
add_action('init', 'zigurat_register_partner_application_post_type');

function zigurat_application_type_label($type)
{
    return $type === 'supplier' ? 'تأمین‌کننده' : 'همکار اجرایی';
}

function zigurat_application_private_file_url($application_id, $token)
{
    return wp_nonce_url(
        admin_url('admin-post.php?action=zigurat_private_application_file&application=' . absint($application_id) . '&file=' . rawurlencode($token)),
        'zigurat_application_file_' . absint($application_id)
    );
}

function zigurat_application_resume_url($application_id)
{
    $application_id = absint($application_id);
    return wp_nonce_url(
        add_query_arg(
            array(
                'manager-section' => 'application-detail',
                'application_id'  => $application_id,
            ),
            home_url('/login/')
        ),
        'zigurat_view_application_' . $application_id,
        'application_nonce'
    );
}

function zigurat_application_fields()
{
    return array(
        'application_type' => 'نوع درخواست',
        'first_name'       => 'نام',
        'last_name'        => 'نام خانوادگی',
        'business_name'    => 'نام مجموعه/کارگاه',
        'phone'            => 'شماره تماس',
        'email'            => 'ایمیل',
        'profession'       => 'زمینه فعالیت',
        'experience_years' => 'سال سابقه',
        'province'         => 'استان محل سکونت/فعالیت',
        'city'             => 'شهر محل سکونت/فعالیت',
        'work_cities'      => 'شهرهای قابل همکاری',
        'nationwide'       => 'امکان همکاری سراسر ایران',
        'description'      => 'توضیحات و امکانات',
        'submitted_at'     => 'زمان ثبت',
    );
}

/** تمام کاربرانی که اجازه ورود به پنل اختصاصی مدیران را دارند. */
function zigurat_application_manager_users()
{
    return array_values(array_filter(get_users(array('fields' => 'all')), static function ($user) {
        if (function_exists('zigurat_user_can_access_manager_panel')) {
            return zigurat_user_can_access_manager_panel($user);
        }
        return $user instanceof WP_User && user_can($user, 'manage_options');
    }));
}

function zigurat_application_seen_ids($user_id = 0)
{
    $user_id = $user_id ? absint($user_id) : get_current_user_id();
    $seen = $user_id ? get_user_meta($user_id, '_zigurat_seen_partner_applications', true) : array();
    return is_array($seen) ? array_values(array_unique(array_filter(array_map('absint', $seen)))) : array();
}

/** شناسه درخواست‌های جدید فقط برای مدیر جاری؛ خواندن یک مدیر روی دیگری اثر ندارد. */
function zigurat_application_unread_ids($user_id = 0)
{
    $user_id = $user_id ? absint($user_id) : get_current_user_id();
    if (!$user_id) {
        return array();
    }
    $notifiable_ids = get_posts(array(
        'post_type' => 'partner_application',
        'post_status' => 'private',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'orderby' => 'date',
        'order' => 'DESC',
        'no_found_rows' => true,
        'meta_key' => '_application_notify_managers',
        'meta_value' => '1',
    ));
    return array_values(array_diff(array_map('absint', $notifiable_ids), zigurat_application_seen_ids($user_id)));
}

function zigurat_application_unread_count($user_id = 0)
{
    return count(zigurat_application_unread_ids($user_id));
}

function zigurat_application_is_unread($application_id, $user_id = 0)
{
    return in_array(absint($application_id), zigurat_application_unread_ids($user_id), true);
}

function zigurat_application_mark_seen($application_id, $user_id = 0)
{
    $application_id = absint($application_id);
    $user_id = $user_id ? absint($user_id) : get_current_user_id();
    $application = $application_id ? get_post($application_id) : null;
    if (!$user_id || !$application || $application->post_type !== 'partner_application') {
        return false;
    }
    $seen = zigurat_application_seen_ids($user_id);
    if (!in_array($application_id, $seen, true)) {
        $seen[] = $application_id;
        update_user_meta($user_id, '_zigurat_seen_partner_applications', $seen);
    }
    return true;
}

/** ایمیل مستقل برای هر مدیر، بدون پیوست‌کردن مدارک محرمانه. */
function zigurat_email_managers_for_application($application_id, $data)
{
    $application_id = absint($application_id);
    $display_name = trim((string) ($data['first_name'] ?? '') . ' ' . (string) ($data['last_name'] ?? ''));
    $type_label = zigurat_application_type_label($data['application_type'] ?? 'collaborator');
    $list_url = add_query_arg('manager-section', 'applications', zigurat_manager_login_url());
    $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $subject = sprintf('[%s] درخواست همکاری جدید: %s', $site_name, $display_name ?: $type_label);
    $recipients = array();
    foreach (zigurat_application_manager_users() as $manager) {
        $email = sanitize_email($manager->user_email);
        if ($email === '' || isset($recipients[strtolower($email)])) {
            continue;
        }
        $recipients[strtolower($email)] = true;
        $manager_name = trim((string) $manager->display_name) ?: $manager->user_login;
        $message = $manager_name . " عزیز،\n\n";
        $message .= "یک درخواست همکاری جدید در سایت ثبت شده است.\n";
        $message .= 'متقاضی: ' . ($display_name ?: 'ثبت نشده') . "\n";
        $message .= 'نوع: ' . $type_label . "\n";
        $message .= 'زمینه فعالیت: ' . ((string) ($data['profession'] ?? '') ?: 'ثبت نشده') . "\n";
        $message .= 'محل فعالیت: ' . trim((string) ($data['province'] ?? '') . '، ' . (string) ($data['city'] ?? ''), '، ') . "\n\n";
        $message .= "برای بررسی اطلاعات و مدارک، وارد پنل مدیران شوید:\n" . $list_url;
        wp_mail($email, $subject, $message, array('Content-Type: text/plain; charset=UTF-8'));
    }
    if (!$recipients) {
        $fallback_email = sanitize_email(get_option('admin_email'));
        if ($fallback_email !== '') {
            wp_mail(
                $fallback_email,
                $subject,
                "یک درخواست همکاری جدید ثبت شده است.\n\n" . $list_url,
                array('Content-Type: text/plain; charset=UTF-8')
            );
        }
    }
}

/** بازکردن رزومه، اعلان همان درخواست را فقط برای مدیر جاری خوانده‌شده می‌کند. */
function zigurat_mark_application_seen_from_resume()
{
    if (!function_exists('zigurat_is_manager') || !zigurat_is_manager()) {
        return;
    }
    $section = isset($_GET['manager-section']) ? sanitize_key(wp_unslash((string) $_GET['manager-section'])) : '';
    $application_id = isset($_GET['application_id']) ? absint($_GET['application_id']) : 0;
    $nonce = isset($_GET['application_nonce']) ? sanitize_text_field(wp_unslash((string) $_GET['application_nonce'])) : '';
    if (
        $section === 'application-detail'
        && $application_id
        && wp_verify_nonce($nonce, 'zigurat_view_application_' . $application_id)
    ) {
        zigurat_application_mark_seen($application_id);
    }
}
add_action('template_redirect', 'zigurat_mark_application_seen_from_resume', 25);

function zigurat_mark_application_seen_in_wp_admin()
{
    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
    if ($post_id && get_post_type($post_id) === 'partner_application' && current_user_can('manage_options')) {
        zigurat_application_mark_seen($post_id);
    }
}
add_action('load-post.php', 'zigurat_mark_application_seen_in_wp_admin');

function zigurat_application_meta_box()
{
    add_meta_box('zigurat_application_details', 'اطلاعات درخواست', 'zigurat_render_application_meta_box', 'partner_application', 'normal', 'high');
}
add_action('add_meta_boxes_partner_application', 'zigurat_application_meta_box');

function zigurat_render_application_meta_box($post)
{
    echo '<table class="widefat striped"><tbody>';
    foreach (zigurat_application_fields() as $key => $label) {
        $value = get_post_meta($post->ID, '_application_' . $key, true);
        if ($key === 'application_type') {
            $value = zigurat_application_type_label($value);
        } elseif ($key === 'nationwide') {
            $value = $value ? 'بله' : 'خیر';
        }
        echo '<tr><th style="width:220px">' . esc_html($label) . '</th><td>' . nl2br(esc_html($value ?: '—')) . '</td></tr>';
    }
    echo '</tbody></table>';

    $files = get_post_meta($post->ID, '_application_files', true);
    if (!is_array($files)) {
        return;
    }
    $file_labels = array('photo' => 'عکس متقاضی/مجموعه', 'national_card' => 'عکس کارت ملی', 'portfolio' => 'نمونه‌کارها');
    echo '<h3>فایل‌های خصوصی</h3><ul>';
    foreach ($file_labels as $group => $label) {
        if (empty($files[$group]) || !is_array($files[$group])) {
            continue;
        }
        foreach ($files[$group] as $index => $file) {
            $token = $group . ':' . $index;
            $url = zigurat_application_private_file_url($post->ID, $token);
            echo '<li><strong>' . esc_html($label) . ':</strong> <a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($file['name'] ?? 'مشاهده فایل') . '</a></li>';
        }
    }
    echo '</ul><p class="description">این فایل‌ها عمومی نیستند و فقط از مسیر امن مدیریت باز می‌شوند.</p>';
}

function zigurat_application_columns($columns)
{
    return array(
        'cb'         => $columns['cb'],
        'title'      => 'متقاضی',
        'app_type'   => 'نوع',
        'profession' => 'زمینه فعالیت',
        'location'   => 'محل فعالیت',
        'phone'      => 'تماس',
        'date'       => 'تاریخ',
    );
}
add_filter('manage_partner_application_posts_columns', 'zigurat_application_columns');

function zigurat_application_column_content($column, $post_id)
{
    if ($column === 'app_type') {
        echo esc_html(zigurat_application_type_label(get_post_meta($post_id, '_application_application_type', true)));
    } elseif ($column === 'profession') {
        echo esc_html(get_post_meta($post_id, '_application_profession', true));
    } elseif ($column === 'location') {
        echo esc_html(trim(get_post_meta($post_id, '_application_province', true) . '، ' . get_post_meta($post_id, '_application_city', true), '، '));
    } elseif ($column === 'phone') {
        echo esc_html(get_post_meta($post_id, '_application_phone', true));
    }
}
add_action('manage_partner_application_posts_custom_column', 'zigurat_application_column_content', 10, 2);

function zigurat_private_application_directory()
{
    $uploads = wp_upload_dir();
    return trailingslashit($uploads['basedir']) . 'zigurat-private-applications';
}

function zigurat_prepare_private_application_directory()
{
    $directory = zigurat_private_application_directory();
    if (!is_dir($directory) && !wp_mkdir_p($directory)) {
        return new WP_Error('directory', 'ساخت پوشه امن ممکن نیست.');
    }
    if (!file_exists($directory . '/.htaccess')) {
        file_put_contents($directory . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    if (!file_exists($directory . '/index.php')) {
        file_put_contents($directory . '/index.php', "<?php exit;\n");
    }
    return $directory;
}

/** پوشه محرمانه را پیش از اولین ثبت‌نام آماده می‌کند. */
add_action('init', function () {
    if (get_option('zigurat_private_application_storage_version') === '1') {
        return;
    }
    $directory = zigurat_prepare_private_application_directory();
    if (!is_wp_error($directory)) {
        update_option('zigurat_private_application_storage_version', '1', false);
    }
}, 70);

function zigurat_normalize_uploads($files)
{
    if (!isset($files['name'])) {
        return array();
    }
    if (!is_array($files['name'])) {
        return array($files);
    }
    $normalized = array();
    foreach ($files['name'] as $index => $name) {
        $normalized[] = array(
            'name'     => $name,
            'type'     => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error'    => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $files['size'][$index] ?? 0,
        );
    }
    return $normalized;
}

function zigurat_store_private_application_file($file, $group)
{
    if (
        ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || empty($file['tmp_name'])
        || !is_string($file['tmp_name'])
        || empty($file['name'])
        || !is_string($file['name'])
        || !is_uploaded_file($file['tmp_name'])
    ) {
        return new WP_Error('upload', 'بارگذاری فایل کامل نشد.');
    }
    if ((int) $file['size'] > 5 * MB_IN_BYTES) {
        return new WP_Error('size', 'حجم هر فایل باید کمتر از ۵ مگابایت باشد.');
    }
    $allowed = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp');
    if ($group === 'portfolio') {
        $allowed['pdf'] = 'application/pdf';
    }
    $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed);
    if (empty($checked['ext']) || empty($checked['type'])) {
        return new WP_Error('type', 'فرمت فایل مجاز نیست.');
    }
    $directory = zigurat_prepare_private_application_directory();
    if (is_wp_error($directory)) {
        return $directory;
    }
    $safe_name = wp_unique_filename($directory, wp_generate_uuid4() . '.' . $checked['ext']);
    $destination = trailingslashit($directory) . $safe_name;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return new WP_Error('move', 'ذخیره فایل انجام نشد.');
    }
    return array(
        'path' => $destination,
        'name' => sanitize_file_name($file['name']),
        'mime' => $checked['type'],
    );
}

function zigurat_application_request_value($key, $textarea = false)
{
    if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
        return '';
    }
    $value = wp_unslash($_POST[$key]);
    return $textarea ? sanitize_textarea_field($value) : sanitize_text_field($value);
}

function zigurat_delete_application_files($files)
{
    foreach ((array) $files as $group) {
        foreach ((array) $group as $file) {
            if (!empty($file['path']) && is_file($file['path'])) {
                unlink($file['path']);
            }
        }
    }
}

function zigurat_handle_partner_application()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['zigurat_partner_application'])) {
        return;
    }
    $redirect = get_permalink(get_page_by_path('cooperation'));
    if (!$redirect) {
        $redirect = home_url('/cooperation/');
    }
    $type = isset($_POST['application_type']) && is_string($_POST['application_type']) && $_POST['application_type'] === 'supplier' ? 'supplier' : 'collaborator';
    $redirect = add_query_arg('type', $type, $redirect);
    $status_redirect = function ($status) use ($redirect) {
        wp_safe_redirect(add_query_arg('application-status', $status, $redirect) . '#application-form');
        exit;
    };
    if (!empty($_POST['company_website'])) {
        $status_redirect('sent');
    }
    if (
        empty($_POST['zigurat_application_nonce'])
        || !is_string($_POST['zigurat_application_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zigurat_application_nonce'])), 'zigurat_partner_application')
    ) {
        $status_redirect('invalid');
    }
    $remote_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $rate_key = 'zigurat_application_' . md5($remote_address);
    if ((int) get_transient($rate_key) >= 3) {
        $status_redirect('limited');
    }

    $data = array(
        'application_type' => $type,
        'first_name'       => zigurat_application_request_value('first_name'),
        'last_name'        => zigurat_application_request_value('last_name'),
        'business_name'    => zigurat_application_request_value('business_name'),
        'phone'            => zigurat_application_request_value('phone'),
        'email'            => sanitize_email(zigurat_application_request_value('email')),
        'profession'       => zigurat_application_request_value('profession'),
        'experience_years' => absint(zigurat_application_request_value('experience_years')),
        'province'         => zigurat_application_request_value('province'),
        'city'             => zigurat_application_request_value('city'),
        'work_cities'      => zigurat_application_request_value('work_cities', true),
        'nationwide'       => !empty($_POST['nationwide']) ? '1' : '0',
        'description'      => zigurat_application_request_value('description', true),
        'submitted_at'     => current_time('mysql'),
    );
    if (!$data['first_name'] || !$data['last_name'] || !$data['phone'] || !$data['profession'] || !$data['province'] || !$data['city'] || empty($_POST['privacy_consent'])) {
        $status_redirect('invalid');
    }

    $photo_files = isset($_FILES['applicant_photo']) ? zigurat_normalize_uploads($_FILES['applicant_photo']) : array();
    $card_files = $type !== 'supplier' && isset($_FILES['national_card'])
        ? zigurat_normalize_uploads($_FILES['national_card'])
        : array();
    $portfolio_files = isset($_FILES['portfolio']) ? array_slice(zigurat_normalize_uploads($_FILES['portfolio']), 0, 5) : array();
    if (!$photo_files || !$portfolio_files || ($type !== 'supplier' && !$card_files)) {
        $status_redirect('upload-error');
    }

    $stored_files = array('photo' => array(), 'national_card' => array(), 'portfolio' => array());
    $upload_groups = array('photo' => $photo_files, 'portfolio' => $portfolio_files);
    if ($type !== 'supplier') {
        $upload_groups['national_card'] = $card_files;
    }
    foreach ($upload_groups as $group => $uploads) {
        foreach ($uploads as $upload) {
            $stored = zigurat_store_private_application_file($upload, $group);
            if (is_wp_error($stored)) {
                zigurat_delete_application_files($stored_files);
                $status_redirect('upload-error');
            }
            $stored_files[$group][] = $stored;
        }
    }

    $display_name = trim($data['first_name'] . ' ' . $data['last_name']);
    $post_id = wp_insert_post(array(
        'post_type'   => 'partner_application',
        'post_status' => 'private',
        'post_title'  => $display_name . ' — ' . zigurat_application_type_label($type),
    ), true);
    if (is_wp_error($post_id)) {
        zigurat_delete_application_files($stored_files);
        $status_redirect('error');
    }
    foreach ($data as $key => $value) {
        update_post_meta($post_id, '_application_' . $key, $value);
    }
    update_post_meta($post_id, '_application_files', $stored_files);
    update_post_meta($post_id, '_application_notify_managers', '1');
    set_transient($rate_key, ((int) get_transient($rate_key)) + 1, HOUR_IN_SECONDS);
    zigurat_email_managers_for_application($post_id, $data);
    $status_redirect('sent');
}
add_action('template_redirect', 'zigurat_handle_partner_application');

function zigurat_download_private_application_file()
{
    if (!zigurat_is_manager()) {
        wp_die('دسترسی غیرمجاز.', 403);
    }
    $application_id = isset($_GET['application']) && is_string($_GET['application']) ? absint($_GET['application']) : 0;
    check_admin_referer('zigurat_application_file_' . $application_id);
    $token = isset($_GET['file']) && is_string($_GET['file']) ? sanitize_text_field(wp_unslash($_GET['file'])) : '';
    if (!preg_match('/^(photo|national_card|portfolio):(\d+)$/', $token, $matches)) {
        wp_die('فایل معتبر نیست.', 400);
    }
    $files = get_post_meta($application_id, '_application_files', true);
    if (!is_array($files)) {
        wp_die('فایل معتبر نیست.', 400);
    }
    $file = $files[$matches[1]][(int) $matches[2]] ?? array();
    $path = $file['path'] ?? '';
    $base = realpath(zigurat_private_application_directory());
    $resolved = $path ? realpath($path) : false;
    if (!$base || !$resolved || strpos($resolved, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($resolved)) {
        wp_die('فایل پیدا نشد.', 404);
    }
    nocache_headers();
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . ($file['mime'] ?? 'application/octet-stream'));
    header('Content-Disposition: inline; filename="' . sanitize_file_name($file['name'] ?? basename($resolved)) . '"');
    header('Content-Length: ' . filesize($resolved));
    readfile($resolved);
    exit;
}
add_action('admin_post_zigurat_private_application_file', 'zigurat_download_private_application_file');

function zigurat_cleanup_application_files($post_id)
{
    if (get_post_type($post_id) === 'partner_application') {
        zigurat_delete_application_files(get_post_meta($post_id, '_application_files', true));
    }
}
add_action('before_delete_post', 'zigurat_cleanup_application_files');

function zigurat_ensure_cooperation_page_and_menu($force = false)
{
    if (!$force && get_option('zigurat_cooperation_setup_version') === '2') {
        return;
    }
    $page = get_page_by_path('cooperation');
    if (!$page) {
        $page_id = wp_insert_post(array(
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'همکاری با ما',
            'post_name'    => 'cooperation',
            'post_content' => 'برای فرصت‌های شغلی، همکاری اجرایی و عضویت در شبکه تأمین‌کنندگان زیگورات درخواست خود را ثبت کنید.',
        ));
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, '_wp_page_template', 'page-cooperation.php');
            $page = get_post($page_id);
        }
    } else {
        update_post_meta($page->ID, '_wp_page_template', 'page-cooperation.php');
    }
    if (!$page) {
        return;
    }

    $locations = get_nav_menu_locations();
    $menu_id = $locations['main-menu'] ?? 0;
    if (!$menu_id) {
        return;
    }
    $items = wp_get_nav_menu_items($menu_id);
    $parent_id = 0;
    foreach ((array) $items as $item) {
        if ((int) $item->object_id === (int) $page->ID || get_post_meta($item->ID, '_zigurat_cooperation_menu', true)) {
            $parent_id = (int) $item->ID;
            break;
        }
    }
    if (!$parent_id) {
        $parent_id = wp_update_nav_menu_item($menu_id, 0, array(
            'menu-item-title'     => 'همکاری با ما',
            'menu-item-object'    => 'page',
            'menu-item-object-id' => $page->ID,
            'menu-item-type'      => 'post_type',
            'menu-item-status'    => 'publish',
        ));
    }
    if ($parent_id && !is_wp_error($parent_id)) {
        update_post_meta($parent_id, '_zigurat_cooperation_menu', '1');
        foreach ((array) $items as $item) {
            if (
                (int) $item->menu_item_parent === (int) $parent_id
                && in_array($item->title, array('ثبت‌نام همکار', 'ثبت‌نام تأمین‌کننده'), true)
            ) {
                wp_delete_post($item->ID, true);
            }
        }
        update_option('zigurat_cooperation_setup_version', '2', false);
    }
}
add_action('init', 'zigurat_ensure_cooperation_page_and_menu', 60);

function zigurat_cooperation_seo_meta()
{
    if (!is_page_template('page-cooperation.php')) {
        return;
    }
    $title = 'فرصت شغلی و همکاری با زیگورات | ثبت‌نام همکار و تأمین‌کننده';
    $description = 'ثبت‌نام نصاب، بنا، نقاش، برقکار، ام‌دی‌اف‌کار و تأمین‌کنندگان سراسر ایران برای دریافت پروژه و همکاری با زیگورات.';
    $url = get_permalink();
    echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    echo '<meta property="og:type" content="website"><meta property="og:title" content="' . esc_attr($title) . '"><meta property="og:description" content="' . esc_attr($description) . '"><meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $title,
        'description' => $description,
        'url' => $url,
        'inLanguage' => 'fa-IR',
        'about' => array('@type' => 'Organization', 'name' => get_bloginfo('name'), 'url' => home_url('/')),
    );
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
add_action('wp_head', 'zigurat_cooperation_seo_meta', 5);

function zigurat_cooperation_document_title($title)
{
    return is_page_template('page-cooperation.php') ? 'فرصت شغلی و همکاری با زیگورات | ثبت‌نام همکار و تأمین‌کننده' : $title;
}
add_filter('pre_get_document_title', 'zigurat_cooperation_document_title');
