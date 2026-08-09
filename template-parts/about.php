<?php
$about = get_page_by_path('about');
if (!$about) {
    return;
}
$title = get_the_title($about);
$link  = get_permalink($about->ID);
$excerpt = $about->post_excerpt;
$about_stats = zigurat_get_about_stats($about->ID);
if (empty($excerpt)) {
    $excerpt = wp_trim_words(
        wp_strip_all_tags($about->post_content),
        55,
        '...'
    );
}
?>
<section id="about" class="about">
    <div class="container">
        <div class="about-wrapper">
            <div class="about-image">
                <?php
                if (has_post_thumbnail($about->ID)) {
                    echo get_the_post_thumbnail(
                        $about->ID,
                        'large',
                        array(
                            'loading' => 'lazy'
                        )
                    );
                }
                ?>
            </div>
            <div class="about-text">
                <div class="section-title">
                    <h2>
                        <?php echo esc_html($title); ?>
                    </h2>
                </div>
                <div class="about-content">
                    <p>
                        <?php echo esc_html($excerpt); ?>
                    </p>
                </div>
                <div class="about-button">
                    <a
                        href="<?php echo esc_url($link); ?>"
                        class="btn-primary">
                        بیشتر بخوانید
                    </a>
                </div>
            </div>
        </div>
        <div class="about-stats">
            <div class="stats-grid">
                <?php foreach ($about_stats as $stat): ?>
                    <div class="stat-item">
                        <div class="stat-number">
                            <strong class="counter" data-count="<?php echo esc_attr($stat['value']); ?>">0</strong>
                        </div>
                        <span class="stat-label"><?php echo esc_html($stat['suffix']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
