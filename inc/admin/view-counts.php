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
        </tbody>
    </table>
    <p>در فهرست مطالب و پروژه‌ها نیز ستون «بازدید» قابل مرتب‌سازی است.</p>
    <?php
}
