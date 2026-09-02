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
            $home_project_ids = zigurat_get_home_project_ids();
            $projects = new WP_Query(array(
                'post_type'      => 'project',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'post__in'       => $home_project_ids ?: array(0),
                'orderby'        => 'post__in',
                'no_found_rows'  => true,
            ));
            if ($projects->have_posts()) :
                while ($projects->have_posts()) :
                    $projects->the_post();
                    $project_client_terms = get_the_terms(get_the_ID(), 'project_client');
                    $project_client_term = $project_client_terms && !is_wp_error($project_client_terms)
                        ? reset($project_client_terms)
                        : null;
                    $project_card_url = $project_client_term
                        ? add_query_arg('project_client', $project_client_term->slug, get_post_type_archive_link('project'))
                        : get_permalink();
            ?>
                    <article class="project-card">
                        <a href="<?php echo esc_url($project_card_url); ?>"<?php if ($project_client_term): ?> aria-label="مشاهده پروژه‌های کارفرمای <?php echo esc_attr($project_client_term->name); ?>"<?php endif; ?>>
                            <div class="project-image">
                                <?php
                                if (has_post_thumbnail()) {
                                    $project_thumbnail_id = get_post_thumbnail_id();
                                    the_post_thumbnail(
                                        zigurat_attachment_size_or_fallback($project_thumbnail_id, 'zigurat-project-card'),
                                        array(
                                            'loading'       => 'lazy',
                                            'decoding'      => 'async',
                                            'fetchpriority' => 'low',
                                            'sizes'         => '(max-width: 600px) calc(100vw - 40px), (max-width: 900px) calc(50vw - 45px), calc(25vw - 38px)',
                                        )
                                    );
                                }
                                ?>
                            </div>
                            <div class="project-content">
                                <h3>
                                    <?php the_title(); ?>
                                </h3>
                                <?php
                                $city = zigurat_get_project_term_name(get_the_ID(), 'project_city', '_project_city');
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
