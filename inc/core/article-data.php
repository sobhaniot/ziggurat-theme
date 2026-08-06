<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_get_article_views($post_id)
{
    return max(0, (int) get_post_meta($post_id, '_article_views', true));
}

/** ثبت یک بازدید برای هر مرورگر در بازه ۱۲ ساعته. */
function zigurat_record_article_view()
{
    if (!is_singular('article') || is_user_logged_in() || is_preview()) {
        return;
    }
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT'])
        ? strtolower($_SERVER['HTTP_USER_AGENT'])
        : '';
    if ($user_agent && preg_match('/bot|crawler|spider|slurp|bingpreview/', $user_agent)) {
        return;
    }
    $post_id = get_queried_object_id();
    if (!$post_id) {
        return;
    }
    $cookie_name = 'zigurat_article_view_' . $post_id;
    if (!empty($_COOKIE[$cookie_name])) {
        return;
    }

    global $wpdb;
    add_post_meta($post_id, '_article_views', 0, true);
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->postmeta} SET meta_value = CAST(meta_value AS UNSIGNED) + 1 WHERE post_id = %d AND meta_key = %s",
        $post_id,
        '_article_views'
    ));
    clean_post_cache($post_id);

    setcookie($cookie_name, '1', array(
        'expires'  => time() + (12 * HOUR_IN_SECONDS),
        'path'     => COOKIEPATH ?: '/',
        'domain'   => COOKIE_DOMAIN,
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ));
}
add_action('template_redirect', 'zigurat_record_article_view', 20);

/** برای مطالب قدیمی نیز مقدار بازدید صفر ایجاد می‌کند تا مرتب‌سازی کامل باشد. */
add_action('init', function () {
    if (get_option('zigurat_article_views_version') === '1') {
        return;
    }
    $article_ids = get_posts(array(
        'post_type'      => 'article',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ));
    foreach ($article_ids as $article_id) {
        add_post_meta($article_id, '_article_views', 0, true);
    }
    update_option('zigurat_article_views_version', '1', false);
}, 50);

add_action('save_post_article', function ($post_id) {
    if (!wp_is_post_revision($post_id)) {
        add_post_meta($post_id, '_article_views', 0, true);
    }
});

function zigurat_get_popular_articles($limit = 5)
{
    return new WP_Query(array(
        'post_type'      => 'article',
        'post_status'    => 'publish',
        'posts_per_page' => absint($limit),
        'meta_key'       => '_article_views',
        'orderby'        => array('meta_value_num' => 'DESC', 'date' => 'DESC'),
        'order'          => 'DESC',
        'no_found_rows'  => true,
    ));
}

/** افزودن پیوند مطالب به منوی اصلی سایت فعلی. */
function zigurat_ensure_articles_menu_item()
{
    if (get_option('zigurat_articles_menu_version') === '1') {
        return;
    }
    $locations = get_nav_menu_locations();
    $menu_id = $locations['main-menu'] ?? 0;
    if (!$menu_id) {
        return;
    }
    $archive_url = get_post_type_archive_link('article');
    foreach ((array) wp_get_nav_menu_items($menu_id) as $item) {
        if (untrailingslashit($item->url) === untrailingslashit($archive_url)) {
            update_option('zigurat_articles_menu_version', '1', false);
            return;
        }
    }
    $item_id = wp_update_nav_menu_item($menu_id, 0, array(
        'menu-item-title'  => 'مطالب',
        'menu-item-url'    => $archive_url,
        'menu-item-type'   => 'custom',
        'menu-item-status' => 'publish',
    ));
    if ($item_id && !is_wp_error($item_id)) {
        update_option('zigurat_articles_menu_version', '1', false);
    }
}
add_action('init', 'zigurat_ensure_articles_menu_item', 62);
