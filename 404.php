<?php
get_header();
?>
<main class="content-archive content-not-found">
    <div class="container">
        <section class="content-archive__header">
            <span class="content-not-found__code">۴۰۴</span>
            <h1>صفحه موردنظر پیدا نشد</h1>
            <p>نشانی واردشده درست نیست یا این صفحه جابه‌جا شده است.</p>
            <div class="content-not-found__actions">
                <a href="<?php echo esc_url(home_url('/')); ?>">بازگشت به خانه</a>
                <a href="<?php echo esc_url(get_post_type_archive_link('project')); ?>">مشاهده پروژه‌ها</a>
            </div>
        </section>
    </div>
</main>
<?php get_footer(); ?>
