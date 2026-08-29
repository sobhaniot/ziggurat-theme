<?php
/* Template Name: Inventory List */
if (!defined('ABSPATH')) { exit; }
zigurat_require_manager();
$category_id = isset($_GET['category_id']) ? absint($_GET['category_id']) : 0;
$product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
$only_available = !empty($_GET['available']);
$inventory_page = isset($_GET['inventory_page']) ? max(1, absint($_GET['inventory_page'])) : 1;
$inventory = zigurat_get_inventory_items(array('category_id'=>$category_id,'product_id'=>$product_id,'available'=>$only_available,'page'=>$inventory_page,'per_page'=>50));
$summary = zigurat_get_inventory_summary();
$catalog = zigurat_inventory_get_catalog(false);
get_header();
?>
<main class="inventory-page"><div class="container">
<?php get_template_part('template-parts/inventory-nav'); ?>
<section class="inventory-card" aria-labelledby="inventory-list-title">
<div class="inventory-heading"><div><span>موجودی لحظه‌ای</span><h1 id="inventory-list-title">لیست انبار</h1></div><p><?php echo esc_html(number_format_i18n($inventory['total'])); ?> قلم مطابق فیلترهای فعلی</p></div>
<div class="inventory-stats"><div><small>کل اقلام تعریف‌شده</small><strong><?php echo esc_html(number_format_i18n($summary['item_count'])); ?></strong></div><div><small>اقلام دارای موجودی</small><strong><?php echo esc_html(number_format_i18n($summary['available_count'])); ?></strong></div><div><small>مجموع تعداد کالاها</small><strong><?php echo esc_html(number_format_i18n($summary['total_quantity'])); ?></strong></div></div>
<div class="inventory-report-toolbar no-print">
<div class="inventory-report-actions"><button class="inventory-print-button" type="button" onclick="window.print()">چاپ لیست انبار</button><?php if ($category_id||$product_id||$only_available): ?><a class="inventory-filter-reset" href="<?php echo esc_url(zigurat_inventory_page_url('inventory-list')); ?>">حذف فیلتر</a><?php endif; ?></div>
<form class="inventory-filters inventory-filters--stock no-print" method="get" data-inventory-dependent data-inventory-auto-filter>
<label><span>دسته‌بندی</span><select name="category_id" data-inventory-category><option value="">همه دسته‌ها</option><?php foreach ($catalog as $category): ?><option value="<?php echo (int)$category['id']; ?>" <?php selected($category_id,$category['id']); ?>><?php echo esc_html($category['name']); ?></option><?php endforeach; ?></select></label>
<label><span>نام کالا</span><select name="product_id" data-inventory-product><option value="">ابتدا دسته را انتخاب کنید</option><?php foreach ($catalog as $category): foreach ($category['products'] as $product): ?><option value="<?php echo (int)$product['id']; ?>" data-category-id="<?php echo (int)$category['id']; ?>" <?php selected($product_id,$product['id']); ?>><?php echo esc_html($product['name']); ?></option><?php endforeach; endforeach; ?></select></label>
<label class="inventory-checkbox"><input type="checkbox" name="available" value="1" <?php checked($only_available); ?>><span>فقط کالاهای موجود</span></label>
</form></div>
<div class="inventory-table-wrap"><table class="inventory-table"><thead><tr><th>دسته‌بندی</th><th>نام کالا</th><th>موجودی</th><th>وضعیت</th><th class="no-print">عملیات</th></tr></thead><tbody>
<?php if ($inventory['items']): foreach ($inventory['items'] as $item): ?><tr><td><?php echo esc_html($item->item_category); ?></td><td><strong><?php echo esc_html($item->item_name); ?></strong></td><td class="inventory-quantity"><?php echo esc_html(number_format_i18n($item->item_quantity)); ?></td><td><span class="inventory-stock-status <?php echo (int)$item->item_quantity>0?'is-available':'is-empty'; ?>"><?php echo (int)$item->item_quantity>0?'موجود':'ناموجود'; ?></span></td><td class="inventory-row-actions no-print"><a href="<?php echo esc_url(add_query_arg(array('category_id'=>$item->category_id,'product_id'=>$item->product_id),zigurat_inventory_page_url('add-item'))); ?>">افزایش</a><?php if ((int)$item->item_quantity>0): ?><a href="<?php echo esc_url(add_query_arg('inventory_id',$item->id,zigurat_inventory_page_url('subtract-item'))); ?>">کسر</a><?php endif; ?></td></tr><?php endforeach; else: ?><tr><td colspan="5">کالایی مطابق این فیلترها وجود ندارد.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($inventory['pages']>1): ?><nav class="inventory-pagination no-print"><?php echo paginate_links(array('base'=>add_query_arg(array('inventory_page'=>'%#%','category_id'=>$category_id,'product_id'=>$product_id,'available'=>$only_available?'1':''),zigurat_inventory_page_url('inventory-list')),'current'=>$inventory['page'],'total'=>$inventory['pages'],'prev_text'=>'قبلی','next_text'=>'بعدی')); ?></nav><?php endif; ?>
</section></div></main><?php get_footer(); ?>
