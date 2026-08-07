<?php
if (!defined('ABSPATH') || !zigurat_is_manager()) { return; }
$active_template = get_page_template_slug(get_queried_object_id());
$inventory_links = array(
    'page-add-item.php' => array('add-item', 'افزودن به انبار', '+'),
    'page-subtract-item.php' => array('subtract-item', 'کسر از انبار', '−'),
    'page-inventory-list.php' => array('inventory-list', 'لیست انبار', '▦'),
    'page-inventory-transactions.php' => array('inventory-transactions', 'گردش انبار', '↕'),
);
if (current_user_can('manage_options')) {
    $inventory_links['page-inventory-catalog.php'] = array('inventory-catalog', 'تعریف دسته و کالا', '⌘');
}
?>
<nav class="inventory-nav no-print" aria-label="بخش‌های انبارداری">
<a class="inventory-nav__panel" href="<?php echo esc_url(zigurat_manager_login_url()); ?>">بازگشت به پنل</a>
<?php foreach ($inventory_links as $template => $link): ?><a class="<?php echo $active_template === $template ? 'is-active' : ''; ?>" href="<?php echo esc_url(zigurat_inventory_page_url($link[0])); ?>"><span aria-hidden="true"><?php echo esc_html($link[2]); ?></span><?php echo esc_html($link[1]); ?></a><?php endforeach; ?>
</nav>
