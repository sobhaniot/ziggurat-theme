<?php
$home = get_page_by_path('hero');
if (!$home) {
    return;
}
$hero_title = get_the_title($home);
$normalized_title = trim(wp_strip_all_tags($hero_title));
if ($normalized_title === '' || in_array(strtolower($normalized_title), array('hero', 'هیرو'), true)) {
    $hero_title = 'طراحی و اجرای تابلوهای تبلیغاتی و دکوراسیون تجاری';
}
$hero_text  = $home->post_excerpt;
if (empty($hero_text)) {
    $hero_text = wp_trim_words(
        wp_strip_all_tags($home->post_content),
        30
    );
}
$image_id = get_post_thumbnail_id($home->ID);
$contact_page = get_page_by_path('contact');
$consultation_url = ($contact_page ? get_permalink($contact_page) : home_url('/contact/')) . '#consultation-form';
?>
<section
    id="hero"
    class="hero">
    <div class="hero-wrapper">
        <?php if ($image_id): ?>
            <?php echo wp_get_attachment_image($image_id, 'full', false, array(
                'class'         => 'hero-bg',
                'alt'           => $hero_title,
                'loading'       => 'eager',
                'fetchpriority' => 'high',
                'decoding'      => 'async',
            )); ?>
        <?php endif; ?>
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
                        href="<?php echo esc_url($consultation_url); ?>"
                        class="hero-btn secondary">
                        درخواست مشاوره
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
