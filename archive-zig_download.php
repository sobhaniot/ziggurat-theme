<?php
get_header();
$archive_url = get_post_type_archive_link('zig_download');
$filter_taxonomies = array(
    'download_type'     => 'همه انواع فایل',
    'download_category' => 'همه دسته‌ها',
    'download_os'       => 'همه سیستم‌عامل‌ها',
);
$has_filter = !empty($_GET['download_search']) || !empty($_GET['download_sort']);
foreach (array_keys($filter_taxonomies) as $filter_key) {
    $has_filter = $has_filter || !empty($_GET[$filter_key]);
}
?>
<main class="download-archive">
    <section class="download-hero">
        <div class="container">
            <span class="download-eyebrow">منابع کاربردی برای طراحان و مجریان</span>
            <h1>مرکز دانلود زیگورات</h1>
            <p>نرم‌افزارها، پلاگین‌های SketchUp، مستندات و فایل‌های آموزشی را همراه با مشخصات نسخه و راهنمای نصب دریافت کنید.</p>
        </div>
    </section>

    <div class="container">
        <form class="download-filters" method="get" action="<?php echo esc_url($archive_url); ?>" data-auto-filter-form>
            <label class="download-search-field">
                <span class="screen-reader-text">جستجو در مرکز دانلود</span>
                <input type="search" name="download_search" value="<?php echo isset($_GET['download_search']) && is_string($_GET['download_search']) ? esc_attr(wp_unslash($_GET['download_search'])) : ''; ?>" placeholder="نام نرم‌افزار، پلاگین یا مستند...">
            </label>
            <?php foreach ($filter_taxonomies as $taxonomy => $placeholder):
                $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => true));
                $selected = isset($_GET[$taxonomy]) && is_string($_GET[$taxonomy]) ? sanitize_title(wp_unslash($_GET[$taxonomy])) : '';
            ?>
                <label>
                    <span class="screen-reader-text"><?php echo esc_html($placeholder); ?></span>
                    <select name="<?php echo esc_attr($taxonomy); ?>">
                        <option value=""><?php echo esc_html($placeholder); ?></option>
                        <?php if (!is_wp_error($terms)): foreach ($terms as $term): ?>
                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($selected, $term->slug); ?>><?php echo esc_html($term->name); ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </label>
            <?php endforeach; ?>
            <label>
                <span class="screen-reader-text">مرتب‌سازی</span>
                <select name="download_sort">
                    <option value="latest" <?php selected($_GET['download_sort'] ?? '', 'latest'); ?>>جدیدترین</option>
                    <option value="popular" <?php selected($_GET['download_sort'] ?? '', 'popular'); ?>>پرمخاطب‌ترین</option>
                </select>
            </label>
            <button type="submit">جستجو</button>
            <?php if ($has_filter): ?><a class="download-clear-filter" href="<?php echo esc_url($archive_url); ?>">حذف فیلترها</a><?php endif; ?>
        </form>

        <?php if (have_posts()): ?>
            <div class="download-grid">
                <?php while (have_posts()): the_post();
                    $post_id = get_the_ID();
                    $version = zigurat_download_meta($post_id, 'version');
                    $os = zigurat_download_term_names($post_id, 'download_os');
                    $sketchup = zigurat_download_term_names($post_id, 'sketchup_version');
                ?>
                    <article class="download-card">
                        <a class="download-card-image" href="<?php the_permalink(); ?>">
                            <?php if (has_post_thumbnail()): the_post_thumbnail('medium_large', array('loading' => 'lazy')); else: ?>
                                <span class="download-card-placeholder" aria-hidden="true">↓</span>
                            <?php endif; ?>
                            <span class="download-type-badge"><?php echo esc_html(zigurat_download_primary_type($post_id)); ?></span>
                        </a>
                        <div class="download-card-body">
                            <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                            <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 20, '…')); ?></p>
                            <div class="download-card-tags">
                                <?php if ($version): ?><span>نسخه <?php echo esc_html($version); ?></span><?php endif; ?>
                                <?php if ($os): ?><span><?php echo esc_html(implode('، ', $os)); ?></span><?php endif; ?>
                                <?php if ($sketchup): ?><span>SketchUp <?php echo esc_html(implode('، ', $sketchup)); ?></span><?php endif; ?>
                            </div>
                            <div class="download-card-footer">
                                <span><?php echo esc_html(number_format_i18n(zigurat_download_count($post_id))); ?> دانلود</span>
                                <a href="<?php the_permalink(); ?>">مشاهده و دانلود</a>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
            <nav class="download-pagination" aria-label="صفحه‌بندی مرکز دانلود">
                <?php the_posts_pagination(array('prev_text' => 'قبلی', 'next_text' => 'بعدی', 'mid_size' => 2)); ?>
            </nav>
        <?php else: ?>
            <div class="download-empty">
                <h2>موردی پیدا نشد</h2>
                <p>عبارت جستجو یا فیلترها را تغییر دهید.</p>
                <?php if ($has_filter): ?><a href="<?php echo esc_url($archive_url); ?>">نمایش همه فایل‌ها</a><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php get_footer(); ?>
