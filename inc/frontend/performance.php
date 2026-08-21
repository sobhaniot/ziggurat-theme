<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Render a right-sized site logo instead of downloading the full attachment. */
function zigurat_site_logo($loading = 'eager')
{
    $logo_id = absint(get_theme_mod('custom_logo'));
    if (!$logo_id) {
        return;
    }

    $image = wp_get_attachment_image($logo_id, 'medium', false, array(
        'class'         => 'custom-logo',
        'loading'       => $loading,
        'decoding'      => 'async',
        'fetchpriority' => $loading === 'eager' ? 'auto' : 'low',
        'sizes'         => '140px',
        'alt'           => get_bloginfo('name'),
    ));

    if ($image) {
        printf(
            '<a href="%1$s" class="custom-logo-link" rel="home" aria-current="page">%2$s</a>',
            esc_url(home_url('/')),
            $image // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        );
    }
}

/** Use a generated optimized size when it exists and a safe core size while it is being built. */
function zigurat_attachment_size_or_fallback($attachment_id, $preferred, $fallback = 'medium_large')
{
    $metadata = wp_get_attachment_metadata(absint($attachment_id));
    if (is_array($metadata) && !empty($metadata['sizes'][$preferred])) {
        return $preferred;
    }

    return $fallback;
}

/**
 * Keep only explicitly eager images out of lazy loading. The hero controls its
 * own loading attributes and every card image can therefore load on demand.
 */
add_filter('wp_omit_loading_attr_threshold', static function () {
    return 0;
});

/** Generate modern, smaller sub-sizes for future Media Library uploads. */
add_filter('image_editor_output_format', static function ($formats) {
    if (function_exists('wp_image_editor_supports') && wp_image_editor_supports(array('mime_type' => 'image/webp'))) {
        $formats['image/jpeg'] = 'image/webp';
        $formats['image/png']  = 'image/webp';
    }

    return $formats;
});

add_filter('wp_editor_set_quality', static function ($quality, $mime_type) {
    if (in_array($mime_type, array('image/jpeg', 'image/webp'), true)) {
        return 82;
    }

    return $quality;
}, 10, 2);

add_filter('big_image_size_threshold', static function () {
    return 1920;
});

add_action('wp_head', static function () {
    if (is_admin()) {
        return;
    }

    $font_path = get_theme_file_path('/assets/fonts/Tanha-FD.woff2');
    if (is_file($font_path)) {
        printf(
            '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>%s',
            esc_url(add_query_arg('ver', filemtime($font_path), get_theme_file_uri('/assets/fonts/Tanha-FD.woff2'))),
            "\n"
        );
    }
}, 2);

/**
 * Let browsers and reverse proxies briefly reuse the public home page. Private
 * manager, invoice and inventory pages are deliberately excluded.
 */
add_filter('wp_headers', static function ($headers) {
    if (!is_admin() && !is_user_logged_in() && (is_front_page() || is_home())) {
        $headers['Cache-Control'] = 'public, max-age=300, s-maxage=600, stale-while-revalidate=60';
    }

    return $headers;
}, 20);

/** WordPress 6.4+ registers emoji CSS through this newer hook. */
add_action('init', static function () {
    remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
    remove_action('admin_enqueue_scripts', 'wp_enqueue_emoji_styles');
}, 2);

/**
 * Build the new card sub-sizes for existing featured images in very small
 * background batches. Originals are never modified or deleted.
 */
function zigurat_schedule_existing_image_optimization()
{
    if (get_option('zigurat_image_subsizes_version') === '1') {
        return;
    }

    if (function_exists('wp_doing_cron') && wp_doing_cron()) {
        return;
    }

    if (!wp_next_scheduled('zigurat_optimize_existing_image_batch')) {
        wp_schedule_single_event(time() + 20, 'zigurat_optimize_existing_image_batch');
    }
}
add_action('init', 'zigurat_schedule_existing_image_optimization', 30);

function zigurat_optimize_existing_image_batch()
{
    global $wpdb;

    if (get_transient('zigurat_image_optimizer_lock')) {
        return;
    }
    set_transient('zigurat_image_optimizer_lock', 1, 5 * MINUTE_IN_SECONDS);

    require_once ABSPATH . 'wp-admin/includes/image.php';

    $last_id = max(0, (int) get_option('zigurat_image_subsizes_last_id', 0));
    $batch_size = 3;
    $attachment_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT attachment.ID
        FROM {$wpdb->posts} AS attachment
        INNER JOIN {$wpdb->postmeta} AS thumbnail
            ON CAST(thumbnail.meta_value AS UNSIGNED) = attachment.ID
            AND thumbnail.meta_key = '_thumbnail_id'
        WHERE attachment.post_type = 'attachment'
            AND attachment.post_mime_type LIKE 'image/%%'
            AND attachment.ID > %d
        ORDER BY attachment.ID ASC
        LIMIT %d",
        $last_id,
        $batch_size
    ));

    if ($last_id === 0) {
        $logo_id = absint(get_theme_mod('custom_logo'));
        if ($logo_id) {
            wp_update_image_subsizes($logo_id);
        }
    }

    foreach ($attachment_ids as $attachment_id) {
        wp_update_image_subsizes(absint($attachment_id));
    }

    if (count($attachment_ids) < $batch_size) {
        delete_option('zigurat_image_subsizes_last_id');
        update_option('zigurat_image_subsizes_version', '1', false);
        delete_transient('zigurat_image_optimizer_lock');
        return;
    }

    update_option('zigurat_image_subsizes_last_id', max(array_map('absint', $attachment_ids)), false);
    wp_schedule_single_event(time() + 60, 'zigurat_optimize_existing_image_batch');
    delete_transient('zigurat_image_optimizer_lock');
}
add_action('zigurat_optimize_existing_image_batch', 'zigurat_optimize_existing_image_batch');
