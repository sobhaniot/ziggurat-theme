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
    <?php if (is_front_page()) : ?>
        <div class="section-header">
            <h2>خدمات زیگورات</h2>
        </div>
    <?php endif; ?>
    <div class="services-grid">
        <?php while ($services->have_posts()) : ?>
            <?php $services->the_post(); ?>
            <article class="service-card">
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
            </article>
        <?php endwhile; ?>
    </div>
</section>
<?php wp_reset_postdata(); ?>
