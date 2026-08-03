<?php
$query = new WP_Query(array(
    'post_type' => 'brand',
    'posts_per_page' => -1,
    'orderby' => 'menu_order',
    'order' => 'ASC'
));
?>
<section class="clients">
    <div class="container">
        <div class="section-title">
            <h2>برخی از مشتریان ما</h2>
            <p>
                اعتماد برندهای معتبر، بزرگ‌ترین سرمایه ماست.
            </p>
        </div>
        <?php if ($query->have_posts()) : ?>
            <div class="clients-grid">
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <?php
                    $website = get_post_meta(
                        get_the_ID(),
                        '_client_website',
                        true
                    );
                    ?>
                    <div class="client-item">
                        <?php if ($website) : ?>
                            <a
                                href="<?php echo esc_url($website); ?>"
                                target="_blank"
                                rel="noopener">
                            <?php endif; ?>
                            <?php the_post_thumbnail(
                                'medium',
                                array(
                                    'loading' => 'lazy'
                                )
                            ); ?>
                            <span>
                                <?php the_title(); ?>
                            </span>
                            <?php if ($website) : ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php wp_reset_postdata(); ?>
