<?php
get_header();
?>

<main class="page-services">
    <?php while (have_posts()) : ?>
        <?php the_post(); ?>
        <section class="services-section">
            <div class="container">
                <div class="section-header">
                    <h1><?php the_title(); ?></h1>
                </div>
                <div class="services-content">
                    <?php the_content(); ?>
                </div>
                <?php get_template_part('template-parts/services'); ?>
            </div>
        </section>
    <?php endwhile; ?>
</main>

<?php
get_footer();
