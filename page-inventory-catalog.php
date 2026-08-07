<?php
/* Template Name: Inventory Catalog */
if (!defined('ABSPATH')) { exit; }
zigurat_require_manager();
if (!current_user_can('manage_options')) {
    wp_safe_redirect(zigurat_inventory_page_url('inventory-list'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = isset($_POST['catalog_nonce']) ? sanitize_text_field(wp_unslash($_POST['catalog_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'zigurat_inventory_catalog')) { $status = 'invalid'; }
    elseif (isset($_POST['create_category'])) {
        $result = zigurat_inventory_create_category($_POST['category_name'] ?? '');
        $status = is_wp_error($result) ? $result->get_error_code() : 'category_created';
    } elseif (isset($_POST['create_product'])) {
        $result = zigurat_inventory_create_product($_POST['category_id'] ?? 0, $_POST['product_name'] ?? '');
        $status = is_wp_error($result) ? $result->get_error_code() : 'product_created';
    } else { $status = 'invalid'; }
    wp_safe_redirect(add_query_arg('catalog-status', sanitize_key($status), zigurat_inventory_page_url('inventory-catalog'))); exit;
}
$status = isset($_GET['catalog-status']) ? sanitize_key(wp_unslash($_GET['catalog-status'])) : '';
$messages = array(
    'category_created'=>array('success','دسته جدید ثبت شد.'),'product_created'=>array('success','کالای جدید ثبت شد.'),
    'duplicate_category'=>array('error','این دسته قبلاً تعریف شده است.'),'duplicate_product'=>array('error','این کالا قبلاً در این دسته تعریف شده است.'),
    'invalid_category'=>array('error','نام دسته را وارد کنید.'),'invalid_product'=>array('error','دسته و نام کالا را کامل وارد کنید.'),
    'invalid'=>array('error','درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.'),'database'=>array('error','ثبت اطلاعات انجام نشد.'),
);
$catalog = zigurat_inventory_get_catalog(false);
get_header();
?>
<main class="inventory-page"><div class="container">
<?php get_template_part('template-parts/inventory-nav'); ?>
<section class="inventory-card" aria-labelledby="catalog-title">
<div class="inventory-heading"><div><span>مدیریت فهرست کالاها</span><h1 id="catalog-title">تعریف دسته و کالا</h1></div><p>این بخش فقط برای مدیرکل قابل مشاهده است. ابتدا دسته و سپس کالاهای زیرمجموعه را تعریف کنید.</p></div>
<?php if (isset($messages[$status])): ?><div class="inventory-notice inventory-notice--<?php echo esc_attr($messages[$status][0]); ?>"><?php echo esc_html($messages[$status][1]); ?></div><?php endif; ?>
<div class="inventory-catalog-forms">
<form class="inventory-form inventory-catalog-form" method="post"><h2>دسته جدید</h2><?php wp_nonce_field('zigurat_inventory_catalog','catalog_nonce'); ?><label for="category-name">نام دسته *</label><input id="category-name" name="category_name" maxlength="191" required><button type="submit" name="create_category" value="1">ثبت دسته</button></form>
<form class="inventory-form inventory-catalog-form" method="post"><h2>کالای جدید</h2><?php wp_nonce_field('zigurat_inventory_catalog','catalog_nonce'); ?><label for="catalog-category">دسته *</label><select id="catalog-category" name="category_id" required><option value="">انتخاب دسته</option><?php foreach ($catalog as $category): ?><option value="<?php echo (int)$category['id']; ?>"><?php echo esc_html($category['name']); ?></option><?php endforeach; ?></select><label for="product-name">نام کالا *</label><input id="product-name" name="product_name" maxlength="191" required><button type="submit" name="create_product" value="1">ثبت کالا</button></form>
</div>
<div class="inventory-catalog-list"><h2>فهرست آکاردئونی دسته‌ها و کالاها</h2>
<?php if (!$catalog): ?><div class="inventory-empty">هنوز دسته‌ای تعریف نشده است.</div><?php else: foreach ($catalog as $category): ?>
<details class="inventory-catalog-group"><summary><strong><?php echo esc_html($category['name']); ?></strong><span><?php echo esc_html(number_format_i18n(count($category['products']))); ?> کالا</span></summary><div><?php if (!$category['products']): ?><p>هنوز کالایی در این دسته تعریف نشده است.</p><?php else: ?><ul><?php foreach ($category['products'] as $product): ?><li><span><?php echo esc_html($product['name']); ?></span><small>موجودی: <?php echo esc_html(number_format_i18n($product['quantity'])); ?></small></li><?php endforeach; ?></ul><?php endif; ?></div></details>
<?php endforeach; endif; ?></div>
</section></div></main><?php get_footer(); ?>
