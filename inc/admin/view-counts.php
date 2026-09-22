<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_add_views_admin_column($columns)
{
    $result = array();
    foreach ($columns as $key => $label) {
        $result[$key] = $label;
        if ($key === 'title') {
            $result['zigurat_views'] = 'بازدید';
        }
    }
    if (!isset($result['zigurat_views'])) {
        $result['zigurat_views'] = 'بازدید';
    }
    return $result;
}
add_filter('manage_article_posts_columns', 'zigurat_add_views_admin_column');
add_filter('manage_project_posts_columns', 'zigurat_add_views_admin_column');

function zigurat_render_views_admin_column($column, $post_id)
{
    if ($column !== 'zigurat_views') {
        return;
    }
    $views = get_post_type($post_id) === 'project'
        ? zigurat_get_project_views($post_id)
        : zigurat_get_article_views($post_id);
    echo esc_html(number_format_i18n($views));
}
add_action('manage_article_posts_custom_column', 'zigurat_render_views_admin_column', 10, 2);
add_action('manage_project_posts_custom_column', 'zigurat_render_views_admin_column', 10, 2);

function zigurat_make_views_admin_column_sortable($columns)
{
    $columns['zigurat_views'] = 'zigurat_views';
    return $columns;
}
add_filter('manage_edit-article_sortable_columns', 'zigurat_make_views_admin_column_sortable');
add_filter('manage_edit-project_sortable_columns', 'zigurat_make_views_admin_column_sortable');

function zigurat_sort_admin_posts_by_views($query)
{
    if (!is_admin() || !$query->is_main_query() || $query->get('orderby') !== 'zigurat_views') {
        return;
    }
    $post_type = $query->get('post_type');
    if (!in_array($post_type, array('article', 'project'), true)) {
        return;
    }
    $query->set('meta_key', $post_type === 'project' ? '_project_views' : '_article_views');
    $query->set('orderby', 'meta_value_num');
}
add_action('pre_get_posts', 'zigurat_sort_admin_posts_by_views');

function zigurat_get_total_post_type_views($post_type, $meta_key)
{
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(CAST(pm.meta_value AS UNSIGNED)), 0)
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish'",
        $meta_key,
        $post_type
    ));
}

/** داده‌های تجمعی بازدید برای پنل اختصاصی مدیران. */
function zigurat_get_manager_views_statistics($limit = 10)
{
    global $wpdb;

    $article_views = zigurat_get_total_post_type_views('article', '_article_views');
    $project_views = zigurat_get_total_post_type_views('project', '_project_views');
    $download_views = zigurat_get_total_post_type_views('zig_download', '_zig_download_count');
    $article_counts = wp_count_posts('article');
    $project_counts = wp_count_posts('project');
    $download_counts = wp_count_posts('zig_download');
    $article_count = isset($article_counts->publish) ? (int) $article_counts->publish : 0;
    $project_count = isset($project_counts->publish) ? (int) $project_counts->publish : 0;
    $download_count = isset($download_counts->publish) ? (int) $download_counts->publish : 0;
    $limit = min(30, max(1, absint($limit)));

    $top_content = $wpdb->get_results($wpdb->prepare(
        "SELECT p.ID, p.post_title, p.post_type, p.post_date,
            COALESCE(MAX(CAST(pm.meta_value AS UNSIGNED)), 0) AS views
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
            AND ((p.post_type = 'article' AND pm.meta_key = '_article_views')
                OR (p.post_type = 'project' AND pm.meta_key = '_project_views')
                OR (p.post_type = 'zig_download' AND pm.meta_key = '_zig_download_count'))
        WHERE p.post_status = 'publish' AND p.post_type IN ('article', 'project', 'zig_download')
        GROUP BY p.ID, p.post_title, p.post_type, p.post_date
        ORDER BY views DESC, p.post_date DESC
        LIMIT %d",
        $limit
    ));

    return array(
        'article_views' => $article_views,
        'project_views' => $project_views,
        'download_views' => $download_views,
        'total_views' => $article_views + $project_views + $download_views,
        'article_count' => $article_count,
        'project_count' => $project_count,
        'download_count' => $download_count,
        'article_average' => $article_count ? (int) round($article_views / $article_count) : 0,
        'project_average' => $project_count ? (int) round($project_views / $project_count) : 0,
        'download_average' => $download_count ? (int) round($download_views / $download_count) : 0,
        'top_content' => is_array($top_content) ? $top_content : array(),
    );
}

/** داده سبک و قابل استفاده برای به‌روزرسانی زنده صفحه آمار مدیران. */
function zigurat_get_live_views_payload()
{
    $statistics = zigurat_get_manager_views_statistics(10);
    $top_content = array();
    foreach ($statistics['top_content'] as $content_item) {
        $top_content[] = array(
            'id' => (int) $content_item->ID,
            'title' => (string) ($content_item->post_title ?: 'بدون عنوان'),
            'type' => (string) $content_item->post_type,
            'url' => (string) get_permalink($content_item->ID),
            'views' => max(0, (int) $content_item->views),
        );
    }

    return array(
        'article_views' => (int) $statistics['article_views'],
        'project_views' => (int) $statistics['project_views'],
        'download_views' => (int) $statistics['download_views'],
        'total_views' => (int) $statistics['total_views'],
        'article_count' => (int) $statistics['article_count'],
        'project_count' => (int) $statistics['project_count'],
        'download_count' => (int) $statistics['download_count'],
        'article_average' => (int) $statistics['article_average'],
        'project_average' => (int) $statistics['project_average'],
        'download_average' => (int) $statistics['download_average'],
        'chart' => zigurat_get_all_views_chart_data(),
        'top_content' => $top_content,
        'updated_at' => current_time('c'),
    );
}

function zigurat_ajax_get_live_views()
{
    if (!zigurat_is_manager()) {
        wp_send_json_error(array('message' => 'دسترسی به آمار مجاز نیست.'), 403);
    }

    check_ajax_referer('zigurat_live_views', 'nonce');
    nocache_headers();
    wp_send_json_success(zigurat_get_live_views_payload());
}
add_action('wp_ajax_zigurat_get_live_views', 'zigurat_ajax_get_live_views');

/** نمایش شمارنده زنده فقط در صفحات اختصاصی مدیریت سایت. */
function zigurat_should_show_live_views_counter()
{
    if (!zigurat_is_manager() || is_admin()) {
        return false;
    }

    $inventory_pages = array('inventory-list', 'inventory-transactions', 'inventory-catalog', 'add-item', 'subtract-item');
    return is_page('login')
        || is_page_template('page-login.php')
        || is_page('invoices')
        || is_page_template('page-invoices.php')
        || is_page($inventory_pages);
}

function zigurat_render_live_views_counter()
{
    if (!zigurat_should_show_live_views_counter()) {
        return;
    }
    $statistics = zigurat_get_manager_views_statistics(1);
    $total_views = max(0, (int) $statistics['total_views']);
    ?>
    <aside class="manager-live-counter no-print" data-live-counter data-initial-total="<?php echo esc_attr($total_views); ?>" aria-live="polite">
        <span><i aria-hidden="true"></i> بازدید زنده</span>
        <strong data-live-counter-value><?php echo esc_html(number_format_i18n($total_views)); ?></strong>
        <small data-live-counter-status>در حال اتصال…</small>
        <div class="manager-live-counter__toast" data-live-toast hidden></div>
    </aside>
    <?php
}
add_action('wp_footer', 'zigurat_render_live_views_counter', 30);

function zigurat_register_views_dashboard_widget()
{
    wp_add_dashboard_widget(
        'zigurat_views_dashboard_widget',
        'آمار بازدید زیگورات',
        'zigurat_render_views_dashboard_widget'
    );
}
add_action('wp_dashboard_setup', 'zigurat_register_views_dashboard_widget');

function zigurat_render_views_dashboard_widget()
{
    $article_views = zigurat_get_total_post_type_views('article', '_article_views');
    $project_views = zigurat_get_total_post_type_views('project', '_project_views');
    $download_views = zigurat_get_total_post_type_views('zig_download', '_zig_download_count');
    ?>
    <table class="widefat striped">
        <tbody>
            <tr>
                <th><a href="<?php echo esc_url(admin_url('edit.php?post_type=article')); ?>">مطالب</a></th>
                <td><strong><?php echo esc_html(number_format_i18n($article_views)); ?></strong> بازدید</td>
            </tr>
            <tr>
                <th><a href="<?php echo esc_url(admin_url('edit.php?post_type=project')); ?>">پروژه‌ها</a></th>
                <td><strong><?php echo esc_html(number_format_i18n($project_views)); ?></strong> بازدید</td>
            </tr>
            <tr>
                <th><a href="<?php echo esc_url(admin_url('edit.php?post_type=zig_download')); ?>">دانلودها</a></th>
                <td><strong><?php echo esc_html(number_format_i18n($download_views)); ?></strong> دریافت</td>
            </tr>
        </tbody>
    </table>
    <p>آمار مطالب و پروژه‌ها براساس بازدید صفحه و آمار دانلودها براساس دریافت واقعی فایل است.</p>
    <?php
}
