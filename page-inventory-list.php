<?php
/*
Template Name: Inventory List
*/
include_once ("inc/check_login.php");

if (!check_login_cookies()) {
    wp_redirect(home_url('/'));
    exit;
}

get_header();

$only_available = isset($_GET['available']) && $_GET['available'] == '1';

// دریافت اطلاعات انبار
$inventory_items = get_inventory_list($only_available);

// تابع برای دریافت اطلاعات انبار
function get_inventory_list($only_available = false) {
    global $wpdb;
    $table_name = 'zigurat_inventory';

    if ($only_available) {
        $query = "
            SELECT *
            FROM $table_name
            WHERE item_quantity > 0
            ORDER BY item_category ASC, item_name ASC
        ";
    } else {
        $query = "
            SELECT *
            FROM $table_name
            ORDER BY item_category ASC, item_name ASC
        ";
    }

    return $wpdb->get_results($query);
}

// دریافت اطلاعات انبار
?>

<div class="inventory-list-container">
    <h2>لیست انبار</h2>
    <div style="margin-bottom:20px;">
    <div class="inventory-filter">
        <?php if($only_available): ?>
            <a href="<?php echo esc_url(remove_query_arg('available')); ?>" class="inventory-filter-btn active">
                <span class="icon">📦</span>
                نمایش همه کالاها
            </a>
        <?php else: ?>
            <a href="<?php echo esc_url(add_query_arg('available', '1')); ?>" class="inventory-filter-btn">
                <span class="icon">🔍</span>
                فقط کالاهای موجود
            </a>
        <?php endif; ?>
    </div>
    </div>
    <?php if (!empty($inventory_items)) : ?>
        <table class="inventory-table">
            <thead>
                <tr>
                    <th>نام کالا</th>
                    <th>دسته‌بندی</th>
                    <th>تعداد</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventory_items as $item) : ?>
                    <tr>
                        <td><?php echo esc_html($item->item_name); ?></td>
                        <td><?php echo esc_html($item->item_category); ?></td>
                        <td><?php echo number_format($item->item_quantity); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p>هیچ کالایی در انبار موجود نیست.</p>
    <?php endif; ?>
</div>
<p class="back-to-home"> 
    <a href="<?php echo home_url(); ?>" class="button">بازگشت به صفحه اصلی</a> 
</p>

<?php get_footer(); ?>


