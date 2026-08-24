<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * سئوی پایه قالب. اگر افزونه سئوی شناخته‌شده‌ای فعال باشد، تولید متا و اسکیما
 * به همان افزونه سپرده می‌شود تا تگ‌های تکراری ساخته نشود.
 */
function zigurat_seo_plugin_is_active()
{
    return defined('WPSEO_VERSION')
        || defined('RANK_MATH_VERSION')
        || defined('AIOSEO_VERSION')
        || defined('SEOPRESS_VERSION')
        || class_exists('The_SEO_Framework\Load');
}

/** حذف خروجی‌های قدیمی و اسکریپت ایموجی که برای این سایت لازم نیستند. */
function zigurat_clean_document_head()
{
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_shortlink_wp_head');
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
}
add_action('init', 'zigurat_clean_document_head', 1);

add_filter('the_generator', '__return_empty_string');

function zigurat_remove_powered_by_header()
{
    if (function_exists('header_remove')) {
        header_remove('X-Powered-By');
    }
}
add_action('send_headers', 'zigurat_remove_powered_by_header');

function zigurat_private_page_slugs()
{
    return array(
        'login',
        'invoices',
        'inventory-list',
        'inventory-transactions',
        'inventory-catalog',
        'add-item',
        'subtract-item',
    );
}

function zigurat_legacy_nonindexable_page_slugs()
{
    return array('portfolio');
}

function zigurat_is_private_management_page()
{
    return is_page(zigurat_private_page_slugs());
}

function zigurat_is_nonindexable_legacy_page()
{
    return is_page(zigurat_legacy_nonindexable_page_slugs());
}

function zigurat_seo_has_archive_filters()
{
    $filter_keys = array(
        'article_search',
        'article_category',
        'article_tag',
        'project_client',
        'project_city',
        'project_province',
        'project_sign_type',
    );
    foreach ($filter_keys as $key) {
        if (isset($_GET[$key]) && $_GET[$key] !== '') {
            return true;
        }
    }
    return false;
}

function zigurat_seo_trim_description($text, $length = 158)
{
    $text = strip_shortcodes((string) $text);
    $text = preg_replace('/\s+/u', ' ', wp_strip_all_tags($text));
    $text = trim((string) $text);
    return $text === '' ? '' : wp_html_excerpt($text, $length, '…');
}

function zigurat_seo_custom_value($key)
{
    if (!is_singular()) {
        return '';
    }
    return trim((string) get_post_meta(get_queried_object_id(), '_zigurat_seo_' . $key, true));
}

function zigurat_seo_default_home_title()
{
    return 'زیگورات | طراحی و اجرای تابلو، دکوراسیون و بازسازی';
}

function zigurat_seo_document_title($title)
{
    if (is_admin() || zigurat_seo_plugin_is_active()) {
        return $title;
    }

    $custom_title = zigurat_seo_custom_value('title');
    if ($custom_title !== '') {
        return $custom_title;
    }
    if (is_front_page()) {
        return zigurat_seo_default_home_title();
    }
    if (is_post_type_archive('project')) {
        return 'پروژه‌های اجراشده زیگورات | تابلو، دکوراسیون و بازسازی';
    }
    if (is_post_type_archive('article')) {
        return 'مطالب و مقالات تخصصی تابلو و دکوراسیون | زیگورات';
    }
    return $title;
}
add_filter('pre_get_document_title', 'zigurat_seo_document_title', 100);

function zigurat_seo_description()
{
    $custom = zigurat_seo_custom_value('description');
    if ($custom !== '') {
        return zigurat_seo_trim_description($custom);
    }

    if (is_front_page()) {
        $front_id = (int) get_option('page_on_front');
        $front_excerpt = $front_id ? get_post_field('post_excerpt', $front_id) : '';
        if ($front_excerpt) {
            return zigurat_seo_trim_description($front_excerpt);
        }
        return 'زیگورات؛ طراحی، ساخت و اجرای تابلوهای تبلیغاتی، دکوراسیون تجاری، بازسازی، چاپ و نورپردازی برای کسب‌وکارها در سراسر ایران.';
    }
    if (is_post_type_archive('project')) {
        return 'نمونه پروژه‌های اجراشده زیگورات در زمینه تابلوهای تبلیغاتی، چاپ، بازسازی و دکوراسیون تجاری؛ قابل فیلتر بر اساس کارفرما، شهر، استان و نوع اجرا.';
    }
    if (is_post_type_archive('article')) {
        return 'مقالات و راهنماهای تخصصی زیگورات درباره تابلو سازی، چاپ، دکوراسیون، بازسازی و اجرای فضاهای تجاری.';
    }
    if (is_page('cooperation') || is_page_template('page-cooperation.php')) {
        return 'ثبت‌نام نصاب، بنا، نقاش، برقکار، ام‌دی‌اف‌کار و تأمین‌کنندگان سراسر ایران برای دریافت پروژه و همکاری با زیگورات.';
    }
    if (is_singular('project')) {
        $post_id = get_queried_object_id();
        $excerpt = get_post_field('post_excerpt', $post_id);
        if (!$excerpt) {
            $excerpt = get_post_field('post_content', $post_id);
        }
        if ($excerpt) {
            return zigurat_seo_trim_description($excerpt);
        }
        $details = array_filter(array(
            zigurat_get_project_term_name($post_id, 'project_client', '_project_client'),
            zigurat_get_project_term_name($post_id, 'project_city', '_project_city'),
            get_post_meta($post_id, '_project_neighborhood', true),
            zigurat_get_project_term_name($post_id, 'project_province', '_project_province'),
            zigurat_get_project_term_name($post_id, 'project_sign_type', '_project_type'),
        ));
        return zigurat_seo_trim_description('مشاهده جزئیات و تصاویر پروژه ' . get_the_title($post_id) . ($details ? '؛ ' . implode('، ', $details) : '') . ' توسط زیگورات.');
    }
    if (is_singular()) {
        $post_id = get_queried_object_id();
        $excerpt = get_post_field('post_excerpt', $post_id);
        if (!$excerpt) {
            $excerpt = get_post_field('post_content', $post_id);
        }
        return zigurat_seo_trim_description($excerpt);
    }
    if (is_tax() || is_category() || is_tag()) {
        $description = term_description();
        return zigurat_seo_trim_description($description ?: single_term_title('', false));
    }
    return '';
}

function zigurat_seo_canonical_url()
{
    if (zigurat_is_private_management_page() || zigurat_is_nonindexable_legacy_page() || is_404() || is_search()) {
        return '';
    }
    if (is_front_page()) {
        return home_url('/');
    }
    if (is_singular()) {
        return get_permalink(get_queried_object_id());
    }
    if (is_post_type_archive()) {
        $url = get_post_type_archive_link(get_query_var('post_type'));
    } elseif (is_tax() || is_category() || is_tag()) {
        $url = get_term_link(get_queried_object());
        if (is_wp_error($url)) {
            return '';
        }
    } else {
        $url = home_url(add_query_arg(array(), $GLOBALS['wp']->request ?? ''));
    }

    $paged = max(1, (int) get_query_var('paged'));
    if ($paged > 1) {
        $url = get_pagenum_link($paged);
    }
    return $url;
}

function zigurat_seo_image()
{
    $attachment_id = 0;
    if (is_singular() && has_post_thumbnail(get_queried_object_id())) {
        $attachment_id = get_post_thumbnail_id(get_queried_object_id());
    } elseif (is_front_page()) {
        $hero = get_page_by_path('hero');
        if ($hero) {
            $attachment_id = get_post_thumbnail_id($hero->ID);
        }
    }
    if (!$attachment_id) {
        $attachment_id = (int) get_theme_mod('custom_logo');
    }
    if (!$attachment_id) {
        return array();
    }
    $image = wp_get_attachment_image_src($attachment_id, 'full');
    if (!$image) {
        return array();
    }
    $alt = '';
    if (is_singular(array('project', 'article'))) {
        $alt = trim((string) get_post_meta(get_queried_object_id(), '_zigurat_seo_image_alt', true));
    }
    if ($alt === '') {
        $alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
    }
    if ($alt === '') {
        $alt = trim((string) get_the_title($attachment_id));
    }
    return array('url' => $image[0], 'width' => $image[1], 'height' => $image[2], 'alt' => $alt);
}

function zigurat_seo_organization_schema()
{
    $contact = function_exists('zigurat_get_contact_details') ? zigurat_get_contact_details() : array();
    $same_as = array();
    if (function_exists('zigurat_get_social_links')) {
        foreach (zigurat_get_social_links() as $social) {
            if (!empty($social['url'])) {
                $same_as[] = esc_url_raw($social['url']);
            }
        }
    }
    $organization = array(
        '@type' => 'Organization',
        '@id'   => home_url('/#organization'),
        'name'  => get_bloginfo('name') ?: 'زیگورات',
        'url'   => home_url('/'),
    );
    $logo_id = (int) get_theme_mod('custom_logo');
    if ($logo_id) {
        $logo = wp_get_attachment_image_url($logo_id, 'full');
        if ($logo) {
            $organization['logo'] = array('@type' => 'ImageObject', 'url' => $logo);
        }
    }
    if (!empty($contact['phone'])) {
        $organization['telephone'] = $contact['phone'];
    }
    if (!empty($contact['email'])) {
        $organization['email'] = $contact['email'];
    }
    if (!empty($contact['address'])) {
        $organization['address'] = array('@type' => 'PostalAddress', 'streetAddress' => $contact['address'], 'addressCountry' => 'IR');
    }
    if ($same_as) {
        $organization['sameAs'] = array_values(array_unique($same_as));
    }
    return $organization;
}

function zigurat_seo_breadcrumb_schema($canonical)
{
    if (is_front_page() || !$canonical) {
        return array();
    }
    $items = array(
        array('@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => home_url('/')),
    );
    if (is_singular(array('project', 'article'))) {
        $post_type = get_post_type();
        $items[] = array(
            '@type' => 'ListItem',
            'position' => 2,
            'name' => $post_type === 'project' ? 'پروژه‌ها' : 'مطالب',
            'item' => get_post_type_archive_link($post_type),
        );
    }
    $items[] = array(
        '@type' => 'ListItem',
        'position' => count($items) + 1,
        'name' => is_singular() ? get_the_title(get_queried_object_id()) : wp_strip_all_tags(get_the_archive_title()),
        'item' => $canonical,
    );
    return array('@type' => 'BreadcrumbList', '@id' => $canonical . '#breadcrumb', 'itemListElement' => $items);
}

function zigurat_output_seo_head()
{
    if (is_admin() || is_feed() || zigurat_seo_plugin_is_active() || zigurat_is_private_management_page() || zigurat_is_nonindexable_legacy_page()) {
        return;
    }

    $description = zigurat_seo_description();
    $canonical = zigurat_seo_canonical_url();
    $title = wp_get_document_title();
    $image = zigurat_seo_image();
    if ($description) {
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    }
    if ($canonical) {
        echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
    }
    echo '<meta property="og:locale" content="fa_IR">' . "\n";
    echo '<meta property="og:type" content="' . (is_singular('article') ? 'article' : 'website') . '">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    if ($description) {
        echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
    }
    if ($canonical) {
        echo '<meta property="og:url" content="' . esc_url($canonical) . '">' . "\n";
    }
    if ($image) {
        echo '<meta property="og:image" content="' . esc_url($image['url']) . '">' . "\n";
        echo '<meta property="og:image:width" content="' . absint($image['width']) . '">' . "\n";
        echo '<meta property="og:image:height" content="' . absint($image['height']) . '">' . "\n";
        if (!empty($image['alt'])) {
            echo '<meta property="og:image:alt" content="' . esc_attr($image['alt']) . '">' . "\n";
        }
    }
    echo '<meta name="twitter:card" content="' . ($image ? 'summary_large_image' : 'summary') . '">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
    if ($description) {
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
    }
    if ($image) {
        echo '<meta name="twitter:image" content="' . esc_url($image['url']) . '">' . "\n";
        if (!empty($image['alt'])) {
            echo '<meta name="twitter:image:alt" content="' . esc_attr($image['alt']) . '">' . "\n";
        }
    }

    if (!$canonical) {
        return;
    }
    $organization = zigurat_seo_organization_schema();
    $graph = array($organization);
    if (is_front_page()) {
        $graph[] = array(
            '@type' => 'WebSite',
            '@id' => home_url('/#website'),
            'url' => home_url('/'),
            'name' => get_bloginfo('name'),
            'inLanguage' => 'fa-IR',
            'publisher' => array('@id' => home_url('/#organization')),
        );
    }
    $page_type = is_post_type_archive() || is_tax() ? 'CollectionPage' : (is_page('contact') ? 'ContactPage' : (is_page('about') ? 'AboutPage' : 'WebPage'));
    $page = array(
        '@type' => $page_type,
        '@id' => $canonical . '#webpage',
        'url' => $canonical,
        'name' => $title,
        'inLanguage' => 'fa-IR',
        'isPartOf' => array('@id' => home_url('/#website')),
        'about' => array('@id' => home_url('/#organization')),
    );
    if ($description) {
        $page['description'] = $description;
    }
    if ($image) {
        $page['primaryImageOfPage'] = array_filter(array('@type' => 'ImageObject', 'url' => $image['url'], 'width' => $image['width'], 'height' => $image['height'], 'caption' => $image['alt'] ?? ''));
    }
    $graph[] = $page;

    if (is_singular('article')) {
        $post_id = get_queried_object_id();
        $article = array(
            '@type' => 'Article',
            '@id' => $canonical . '#article',
            'mainEntityOfPage' => array('@id' => $canonical . '#webpage'),
            'headline' => get_the_title($post_id),
            'datePublished' => get_post_time(DATE_W3C, true, $post_id),
            'dateModified' => get_post_modified_time(DATE_W3C, true, $post_id),
            'author' => array('@type' => 'Person', 'name' => get_the_author_meta('display_name', (int) get_post_field('post_author', $post_id))),
            'publisher' => array('@id' => home_url('/#organization')),
        );
        if ($description) {
            $article['description'] = $description;
        }
        if ($image) {
            $article['image'] = $image['url'];
        }
        $graph[] = $article;
    } elseif (is_singular('project')) {
        $post_id = get_queried_object_id();
        $graph[] = array_filter(array(
            '@type' => 'CreativeWork',
            '@id' => $canonical . '#project',
            'mainEntityOfPage' => array('@id' => $canonical . '#webpage'),
            'name' => get_the_title($post_id),
            'description' => $description,
            'image' => $image ? $image['url'] : null,
            'creator' => array('@id' => home_url('/#organization')),
            'dateCreated' => get_post_time(DATE_W3C, true, $post_id),
        ));
    }
    $breadcrumb = zigurat_seo_breadcrumb_schema($canonical);
    if ($breadcrumb) {
        $graph[] = $breadcrumb;
    }
    echo '<script type="application/ld+json">' . wp_json_encode(array('@context' => 'https://schema.org', '@graph' => $graph), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}

function zigurat_prepare_seo_head()
{
    remove_action('wp_head', 'zigurat_cooperation_seo_meta', 5);
    if (!zigurat_seo_plugin_is_active()) {
        remove_action('wp_head', 'rel_canonical');
        add_action('wp_head', 'zigurat_output_seo_head', 5);
    }
}
add_action('wp', 'zigurat_prepare_seo_head', 1);

function zigurat_seo_robots($robots)
{
    if (zigurat_is_private_management_page()) {
        $robots['noindex'] = true;
        $robots['nofollow'] = true;
        $robots['noarchive'] = true;
        $robots['nosnippet'] = true;
        unset($robots['index'], $robots['follow'], $robots['max-image-preview']);
    } elseif (zigurat_is_nonindexable_legacy_page() || is_search() || is_404() || is_author() || is_date() || zigurat_seo_has_archive_filters()) {
        $robots['noindex'] = true;
        $robots['follow'] = true;
        unset($robots['index']);
    } else {
        $robots['max-image-preview'] = 'large';
    }
    return $robots;
}
add_filter('wp_robots', 'zigurat_seo_robots', 20);

function zigurat_exclude_private_pages_from_sitemap($args, $post_type)
{
    if ($post_type !== 'page') {
        return $args;
    }
    $excluded = array();
    foreach (array_merge(zigurat_private_page_slugs(), zigurat_legacy_nonindexable_page_slugs(), array('hero')) as $slug) {
        $page = get_page_by_path($slug, OBJECT, 'page');
        if ($page) {
            $excluded[] = (int) $page->ID;
        }
    }
    if ($excluded) {
        $args['post__not_in'] = array_values(array_unique(array_merge((array) ($args['post__not_in'] ?? array()), $excluded)));
    }
    return $args;
}
add_filter('wp_sitemaps_posts_query_args', 'zigurat_exclude_private_pages_from_sitemap', 10, 2);

function zigurat_limit_public_sitemap_post_types($post_types)
{
    foreach (array('post', 'brand', 'service', 'partner_application') as $post_type) {
        unset($post_types[$post_type]);
    }
    return $post_types;
}
add_filter('wp_sitemaps_post_types', 'zigurat_limit_public_sitemap_post_types');

function zigurat_limit_public_sitemap_taxonomies($taxonomies)
{
    foreach (array('category', 'post_tag', 'item_name', 'project_item', 'employee', 'project_type') as $taxonomy) {
        unset($taxonomies[$taxonomy]);
    }
    return $taxonomies;
}
add_filter('wp_sitemaps_taxonomies', 'zigurat_limit_public_sitemap_taxonomies');

function zigurat_remove_user_sitemap($provider, $name)
{
    return $name === 'users' ? false : $provider;
}
add_filter('wp_sitemaps_add_provider', 'zigurat_remove_user_sitemap', 10, 2);

/** صفحه «هیرو» فقط منبع محتوای صفحه اصلی است و نباید URL مستقلی داشته باشد. */
function zigurat_redirect_hero_source_page()
{
    if (is_page('hero')) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
}
add_action('template_redirect', 'zigurat_redirect_hero_source_page', 1);

/** برای تصاویر محتوایی فاقد متن جایگزین، عنوان رسانه را به‌عنوان fallback قرار می‌دهد. */
function zigurat_image_alt_fallback($attr, $attachment)
{
    $current_post_id = get_the_ID();
    if (
        !is_admin()
        && $attachment instanceof WP_Post
        && $current_post_id
        && in_array(get_post_type($current_post_id), array('project', 'article'), true)
        && get_post_thumbnail_id($current_post_id) === $attachment->ID
    ) {
        $project_alt = trim((string) get_post_meta($current_post_id, '_zigurat_seo_image_alt', true));
        if ($project_alt !== '') {
            $attr['alt'] = $project_alt;
            return $attr;
        }
    }
    if (empty($attr['alt']) && $attachment instanceof WP_Post) {
        $alt = trim((string) get_post_meta($attachment->ID, '_wp_attachment_image_alt', true));
        if ($alt === '') {
            $alt = trim((string) $attachment->post_excerpt);
        }
        if ($alt === '') {
            $alt = trim((string) $attachment->post_title);
        }
        if ($alt !== '') {
            $attr['alt'] = $alt;
        }
    }
    return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'zigurat_image_alt_fallback', 10, 2);
