<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_get_contact_details()
{
    $defaults = array(
        'phone'        => '09125606941',
        'phone_2'      => '',
        'email'        => 'zigguratcorporation@gmail.com',
        'address'      => 'تهران، ایران',
        'location_url' => '',
    );

    return wp_parse_args(get_option('zigurat_contact_details', array()), $defaults);
}

function zigurat_contact_phone_url($phone)
{
    $phone = strtr((string) $phone, array(
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ));

    return preg_replace('/[^0-9+]/', '', $phone);
}

function zigurat_get_contact_phones($details = null)
{
    $details = is_array($details) ? $details : zigurat_get_contact_details();
    $phones = array_filter(array(
        trim((string) ($details['phone'] ?? '')),
        trim((string) ($details['phone_2'] ?? '')),
    ));

    return array_values(array_unique($phones));
}

function zigurat_get_contact_location_url($details = null)
{
    $details = is_array($details) ? $details : zigurat_get_contact_details();
    if (!empty($details['location_url'])) {
        return esc_url_raw($details['location_url']);
    }

    $address = trim((string) ($details['address'] ?? ''));
    if (!$address) {
        return '';
    }

    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
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
            <?php
            $icon_html = !empty($link['icon_id'])
                ? wp_get_attachment_image((int) $link['icon_id'], 'thumbnail', false, array(
                    'alt'      => $label,
                    'class'    => 'social-link__icon',
                    'loading'  => 'eager',
                    'decoding' => 'async',
                ))
                : '';
            ?>
            <a class="social-link" href="<?php echo esc_url($link['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($label); ?>" title="<?php echo esc_attr($label); ?>">
                <?php if ($icon_html): ?>
                    <?php echo wp_kses_post($icon_html); ?>
                <?php else: ?>
                    <span><?php echo esc_html($label); ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
}
