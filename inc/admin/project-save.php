<?php
if (!defined('ABSPATH')) {
    exit;
}

/** عنوان پروژه را برای تشخیص فاصله و حروف هم‌ارز فارسی یکسان می‌کند. */
function zigurat_project_normalized_title($title)
{
    if (function_exists('zigurat_inventory_normalize_project_name')) {
        return zigurat_inventory_normalize_project_name($title);
    }
    $title = wp_strip_all_tags((string) $title);
    $title = strtr($title, array(
        'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'ۀ' => 'ه',
        'أ' => 'ا', 'إ' => 'ا',
    ));
    $title = preg_replace('/[\s\x{200c}\x{200e}\x{200f}]+/u', ' ', trim($title));
    return function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
}

/** پروژه هم‌نام را در تمام وضعیت‌های قابل استفاده، از جمله پیش‌نویس داخلی، پیدا می‌کند. */
function zigurat_project_find_duplicate_title($title, $exclude_post_id = 0)
{
    $needle = zigurat_project_normalized_title($title);
    if ($needle === '') {
        return null;
    }
    $exclude_post_id = absint($exclude_post_id);
    $project_ids = get_posts(array(
        'post_type' => 'project',
        'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
        'posts_per_page' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'no_found_rows' => true,
        'exclude' => $exclude_post_id ? array($exclude_post_id) : array(),
    ));
    foreach ($project_ids as $project_id) {
        // نسخه داخلی که قبلاً به همین پروژه متصل و بایگانی شده، تکراری محسوب نمی‌شود.
        if (
            $exclude_post_id
            && (int) get_post_meta($project_id, '_zigurat_inventory_merged_into', true) === $exclude_post_id
        ) {
            continue;
        }
        $project = get_post($project_id);
        if ($project && zigurat_project_normalized_title($project->post_title) === $needle) {
            return $project;
        }
    }
    return null;
}

function zigurat_project_duplicate_message($duplicate)
{
    $is_internal = function_exists('zigurat_inventory_is_internal_project')
        && zigurat_inventory_is_internal_project($duplicate);
    if ($is_internal) {
        $kind = 'پیش‌نویس داخلی انبار';
    } elseif ($duplicate->post_status === 'draft') {
        $kind = 'پیش‌نویس';
    } elseif ($duplicate->post_status === 'publish') {
        $kind = 'پروژه منتشرشده';
    } else {
        $kind = 'پروژه ثبت‌شده';
    }
    return sprintf(
        'پروژه‌ای با نام «%s» قبلاً به‌عنوان %s ثبت شده است. همان پروژه را باز و تکمیل کنید؛ پروژه هم‌نام جدید ذخیره نشد.',
        get_the_title($duplicate),
        $kind
    );
}

/** جلوگیری از ذخیره عنوان تکراری در ویرایشگر بلوکی وردپرس. */
function zigurat_prevent_duplicate_project_rest_save($prepared_post, $request)
{
    if (!($prepared_post instanceof stdClass) && !($prepared_post instanceof WP_Post)) {
        return $prepared_post;
    }
    $title = isset($prepared_post->post_title) ? (string) $prepared_post->post_title : '';
    $post_id = isset($prepared_post->ID) ? absint($prepared_post->ID) : absint($request->get_param('id'));
    $duplicate = zigurat_project_find_duplicate_title($title, $post_id);
    if (!$duplicate) {
        return $prepared_post;
    }
    return new WP_Error(
        'zigurat_duplicate_project_title',
        zigurat_project_duplicate_message($duplicate),
        array(
            'status' => 409,
            'duplicate_project_id' => (int) $duplicate->ID,
            'duplicate_edit_url' => get_edit_post_link($duplicate->ID, 'raw'),
        )
    );
}
add_filter('rest_pre_insert_project', 'zigurat_prevent_duplicate_project_rest_save', 10, 2);

/** جلوگیری از ذخیره تکراری در ویرایشگر کلاسیک، پیش از آن‌که وردپرس پست را تغییر دهد. */
function zigurat_prevent_duplicate_project_classic_save()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || sanitize_key((string) ($_POST['action'] ?? '')) !== 'editpost') {
        return;
    }
    $post_id = absint($_POST['post_ID'] ?? 0);
    $post_type = sanitize_key((string) ($_POST['post_type'] ?? get_post_type($post_id)));
    $nonce = sanitize_text_field(wp_unslash((string) ($_POST['_wpnonce'] ?? '')));
    if (
        $post_type !== 'project'
        || !$post_id
        || !wp_verify_nonce($nonce, 'update-post_' . $post_id)
        || !current_user_can('edit_post', $post_id)
    ) {
        return;
    }
    $title = sanitize_text_field(wp_unslash((string) ($_POST['post_title'] ?? '')));
    $duplicate = zigurat_project_find_duplicate_title($title, $post_id);
    if (!$duplicate) {
        return;
    }
    wp_safe_redirect(add_query_arg(array(
        'post' => $post_id,
        'action' => 'edit',
        'zigurat_project_duplicate' => (int) $duplicate->ID,
    ), admin_url('post.php')));
    exit;
}
add_action('load-post.php', 'zigurat_prevent_duplicate_project_classic_save', 1);

/** ویرایش سریع نیز نباید مسیر دورزدن کنترل عنوان تکراری باشد. */
function zigurat_prevent_duplicate_project_quick_edit()
{
    if (sanitize_key((string) ($_POST['post_type'] ?? '')) !== 'project') {
        return;
    }
    $post_id = absint($_POST['post_ID'] ?? 0);
    if (!$post_id || !check_ajax_referer('inlineeditnonce', '_inline_edit', false) || !current_user_can('edit_post', $post_id)) {
        return;
    }
    $title = sanitize_text_field(wp_unslash((string) ($_POST['post_title'] ?? '')));
    $duplicate = zigurat_project_find_duplicate_title($title, $post_id);
    if ($duplicate) {
        wp_die(esc_html(zigurat_project_duplicate_message($duplicate)), '', array('response' => 409));
    }
}
add_action('wp_ajax_inline-save', 'zigurat_prevent_duplicate_project_quick_edit', 1);

function zigurat_project_duplicate_admin_notice()
{
    $duplicate_id = isset($_GET['zigurat_project_duplicate']) ? absint($_GET['zigurat_project_duplicate']) : 0;
    $duplicate = $duplicate_id ? get_post($duplicate_id) : null;
    if (!$duplicate || $duplicate->post_type !== 'project') {
        return;
    }
    echo '<div class="notice notice-error"><p>' . esc_html(zigurat_project_duplicate_message($duplicate)) . ' ';
    echo '<a href="' . esc_url(get_edit_post_link($duplicate->ID)) . '">بازکردن پروژه قبلی</a></p></div>';
}
add_action('admin_notices', 'zigurat_project_duplicate_admin_notice');

/**
 * ذخیره اطلاعات پروژه
 */
function zigurat_save_project_meta($post_id)
{
    // بررسی nonce
    if (
        !isset($_POST['zigurat_project_nonce_field']) ||
        !wp_verify_nonce(
            $_POST['zigurat_project_nonce_field'],
            'zigurat_project_nonce'
        )
    ) {
        return;
    }
    // جلوگیری از Auto Save
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    // فقط برای پروژه‌ها
    if (get_post_type($post_id) !== 'project') {
        return;
    }
    // دسترسی کاربر
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    $fields = array(
        'project_city',
        'project_neighborhood',
        'project_province',
        'project_client',
        'project_date',
        'project_type',
        'project_duration'
    );
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta(
                $post_id,
                '_' . $field,
                sanitize_text_field(wp_unslash($_POST[$field]))
            );
        }
    }
    update_post_meta(
        $post_id,
        '_project_featured_for_client',
        isset($_POST['project_featured_for_client']) ? '1' : '0'
    );
    zigurat_sync_project_taxonomies($post_id, true);
    // ذخیره گالری تصاویر
    if (isset($_POST['project_gallery'])) {
        $gallery = sanitize_text_field(wp_unslash($_POST['project_gallery']));
        update_post_meta(
            $post_id,
            '_project_gallery',
            $gallery
        );
    }
}
add_action('save_post', 'zigurat_save_project_meta');
