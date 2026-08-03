<section class="projects-section">
    <div class="container">
        <div class="section-header">
            <h2>
                آخرین پروژه‌ها
            </h2>
            <p>
                بخشی از پروژه‌های اجرا شده توسط زیگورات
            </p>
        </div>
        <div class="projects-grid">
            <?php
            $projects = new WP_Query(array(
                'post_type'      => 'project',
                'posts_per_page' => 6,
                'post_status'    => 'publish'
            ));
            if ($projects->have_posts()) :
                while ($projects->have_posts()) :
                    $projects->the_post();
            ?>
                    <article class="project-card">
                        <a href="<?php the_permalink(); ?>">
                            <div class="project-image">
                                <?php
                                if (has_post_thumbnail()) {
                                    the_post_thumbnail(
                                        'large'
                                    );
                                }
                                ?>
                            </div>
                            <div class="project-content">
                                <h3>
                                    <?php the_title(); ?>
                                </h3>
                                <?php
                                $city = get_post_meta(
                                    get_the_ID(),
                                    '_project_city',
                                    true
                                );
                                if ($city) :
                                ?>
                                    <span>
                                        <?php echo esc_html($city); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </a>
                    </article>
                <?php
                endwhile;
                wp_reset_postdata();
            else:
                ?>
                <p>
                    هنوز پروژه‌ای ثبت نشده است.
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>
