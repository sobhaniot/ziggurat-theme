<?php
/*
Template Name: Portfolio
*/
if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();
    $projects_url = get_post_type_archive_link('project');
    $catalog_pdf_id = (int) get_post_meta(get_the_ID(), '_zigurat_portfolio_pdf_id', true);
    $catalog_pdf_url = $catalog_pdf_id && get_post_mime_type($catalog_pdf_id) === 'application/pdf'
        ? wp_make_link_relative((string) wp_get_attachment_url($catalog_pdf_id))
        : '';
    $catalog_pdf_title = $catalog_pdf_id ? get_the_title($catalog_pdf_id) : '';
    $projects = new WP_Query(array(
        'post_type'              => 'project',
        'post_status'            => 'publish',
        'posts_per_page'         => 6,
        'orderby'                => 'date',
        'order'                  => 'DESC',
        'ignore_sticky_posts'    => true,
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ));
?>
    <main class="portfolio-page">
        <section class="portfolio-hero" aria-labelledby="portfolio-title">
            <div class="portfolio-hero__glow portfolio-hero__glow--one" aria-hidden="true"></div>
            <div class="portfolio-hero__glow portfolio-hero__glow--two" aria-hidden="true"></div>
            <div class="container portfolio-hero__inner">
                <p class="portfolio-eyebrow">کاتالوگ دیجیتال زیگورات</p>
                <h1 id="portfolio-title"><?php the_title(); ?></h1>
                <p class="portfolio-hero__description">
                    مجموعه‌ای از توانمندی‌ها، نمونه‌کارها و پروژه‌های اجراشده زیگورات را در یک صفحه ببینید.
                </p>
                <div class="portfolio-hero__actions">
                    <a class="portfolio-button portfolio-button--primary" href="#digital-catalog">مشاهده کاتالوگ</a>
                    <?php if ($projects_url) : ?>
                        <a class="portfolio-button portfolio-button--secondary" href="<?php echo esc_url($projects_url); ?>">مشاهده همه پروژه‌ها</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section id="digital-catalog" class="portfolio-catalog" aria-labelledby="portfolio-catalog-title">
            <div class="container">
                <div class="portfolio-section-heading">
                    <span>نسخه دیجیتال</span>
                    <h2 id="portfolio-catalog-title">کاتالوگ زیگورات</h2>
                </div>

                <article class="portfolio-editor-content">
                    <?php if ($catalog_pdf_url) : ?>
                        <div class="portfolio-flipbook" data-portfolio-flipbook data-pdf-url="<?php echo esc_url($catalog_pdf_url); ?>">
                            <div class="portfolio-flipbook__topbar">
                                <div>
                                    <strong><?php echo esc_html($catalog_pdf_title ?: 'کاتالوگ زیگورات'); ?></strong>
                                    <span data-flipbook-status>در حال آماده‌سازی کاتالوگ…</span>
                                </div>
                                <a class="portfolio-flipbook__download" href="<?php echo esc_url($catalog_pdf_url); ?>" download>
                                    دانلود PDF
                                </a>
                            </div>

                            <div class="portfolio-flipbook__stage" data-flipbook-stage>
                                <div class="portfolio-flipbook__loading" data-flipbook-loading>
                                    <span aria-hidden="true"></span>
                                    <strong>در حال بارگذاری دفترچه</strong>
                                    <small>لطفاً چند لحظه صبر کنید</small>
                                </div>
                                <div class="portfolio-flipbook__book" data-flipbook-book aria-label="کاتالوگ ورق‌خور زیگورات"></div>
                            </div>

                            <div class="portfolio-flipbook__controls" aria-label="کنترل‌های کاتالوگ">
                                <button type="button" data-flipbook-next aria-label="صفحه بعد">صفحه بعد <span aria-hidden="true">←</span></button>
                                <div class="portfolio-flipbook__counter" aria-live="polite">
                                    صفحه <b data-flipbook-current>۱</b> از <b data-flipbook-total>—</b>
                                </div>
                                <button type="button" data-flipbook-prev aria-label="صفحه قبل"><span aria-hidden="true">→</span> صفحه قبل</button>
                                <button class="portfolio-flipbook__fullscreen" type="button" data-flipbook-fullscreen aria-label="نمایش تمام‌صفحه">تمام‌صفحه</button>
                            </div>
                            <p class="portfolio-flipbook__hint">برای ورق‌زدن، گوشه صفحه را با ماوس یا انگشت بکشید.</p>
                        </div>
                    <?php endif; ?>

                    <?php if (has_post_thumbnail()) : ?>
                        <figure class="portfolio-cover">
                            <?php the_post_thumbnail('full', array('loading' => 'eager')); ?>
                        </figure>
                    <?php endif; ?>

                    <div class="portfolio-content-body<?php echo $catalog_pdf_url ? ' portfolio-content-body--with-catalog' : ''; ?>">
                        <?php the_content(); ?>
                    </div>

                    <?php
                    edit_post_link(
                        'ویرایش محتوای این صفحه',
                        '<div class="portfolio-edit-link">',
                        '</div>'
                    );
                    ?>
                </article>
            </div>
        </section>

        <?php if ($projects->have_posts()) : ?>
            <section class="portfolio-projects" aria-labelledby="portfolio-projects-title">
                <div class="container">
                    <div class="portfolio-section-heading portfolio-section-heading--row">
                        <div>
                            <span>منتخب پروژه‌ها</span>
                            <h2 id="portfolio-projects-title">نمونه کارهای اخیر</h2>
                        </div>
                        <?php if ($projects_url) : ?>
                            <a href="<?php echo esc_url($projects_url); ?>">مشاهده همه پروژه‌ها</a>
                        <?php endif; ?>
                    </div>

                    <div class="portfolio-project-grid">
                        <?php while ($projects->have_posts()) : $projects->the_post(); ?>
                            <article class="portfolio-project-card">
                                <a href="<?php the_permalink(); ?>" aria-label="مشاهده پروژه <?php echo esc_attr(get_the_title()); ?>">
                                    <div class="portfolio-project-card__image">
                                        <?php if (has_post_thumbnail()) : ?>
                                            <?php the_post_thumbnail('large', array('loading' => 'lazy')); ?>
                                        <?php else : ?>
                                            <span aria-hidden="true">Z</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="portfolio-project-card__content">
                                        <h3><?php the_title(); ?></h3>
                                        <span>مشاهده جزئیات</span>
                                    </div>
                                </a>
                            </article>
                        <?php endwhile; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>
<?php
    wp_reset_postdata();
endwhile;

get_footer();
