<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_is_portfolio_admin_post($post)
{
    return $post instanceof WP_Post
        && $post->post_type === 'page'
        && ($post->post_name === 'portfolio' || get_page_template_slug($post->ID) === 'page-portfolio.php');
}

function zigurat_add_portfolio_catalog_meta_box()
{
    global $post;
    if (!zigurat_is_portfolio_admin_post($post)) {
        return;
    }

    add_meta_box(
        'zigurat_portfolio_catalog',
        'کاتالوگ ورق‌خور',
        'zigurat_render_portfolio_catalog_meta_box',
        'page',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'zigurat_add_portfolio_catalog_meta_box');

function zigurat_render_portfolio_catalog_meta_box($post)
{
    $attachment_id = (int) get_post_meta($post->ID, '_zigurat_portfolio_pdf_id', true);
    $attachment = $attachment_id ? get_post($attachment_id) : null;
    $filename = $attachment ? wp_basename((string) get_attached_file($attachment_id)) : '';

    wp_nonce_field('zigurat_save_portfolio_catalog', 'zigurat_portfolio_catalog_nonce');
?>
    <div class="zigurat-portfolio-pdf" data-portfolio-pdf-picker>
        <input type="hidden" name="zigurat_portfolio_pdf_id" value="<?php echo esc_attr($attachment_id); ?>" data-portfolio-pdf-id>
        <div class="zigurat-portfolio-pdf__status" data-portfolio-pdf-status <?php echo $attachment_id ? '' : 'hidden'; ?>>
            <span class="dashicons dashicons-pdf" aria-hidden="true"></span>
            <strong data-portfolio-pdf-name><?php echo esc_html($filename); ?></strong>
        </div>
        <p class="description">فقط یک PDF انتخاب کنید؛ صفحات و دفترچه به‌صورت خودکار ساخته می‌شوند.</p>
        <p>
            <button type="button" class="button button-primary" data-portfolio-pdf-select>
                <?php echo $attachment_id ? 'تعویض PDF' : 'انتخاب PDF'; ?>
            </button>
            <button type="button" class="button-link-delete" data-portfolio-pdf-remove <?php echo $attachment_id ? '' : 'hidden'; ?>>حذف فایل</button>
        </p>
    </div>
<?php
}

function zigurat_save_portfolio_catalog_meta($post_id, $post)
{
    if (!zigurat_is_portfolio_admin_post($post)) {
        return;
    }
    if (!isset($_POST['zigurat_portfolio_catalog_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zigurat_portfolio_catalog_nonce'])), 'zigurat_save_portfolio_catalog')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $attachment_id = isset($_POST['zigurat_portfolio_pdf_id']) ? absint($_POST['zigurat_portfolio_pdf_id']) : 0;
    if (!$attachment_id) {
        delete_post_meta($post_id, '_zigurat_portfolio_pdf_id');
        return;
    }

    if (get_post_mime_type($attachment_id) === 'application/pdf') {
        update_post_meta($post_id, '_zigurat_portfolio_pdf_id', $attachment_id);
    }
}
add_action('save_post_page', 'zigurat_save_portfolio_catalog_meta', 10, 2);

function zigurat_enqueue_portfolio_admin_assets($hook)
{
    if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
        return;
    }
    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
    $post = $post_id ? get_post($post_id) : null;
    if (!zigurat_is_portfolio_admin_post($post)) {
        return;
    }

    wp_enqueue_media();
    $path = get_template_directory() . '/assets/js/portfolio-admin.js';
    wp_enqueue_script(
        'zigurat-portfolio-admin',
        get_template_directory_uri() . '/assets/js/portfolio-admin.js',
        array('jquery'),
        is_file($path) ? filemtime($path) : null,
        true
    );
}
add_action('admin_enqueue_scripts', 'zigurat_enqueue_portfolio_admin_assets');
