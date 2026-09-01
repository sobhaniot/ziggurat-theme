<?php
/* Template Name: Subtract Item */
if (!defined('ABSPATH')) {
    exit;
}
zigurat_require_manager();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zigurat_internal_project_action'])) {
    $nonce = isset($_POST['internal_project_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['internal_project_nonce']))
        : '';
    $action = sanitize_key(wp_unslash((string) $_POST['zigurat_internal_project_action']));
    $project_id = isset($_POST['internal_project_id']) ? absint($_POST['internal_project_id']) : 0;
    $status = 'invalid';
    $result = null;
    if (!wp_verify_nonce($nonce, 'zigurat_manage_internal_project')) {
        $status = 'invalid';
    } elseif ($action === 'create') {
        $result = zigurat_inventory_save_internal_project($_POST);
        $status = is_wp_error($result) ? $result->get_error_code() : 'internal_project_created';
        $project_id = is_wp_error($result) ? 0 : absint($result);
    } elseif ($action === 'update') {
        $result = zigurat_inventory_save_internal_project($_POST, $project_id);
        $status = is_wp_error($result) ? $result->get_error_code() : 'internal_project_updated';
    } elseif ($action === 'archive') {
        $result = zigurat_inventory_set_internal_project_archived($project_id, true);
        $status = is_wp_error($result) ? $result->get_error_code() : 'internal_project_archived';
        $project_id = 0;
    } elseif ($action === 'activate') {
        $result = zigurat_inventory_set_internal_project_archived($project_id, false);
        $status = is_wp_error($result) ? $result->get_error_code() : 'internal_project_activated';
    } elseif ($action === 'merge') {
        $target_project_id = isset($_POST['target_project_id']) ? absint($_POST['target_project_id']) : 0;
        $result = zigurat_inventory_merge_internal_project_into_public($project_id, $target_project_id);
        $status = is_wp_error($result) ? $result->get_error_code() : 'internal_project_merged';
        $project_id = is_wp_error($result) ? $project_id : $target_project_id;
    }
    $return_args = array('inventory-status' => sanitize_key($status));
    if ($project_id) {
        $return_args['project_id'] = $project_id;
    }
    if (!empty($_POST['return_inventory_id'])) {
        $return_args['inventory_id'] = absint($_POST['return_inventory_id']);
    }
    wp_safe_redirect(add_query_arg($return_args, zigurat_inventory_page_url('subtract-item')));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zigurat_inventory_subtract'])) {
    $nonce = isset($_POST['zigurat_inventory_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['zigurat_inventory_nonce']))
        : '';
    if (!wp_verify_nonce($nonce, 'zigurat_inventory_subtract')) {
        $status = 'invalid';
    } else {
        $result = zigurat_inventory_adjust_stock('subtract', array(
            'inventory_id' => $_POST['inventory_id'] ?? 0,
            'project_id' => $_POST['project_id'] ?? 0,
            'quantity' => $_POST['item_quantity'] ?? 0,
            'notes' => $_POST['notes'] ?? '',
        ));
        $status = is_wp_error($result) ? $result->get_error_code() : 'subtracted';
    }
    wp_safe_redirect(add_query_arg('inventory-status', sanitize_key($status), zigurat_inventory_page_url('subtract-item')));
    exit;
}

$status = isset($_GET['inventory-status']) ? sanitize_key(wp_unslash($_GET['inventory-status'])) : '';
$messages = array(
    'subtracted' => array('success', 'کالا از انبار کسر و گردش آن ثبت شد.'),
    'internal_project_created' => array('success', 'پیش‌نویس پروژه ساخته شد و اکنون در فهرست پروژه‌های خروج کالا و بخش پروژه‌های وردپرس قابل مشاهده است.'),
    'internal_project_updated' => array('success', 'اطلاعات پروژه داخلی به‌روزرسانی شد.'),
    'internal_project_archived' => array('success', 'پروژه داخلی بایگانی شد؛ سوابق قبلی آن در گردش انبار باقی می‌ماند.'),
    'internal_project_activated' => array('success', 'پروژه داخلی دوباره فعال شد.'),
    'internal_project_merged' => array('success', 'سوابق انبار به پروژه سایت متصل و نسخه داخلی تکراری بایگانی شد.'),
    'internal_project_name' => array('error', 'نام پروژه داخلی را وارد کنید.'),
    'internal_project_duplicate' => array('error', 'پروژه‌ای با همین نام قبلاً ثبت شده است؛ پروژه تکراری ساخته نشد.'),
    'internal_project_invalid' => array('error', 'پروژه داخلی معتبر نیست.'),
    'internal_project_merge_target' => array('error', 'پروژه سایت برای اتصال معتبر نیست.'),
    'internal_project_merge_name' => array('error', 'اتصال انجام نشد؛ نام دو پروژه یکسان نیست.'),
    'forbidden' => array('error', 'فقط مدیرکل اجازه مدیریت پروژه‌های داخلی را دارد.'),
    'insufficient' => array('error', 'تعداد درخواستی بیشتر از موجودی است.'),
    'invalid_quantity' => array('error', 'تعداد باید بیشتر از صفر باشد.'),
    'invalid_item' => array('error', 'کالای انتخاب‌شده معتبر نیست.'),
    'invalid_project' => array('error', 'پروژه را انتخاب کنید.'),
    'invalid' => array('error', 'درخواست معتبر نیست.'),
    'database' => array('error', 'ثبت انجام نشد و موجودی تغییری نکرد.'),
);

$catalog = zigurat_inventory_get_catalog(true);
$project_groups = zigurat_inventory_get_project_groups(false);
$projects = array_merge($project_groups['public'], $project_groups['internal']);
$managed_project_groups = current_user_can('manage_options')
    ? zigurat_inventory_get_project_groups(true)
    : array('internal' => array(), 'archived' => array());
$selected_inventory_id = isset($_GET['inventory_id']) ? absint($_GET['inventory_id']) : 0;
$selected_project_id = isset($_GET['project_id']) ? absint($_GET['project_id']) : 0;
$selected_category_id = 0;
foreach ($catalog as $category) {
    foreach ($category['products'] as $product) {
        if ($product['inventory_id'] === $selected_inventory_id) {
            $selected_category_id = $category['id'];
        }
    }
}
get_header();
?>
<main class="inventory-page">
    <div class="container">
        <?php get_template_part('template-parts/inventory-nav'); ?>
        <section class="inventory-card inventory-form-card" aria-labelledby="inventory-subtract-title">
            <div class="inventory-heading">
                <div><span>خروج کالا</span><h1 id="inventory-subtract-title">کسر از انبار</h1></div>
                <p>پس از انتخاب دسته، فقط کالاهای موجود همان دسته نمایش داده می‌شوند.</p>
            </div>

            <?php if (isset($messages[$status])): ?>
                <div class="inventory-notice inventory-notice--<?php echo esc_attr($messages[$status][0]); ?>"><?php echo esc_html($messages[$status][1]); ?></div>
            <?php endif; ?>

            <?php if (current_user_can('manage_options')): ?>
                <details class="inventory-internal-projects no-print" <?php echo strpos($status, 'internal_project_') === 0 ? 'open' : ''; ?>>
                    <summary>
                        <span><strong>تعریف و مدیریت پروژه داخلی</strong><small>پروژه به‌صورت پیش‌نویس ساخته می‌شود و تا زمان انتشار فقط در انبار و پیشخوان قابل مشاهده است.</small></span>
                        <b aria-hidden="true">＋</b>
                    </summary>
                    <div class="inventory-internal-projects__body">
                        <section class="inventory-internal-project-create">
                            <h2>پروژه داخلی جدید</h2>
                            <form class="inventory-form" method="post">
                                <?php wp_nonce_field('zigurat_manage_internal_project', 'internal_project_nonce'); ?>
                                <input type="hidden" name="zigurat_internal_project_action" value="create">
                                <input type="hidden" name="return_inventory_id" value="<?php echo (int) $selected_inventory_id; ?>">
                                <label>نام پروژه *
                                    <input name="internal_project_name" type="text" maxlength="180" required>
                                </label>
                                <label>نام کارفرما
                                    <input name="internal_project_client" type="text" maxlength="180">
                                </label>
                                <label>توضیحات
                                    <textarea name="internal_project_notes" rows="3" maxlength="1000"></textarea>
                                </label>
                                <button type="submit">ساخت پروژه داخلی</button>
                            </form>
                        </section>

                        <section class="inventory-internal-project-list">
                            <h2>پروژه‌های داخلی</h2>
                            <?php $managed_internal_projects = array_merge($managed_project_groups['internal'], $managed_project_groups['archived']); ?>
                            <?php if (!$managed_internal_projects): ?>
                                <p class="inventory-internal-project-empty">هنوز پروژه داخلی تعریف نشده است.</p>
                            <?php else: ?>
                                <?php foreach ($managed_internal_projects as $internal_project):
                                    $is_archived = zigurat_inventory_internal_project_status($internal_project->ID) === 'archived';
                                    $client = (string) get_post_meta($internal_project->ID, '_zigurat_inventory_project_client', true);
                                    $notes = (string) get_post_meta($internal_project->ID, '_zigurat_inventory_project_notes', true);
                                    $code = zigurat_inventory_internal_project_code($internal_project->ID);
                                    $same_name_public = !$is_archived
                                        ? zigurat_inventory_find_project_by_name(get_the_title($internal_project), $internal_project->ID, array('publish'), false)
                                        : null;
                                ?>
                                    <details class="inventory-internal-project<?php echo $is_archived ? ' is-archived' : ''; ?>">
                                        <summary>
                                            <span><strong><?php echo esc_html(get_the_title($internal_project)); ?></strong><small><?php echo esc_html($code . ($client !== '' ? ' — ' . $client : '')); ?></small></span>
                                            <em><?php echo $is_archived ? 'بایگانی‌شده' : 'فعال'; ?></em>
                                        </summary>
                                        <div>
                                            <?php if (!$is_archived): ?>
                                                <?php if ($same_name_public): ?>
                                                    <div class="inventory-internal-project-duplicate">
                                                        <strong>پروژه سایت هم‌نام پیدا شد</strong>
                                                        <p>«<?php echo esc_html(get_the_title($same_name_public)); ?>» از قبل در پروژه‌های سایت وجود دارد. می‌توانید سوابق انبار را به آن متصل و این نسخه داخلی را بایگانی کنید.</p>
                                                        <div>
                                                            <a href="<?php echo esc_url(get_edit_post_link($same_name_public->ID)); ?>">مشاهده پروژه سایت</a>
                                                            <form method="post" onsubmit="return confirm('سوابق انبار به پروژه سایت متصل و نسخه داخلی بایگانی شود؟ هیچ تراکنشی حذف نخواهد شد.');">
                                                                <?php wp_nonce_field('zigurat_manage_internal_project', 'internal_project_nonce'); ?>
                                                                <input type="hidden" name="zigurat_internal_project_action" value="merge">
                                                                <input type="hidden" name="internal_project_id" value="<?php echo (int) $internal_project->ID; ?>">
                                                                <input type="hidden" name="target_project_id" value="<?php echo (int) $same_name_public->ID; ?>">
                                                                <input type="hidden" name="return_inventory_id" value="<?php echo (int) $selected_inventory_id; ?>">
                                                                <button type="submit">اتصال سوابق و رفع تکراری</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <a class="inventory-internal-project-edit-link" href="<?php echo esc_url(get_edit_post_link($internal_project->ID)); ?>">تکمیل اطلاعات و انتشار در پروژه‌های سایت</a>
                                                <form class="inventory-form inventory-internal-project-edit" method="post">
                                                    <?php wp_nonce_field('zigurat_manage_internal_project', 'internal_project_nonce'); ?>
                                                    <input type="hidden" name="zigurat_internal_project_action" value="update">
                                                    <input type="hidden" name="internal_project_id" value="<?php echo (int) $internal_project->ID; ?>">
                                                    <input type="hidden" name="return_inventory_id" value="<?php echo (int) $selected_inventory_id; ?>">
                                                    <label>نام پروژه *<input name="internal_project_name" type="text" maxlength="180" value="<?php echo esc_attr(get_the_title($internal_project)); ?>" required></label>
                                                    <label>نام کارفرما<input name="internal_project_client" type="text" maxlength="180" value="<?php echo esc_attr($client); ?>"></label>
                                                    <label>توضیحات<textarea name="internal_project_notes" rows="3" maxlength="1000"><?php echo esc_textarea($notes); ?></textarea></label>
                                                    <button type="submit">ذخیره ویرایش</button>
                                                </form>
                                            <?php endif; ?>
                                            <form class="inventory-internal-project-state" method="post" <?php echo !$is_archived ? 'onsubmit="return confirm(\'این پروژه بایگانی شود؟ سوابق گردش انبار حذف نخواهد شد.\');"' : ''; ?>>
                                                <?php wp_nonce_field('zigurat_manage_internal_project', 'internal_project_nonce'); ?>
                                                <input type="hidden" name="zigurat_internal_project_action" value="<?php echo $is_archived ? 'activate' : 'archive'; ?>">
                                                <input type="hidden" name="internal_project_id" value="<?php echo (int) $internal_project->ID; ?>">
                                                <input type="hidden" name="return_inventory_id" value="<?php echo (int) $selected_inventory_id; ?>">
                                                <button type="submit"><?php echo $is_archived ? 'فعال‌کردن دوباره' : 'بایگانی پروژه'; ?></button>
                                            </form>
                                        </div>
                                    </details>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </section>
                    </div>
                </details>
            <?php endif; ?>

            <?php if (!$catalog): ?>
                <div class="inventory-empty">کالای دارای موجودی برای کسر وجود ندارد.</div>
            <?php elseif (!$projects): ?>
                <div class="inventory-empty">ابتدا یک پروژه سایت یا پروژه داخلی تعریف کنید.</div>
            <?php else: ?>
                <form class="inventory-form" method="post" data-inventory-dependent>
                    <?php wp_nonce_field('zigurat_inventory_subtract', 'zigurat_inventory_nonce'); ?>
                    <input type="hidden" name="zigurat_inventory_subtract" value="1">
                    <label for="subtract-category">دسته‌بندی *</label>
                    <select id="subtract-category" data-inventory-category required>
                        <option value="">انتخاب دسته</option>
                        <?php foreach ($catalog as $category): ?>
                            <option value="<?php echo (int) $category['id']; ?>" <?php selected($selected_category_id, $category['id']); ?>><?php echo esc_html($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="inventory-item">نام کالا *</label>
                    <select id="inventory-item" name="inventory_id" data-inventory-product required>
                        <option value="">ابتدا دسته را انتخاب کنید</option>
                        <?php foreach ($catalog as $category): foreach ($category['products'] as $product): ?>
                            <option value="<?php echo (int) $product['inventory_id']; ?>" data-category-id="<?php echo (int) $category['id']; ?>" <?php selected($selected_inventory_id, $product['inventory_id']); ?>><?php echo esc_html($product['name'] . ' | موجودی: ' . number_format_i18n($product['quantity'])); ?></option>
                        <?php endforeach; endforeach; ?>
                    </select>
                    <label for="inventory-project">پروژه مصرف‌کننده *</label>
                    <select id="inventory-project" name="project_id" required>
                        <option value="">انتخاب پروژه</option>
                        <?php if ($project_groups['public']): ?>
                            <optgroup label="پروژه‌های سایت">
                                <?php foreach ($project_groups['public'] as $project): ?>
                                    <option value="<?php echo (int) $project->ID; ?>" <?php selected($selected_project_id, $project->ID); ?>><?php echo esc_html(get_the_title($project)); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if ($project_groups['internal']): ?>
                            <optgroup label="پروژه‌های داخلی انبار">
                                <?php foreach ($project_groups['internal'] as $project): ?>
                                    <option value="<?php echo (int) $project->ID; ?>" <?php selected($selected_project_id, $project->ID); ?>><?php echo esc_html(get_the_title($project) . ' — ' . zigurat_inventory_internal_project_code($project->ID)); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                    <label for="subtract-quantity">تعداد خروجی *</label>
                    <input id="subtract-quantity" name="item_quantity" type="number" min="1" step="1" required>
                    <label for="subtract-notes">توضیحات</label>
                    <textarea id="subtract-notes" name="notes" rows="4"></textarea>
                    <button type="submit">ثبت خروج کالا</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php get_footer(); ?>
