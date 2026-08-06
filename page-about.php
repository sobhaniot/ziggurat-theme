<?php
get_header();
?>
<main class="page-about">
    <?php
    while (have_posts()) :
        the_post();
        $about_stats = zigurat_get_about_stats(get_the_ID());
    ?>
        <section class="about-hero">
            <div class="container">
                <h1>
                    <?php the_title(); ?>
                </h1>
                <?php
                if (has_post_thumbnail()) {
                    the_post_thumbnail(
                        'large'
                    );
                }
                ?>
            </div>
        </section>
        <section class="about-content">
            <div class="container">
                <?php
                the_content();
                ?>
            </div>
        </section>
        <section class="about-stats">
            <div class="container">
                <div class="stats-grid">
                    <?php foreach ($about_stats as $stat): ?>
                        <div class="stat-item">
                            <strong class="counter" data-count="<?php echo esc_attr($stat['value']); ?>">0</strong>
                            <span><?php echo esc_html($stat['suffix']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php
    endwhile;
    ?>
</main>
<?php
get_footer();
?>
