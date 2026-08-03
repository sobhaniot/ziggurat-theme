<?php
get_header();
if (have_posts()) :
    while (have_posts()) : the_post();
?>
        <section class="article-single">
            <div class="container">
                <article class="article-content">
                    <h1 class="article-title">
                        <?php the_title(); ?>
                    </h1>
                    <div class="article-meta">
                        <span>
                            تاریخ انتشار:
                            <?php echo get_the_date(); ?>
                        </span>
                    </div>
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="article-image">
                            <?php
                            the_post_thumbnail(
                                'large'
                            );
                            ?>
                        </div>
                    <?php endif; ?>
                    <div class="article-body">
                        <?php the_content(); ?>
                    </div>
                </article>
                <div class="article-navigation">
                    <?php
                    the_post_navigation(
                        array(
                            'prev_text' => '← مطلب قبلی: %title',
                            'next_text' => 'مطلب بعدی: %title →'
                        )
                    );
                    ?>
                </div>
            </div>
        </section>
<?php
    endwhile;
endif;
get_footer();
