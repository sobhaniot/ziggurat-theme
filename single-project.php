<?php
get_header();
if (have_posts()) :
    while (have_posts()) : the_post();
        $project_id = get_the_ID();
        // Meta fields
        $city = zigurat_get_project_term_name($project_id, 'project_city', '_project_city');
        $province = zigurat_get_project_term_name($project_id, 'project_province', '_project_province');
        $client = zigurat_get_project_term_name($project_id, 'project_client', '_project_client');
        $date = get_post_meta(get_the_ID(), '_project_date', true);
        $type = zigurat_get_project_term_name($project_id, 'project_sign_type', '_project_type');
        $duration = get_post_meta(get_the_ID(), '_project_duration', true);
        // Gallery
        $gallery = get_post_meta(
            $project_id,
            '_project_gallery',
            true
        );
        $images = array();
        if ($gallery) {
            $images = explode(',', $gallery);
        }
?>
        <section class="project-single">
            <div class="container">
                <div class="project-hero">
                    <?php if (has_post_thumbnail()) : ?>
                        <?php the_post_thumbnail('full'); ?>
                    <?php endif; ?>
                    <div class="project-hero-overlay">
                        <h1>
                            <?php the_title(); ?>
                        </h1>
                    </div>
                </div>
                <div class="project-info-card">
                    <div class="info-item">
                        <strong>کارفرما:</strong>
                        <?php echo esc_html($client); ?>
                    </div>
                    <div class="info-item">
                        <strong>شهر اجرا:</strong>
                        <?php echo esc_html($city); ?>
                    </div>
                    <div class="info-item">
                        <strong>استان اجرا:</strong>
                        <?php echo esc_html($province); ?>
                    </div>
                    <div class="info-item">
                        <strong>تاریخ اجرا:</strong>
                        <?php echo esc_html($date); ?>
                    </div>
                    <div class="info-item">
                        <strong>نوع فعالیت/اجرا:</strong>
                        <?php echo esc_html($type); ?>
                    </div>
                    <div class="info-item">
                        <strong>مدت اجرا:</strong>
                        <?php echo esc_html($duration); ?>
                    </div>
                </div>
                <div class="project-content">
                    <?php the_content(); ?>
                </div>
                <?php
                $gallery = get_post_meta(
                    get_the_ID(),
                    '_project_gallery',
                    true
                );
                if ($gallery):
                    $images = explode(',', $gallery);
                ?>
                    <div class="project-gallery">
                        <h2>
                            تصاویر پروژه
                        </h2>
                        <div class="masonry-gallery">
                            <?php foreach ($images as $image_id): ?>
                                <div class="masonry-item">
                                    <a
                                        href="<?php echo esc_url(wp_get_attachment_image_url($image_id, 'full')); ?>"
                                        class="glightbox"
                                        data-gallery="project-gallery">
                                        <?php
                                        echo wp_get_attachment_image(
                                            $image_id,
                                            'large',
                                            false,
                                            array(
                                                'loading' => 'lazy'
                                            )
                                        );
                                        ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php
        $related_projects = new WP_Query(array(
            'post_type' => 'project',
            'posts_per_page' => 3,
            'post__not_in' => array(get_the_ID()),
            'orderby' => 'rand',
        ));
        if ($related_projects->have_posts()) :
        ?>
            <section class="related-projects">
                <div class="container">
                    <h2>
                        پروژه‌های مشابه
                    </h2>
                    <div class="related-projects-grid">
                        <?php while ($related_projects->have_posts()) : $related_projects->the_post(); ?>
                            <a href="<?php the_permalink(); ?>" class="related-card">
                                <?php if (has_post_thumbnail()) : ?>
                                    <div class="related-image">
                                        <?php
                                        the_post_thumbnail(
                                            'medium_large'
                                        );
                                        ?>
                                    </div>
                                <?php endif; ?>
                                <h3>
                                    <?php the_title(); ?>
                                </h3>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            </section>
        <?php
        endif;
        wp_reset_postdata();
        ?>
<?php
    endwhile;
endif;
get_footer();
