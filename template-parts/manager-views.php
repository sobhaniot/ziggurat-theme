<?php
if (!defined('ABSPATH') || !zigurat_is_manager()) {
    return;
}

$statistics = zigurat_get_manager_views_statistics(10);
$total_views = max(0, (int) $statistics['total_views']);
$article_share = $total_views ? round(((int) $statistics['article_views'] / $total_views) * 100, 1) : 0;
$project_share = $total_views ? round(((int) $statistics['project_views'] / $total_views) * 100, 1) : 0;
$top_max = 0;
foreach ($statistics['top_content'] as $content_item) {
    $top_max = max($top_max, (int) $content_item->views);
}
?>
<section class="manager-views" aria-labelledby="manager-views-title">
    <div class="manager-views__toolbar no-print">
        <a href="<?php echo esc_url(zigurat_manager_login_url()); ?>">بازگشت به پنل مدیران</a>
        <span>آمار تجمعی ثبت‌شده تا این لحظه</span>
    </div>

    <header class="manager-views__heading">
        <div>
            <span>گزارش عملکرد محتوا</span>
            <h2 id="manager-views-title">آمار بازدید سایت</h2>
        </div>
        <p>بازدید کاربران مهمان از صفحات مطالب و پروژه‌ها؛ بازدید مدیران و ربات‌های شناخته‌شده محاسبه نمی‌شود.</p>
    </header>

    <div class="manager-view-cards">
        <article class="manager-view-card manager-view-card--total">
            <span>مجموع بازدید</span>
            <strong><?php echo esc_html(number_format_i18n($total_views)); ?></strong>
            <small>مطالب و پروژه‌ها</small>
        </article>
        <article class="manager-view-card">
            <span>بازدید مطالب</span>
            <strong><?php echo esc_html(number_format_i18n($statistics['article_views'])); ?></strong>
            <small><?php echo esc_html(number_format_i18n($statistics['article_count'])); ?> مطلب منتشرشده</small>
        </article>
        <article class="manager-view-card">
            <span>بازدید پروژه‌ها</span>
            <strong><?php echo esc_html(number_format_i18n($statistics['project_views'])); ?></strong>
            <small><?php echo esc_html(number_format_i18n($statistics['project_count'])); ?> پروژه منتشرشده</small>
        </article>
        <article class="manager-view-card">
            <span>میانگین بازدید</span>
            <div class="manager-view-card__averages">
                <small>هر مطلب <b><?php echo esc_html(number_format_i18n($statistics['article_average'])); ?></b></small>
                <small>هر پروژه <b><?php echo esc_html(number_format_i18n($statistics['project_average'])); ?></b></small>
            </div>
        </article>
    </div>

    <div class="manager-view-charts">
        <section class="manager-view-chart manager-view-chart--distribution">
            <div class="manager-view-chart__heading">
                <h3>سهم بخش‌ها از بازدید</h3>
                <small>مقایسه مطالب و پروژه‌ها</small>
            </div>
            <div class="manager-view-donut <?php echo $total_views ? '' : 'is-empty'; ?>" style="--article-share: <?php echo esc_attr($article_share); ?>%;" role="img" aria-label="<?php echo esc_attr('سهم مطالب ' . $article_share . ' درصد و سهم پروژه‌ها ' . $project_share . ' درصد'); ?>">
                <div><strong><?php echo esc_html(number_format_i18n($total_views)); ?></strong><span>کل بازدید</span></div>
            </div>
            <ul class="manager-view-legend">
                <li><i class="is-article"></i><span>مطالب</span><strong><?php echo esc_html(number_format_i18n($article_share, 1)); ?>٪</strong></li>
                <li><i class="is-project"></i><span>پروژه‌ها</span><strong><?php echo esc_html(number_format_i18n($project_share, 1)); ?>٪</strong></li>
            </ul>
        </section>

        <section class="manager-view-chart manager-view-chart--top">
            <div class="manager-view-chart__heading">
                <h3>پربازدیدترین محتواها</h3>
                <small>۱۰ مطلب یا پروژه برتر</small>
            </div>
            <?php if ($statistics['top_content']): ?>
                <ol class="manager-view-bars">
                    <?php foreach ($statistics['top_content'] as $content_item):
                        $views = max(0, (int) $content_item->views);
                        $width = ($top_max && $views > 0) ? max(2, round(($views / $top_max) * 100, 2)) : 0;
                        $type_label = $content_item->post_type === 'project' ? 'پروژه' : 'مطلب';
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
                <div class="manager-view-empty">هنوز مطلب یا پروژه‌ای برای نمایش آمار وجود ندارد.</div>
            <?php endif; ?>
        </section>
    </div>
</section>
