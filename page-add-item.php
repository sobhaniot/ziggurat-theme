<?php
/* Template Name: Add Item */
if (!defined('ABSPATH')) { exit; }
zigurat_require_manager();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zigurat_inventory_add'])) {
    $nonce = isset($_POST['zigurat_inventory_nonce']) ? sanitize_text_field(wp_unslash($_POST['zigurat_inventory_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'zigurat_inventory_add')) {
        $status = 'invalid';
    } else {
        $result = zigurat_inventory_adjust_stock('add', array(
            'product_id' => $_POST['product_id'] ?? 0,
            'quantity' => $_POST['item_quantity'] ?? 0,
            'notes' => $_POST['notes'] ?? '',
        ));
        $status = is_wp_error($result) ? $result->get_error_code() : 'added';
    }
    wp_safe_redirect(add_query_arg('inventory-status', sanitize_key($status), zigurat_inventory_page_url('add-item')));
    exit;
}

$status = isset($_GET['inventory-status']) ? sanitize_key(wp_unslash($_GET['inventory-status'])) : '';
$messages = array(
    'added' => array('success', 'موجودی کالا افزایش یافت و گردش آن ثبت شد.'),
    'invalid_quantity' => array('error', 'تعداد باید بیشتر از صفر باشد.'),
    'invalid_item' => array('error', 'دسته و کالای معتبر را انتخاب کنید.'),
    'invalid' => array('error', 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.'),
    'database' => array('error', 'ثبت انجام نشد و موجودی تغییری نکرد.'),
);
$catalog = zigurat_inventory_get_catalog(false);
$selected_product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
$selected_category_id = isset($_GET['category_id']) ? absint($_GET['category_id']) : 0;
get_header();
?>
<main class="inventory-page"><div class="container">
    <?php get_template_part('template-parts/inventory-nav'); ?>
    <section class="inventory-card inventory-form-card" aria-labelledby="inventory-add-title">
        <div class="inventory-heading"><div><span>ورود کالا</span><h1 id="inventory-add-title">افزودن به انبار</h1></div><p>کالاها از فهرستی که مدیرکل تعریف کرده انتخاب می‌شوند.</p></div>
        <?php if (isset($messages[$status])): ?><div class="inventory-notice inventory-notice--<?php echo esc_attr($messages[$status][0]); ?>" role="alert"><?php echo esc_html($messages[$status][1]); ?></div><?php endif; ?>
        <?php if (!$catalog || !array_filter(wp_list_pluck($catalog, 'products'))): ?>
            <div class="inventory-empty">هنوز کالایی تعریف نشده است. <?php if (current_user_can('manage_options')): ?><a href="<?php echo esc_url(zigurat_inventory_page_url('inventory-catalog')); ?>">تعریف دسته و کالا</a><?php else: ?>از مدیرکل بخواهید ابتدا کالاها را تعریف کند.<?php endif; ?></div>
        <?php else: ?>
            <form class="inventory-form" method="post" data-inventory-dependent>
                <?php wp_nonce_field('zigurat_inventory_add', 'zigurat_inventory_nonce'); ?><input type="hidden" name="zigurat_inventory_add" value="1">
                <label for="inventory-category">دسته‌بندی *</label>
                <select id="inventory-category" data-inventory-category required><option value="">انتخاب دسته</option><?php foreach ($catalog as $category): if (!$category['products']) continue; ?><option value="<?php echo (int) $category['id']; ?>" <?php selected($selected_category_id, $category['id']); ?>><?php echo esc_html($category['name']); ?></option><?php endforeach; ?></select>
                <label for="inventory-product">نام کالا *</label>
                <select id="inventory-product" name="product_id" data-inventory-product required><option value="">ابتدا دسته را انتخاب کنید</option><?php foreach ($catalog as $category): foreach ($category['products'] as $product): ?><option value="<?php echo (int) $product['id']; ?>" data-category-id="<?php echo (int) $category['id']; ?>" <?php selected($selected_product_id, $product['id']); ?>><?php echo esc_html($product['name']); ?></option><?php endforeach; endforeach; ?></select>
                <label for="inventory-add-quantity">تعداد ورودی *</label><input id="inventory-add-quantity" name="item_quantity" type="number" min="1" step="1" required>
                <label for="inventory-add-notes">توضیحات</label><textarea id="inventory-add-notes" name="notes" rows="4" placeholder="نام تأمین‌کننده یا شماره فاکتور"></textarea>
                <button type="submit">ثبت ورود کالا</button>
            </form>
        <?php endif; ?>
    </section>
</div></main>
<?php get_footer(); ?>
