<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_comments_enabled_post_types()
{
    return array('article', 'zig_download');
}

/** دیدگاه را برای مطالب و دانلودهای جدید باز و برای سایر انواع محتوا دست‌نخورده نگه می‌دارد. */
function zigurat_default_comment_status($status, $post_type)
{
    return in_array($post_type, zigurat_comments_enabled_post_types(), true) ? 'open' : $status;
}
add_filter('get_default_comment_status', 'zigurat_default_comment_status', 10, 2);

/** دیدگاه را یک‌بار برای محتوای فعلی بخش‌های مجاز فعال می‌کند. */
function zigurat_enable_existing_content_comments()
{
    if (get_option('zigurat_comments_setup_version') === '1') {
        return;
    }

    global $wpdb;
    $post_types = zigurat_comments_enabled_post_types();
    $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->posts} SET comment_status = 'open', ping_status = 'closed' WHERE post_type IN ({$placeholders})",
        $post_types
    ));
    update_option('zigurat_comments_setup_version', '1', false);
}
add_action('init', 'zigurat_enable_existing_content_comments', 40);

/** نام و ایمیل برای پرسش‌های مهمان الزامی است؛ ایمیل هرگز در قالب نمایش داده نمی‌شود. */
function zigurat_validate_comment_author($commentdata)
{
    $post_type = get_post_type(absint($commentdata['comment_post_ID'] ?? 0));
    if (!in_array($post_type, zigurat_comments_enabled_post_types(), true) || is_user_logged_in()) {
        return $commentdata;
    }

    $name = trim((string) ($commentdata['comment_author'] ?? ''));
    $email = trim((string) ($commentdata['comment_author_email'] ?? ''));
    if ($name === '' || !is_email($email)) {
        wp_die('برای ارسال پرسش، نام و یک ایمیل معتبر را وارد کنید.', 'اطلاعات ناقص', array('response' => 400, 'back_link' => true));
    }
    return $commentdata;
}
add_filter('preprocess_comment', 'zigurat_validate_comment_author');

/** همه پرسش‌ها را در صف تأیید می‌گذارد و ارسال‌های رباتی یا پرلینک را اسپم می‌کند. */
function zigurat_moderate_public_questions($approved, $commentdata)
{
    $post_type = get_post_type(absint($commentdata['comment_post_ID'] ?? 0));
    if (!in_array($post_type, zigurat_comments_enabled_post_types(), true)) {
        return $approved;
    }
    if (is_wp_error($approved) || in_array($approved, array('spam', 'trash'), true)) {
        return $approved;
    }
    if (current_user_can('moderate_comments')) {
        return 1;
    }

    $honeypot = isset($_POST['zigurat_company']) ? trim((string) wp_unslash($_POST['zigurat_company'])) : '';
    $content = (string) ($commentdata['comment_content'] ?? '');
    preg_match_all('~(?:https?://|www\.)\S+~iu', $content, $links);
    if ($honeypot !== '' || count($links[0]) > 1) {
        return 'spam';
    }
    return 0;
}
add_filter('pre_comment_approved', 'zigurat_moderate_public_questions', 10, 2);

function zigurat_comment_honeypot()
{
    echo '<p class="zigurat-comment-trap" aria-hidden="true"><label>نام شرکت<input type="text" name="zigurat_company" value="" tabindex="-1" autocomplete="off"></label></p>';
}
add_action('comment_form_after_fields', 'zigurat_comment_honeypot');

function zigurat_comment_item($comment, $args, $depth)
{
    $is_manager = !empty($comment->user_id) && user_can((int) $comment->user_id, 'manage_options');
    ?>
    <li <?php comment_class('zigurat-question' . ($is_manager ? ' is-manager-reply' : '')); ?> id="comment-<?php comment_ID(); ?>">
        <article class="zigurat-question__body">
            <header>
                <div class="zigurat-question__author">
                    <?php echo get_avatar($comment, 52, '', '', array('class' => 'zigurat-question__avatar')); ?>
                    <div><strong><?php echo esc_html(get_comment_author($comment)); ?></strong><?php if ($is_manager): ?><span>پاسخ زیگورات</span><?php endif; ?><time datetime="<?php comment_time('c'); ?>"><?php echo esc_html(get_comment_date('', $comment)); ?></time></div>
                </div>
                <?php if ($comment->comment_approved === '0'): ?><em>پرسش شما پس از تأیید نمایش داده می‌شود.</em><?php endif; ?>
            </header>
            <div class="zigurat-question__content"><?php comment_text(); ?></div>
            <?php comment_reply_link(array_merge($args, array('depth' => $depth, 'max_depth' => $args['max_depth'], 'reply_text' => 'پاسخ'))); ?>
        </article>
    <?php
}
