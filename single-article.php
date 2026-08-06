<?php
get_header();
if (have_posts()) :
    while (have_posts()) : the_post();
        $popular_articles = zigurat_get_popular_articles(5);
        $article_categories = get_terms(array('taxonomy' => 'article_category', 'hide_empty' => true));
?>
        <section class="article-single">
            <div class="container article-single-layout">
                <article class="article-content">
                    <h1 class="article-title"><?php the_title(); ?></h1>
                    <div class="article-meta">
                        <span>تاریخ انتشار: <?php echo esc_html(get_the_date()); ?></span>
                        <span><?php echo esc_html(number_format_i18n(zigurat_get_article_views(get_the_ID()))); ?> بازدید</span>
                    </div>
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="article-image"><?php the_post_thumbnail('large'); ?></div>
                    <?php endif; ?>
                    <div class="article-body"><?php the_content(); ?></div>
                    <div class="article-navigation">
                        <?php the_post_navigation(array(
                            'prev_text' => '← مطلب قبلی: %title',
                            'next_text' => 'مطلب بعدی: %title →'
                        )); ?>
                    </div>
                </article>

                <aside class="article-sidebar" aria-label="آرشیو و مطالب پربازدید">
                    <section class="article-sidebar-widget">
                        <h2>آرشیو مطالب</h2>
                        <form class="article-sidebar-search" method="get" action="<?php echo esc_url(get_post_type_archive_link('article')); ?>">
                            <label class="screen-reader-text" for="sidebar-article-search">جستجو در مطالب</label>
                            <input id="sidebar-article-search" type="search" name="article_search" placeholder="جستجو...">
                            <button type="submit">جستجو</button>
                        </form>
                        <a class="all-articles-link" href="<?php echo esc_url(get_post_type_archive_link('article')); ?>">نمایش همه مطالب</a>
                        <?php if ($article_categories && !is_wp_error($article_categories)): ?>
                            <ul class="article-sidebar-categories">
                                <?php foreach ($article_categories as $category): ?>
                                    <li>
                                        <a href="<?php echo esc_url(add_query_arg('article_category', $category->slug, get_post_type_archive_link('article'))); ?>">
                                            <?php echo esc_html($category->name); ?>
                                            <span><?php echo esc_html(number_format_i18n($category->count)); ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>

                    <section class="article-sidebar-widget">
                        <h2>۵ مطلب پربازدید</h2>
                        <?php if ($popular_articles->have_posts()): ?>
                            <ol class="popular-articles">
                                <?php while ($popular_articles->have_posts()): $popular_articles->the_post(); ?>
                                    <li>
                                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                        <small><?php echo esc_html(number_format_i18n(zigurat_get_article_views(get_the_ID()))); ?> بازدید</small>
                                    </li>
                                <?php endwhile; ?>
                            </ol>
                        <?php else: ?>
                            <p>هنوز مطلبی برای نمایش وجود ندارد.</p>
                        <?php endif; wp_reset_postdata(); ?>
                    </section>
                </aside>
            </div>
        </section>
<?php
    endwhile;
endif;
get_footer();
