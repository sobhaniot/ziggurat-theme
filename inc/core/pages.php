<?php
if (!defined('ABSPATH')) {
    exit;
}
function zigurat_create_custom_pages()
{
    if (get_option('pages_created') == true) {
        return;
    }
    $pages = array(
        'main' => 'main.php',
        'add-item' => 'add-item.php',
        'subtract-item' => 'subtract-item.php',
        'inventory-list' => 'inventory-list.php',
        'inventory-transactions' => 'inventory-transactions.php',
        'portfolio' => 'portfolio.php',
    );
    foreach ($pages as $title => $template) {
        $page_query = new WP_Query(array(
            'post_type' => 'page',
            'title' => $title,
            'post_status' => 'any'
        ));
        if (!$page_query->have_posts()) {
            wp_insert_post(array(
                'post_title'    => $title,
                'post_content'  => '',
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'page_template' => $template,
            ));
        }
        wp_reset_postdata();
    }
    update_option(
        'pages_created',
        true
    );
}
add_action(
    'after_setup_theme',
    'zigurat_create_custom_pages'
);
