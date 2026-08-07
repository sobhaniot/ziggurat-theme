<?php
/* Template Name: Subtract Item */
if (!defined('ABSPATH')) { exit; }
zigurat_require_manager();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zigurat_inventory_subtract'])) {
    $nonce = isset($_POST['zigurat_inventory_nonce']) ? sanitize_text_field(wp_unslash($_POST['zigurat_inventory_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'zigurat_inventory_subtract')) { $status = 'invalid'; }
    else {
        $result = zigurat_inventory_adjust_stock('subtract', array('inventory_id' => $_POST['inventory_id'] ?? 0, 'project_id' => $_POST['project_id'] ?? 0, 'quantity' => $_POST['item_quantity'] ?? 0, 'notes' => $_POST['notes'] ?? ''));
        $status = is_wp_error($result) ? $result->get_error_code() : 'subtracted';
    }
    wp_safe_redirect(add_query_arg('inventory-status', sanitize_key($status), zigurat_inventory_page_url('subtract-item'))); exit;
}
$status = isset($_GET['inventory-status']) ? sanitize_key(wp_unslash($_GET['inventory-status'])) : '';
$messages = array('subtracted'=>array('success','کالا از انبار کسر و گردش آن ثبت شد.'),'insufficient'=>array('error','تعداد درخواستی بیشتر از موجودی است.'),'invalid_quantity'=>array('error','تعداد باید بیشتر از صفر باشد.'),'invalid_item'=>array('error','کالای انتخاب‌شده معتبر نیست.'),'invalid_project'=>array('error','پروژه را انتخاب کنید.'),'invalid'=>array('error','درخواست معتبر نیست.'),'database'=>array('error','ثبت انجام نشد و موجودی تغییری نکرد.'));
$catalog = zigurat_inventory_get_catalog(true);
$projects = get_posts(array('post_type'=>'project','post_status'=>'publish','posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC'));
$selected_inventory_id = isset($_GET['inventory_id']) ? absint($_GET['inventory_id']) : 0;
$selected_category_id = 0;
foreach ($catalog as $category) foreach ($category['products'] as $product) if ($product['inventory_id'] === $selected_inventory_id) $selected_category_id = $category['id'];
get_header();
?>
<main class="inventory-page"><div class="container">
<?php get_template_part('template-parts/inventory-nav'); ?>
<section class="inventory-card inventory-form-card" aria-labelledby="inventory-subtract-title">
<div class="inventory-heading"><div><span>خروج کالا</span><h1 id="inventory-subtract-title">کسر از انبار</h1></div><p>پس از انتخاب دسته، فقط کالاهای موجود همان دسته نمایش داده می‌شوند.</p></div>
<?php if (isset($messages[$status])): ?><div class="inventory-notice inventory-notice--<?php echo esc_attr($messages[$status][0]); ?>"><?php echo esc_html($messages[$status][1]); ?></div><?php endif; ?>
<?php if (!$catalog): ?><div class="inventory-empty">کالای دارای موجودی برای کسر وجود ندارد.</div>
<?php elseif (!$projects): ?><div class="inventory-empty">ابتدا باید حداقل یک پروژه منتشرشده داشته باشید.</div>
<?php else: ?><form class="inventory-form" method="post" data-inventory-dependent>
<?php wp_nonce_field('zigurat_inventory_subtract','zigurat_inventory_nonce'); ?><input type="hidden" name="zigurat_inventory_subtract" value="1">
<label for="subtract-category">دسته‌بندی *</label><select id="subtract-category" data-inventory-category required><option value="">انتخاب دسته</option><?php foreach ($catalog as $category): ?><option value="<?php echo (int)$category['id']; ?>" <?php selected($selected_category_id,$category['id']); ?>><?php echo esc_html($category['name']); ?></option><?php endforeach; ?></select>
<label for="inventory-item">نام کالا *</label><select id="inventory-item" name="inventory_id" data-inventory-product required><option value="">ابتدا دسته را انتخاب کنید</option><?php foreach ($catalog as $category): foreach ($category['products'] as $product): ?><option value="<?php echo (int)$product['inventory_id']; ?>" data-category-id="<?php echo (int)$category['id']; ?>" <?php selected($selected_inventory_id,$product['inventory_id']); ?>><?php echo esc_html($product['name'].' | موجودی: '.number_format_i18n($product['quantity'])); ?></option><?php endforeach; endforeach; ?></select>
<label for="inventory-project">پروژه مصرف‌کننده *</label><select id="inventory-project" name="project_id" required><option value="">انتخاب پروژه</option><?php foreach ($projects as $project): ?><option value="<?php echo (int)$project->ID; ?>"><?php echo esc_html(get_the_title($project)); ?></option><?php endforeach; ?></select>
<label for="subtract-quantity">تعداد خروجی *</label><input id="subtract-quantity" name="item_quantity" type="number" min="1" step="1" required>
<label for="subtract-notes">توضیحات</label><textarea id="subtract-notes" name="notes" rows="4"></textarea><button type="submit">ثبت خروج کالا</button>
</form><?php endif; ?></section></div></main><?php get_footer(); ?>
