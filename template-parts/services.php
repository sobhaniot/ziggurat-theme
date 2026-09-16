<?php
$services = new WP_Query(array(
    'post_type'      => 'service',
    'post_status'    => 'publish',
    'posts_per_page' => 6,
    'orderby'        => array(
        'menu_order' => 'ASC',
        'title'      => 'ASC',
    ),
));

if (!$services->have_posts()) {
    return;
}
?>
<section class="services-section">
    <div class="<?php echo is_front_page() ? 'container' : 'services-inner'; ?>">
        <?php if (is_front_page()) : ?>
            <div class="services-signboard">
                <span class="services-signboard__screw services-signboard__screw--right" aria-hidden="true"></span>
                <span class="services-signboard__screw services-signboard__screw--left" aria-hidden="true"></span>
        <?php endif; ?>
        <?php if (is_front_page()) : ?>
            <div class="section-header">
                <h2>خدمات زیگورات</h2>
                <p>طراحی، ساخت و اجرای تابلوهای تبلیغاتی</p>
            </div>
        <?php endif; ?>
        <div class="services-grid">
            <?php while ($services->have_posts()) : ?>
                <?php
                $services->the_post();
                $service_slug = get_post_field('post_name', get_the_ID());
                $service_term = get_term_by('slug', $service_slug, 'project_service');
                if (!$service_term || is_wp_error($service_term)) {
                    $service_term = get_term_by('name', get_the_title(), 'project_service');
                }
                $service_filter = ($service_term && !is_wp_error($service_term))
                    ? $service_term->slug
                    : sanitize_title(get_the_title());
                $service_projects_url = add_query_arg(
                    'project_service',
                    $service_filter,
                    get_post_type_archive_link('project')
                );
                ?>
                <article id="service-<?php the_ID(); ?>" class="service-card">
                    <a class="service-card__link" href="<?php echo esc_url($service_projects_url); ?>" aria-label="<?php echo esc_attr(sprintf('مشاهده پروژه‌های %s', get_the_title())); ?>">
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="service-image">
                                <?php the_post_thumbnail('medium_large'); ?>
                            </div>
                        <?php else : ?>
                            <div class="service-icon" aria-hidden="true">✦</div>
                        <?php endif; ?>
                        <h3><?php the_title(); ?></h3>
                        <div class="service-description">
                            <?php
                            $description = has_excerpt() ? get_the_excerpt() : wp_trim_words(get_the_content(), 24);
                            echo wp_kses_post(wpautop($description));
                            ?>
                        </div>
                    </a>
                </article>
            <?php endwhile; ?>
        </div>
        <?php if (is_front_page()) : ?>
                <div class="services-signboard__gold-strip" aria-hidden="true"></div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php wp_reset_postdata(); ?>
