<?php
get_header();
?>
<main class="page-about">
    <?php
    while (have_posts()) :
        the_post();
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
                    <div class="stat-item">
                        <strong
                            class="counter"
                            data-count="<?php echo esc_attr(get_post_meta(get_the_ID(), '_about_projects', true)); ?>">
                            0
                        </strong>
                        <span>
                            <?php
                            echo esc_html(
                                get_post_meta(
                                    get_the_ID(),
                                    '_about_projects_suffix',
                                    true
                                )
                            );
                            ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <strong
                            class="counter"
                            data-count="<?php echo esc_attr(get_post_meta(get_the_ID(), '_about_experience', true)); ?>">
                            0
                        </strong>
                        <span>
                            <?php
                            echo esc_html(
                                get_post_meta(
                                    get_the_ID(),
                                    '_about_experience_suffix',
                                    true
                                )
                            );
                            ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <strong
                            class="counter"
                            data-count="<?php echo esc_attr(get_post_meta(get_the_ID(), '_about_clients', true)); ?>">
                            0
                        </strong>
                        <span>
                            <?php
                            echo esc_html(
                                get_post_meta(
                                    get_the_ID(),
                                    '_about_clients_suffix',
                                    true
                                )
                            );
                            ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <strong
                            class="counter"
                            data-count="<?php echo esc_attr(get_post_meta(get_the_ID(), '_about_cities', true)); ?>">
                            0
                        </strong>
                        <span>
                            <?php
                            echo esc_html(
                                get_post_meta(
                                    get_the_ID(),
                                    '_about_cities_suffix',
                                    true
                                )
                            );
                            ?> </span>
                    </div>
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
