<?php
get_header();
?>
<section class="projects-archive">
    <div class="container">
        <div class="projects-header">
            <h1>
                پروژه‌های زیگورات
            </h1>
            <p>
                نمونه‌ای از پروژه‌های اجرا شده در زمینه تابلو،
                دکوراسیون و تبلیغات محیطی
            </p>
        </div>
        <div class="projects-grid">
            <?php
            if (have_posts()) :
                while (have_posts()) :
                    the_post();
                    $city = get_post_meta(
                        get_the_ID(),
                        '_project_city',
                        true
                    );
                    $type = get_post_meta(
                        get_the_ID(),
                        '_project_type',
                        true
                    );
            ?>
                    <div class="project-card">
                        <a href="<?php the_permalink(); ?>">
                            <?php
                            if (has_post_thumbnail()) {
                                the_post_thumbnail('large');
                            }
                            ?>
                            <div class="project-card-content">
                                <h2>
                                    <?php the_title(); ?>
                                </h2>
                                <?php if ($city): ?>
                                    <span>
                                        📍 <?php echo esc_html($city); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($type): ?>
                                    <span>
                                        🛠 <?php echo esc_html($type); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php
                endwhile;
            else:
                ?>
                <p>
                    هنوز پروژه‌ای ثبت نشده است.
                </p>
            <?php
            endif;
            ?>
        </div>
    </div>
</section>
<?php
get_footer();
?>
