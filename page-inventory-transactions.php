<?php
/* Template Name: Inventory Transactions */
if (!defined('ABSPATH')) { exit; }
zigurat_require_manager();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reverse_inventory_transaction'])) {
    $transaction_id = isset($_POST['transaction_id']) ? absint($_POST['transaction_id']) : 0;
    $nonce = isset($_POST['reverse_nonce']) ? sanitize_text_field(wp_unslash($_POST['reverse_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'zigurat_reverse_inventory_transaction_' . $transaction_id)) {
        $reverse_status = 'invalid';
    } else {
        $result = zigurat_inventory_reverse_transaction($transaction_id, $_POST['reverse_reason'] ?? '');
        $reverse_status = is_wp_error($result) ? $result->get_error_code() : 'reversed';
    }
    $return_args = array('inventory-status' => sanitize_key($reverse_status));
    foreach (array('category_id', 'product_id', 'project_id', 'transaction_page') as $return_key) {
        if (!empty($_POST[$return_key])) {
            $return_args[$return_key] = absint($_POST[$return_key]);
        }
    }
    if (!empty($_POST['transaction_action']) && in_array($_POST['transaction_action'], array('add', 'subtract'), true)) {
        $return_args['transaction_action'] = sanitize_key($_POST['transaction_action']);
    }
    wp_safe_redirect(add_query_arg($return_args, zigurat_inventory_page_url('inventory-transactions')));
    exit;
}

$category_id = isset($_GET['category_id']) ? absint($_GET['category_id']) : 0;
$product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
$action = isset($_GET['transaction_action']) ? sanitize_key(wp_unslash($_GET['transaction_action'])) : '';
$project_id = isset($_GET['project_id']) ? absint($_GET['project_id']) : 0;
if ($action === 'add') {
    $project_id = 0;
}
$transaction_page = isset($_GET['transaction_page']) ? max(1,absint($_GET['transaction_page'])) : 1;
$selection = ($category_id && $product_id) ? zigurat_inventory_find_catalog_selection($category_id,$product_id) : null;
$catalog = zigurat_inventory_get_catalog(false);
$category_name = '';
if ($category_id) foreach ($catalog as $category) if ($category['id']===$category_id) $category_name=$category['name'];
$selected_project = $project_id ? get_post($project_id) : null;
$transactions = zigurat_get_inventory_transactions(array('item_category'=>$category_name,'item_name'=>$selection?$selection->product_name:'','action'=>$action,'project_id'=>$project_id,'project_name'=>$selected_project&&$selected_project->post_type==='project'?get_the_title($selected_project):'','page'=>$transaction_page,'per_page'=>50));
$project_groups = zigurat_inventory_get_project_groups(true);
$inventory_status = isset($_GET['inventory-status']) ? sanitize_key(wp_unslash($_GET['inventory-status'])) : '';
$status_messages = array(
    'reversed' => array('success', 'تراکنش با موفقیت ابطال شد و اثر آن بر موجودی برگشت داده شد.'),
    'invalid_reason' => array('error', 'برای ابطال، دلیل را وارد کنید.'),
    'already_reversed' => array('error', 'این تراکنش قبلاً ابطال شده است.'),
    'cannot_reverse_reversal' => array('error', 'سند ابطال را نمی‌توان دوباره ابطال کرد.'),
    'insufficient_reversal' => array('error', 'موجودی فعلی برای ابطال این ورود کافی نیست؛ بخشی از کالا قبلاً مصرف شده است.'),
    'invalid_transaction' => array('error', 'تراکنش معتبر نیست یا پیدا نشد.'),
    'invalid_item' => array('error', 'کالای مربوط به این تراکنش پیدا نشد.'),
    'forbidden' => array('error', 'فقط مدیرکل اجازه ابطال تراکنش را دارد.'),
    'database' => array('error', 'ابطال انجام نشد و موجودی تغییری نکرد.'),
    'invalid' => array('error', 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.'),
);
get_header();
?>
<main class="inventory-page"><div class="container">
<?php get_template_part('template-parts/inventory-nav'); ?>
<section class="inventory-card" aria-labelledby="transactions-title">
<div class="inventory-heading"><div><span>سوابق انبار</span><h1 id="transactions-title">گردش انبار</h1></div><p><?php echo esc_html(number_format_i18n($transactions['total'])); ?> تراکنش مطابق فیلترها</p></div>
<?php if (isset($status_messages[$inventory_status])): ?><div class="inventory-notice inventory-notice--<?php echo esc_attr($status_messages[$inventory_status][0]); ?>" role="alert"><?php echo esc_html($status_messages[$inventory_status][1]); ?></div><?php endif; ?>
<?php if (current_user_can('manage_options')): ?><div class="inventory-correction-help no-print"><strong>اصلاح تراکنش اشتباه:</strong> تراکنش را ابطال کنید تا موجودی به‌صورت خودکار برگردد؛ سپس در صورت نیاز مقدار صحیح را از تب ورود یا خروج ثبت کنید. سابقه ابطال برای کنترل‌های بعدی نگهداری می‌شود.</div><?php endif; ?>
<div class="inventory-report-toolbar no-print"><div class="inventory-report-actions"><button class="inventory-print-button" type="button" onclick="window.print()">چاپ گزارش</button><?php if ($category_id||$product_id||$action||$project_id): ?><a class="inventory-filter-reset" href="<?php echo esc_url(zigurat_inventory_page_url('inventory-transactions')); ?>">حذف فیلتر</a><?php endif; ?></div>
<form class="inventory-filters inventory-filters--transactions" method="get" data-inventory-dependent data-inventory-auto-filter>
<label><span>دسته‌بندی</span><select name="category_id" data-inventory-category><option value="">همه دسته‌ها</option><?php foreach ($catalog as $category): ?><option value="<?php echo (int)$category['id']; ?>" <?php selected($category_id,$category['id']); ?>><?php echo esc_html($category['name']); ?></option><?php endforeach; ?></select></label>
<label><span>نام کالا</span><select name="product_id" data-inventory-product><option value="">ابتدا دسته را انتخاب کنید</option><?php foreach ($catalog as $category): foreach ($category['products'] as $product): ?><option value="<?php echo (int)$product['id']; ?>" data-category-id="<?php echo (int)$category['id']; ?>" <?php selected($product_id,$product['id']); ?>><?php echo esc_html($product['name']); ?></option><?php endforeach; endforeach; ?></select></label>
<label><span>نوع عملیات</span><select name="transaction_action" data-inventory-action><option value="">همه عملیات</option><option value="add" <?php selected($action,'add'); ?>>ورود کالا</option><option value="subtract" <?php selected($action,'subtract'); ?>>خروج کالا</option></select></label>
<label><span>پروژه</span><select name="project_id" data-inventory-project><option value="">همه پروژه‌ها</option><?php if ($project_groups['public']): ?><optgroup label="پروژه‌های سایت"><?php foreach ($project_groups['public'] as $project): ?><option value="<?php echo (int)$project->ID; ?>" <?php selected($project_id,$project->ID); ?>><?php echo esc_html(get_the_title($project)); ?></option><?php endforeach; ?></optgroup><?php endif; ?><?php if ($project_groups['internal']): ?><optgroup label="پروژه‌های داخلی انبار"><?php foreach ($project_groups['internal'] as $project): ?><option value="<?php echo (int)$project->ID; ?>" <?php selected($project_id,$project->ID); ?>><?php echo esc_html(get_the_title($project).' — '.zigurat_inventory_internal_project_code($project->ID)); ?></option><?php endforeach; ?></optgroup><?php endif; ?><?php if ($project_groups['archived']): ?><optgroup label="پروژه‌های داخلی بایگانی‌شده"><?php foreach ($project_groups['archived'] as $project): ?><option value="<?php echo (int)$project->ID; ?>" <?php selected($project_id,$project->ID); ?>><?php echo esc_html(get_the_title($project).' — '.zigurat_inventory_internal_project_code($project->ID)); ?></option><?php endforeach; ?></optgroup><?php endif; ?></select></label>
</form></div>
<div class="inventory-table-wrap"><table class="inventory-table inventory-transactions-table"><thead><tr><th>تاریخ شمسی (تهران)</th><th>شماره سند</th><th>کاربر</th><th>عملیات</th><th>دسته‌بندی</th><th>کالا</th><th>پروژه</th><th>تعداد</th><th>موجودی قبل/بعد</th><th>توضیحات</th><?php if (current_user_can('manage_options')): ?><th class="no-print">اصلاح</th><?php endif; ?></tr></thead><tbody>
<?php if ($transactions['transactions']): foreach ($transactions['transactions'] as $transaction): ?>
<tr class="<?php echo !empty($transaction->reversed_by_transaction_id) ? 'is-reversed' : ''; ?>">
<td><?php echo esc_html(zigurat_inventory_format_jalali_datetime($transaction->created_at)); ?></td>
<td><strong>#<?php echo (int)$transaction->id; ?></strong></td>
<td><?php echo esc_html($transaction->user_name?:'نامشخص'); ?></td>
<td><span class="inventory-action inventory-action--<?php echo esc_attr($transaction->action); ?>"><?php echo $transaction->action==='subtract'?'خروج':'ورود'; ?></span><?php if (!empty($transaction->reverses_transaction_id)): ?><small class="inventory-correction-status is-reversal">ابطال سند #<?php echo (int)$transaction->reverses_transaction_id; ?></small><?php elseif (!empty($transaction->reversed_by_transaction_id)): ?><small class="inventory-correction-status is-reversed">باطل‌شده با سند اصلاحی #<?php echo (int)$transaction->reversed_by_transaction_id; ?></small><?php endif; ?></td>
<td><?php echo esc_html($transaction->item_category); ?></td><td><strong><?php echo esc_html($transaction->item_name); ?></strong></td><td><?php echo esc_html($transaction->project_name?:'—'); ?></td><td><?php echo esc_html(number_format_i18n($transaction->quantity)); ?></td><td><?php echo $transaction->quantity_before!==null?esc_html(number_format_i18n($transaction->quantity_before).' ← '.number_format_i18n($transaction->quantity_after)):'—'; ?></td><td><?php echo esc_html($transaction->notes?:'—'); ?></td>
<?php if (current_user_can('manage_options')): ?><td class="inventory-correction-cell no-print"><?php if (empty($transaction->reverses_transaction_id) && empty($transaction->reversed_by_transaction_id)): ?><details class="inventory-correction"><summary>ابطال تراکنش</summary><form method="post" onsubmit="return confirm('آیا از ابطال این تراکنش و برگرداندن موجودی مطمئن هستید؟');"><?php wp_nonce_field('zigurat_reverse_inventory_transaction_'.$transaction->id,'reverse_nonce'); ?><input type="hidden" name="reverse_inventory_transaction" value="1"><input type="hidden" name="transaction_id" value="<?php echo (int)$transaction->id; ?>"><input type="hidden" name="category_id" value="<?php echo (int)$category_id; ?>"><input type="hidden" name="product_id" value="<?php echo (int)$product_id; ?>"><input type="hidden" name="transaction_action" value="<?php echo esc_attr($action); ?>"><input type="hidden" name="project_id" value="<?php echo (int)$project_id; ?>"><input type="hidden" name="transaction_page" value="<?php echo (int)$transaction_page; ?>"><label>دلیل ابطال<textarea name="reverse_reason" rows="3" maxlength="500" required></textarea></label><button type="submit">تأیید ابطال</button></form></details><?php else: ?>—<?php endif; ?></td><?php endif; ?>
</tr>
<?php endforeach; else: ?><tr><td colspan="<?php echo current_user_can('manage_options') ? 11 : 10; ?>">تراکنشی مطابق این فیلترها وجود ندارد.</td></tr><?php endif; ?></tbody></table></div>
<?php if ($transactions['pages']>1): ?><nav class="inventory-pagination no-print"><?php echo paginate_links(array('base'=>add_query_arg(array('transaction_page'=>'%#%','category_id'=>$category_id,'product_id'=>$product_id,'transaction_action'=>$action,'project_id'=>$project_id),zigurat_inventory_page_url('inventory-transactions')),'current'=>$transactions['page'],'total'=>$transactions['pages'],'prev_text'=>'قبلی','next_text'=>'بعدی')); ?></nav><?php endif; ?>
</section></div></main><?php get_footer(); ?>
