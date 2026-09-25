<?php
if (!defined('ABSPATH') || !zigurat_is_manager()) {
    return;
}

$map_file = get_template_directory() . '/assets/data/iran-provinces.json';
if (!is_readable($map_file)) {
    return;
}

$map_provinces = json_decode((string) file_get_contents($map_file), true);
if (!is_array($map_provinces) || count($map_provinces) !== 31) {
    return;
}

$normalize_province = static function ($value) {
    $value = trim((string) $value);
    $value = str_replace(array('ي', 'ك', "\xE2\x80\x8C"), array('ی', 'ک', ' '), $value);
    $value = preg_replace('/^استان\s+/u', '', $value);
    return preg_replace('/\s+/u', ' ', $value);
};

$province_lookup = array();
foreach ($map_provinces as $province) {
    if (!empty($province['name'])) {
        $province_lookup[$normalize_province($province['name'])] = (string) $province['name'];
    }
}

$partners_by_province = array();
$partner_total = 0;
$mapped_total = 0;
$partner_application_ids = isset($args['application_ids']) && is_array($args['application_ids'])
    ? $args['application_ids']
    : array();
foreach ($partner_application_ids as $application_id) {
    $raw_province = get_post_meta($application_id, '_application_province', true);
    $province_key = $normalize_province($raw_province);
    $province = isset($province_lookup[$province_key]) ? $province_lookup[$province_key] : '';
    $first_name = trim((string) get_post_meta($application_id, '_application_first_name', true));
    $last_name = trim((string) get_post_meta($application_id, '_application_last_name', true));
    $business_name = trim((string) get_post_meta($application_id, '_application_business_name', true));
    $person_name = trim($first_name . ' ' . $last_name);
    $display_name = $business_name !== '' ? $business_name : ($person_name !== '' ? $person_name : get_the_title($application_id));
    $files = get_post_meta($application_id, '_application_files', true);
    $files = is_array($files) ? $files : array();
    $photo = !empty($files['photo'][0]) && is_array($files['photo'][0]) ? $files['photo'][0] : null;

    $record = array(
        'id'         => (int) $application_id,
        'name'       => $display_name,
        'person'     => $business_name !== '' ? $person_name : '',
        'city'       => trim((string) get_post_meta($application_id, '_application_city', true)),
        'profession' => trim((string) get_post_meta($application_id, '_application_profession', true)),
        'type'       => zigurat_application_type_label(get_post_meta($application_id, '_application_application_type', true)),
        'photo'      => $photo ? zigurat_application_private_file_url($application_id, 'photo:0') : '',
        'url'        => zigurat_application_resume_url($application_id),
    );
    $partner_total++;

    if ($province !== '') {
        if (!isset($partners_by_province[$province])) {
            $partners_by_province[$province] = array();
        }
        $partners_by_province[$province][] = $record;
        $mapped_total++;
    }
}
?>
<section class="manager-partner-map no-print" data-partner-map aria-labelledby="manager-partner-map-title">
    <div class="manager-partner-map__heading">
        <div>
            <span>شبکه همکاری زیگورات</span>
            <h3 id="manager-partner-map-title">نقشه همکاران و تأمین‌کنندگان</h3>
            <p>روی هر استان بزنید تا افراد ثبت‌شده، عکس و زمینه فعالیت آن‌ها نمایش داده شود.</p>
        </div>
        <div class="manager-partner-map__summary">
            <strong><?php echo esc_html(number_format_i18n($partner_total)); ?></strong>
            <span>رزومه ثبت‌شده</span>
            <small><?php echo esc_html(number_format_i18n(count($partners_by_province))); ?> استان فعال</small>
        </div>
    </div>

    <div class="manager-partner-map__layout">
        <div class="manager-partner-map__visual">
            <div class="manager-partner-map__status" aria-live="polite">
                <strong data-partner-map-title>یک استان را انتخاب کنید</strong>
                <span data-partner-map-description>استان‌های طلایی دارای همکار یا تأمین‌کننده ثبت‌شده هستند.</span>
            </div>
            <svg viewBox="20 0 970 960" role="img" aria-labelledby="manager-partner-map-svg-title manager-partner-map-svg-desc" preserveAspectRatio="xMidYMid meet">
                <title id="manager-partner-map-svg-title">نقشه خصوصی همکاران زیگورات</title>
                <desc id="manager-partner-map-svg-desc">با انتخاب هر استان، همکاران و تأمین‌کنندگان ثبت‌شده همان استان در کنار نقشه نمایش داده می‌شوند.</desc>
                <g>
                    <?php foreach ($map_provinces as $province):
                        $name = isset($province['name']) ? (string) $province['name'] : '';
                        $path = isset($province['path']) ? (string) $province['path'] : '';
                        if ($name === '' || $path === '') {
                            continue;
                        }
                        $count = isset($partners_by_province[$name]) ? count($partners_by_province[$name]) : 0;
                        $level = $count >= 5 ? 3 : ($count >= 2 ? 2 : ($count === 1 ? 1 : 0));
                        $label = $count
                            ? sprintf('%s، %s نفر ثبت‌شده', $name, number_format_i18n($count))
                            : sprintf('%s، بدون همکار ثبت‌شده', $name);
                    ?>
                        <path
                            class="manager-partner-map__province<?php echo $count ? ' is-active level-' . esc_attr($level) : ''; ?>"
                            d="<?php echo esc_attr($path); ?>"
                            data-province="<?php echo esc_attr($name); ?>"
                            data-count="<?php echo esc_attr($count); ?>"
                            role="button"
                            tabindex="0"
                            aria-label="<?php echo esc_attr($label); ?>"
                        ><title><?php echo esc_html($label); ?></title></path>
                    <?php endforeach; ?>
                </g>
            </svg>
            <div class="manager-partner-map__legend" aria-label="راهنمای نقشه">
                <span><i class="has-partners" aria-hidden="true"></i> دارای همکار</span>
                <span><i aria-hidden="true"></i> بدون همکار</span>
            </div>
        </div>

        <div class="manager-partner-map__results" data-partner-map-results>
            <div class="manager-partner-map__empty">
                <span aria-hidden="true">🗺️</span>
                <strong>استان موردنظر را انتخاب کنید</strong>
                <p>کارت همکاران همان استان در این قسمت نشان داده می‌شود.</p>
            </div>
        </div>
    </div>
    <?php if ($mapped_total < $partner_total): ?>
        <p class="manager-partner-map__unmapped"><?php echo esc_html(number_format_i18n($partner_total - $mapped_total)); ?> رزومه به‌دلیل نداشتن استان معتبر روی نقشه قرار نگرفته است.</p>
    <?php endif; ?>
    <script type="application/json" data-partner-map-data><?php echo wp_json_encode($partners_by_province, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
</section>
