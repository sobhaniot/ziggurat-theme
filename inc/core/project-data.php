<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * نگاشت فیلدهای قدیمی پروژه به دسته‌بندی‌های قابل فیلتر.
 */
function zigurat_project_taxonomy_map()
{
    return array(
        '_project_client'   => 'project_client',
        '_project_city'     => 'project_city',
        '_project_province' => 'project_province',
        '_project_type'     => 'project_sign_type',
    );
}

function zigurat_get_project_term_name($post_id, $taxonomy, $fallback_meta = '')
{
    $terms = get_the_terms($post_id, $taxonomy);
    if ($terms && !is_wp_error($terms)) {
        return $terms[0]->name;
    }

    return $fallback_meta ? (string) get_post_meta($post_id, $fallback_meta, true) : '';
}

function zigurat_sync_project_taxonomies($post_id, $allow_clearing = false)
{
    foreach (zigurat_project_taxonomy_map() as $meta_key => $taxonomy) {
        $value = trim((string) get_post_meta($post_id, $meta_key, true));
        if ($value !== '') {
            wp_set_object_terms($post_id, array($value), $taxonomy, false);
        } elseif ($allow_clearing) {
            wp_set_object_terms($post_id, array(), $taxonomy, false);
        }
    }
}

function zigurat_count_used_project_terms($taxonomy)
{
    $terms = get_terms(array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => true,
        'fields'     => 'ids',
    ));

    return is_wp_error($terms) ? 0 : count($terms);
}

/**
 * آمار و پروژه‌های صفحه اول را یک بار محاسبه و در wp_options ذخیره می‌کند.
 */
function zigurat_rebuild_project_cache()
{
    $project_ids = get_posts(array(
        'post_type'              => 'project',
        'post_status'            => 'publish',
        'posts_per_page'         => -1,
        'orderby'                => 'date',
        'order'                  => 'DESC',
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
    ));

    $companies = array();
    foreach ($project_ids as $project_id) {
        $client_terms = get_the_terms($project_id, 'project_client');
        if ($client_terms && !is_wp_error($client_terms)) {
            $company_key = 'term:' . $client_terms[0]->term_id;
        } else {
            $client = trim((string) get_post_meta($project_id, '_project_client', true));
            $company_key = $client !== ''
                ? 'meta:' . sanitize_title($client)
                : 'project:' . $project_id;
        }

        if (!isset($companies[$company_key])) {
            $companies[$company_key] = array(
                'latest'   => $project_id,
                'featured' => 0,
            );
        }

        // چون لیست نزولی است، اولین گزینه برگزیده همان جدیدترین گزینه است.
        if (
            !$companies[$company_key]['featured']
            && get_post_meta($project_id, '_project_featured_for_client', true)
        ) {
            $companies[$company_key]['featured'] = $project_id;
        }
    }

    $home_ids = array();
    foreach ($companies as $company) {
        $home_ids[] = $company['featured'] ?: $company['latest'];
    }
    usort($home_ids, function ($first, $second) {
        return get_post_time('U', true, $second) <=> get_post_time('U', true, $first);
    });

    $stats = array(
        'projects'         => count($project_ids),
        'cities'           => zigurat_count_used_project_terms('project_city'),
        'provinces'        => zigurat_count_used_project_terms('project_province'),
        'home_project_ids' => array_slice($home_ids, 0, 6),
        'updated_at'       => time(),
    );

    update_option('zigurat_project_stats', $stats, false);
    return $stats;
}

function zigurat_get_project_stats()
{
    $stats = get_option('zigurat_project_stats');
    if (!is_array($stats) || !isset($stats['projects'], $stats['cities'], $stats['provinces'])) {
        $stats = zigurat_rebuild_project_cache();
    }
    return $stats;
}

function zigurat_get_home_project_ids()
{
    $stats = zigurat_get_project_stats();
    return isset($stats['home_project_ids']) ? array_map('absint', $stats['home_project_ids']) : array();
}

function zigurat_schedule_project_cache_refresh()
{
    static $scheduled = false;
    if (!$scheduled) {
        $scheduled = true;
        add_action('shutdown', 'zigurat_rebuild_project_cache', 999);
    }
}

add_action('save_post_project', 'zigurat_schedule_project_cache_refresh', 100);
add_action('trashed_post', 'zigurat_schedule_project_cache_refresh');
add_action('untrashed_post', 'zigurat_schedule_project_cache_refresh');
add_action('before_delete_post', function ($post_id) {
    if (get_post_type($post_id) === 'project') {
        zigurat_schedule_project_cache_refresh();
    }
});
add_action('set_object_terms', function ($object_id, $terms, $term_taxonomy_ids, $taxonomy) {
    if (in_array($taxonomy, array_values(zigurat_project_taxonomy_map()), true)) {
        zigurat_schedule_project_cache_refresh();
    }
}, 10, 4);

/** انتقال یک‌باره اطلاعات پروژه‌های قبلی به taxonomy و ساخت کش اولیه. */
add_action('init', function () {
    if (get_option('zigurat_project_data_version') === '2') {
        return;
    }
    $project_ids = get_posts(array(
        'post_type'      => 'project',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ));
    foreach ($project_ids as $project_id) {
        zigurat_sync_project_taxonomies($project_id, false);
    }
    zigurat_rebuild_project_cache();
    update_option('zigurat_project_data_version', '2', false);
}, 40);

/** انتقال اصطلاحات taxonomy قدیمی نوع اجرا به ساختار جدید. */
add_action('init', function () {
    if (get_option('zigurat_project_type_migration_version') === '1') {
        return;
    }
    $project_ids = get_posts(array(
        'post_type'      => 'project',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ));
    foreach ($project_ids as $project_id) {
        $new_terms = get_the_terms($project_id, 'project_sign_type');
        if ($new_terms && !is_wp_error($new_terms)) {
            continue;
        }
        $legacy_terms = get_the_terms($project_id, 'project_type');
        if ($legacy_terms && !is_wp_error($legacy_terms)) {
            wp_set_object_terms($project_id, wp_list_pluck($legacy_terms, 'name'), 'project_sign_type', false);
        }
    }
    update_option('zigurat_project_type_migration_version', '1', false);
}, 45);

/** فیلتر آرشیو پروژه‌ها. */
add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive('project')) {
        return;
    }

    $tax_query = array();
    foreach (array('project_client', 'project_city', 'project_province', 'project_sign_type') as $taxonomy) {
        if (!empty($_GET[$taxonomy]) && is_string($_GET[$taxonomy])) {
            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => sanitize_title(wp_unslash($_GET[$taxonomy])),
            );
        }
    }
    if (count($tax_query) > 1) {
        $tax_query['relation'] = 'AND';
    }
    if ($tax_query) {
        $query->set('tax_query', $tax_query);
    }
});
