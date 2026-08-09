<?php
get_header();

$archive_title = is_search()
    ? sprintf('نتایج جست‌وجو برای «%s»', get_search_query())
    : (is_archive() ? get_the_archive_title() : 'آخرین مطالب');
?>
<main class="content-archive">
    <div class="container">
        <header class="content-archive__header">
            <h1><?php echo esc_html(wp_strip_all_tags($archive_title)); ?></h1>
            <?php if (is_archive() && get_the_archive_description()): ?>
                <div class="content-archive__description"><?php echo wp_kses_post(get_the_archive_description()); ?></div>
            <?php endif; ?>
        </header>

        <?php if (have_posts()): ?>
            <div class="content-archive__grid">
                <?php while (have_posts()): the_post(); ?>
                    <article <?php post_class('content-card'); ?>>
                        <a class="content-card__link" href="<?php the_permalink(); ?>">
                            <?php if (has_post_thumbnail()): ?>
                                <div class="content-card__image"><?php the_post_thumbnail('medium_large', array('loading' => 'lazy')); ?></div>
                            <?php endif; ?>
                            <div class="content-card__body">
                                <h2><?php the_title(); ?></h2>
                                <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 28, '…')); ?></p>
                            </div>
                        </a>
                    </article>
                <?php endwhile; ?>
            </div>
            <?php the_posts_pagination(array('prev_text' => 'قبلی', 'next_text' => 'بعدی')); ?>
        <?php else: ?>
            <p class="content-archive__empty">محتوایی برای نمایش پیدا نشد.</p>
        <?php endif; ?>
    </div>
</main>
<?php get_footer(); ?>
