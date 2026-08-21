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
$image_path = $image_id ? get_attached_file($image_id) : '';
$bundled_hero_path = get_theme_file_path('/assets/images/hero.png');
$optimized_hero_path = get_theme_file_path('/assets/images/hero.webp');
$use_optimized_hero = $image_path
    && is_file($image_path)
    && is_file($bundled_hero_path)
    && is_file($optimized_hero_path)
    && wp_basename($image_path) === 'hero.png'
    && filesize($image_path) === filesize($bundled_hero_path);
$optimized_hero_url = $use_optimized_hero
    ? add_query_arg('ver', filemtime($optimized_hero_path), get_theme_file_uri('/assets/images/hero.webp'))
    : '';
$contact_page = get_page_by_path('contact');
$consultation_url = ($contact_page ? get_permalink($contact_page) : home_url('/contact/')) . '#consultation-form';
?>
<section
    id="hero"
    class="hero">
    <div class="hero-wrapper">
        <?php if ($image_id): ?>
            <?php $hero_image = wp_get_attachment_image($image_id, 'full', false, array(
                'class'         => 'hero-bg',
                'alt'           => $hero_title,
                'loading'       => 'eager',
                'fetchpriority' => 'high',
                'decoding'      => 'async',
            )); ?>
            <?php if ($use_optimized_hero): ?>
                <picture class="hero-picture">
                    <source srcset="<?php echo esc_url($optimized_hero_url); ?>" type="image/webp">
                    <?php echo $hero_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </picture>
            <?php else: ?>
                <?php echo $hero_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
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
