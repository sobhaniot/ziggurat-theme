<?php
if (!defined('ABSPATH') || !zigurat_is_manager()) {
    return;
}

$read_filter = static function ($key) {
    return isset($_GET[$key]) && is_string($_GET[$key])
        ? sanitize_text_field(wp_unslash($_GET[$key]))
        : '';
};
$selected_province = $read_filter('application_province');
$selected_profession = $read_filter('application_profession');
$selected_type = $read_filter('application_type');
$application_page = max(1, absint($read_filter('application_page')));

$all_application_ids = get_posts(array(
    'post_type'      => 'partner_application',
    'post_status'    => 'private',
    'posts_per_page' => -1,
    'fields'         => 'ids',
));
$provinces = array();
$professions = array();
foreach ($all_application_ids as $application_id) {
    $province = trim((string) get_post_meta($application_id, '_application_province', true));
    $profession = trim((string) get_post_meta($application_id, '_application_profession', true));
    if ($province !== '') {
        $provinces[$province] = $province;
    }
    if ($profession !== '') {
        $professions[$profession] = $profession;
    }
}
natcasesort($provinces);
natcasesort($professions);

$meta_query = array();
if ($selected_province !== '') {
    $meta_query[] = array('key' => '_application_province', 'value' => $selected_province);
}
if ($selected_profession !== '') {
    $meta_query[] = array('key' => '_application_profession', 'value' => $selected_profession);
}
if (in_array($selected_type, array('collaborator', 'supplier'), true)) {
    $meta_query[] = array('key' => '_application_application_type', 'value' => $selected_type);
}
if (count($meta_query) > 1) {
    $meta_query['relation'] = 'AND';
}

$applications = new WP_Query(array(
    'post_type'      => 'partner_application',
    'post_status'    => 'private',
    'posts_per_page' => 20,
    'paged'          => $application_page,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => $meta_query,
));
$unread_application_ids = function_exists('zigurat_application_unread_ids')
    ? zigurat_application_unread_ids()
    : array();

?>
<div class="manager-applications">
    <div class="manager-applications__toolbar no-print">
        <a href="<?php echo esc_url(home_url('/login/')); ?>">بازگشت به پنل</a>
        <button type="button" onclick="window.print()">چاپ فهرست</button>
    </div>
    <div class="manager-applications__heading">
        <div>
            <h2>درخواست‌های همکاری</h2>
            <p><?php echo esc_html(number_format_i18n($applications->found_posts)); ?> درخواست مطابق فیلترها</p>
        </div>
    </div>

    <form class="manager-application-filters no-print" method="get" action="<?php echo esc_url(home_url('/login/')); ?>">
        <input type="hidden" name="manager-section" value="applications">
        <label>استان
            <select name="application_province">
                <option value="">همه استان‌ها</option>
                <?php foreach ($provinces as $province): ?>
                    <option value="<?php echo esc_attr($province); ?>" <?php selected($selected_province, $province); ?>><?php echo esc_html($province); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>زمینه شغلی/تأمین
            <select name="application_profession">
                <option value="">همه زمینه‌ها</option>
                <?php foreach ($professions as $profession): ?>
                    <option value="<?php echo esc_attr($profession); ?>" <?php selected($selected_profession, $profession); ?>><?php echo esc_html($profession); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>نوع متقاضی
            <select name="application_type">
                <option value="">همه</option>
                <option value="collaborator" <?php selected($selected_type, 'collaborator'); ?>>همکار اجرایی</option>
                <option value="supplier" <?php selected($selected_type, 'supplier'); ?>>تأمین‌کننده</option>
            </select>
        </label>
        <button type="submit">اعمال فیلتر</button>
        <?php if ($selected_province || $selected_profession || $selected_type): ?>
            <a href="<?php echo esc_url(add_query_arg('manager-section', 'applications', home_url('/login/'))); ?>">حذف فیلترها</a>
        <?php endif; ?>
    </form>

    <div class="manager-application-table-wrap">
        <table class="manager-application-table">
            <thead>
                <tr>
                    <th>نام/مجموعه</th>
                    <th>نوع</th>
                    <th>زمینه فعالیت</th>
                    <th>استان و شهر</th>
                    <th>شماره تماس</th>
                    <th>محدوده همکاری</th>
                    <th class="no-print">جزئیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($applications->have_posts()): ?>
                    <?php while ($applications->have_posts()): $applications->the_post();
                        $application_id = get_the_ID();
                        $is_unread = in_array($application_id, $unread_application_ids, true);
                        $type = get_post_meta($application_id, '_application_application_type', true);
                        $business = get_post_meta($application_id, '_application_business_name', true);
                        $province = get_post_meta($application_id, '_application_province', true);
                        $city = get_post_meta($application_id, '_application_city', true);
                        $nationwide = get_post_meta($application_id, '_application_nationwide', true);
                        $work_cities = get_post_meta($application_id, '_application_work_cities', true);
                    ?>
                        <tr class="<?php echo $is_unread ? 'is-unread' : ''; ?>">
                            <td><strong><?php the_title(); ?></strong><?php if ($is_unread): ?><span class="manager-application-new">جدید برای شما</span><?php endif; ?><?php if ($business): ?><small><?php echo esc_html($business); ?></small><?php endif; ?></td>
                            <td><?php echo esc_html(zigurat_application_type_label($type)); ?></td>
                            <td><?php echo esc_html(get_post_meta($application_id, '_application_profession', true)); ?></td>
                            <td><?php echo esc_html(trim($province . '، ' . $city, '، ')); ?></td>
                            <td class="ltr-cell"><?php echo esc_html(get_post_meta($application_id, '_application_phone', true)); ?></td>
                            <td><?php echo $nationwide ? 'سراسر ایران' : esc_html($work_cities ?: '—'); ?></td>
                            <td class="no-print">
                                <a class="manager-application-view" href="<?php echo esc_url(zigurat_application_resume_url($application_id)); ?>" target="_blank" rel="noopener">مشاهده رزومه</a>
                            </td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                <?php else: ?>
                    <tr><td colspan="7">درخواستی مطابق این فیلترها وجود ندارد.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($applications->max_num_pages > 1): ?>
        <nav class="manager-application-pagination no-print" aria-label="صفحه‌بندی درخواست‌ها">
            <?php
            $pagination_base = add_query_arg('application_page', 999999999, add_query_arg(array(
                    'manager-section'       => 'applications',
                    'application_province'  => $selected_province,
                    'application_profession'=> $selected_profession,
                    'application_type'      => $selected_type,
                ), home_url('/login/')));
            echo paginate_links(array(
                'base'      => str_replace('999999999', '%#%', $pagination_base),
                'format'    => '',
                'current'   => $application_page,
                'total'     => $applications->max_num_pages,
                'prev_text' => 'قبلی',
                'next_text' => 'بعدی',
            )); ?>
        </nav>
    <?php endif; ?>
</div>
