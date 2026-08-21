<section class="latest-posts">
    <div class="container">
        <div class="section-header">
            <h2>
                آخرین مطالب
            </h2>
            <p>
                جدیدترین اخبار، ایده‌ها و مطالب تخصصی زیگورات
            </p>
        </div>
        <div class="posts-grid">
            <?php
            $latest_posts = new WP_Query(array(
                'post_type' => 'article',
                'posts_per_page' => 3,
                'post_status'    => 'publish',
                'ignore_sticky_posts' => true,
                'no_found_rows'  => true,
            ));
            if ($latest_posts->have_posts()) :
                while ($latest_posts->have_posts()) :
                    $latest_posts->the_post();
            ?>
                    <article class="post-card">
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="post-image">
                                <a href="<?php the_permalink(); ?>">
                                    <?php
                                    $article_thumbnail_id = get_post_thumbnail_id();
                                    the_post_thumbnail(
                                        zigurat_attachment_size_or_fallback($article_thumbnail_id, 'zigurat-article-card'),
                                        array(
                                            'loading'       => 'lazy',
                                            'decoding'      => 'async',
                                            'fetchpriority' => 'low',
                                            'sizes'         => '(max-width: 600px) calc(100vw - 40px), (max-width: 900px) calc(50vw - 45px), calc(33vw - 40px)',
                                        )
                                    );
                                    ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="post-content">
                            <h3>
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </h3>
                            <p>
                                <?php
                                echo wp_trim_words(
                                    get_the_excerpt(),
                                    20,
                                    '...'
                                );
                                ?>
                            </p>
                            <a class="read-more"
                                href="<?php the_permalink(); ?>">
                                ادامه مطلب
                            </a>
                        </div>
                    </article>
                <?php
                endwhile;
                wp_reset_postdata();
            else:
                ?>
                <p>
                    مطلبی برای نمایش وجود ندارد.
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>
