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
        <form class="article-filters" method="get" action="<?php echo esc_url(get_post_type_archive_link('article')); ?>">
            <label>
                <span class="screen-reader-text">جستجو در مطالب</span>
                <input type="search" name="article_search" value="<?php echo isset($_GET['article_search']) && is_string($_GET['article_search']) ? esc_attr(wp_unslash($_GET['article_search'])) : ''; ?>" placeholder="جستجو در مطالب...">
            </label>
            <?php
            $article_filters = array(
                'article_category' => 'همه دسته‌ها',
                'article_tag'      => 'همه برچسب‌ها',
            );
            foreach ($article_filters as $taxonomy => $placeholder):
                $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => true));
                $selected = isset($_GET[$taxonomy]) && is_string($_GET[$taxonomy])
                    ? sanitize_title(wp_unslash($_GET[$taxonomy]))
                    : '';
            ?>
                <label>
                    <span class="screen-reader-text"><?php echo esc_html($placeholder); ?></span>
                    <select name="<?php echo esc_attr($taxonomy); ?>">
                        <option value=""><?php echo esc_html($placeholder); ?></option>
                        <?php if (!is_wp_error($terms)): foreach ($terms as $term): ?>
                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($selected, $term->slug); ?>><?php echo esc_html($term->name); ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </label>
            <?php endforeach; ?>
            <button type="submit">نمایش مطالب</button>
            <?php if (!empty($_GET['article_search']) || !empty($_GET['article_category']) || !empty($_GET['article_tag'])): ?>
                <a href="<?php echo esc_url(get_post_type_archive_link('article')); ?>">حذف فیلترها</a>
            <?php endif; ?>
        </form>
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
                            <?php $article_categories = get_the_terms(get_the_ID(), 'article_category'); ?>
                            <?php if ($article_categories && !is_wp_error($article_categories)): ?>
                                <div class="article-categories">
                                    <?php foreach ($article_categories as $article_category): ?>
                                        <span><?php echo esc_html($article_category->name); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="article-date">
                                <?php echo get_the_date(); ?> · <?php echo esc_html(number_format_i18n(zigurat_get_article_views(get_the_ID()))); ?> بازدید
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
