<?php
if (!defined('ABSPATH')) {
    exit;
}

$map_file = get_template_directory() . '/assets/data/iran-provinces.json';
if (!is_readable($map_file)) {
    return;
}

$provinces = json_decode((string) file_get_contents($map_file), true);
if (!is_array($provinces) || count($provinces) !== 31) {
    return;
}

$project_stats = zigurat_get_project_stats();
$activity = isset($project_stats['province_activity']) && is_array($project_stats['province_activity'])
    ? $project_stats['province_activity']
    : array();
$archive_url = get_post_type_archive_link('project');
$active_total = count($activity);

$province_url = static function ($province) use ($activity, $archive_url) {
    if (empty($activity[$province]['slugs']) || !$archive_url) {
        return '';
    }
    return add_query_arg(
        'project_province',
        implode(',', array_map('sanitize_title', $activity[$province]['slugs'])),
        $archive_url
    );
};
?>
<section class="iran-project-map" aria-labelledby="iran-project-map-title" data-project-map>
    <div class="container">
        <div class="iran-project-map__card">
            <div class="iran-project-map__content">
                <span class="iran-project-map__eyebrow">پروژه‌ها در سراسر ایران</span>
                <h2 id="iran-project-map-title">گستره فعالیت زیگورات</h2>
                <p>استان‌های طلایی محل اجرای پروژه‌های ثبت‌شده زیگورات هستند. روی هر استان فعال بزنید تا پروژه‌های همان استان را ببینید.</p>

                <div class="iran-project-map__status" aria-live="polite">
                    <strong data-map-status-title><?php echo esc_html(number_format_i18n($active_total)); ?> استان فعال</strong>
                    <span data-map-status-description>برای مشاهده تعداد پروژه، نشانگر را روی نقشه ببرید.</span>
                </div>

                <div class="iran-project-map__legend" aria-label="راهنمای رنگ‌های نقشه">
                    <span><i class="is-active" aria-hidden="true"></i> دارای پروژه</span>
                    <span><i aria-hidden="true"></i> بدون پروژه ثبت‌شده</span>
                </div>

                <?php if ($activity): ?>
                    <div class="iran-project-map__active-list" aria-label="استان‌های دارای پروژه">
                        <?php foreach ($provinces as $province):
                            $name = isset($province['name']) ? (string) $province['name'] : '';
                            if ($name === '' || empty($activity[$name]['count'])) {
                                continue;
                            }
                            $url = $province_url($name);
                        ?>
                            <a href="<?php echo esc_url($url); ?>">
                                <?php echo esc_html($name); ?>
                                <small><?php echo esc_html(number_format_i18n((int) $activity[$name]['count'])); ?> پروژه</small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="iran-project-map__visual">
                <svg viewBox="20 0 970 960" role="img" aria-labelledby="iran-map-svg-title iran-map-svg-desc" preserveAspectRatio="xMidYMid meet">
                    <title id="iran-map-svg-title">نقشه استان‌های محل اجرای پروژه‌های زیگورات</title>
                    <desc id="iran-map-svg-desc">استان‌های طلایی دارای پروژه ثبت‌شده هستند و با انتخاب آن‌ها فهرست پروژه‌های همان استان باز می‌شود.</desc>
                    <g class="iran-project-map__provinces">
                        <?php foreach ($provinces as $province):
                            $name = isset($province['name']) ? (string) $province['name'] : '';
                            $path = isset($province['path']) ? (string) $province['path'] : '';
                            if ($name === '' || $path === '') {
                                continue;
                            }
                            $count = isset($activity[$name]['count']) ? (int) $activity[$name]['count'] : 0;
                            $label = $count > 0
                                ? sprintf('%s، %s پروژه؛ مشاهده پروژه‌ها', $name, number_format_i18n($count))
                                : sprintf('%s، بدون پروژه ثبت‌شده', $name);
                            $path_markup = sprintf(
                                '<path class="iran-project-map__province%1$s" d="%2$s" data-province="%3$s" data-count="%4$d" aria-label="%5$s"><title>%5$s</title></path>',
                                $count > 0 ? ' is-active' : '',
                                esc_attr($path),
                                esc_attr($name),
                                $count,
                                esc_attr($label)
                            );
                            $url = $province_url($name);
                            if ($count > 0 && $url !== ''):
                        ?>
                                <a href="<?php echo esc_url($url); ?>" class="iran-project-map__province-link" aria-label="<?php echo esc_attr($label); ?>">
                                    <?php echo $path_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </a>
                            <?php else: ?>
                                <?php echo $path_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </g>
                </svg>
            </div>
        </div>
    </div>
</section>
