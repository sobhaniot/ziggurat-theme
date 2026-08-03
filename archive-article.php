<?php
get_header();
?>
<section class="articles-archive">
    <div class="container">
        <div class="section-header">
            <h1>
                مطالب و مقالات
            </h1>
            <p>
                آخرین مطالب تخصصی زیگورات در زمینه تابلو سازی، دکوراسیون و طراحی فضاهای تجاری
            </p>
        </div>
        <?php if (have_posts()) : ?>
            <div class="articles-grid">
                <?php while (have_posts()) : the_post(); ?>
                    <article class="article-card">
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="article-card-image">
                                <a href="<?php the_permalink(); ?>">
                                    <?php
                                    the_post_thumbnail(
                                        'medium_large'
                                    );
                                    ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="article-card-content">
                            <div class="article-date">
                                <?php echo get_the_date(); ?>
                            </div>
                            <h2>
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </h2>
                            <p>
                                <?php
                                echo wp_trim_words(
                                    get_the_excerpt(),
                                    25,
                                    '...'
                                );
                                ?>
                            </p>
                            <a class="article-more"
                                href="<?php the_permalink(); ?>">
                                مطالعه مطلب
                            </a>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
            <div class="pagination">
                <?php
                the_posts_pagination(
                    array(
                        'prev_text' => 'قبلی',
                        'next_text' => 'بعدی'
                    )
                );
                ?>
            </div>
        <?php else : ?>
            <p>
                هنوز مطلبی منتشر نشده است.
            </p>
        <?php endif; ?>
    </div>
</section>
<?php
get_footer();
