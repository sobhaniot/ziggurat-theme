<?php
if (!defined('ABSPATH') || !zigurat_is_manager()) {
    return;
}

$statistics = zigurat_get_manager_views_statistics(10);
$total_views = max(0, (int) $statistics['total_views']);
$article_share = $total_views ? round(((int) $statistics['article_views'] / $total_views) * 100, 1) : 0;
$project_share = $total_views ? round(((int) $statistics['project_views'] / $total_views) * 100, 1) : 0;
$download_share = $total_views ? max(0, round(((int) $statistics['download_views'] / $total_views) * 100, 1)) : 0;
$project_share_end = min(100, $article_share + $project_share);
$traffic_chart_data = zigurat_get_all_views_chart_data();
$top_max = 0;
foreach ($statistics['top_content'] as $content_item) {
    $top_max = max($top_max, (int) $content_item->views);
}
?>
<section class="manager-views" data-live-views-root data-initial-total="<?php echo esc_attr($total_views); ?>" aria-labelledby="manager-views-title">
    <div class="manager-views__toolbar no-print">
        <a href="<?php echo esc_url(zigurat_manager_login_url()); ?>">بازگشت به پنل مدیران</a>
        <span>آمار تجمعی ثبت‌شده تا این لحظه</span>
        <span class="manager-views__live-state" data-live-state><i aria-hidden="true"></i><b>در حال اتصال زنده…</b></span>
    </div>

    <header class="manager-views__heading">
        <div>
            <span>گزارش عملکرد محتوا</span>
            <h2 id="manager-views-title">آمار بازدید سایت</h2>
        </div>
        <p>بازدید کاربران مهمان از صفحات مطالب و پروژه‌ها، به‌همراه تعداد دریافت واقعی فایل‌های مرکز دانلود.</p>
    </header>

    <div class="manager-view-cards">
        <article class="manager-view-card manager-view-card--total">
            <span>مجموع بازدید و دانلود</span>
            <strong data-live-metric="total_views"><?php echo esc_html(number_format_i18n($total_views)); ?></strong>
            <small>مطالب، پروژه‌ها و دریافت فایل</small>
            <div class="manager-view-card__toast" data-live-card-toast hidden></div>
        </article>
        <article class="manager-view-card">
            <span>بازدید مطالب</span>
            <strong data-live-metric="article_views"><?php echo esc_html(number_format_i18n($statistics['article_views'])); ?></strong>
            <small><?php echo esc_html(number_format_i18n($statistics['article_count'])); ?> مطلب منتشرشده</small>
        </article>
        <article class="manager-view-card">
            <span>بازدید پروژه‌ها</span>
            <strong data-live-metric="project_views"><?php echo esc_html(number_format_i18n($statistics['project_views'])); ?></strong>
            <small><?php echo esc_html(number_format_i18n($statistics['project_count'])); ?> پروژه منتشرشده</small>
        </article>
        <article class="manager-view-card manager-view-card--download">
            <span>دانلود فایل‌ها</span>
            <strong data-live-metric="download_views"><?php echo esc_html(number_format_i18n($statistics['download_views'])); ?></strong>
            <small><?php echo esc_html(number_format_i18n($statistics['download_count'])); ?> فایل منتشرشده</small>
        </article>
        <article class="manager-view-card">
            <span>میانگین بازدید</span>
            <div class="manager-view-card__averages">
                <small>هر مطلب <b data-live-metric="article_average"><?php echo esc_html(number_format_i18n($statistics['article_average'])); ?></b></small>
                <small>هر پروژه <b data-live-metric="project_average"><?php echo esc_html(number_format_i18n($statistics['project_average'])); ?></b></small>
                <small>هر فایل <b data-live-metric="download_average"><?php echo esc_html(number_format_i18n($statistics['download_average'])); ?></b></small>
            </div>
        </article>
    </div>

    <section class="manager-traffic" data-manager-traffic-chart aria-labelledby="manager-traffic-title">
        <div class="manager-traffic__heading">
            <div><span>روند زمانی بازدید</span><h3 id="manager-traffic-title">نمودار بازدید روزانه</h3><small data-traffic-subtitle>۱۴ روز اخیر</small></div>
            <div class="manager-traffic__ranges" role="group" aria-label="انتخاب بازه نمودار">
                <button type="button" class="is-active" data-traffic-range="daily">روزانه</button>
                <button type="button" data-traffic-range="weekly">هفتگی</button>
                <button type="button" data-traffic-range="monthly">ماهانه</button>
                <button type="button" data-traffic-range="yearly">سالانه</button>
            </div>
        </div>
        <div class="manager-traffic__summary"><span>مجموع بازدید این بازه</span><strong data-traffic-total>۰</strong></div>
        <div class="manager-traffic__legend"><span><i class="is-article"></i>مطالب</span><span><i class="is-project"></i>پروژه‌ها</span><span><i class="is-download"></i>دانلودها</span></div>
        <div class="manager-traffic__scroll">
            <div class="manager-traffic__plot" data-traffic-plot role="img" aria-label="نمودار بازدید مطالب، پروژه‌ها و دانلودها">
                <div class="manager-traffic__bars" data-traffic-bars></div>
                <div class="manager-traffic__empty" data-traffic-empty hidden>در این بازه هنوز بازدیدی ثبت نشده است.</div>
            </div>
        </div>
        <p class="manager-traffic__note">داده‌های زمانی از زمان فعال‌شدن این نمودار ثبت می‌شوند؛ آمار تجمعی قبلی بدون تغییر در کارت‌های بالای صفحه باقی می‌ماند.</p>
        <script type="application/json" data-manager-traffic-data><?php echo wp_json_encode($traffic_chart_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    </section>

    <div class="manager-view-charts">
        <section class="manager-view-chart manager-view-chart--distribution">
            <div class="manager-view-chart__heading">
                <h3>سهم بخش‌ها از بازدید</h3>
                <small>مقایسه مطالب، پروژه‌ها و دانلودها</small>
            </div>
            <div class="manager-view-donut <?php echo $total_views ? '' : 'is-empty'; ?>" data-live-donut style="--article-share: <?php echo esc_attr($article_share); ?>%;--project-share-end: <?php echo esc_attr($project_share_end); ?>%;" role="img" aria-label="<?php echo esc_attr('سهم مطالب ' . $article_share . ' درصد، سهم پروژه‌ها ' . $project_share . ' درصد و سهم دانلودها ' . $download_share . ' درصد'); ?>">
                <div><strong data-live-metric="total_views"><?php echo esc_html(number_format_i18n($total_views)); ?></strong><span>کل بازدید</span></div>
            </div>
            <ul class="manager-view-legend">
                <li><i class="is-article"></i><span>مطالب</span><strong data-live-share="article"> <?php echo esc_html(number_format_i18n($article_share, 1)); ?>٪</strong></li>
                <li><i class="is-project"></i><span>پروژه‌ها</span><strong data-live-share="project"> <?php echo esc_html(number_format_i18n($project_share, 1)); ?>٪</strong></li>
                <li><i class="is-download"></i><span>دانلودها</span><strong data-live-share="download"> <?php echo esc_html(number_format_i18n($download_share, 1)); ?>٪</strong></li>
            </ul>
        </section>

        <section class="manager-view-chart manager-view-chart--top">
            <div class="manager-view-chart__heading">
                <h3>پربازدیدترین محتواها</h3>
                <small>۱۰ مطلب، پروژه یا فایل برتر</small>
            </div>
            <?php if ($statistics['top_content']): ?>
                <ol class="manager-view-bars" data-live-top-content>
                    <?php foreach ($statistics['top_content'] as $content_item):
                        $views = max(0, (int) $content_item->views);
                        $width = ($top_max && $views > 0) ? max(2, round(($views / $top_max) * 100, 2)) : 0;
                        $type_label = $content_item->post_type === 'project' ? 'پروژه' : ($content_item->post_type === 'zig_download' ? 'دانلود' : 'مطلب');
                    ?>
                        <li>
                            <div class="manager-view-bar__label">
                                <a href="<?php echo esc_url(get_permalink($content_item->ID)); ?>" target="_blank" rel="noopener"><?php echo esc_html($content_item->post_title ?: 'بدون عنوان'); ?></a>
                                <small><?php echo esc_html($type_label); ?></small>
                            </div>
                            <div class="manager-view-bar" aria-hidden="true"><span style="width: <?php echo esc_attr($width); ?>%;"></span></div>
                            <strong><?php echo esc_html(number_format_i18n($views)); ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <div class="manager-view-empty">هنوز مطلب، پروژه یا فایل دانلودی برای نمایش آمار وجود ندارد.</div>
            <?php endif; ?>
        </section>
    </div>

</section>
