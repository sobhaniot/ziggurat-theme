<?php
if (!defined('ABSPATH')) {
    exit;
}
if (post_password_required() || !in_array(get_post_type(), zigurat_comments_enabled_post_types(), true)) {
    return;
}
$comment_count = get_comments_number();
?>
<section class="zigurat-comments" id="questions" aria-labelledby="questions-title">
    <div class="zigurat-comments__heading">
        <span>گفت‌وگوی تخصصی</span>
        <h2 id="questions-title">پرسش و پاسخ کاربران</h2>
        <p>سؤال یا تجربه خود را بنویسید؛ پاسخ‌ها پس از بررسی منتشر می‌شوند.</p>
    </div>

    <?php if (have_comments()): ?>
        <h3 class="zigurat-comments__count"><?php echo esc_html(number_format_i18n($comment_count)); ?> پرسش و پاسخ</h3>
        <ol class="zigurat-comment-list">
            <?php wp_list_comments(array('style' => 'ol', 'short_ping' => true, 'avatar_size' => 52, 'callback' => 'zigurat_comment_item')); ?>
        </ol>
        <?php the_comments_pagination(array('prev_text' => 'پرسش‌های قبلی', 'next_text' => 'پرسش‌های بعدی')); ?>
    <?php endif; ?>

    <?php if (comments_open()):
        comment_form(array(
            'title_reply'          => 'سؤال یا نظر خود را بنویسید',
            'title_reply_before'   => '<h3 id="reply-title" class="comment-reply-title">',
            'title_reply_after'    => '</h3>',
            'label_submit'         => 'ارسال برای بررسی',
            'comment_notes_before' => '<p class="comment-notes">نام و ایمیل الزامی است. ایمیل شما نمایش داده نمی‌شود.</p>',
            'comment_notes_after'  => '',
            'comment_field'        => '<p class="comment-form-comment"><label for="comment">متن پرسش یا نظر *</label><textarea id="comment" name="comment" cols="45" rows="6" maxlength="3000" required></textarea></p>',
            'fields'               => array(
                'author' => '<p class="comment-form-author"><label for="author">نام *</label><input id="author" name="author" type="text" maxlength="80" autocomplete="name" required></p>',
                'email'  => '<p class="comment-form-email"><label for="email">ایمیل *</label><input id="email" name="email" type="email" maxlength="100" autocomplete="email" required><small>ایمیل شما در سایت نمایش داده نمی‌شود.</small></p>',
            ),
        ));
    else: ?>
        <p class="zigurat-comments__closed">ارسال پرسش برای این محتوا بسته شده است.</p>
    <?php endif; ?>
</section>
