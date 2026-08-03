<?php
$home = get_page_by_path('hero');
if (!$home) {
    return;
}
$hero_title = get_the_title($home);
$hero_text  = $home->post_excerpt;
if (empty($hero_text)) {
    $hero_text = wp_trim_words(
        wp_strip_all_tags($home->post_content),
        30
    );
}
$image = get_the_post_thumbnail_url(
    $home->ID,
    'full'
);
?>
<section
    id="hero"
    class="hero">
    <div class="hero-wrapper">
        <img
            src="<?php echo esc_url($image); ?>"
            class="hero-bg"
            alt="">
        <div class="hero-overlay"></div>
        <div class="container">
            <div class="hero-content">
                <h1>
                    <?php echo nl2br(esc_html($hero_title)); ?>
                </h1>
                <p>
                    <?php echo esc_html($hero_text); ?>
                </p>
                <div class="hero-buttons">
                    <a
                        href="<?php echo esc_url(home_url('/projects')); ?>"
                        class="hero-btn primary">
                        مشاهده پروژه‌ها
                    </a>
                    <a
                        href="<?php echo esc_url(home_url('/contact')); ?>"
                        class="hero-btn secondary">
                        درخواست مشاوره
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
<a href="#about" class="scroll-down">
    ↓
</a>
