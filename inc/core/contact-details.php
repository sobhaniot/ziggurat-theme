<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_get_contact_details()
{
    $defaults = array(
        'phone'   => '09125606941',
        'email'   => 'zigguratcorporation@gmail.com',
        'address' => 'تهران، ایران',
    );

    return wp_parse_args(get_option('zigurat_contact_details', array()), $defaults);
}

function zigurat_get_social_links()
{
    $links = get_option('zigurat_social_links', array());
    return is_array($links) ? $links : array();
}

function zigurat_render_social_links($class = '')
{
    $links = zigurat_get_social_links();
    if (!$links) {
        return;
    }
    ?>
    <div class="social-links <?php echo esc_attr($class); ?>" aria-label="شبکه‌های اجتماعی">
        <?php foreach ($links as $link):
            if (!is_array($link) || empty($link['url'])) {
                continue;
            }
            $label = !empty($link['label']) ? $link['label'] : 'شبکه اجتماعی';
        ?>
            <a href="<?php echo esc_url($link['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($label); ?>">
                <?php if (!empty($link['icon_id'])): ?>
                    <?php echo wp_get_attachment_image((int) $link['icon_id'], 'thumbnail', false, array('alt' => $label, 'loading' => 'lazy')); ?>
                <?php else: ?>
                    <span><?php echo esc_html($label); ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
}
