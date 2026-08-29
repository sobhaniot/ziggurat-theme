<?php
get_header();
if (have_posts()): while (have_posts()): the_post();
    $post_id = get_the_ID();
    $version = zigurat_download_meta($post_id, 'version');
    $file_size = zigurat_download_meta($post_id, 'file_size');
    $format = zigurat_download_meta($post_id, 'file_format');
    $developer = zigurat_download_meta($post_id, 'developer');
    $license = zigurat_download_meta($post_id, 'license');
    $requirements = zigurat_download_meta($post_id, 'requirements');
    $installation = zigurat_download_meta($post_id, 'installation');
    $changelog = zigurat_download_meta($post_id, 'changelog');
    $official_url = zigurat_download_meta($post_id, 'official_url');
    $source_url = zigurat_download_source_url($post_id);
    $sketchup = zigurat_download_term_names($post_id, 'sketchup_version');
    $os = zigurat_download_term_names($post_id, 'download_os');
    $categories = zigurat_download_term_names($post_id, 'download_category');
    $facts = array_filter(array(
        'نوع فایل' => zigurat_download_primary_type($post_id),
        'نسخه' => $version,
        'حجم فایل' => $file_size,
        'فرمت' => $format,
        'سازنده / ناشر' => $developer,
        'مجوز' => $license,
        'سیستم‌عامل' => $os ? implode('، ', $os) : '',
        'نسخه‌های SketchUp' => $sketchup ? implode('، ', $sketchup) : '',
        'دسته‌بندی' => $categories ? implode('، ', $categories) : '',
    ));
?>
<main class="download-single">
    <div class="container">
        <nav class="download-breadcrumb" aria-label="مسیر صفحه">
            <a href="<?php echo esc_url(home_url('/')); ?>">خانه</a><span>/</span>
            <a href="<?php echo esc_url(get_post_type_archive_link('zig_download')); ?>">مرکز دانلود</a><span>/</span>
            <span><?php the_title(); ?></span>
        </nav>

        <section class="download-product-header">
            <div class="download-product-image">
                <?php if (has_post_thumbnail()): the_post_thumbnail('large'); else: ?><span aria-hidden="true">↓</span><?php endif; ?>
            </div>
            <div class="download-product-intro">
                <span class="download-type-badge"><?php echo esc_html(zigurat_download_primary_type($post_id)); ?></span>
                <h1><?php the_title(); ?></h1>
                <?php if (has_excerpt()): ?><p><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?>
                <div class="download-quick-stats">
                    <?php if ($version): ?><span><strong>نسخه</strong><?php echo esc_html($version); ?></span><?php endif; ?>
                    <?php if ($file_size): ?><span><strong>حجم</strong><?php echo esc_html($file_size); ?></span><?php endif; ?>
                    <span><strong>دریافت</strong><?php echo esc_html(number_format_i18n(zigurat_download_count($post_id))); ?> بار</span>
                </div>
                <?php if ($source_url): ?>
                    <a class="download-primary-button" href="<?php echo esc_url(zigurat_download_action_url($post_id)); ?>" rel="nofollow">دانلود <?php the_title(); ?></a>
                <?php else: ?>
                    <span class="download-primary-button is-disabled">فایل به‌زودی اضافه می‌شود</span>
                <?php endif; ?>
                <?php if ($official_url): ?><a class="download-official-link" href="<?php echo esc_url($official_url); ?>" target="_blank" rel="noopener nofollow">مشاهده منبع رسمی</a><?php endif; ?>
            </div>
        </section>

        <div class="download-single-layout">
            <article class="download-content">
                <section class="download-panel">
                    <h2>معرفی و توضیحات</h2>
                    <div class="download-entry-content"><?php the_content(); ?></div>
                </section>
                <?php if ($installation): ?>
                    <section class="download-panel"><h2>راهنمای نصب و استفاده</h2><div><?php echo wpautop(wp_kses_post($installation)); ?></div></section>
                <?php endif; ?>
                <?php if ($requirements): ?>
                    <section class="download-panel"><h2>پیش‌نیازها و سازگاری</h2><div><?php echo wpautop(esc_html($requirements)); ?></div></section>
                <?php endif; ?>
                <?php if ($changelog): ?>
                    <section class="download-panel"><h2>تغییرات این نسخه</h2><div><?php echo wpautop(wp_kses_post($changelog)); ?></div></section>
                <?php endif; ?>
            </article>
            <aside class="download-sidebar">
                <section class="download-panel download-facts">
                    <h2>مشخصات فایل</h2>
                    <dl>
                        <?php foreach ($facts as $label => $value): ?><div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html($value); ?></dd></div><?php endforeach; ?>
                        <div><dt>آخرین بروزرسانی</dt><dd><?php echo esc_html(get_the_modified_date()); ?></dd></div>
                    </dl>
                </section>
                <section class="download-safety-note">
                    <strong>دانلود مطمئن</strong>
                    <p>پیش از نصب، سازگاری نسخه و راهنمای ارائه‌شده در همین صفحه را بررسی کنید.</p>
                </section>
            </aside>
        </div>
    </div>
</main>
<?php endwhile; endif; get_footer(); ?>

