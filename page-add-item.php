<?php
/*
Template Name: Add Item
*/
// چک کردن وضعیت لاگین

include_once ("inc/check_login.php");
include_once ("inc/create_post.php");

if (!check_login_cookies()) {
    wp_redirect(home_url('/'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    global $wpdb;
    $table_name = 'zigurat_inventory';

    $item_name = sanitize_text_field($_POST['item_name']);
    $item_category = sanitize_text_field($_POST['item_category']);
    $item_quantity = intval($_POST['item_quantity']);
    
    if(!empty($item_category) && !empty($item_name) && !empty($item_quantity)){
        
        // دریافت اطلاعات فعلی کالا
        $current_quantity = $wpdb->get_var($wpdb->prepare(
            "SELECT item_quantity FROM $table_name WHERE item_name = %s AND item_category = %s",
            $item_name, $item_category
        ));

        // به‌روزرسانی تعداد کالا در انبار
        if ($current_quantity !== null) {
            $new_quantity = $current_quantity + $item_quantity;
            $wpdb->update(
                $table_name,
                array('item_quantity' => $new_quantity),
                array('item_name' => $item_name, 'item_category' => $item_category)
            );
        } else {
            // وارد کردن داده‌ها به جدول
            $wpdb->insert(
                $table_name,
                array(
                    'item_name' => $item_name,
                    'item_category' => $item_category,
                    'item_quantity' => $item_quantity
                )
            );
        }

        if(create_post($item_category, $item_name, "Add", $item_quantity)){
            
            wp_redirect($_SERVER['REQUEST_URI'] . '?success=1');
            exit;
        } else {
            wp_redirect($_SERVER['REQUEST_URI'] . '?success=0');
            exit;
        }
    }
}

get_header();

function get_item_names() {
    $terms = get_terms(array(
        'taxonomy' => 'item_name',
        'parent'   => 0,
        'hide_empty' => false,
    ));
    return $terms;
}



$item_names = get_item_names();

// echo '<pre>';
// var_dump($item_names);
// echo '</pre>';


$all_parents = [];
// az tamame daste ha va kalaha yek list dorost mikonad
if (!empty($item_names) && !is_wp_error($item_names)) {

    foreach ($item_names as $term) {
        
        $child_terms = get_terms(array(
            'taxonomy' => 'item_name',
            'parent'   => $term->term_id,
            'hide_empty' => false,
        ));

        if (!empty($child_terms) && !is_wp_error($child_terms)) {
            foreach ($child_terms as $child_term) {
                $all_parents[$term->name][] = $child_term->name;
            }
        }
    }
}

?>

<div class="add-item-form-container">
    <h2>افزودن کالا به انبار</h2>
    
    <?php if (isset($_GET['success'])): ?>
        <?php if ($_GET['success'] == '1'): ?>
            <div class="success-message" style="background-color: #dff0d8; color: #3c763d; padding: 15px; margin-bottom: 20px; border: 1px solid #d6e9c6; border-radius: 4px; text-align: center; font-size: 16px;">
                کالا با موفقیت به انبار اضافه شد
            </div>
        <?php else: ?>
            <div class="error-message" style="background-color: #f2dede; color: #a94442; padding: 15px; margin-bottom: 20px; border: 1px solid #ebccd1; border-radius: 4px; text-align: center; font-size: 16px;">
                خطا در ثبت تراکنش
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <form id="add-item-form" action="" method="post">
        
        <p>
            <label for="category">دسته‌بندی</label>
            <select name="item_category" id="category">
                <option value="">انتخاب کنید</option>
            </select>
        </p>
        <p>
            <label for="items">نام کالا</label>
            <select name="item_name" id="items">
                <option value="">انتخاب کنید</option>
            </select>
        </p>
        <p>
            <label for="item_quantity">تعداد</label>
            <input type="number" name="item_quantity" id="item_quantity" class="input" value="" size="20"  />
        </p>
        <p class="submit">
            <input type="submit" name="submit" id="add-item-submit" class="button button-primary" value="افزودن کالا" />
        </p>
    </form>

</div>
<script>
    // list dasteha va kalaha ra be front enteghal midahad
    var jsonData = <?php echo json_encode($all_parents); ?>;
    console.log(jsonData);

    $(document).ready(function() {
            $.each(jsonData, function(key, value) {
                $('#category').append($('<option></option>').attr('value', key).text(key));
            });

            // تغییر کشویی دوم با توجه به انتخاب کشویی اول
            $('#category').on('change', function() {
                var selectedCategory = $(this).val();
                $('#items').empty().append($('<option></option>').attr('value', '').text('انتخاب کنید'));

                if (selectedCategory) {
                    $.each(jsonData[selectedCategory], function(index, item) {
                        $('#items').append($('<option></option>').attr('value', item).text(item));
                    });
                }
            });
            // اضافه کردن بررسی ورودی‌ها هنگام کلیک روی دکمه ارسال
            $('#add-item-form').on('submit', function(e) {
                let isValid = true;

                // بررسی هر فیلد
                $('#add-item-form select, #add-item-form input').each(function() {
                    if ($(this).val() === '') {
                        isValid = false;
                        $(this).css('border', '2px solid red'); // تغییر رنگ به قرمز
                    } else {
                        $(this).css('border', ''); // بازگرداندن رنگ به حالت عادی
                    }
                });

                // اگر ورودی نامعتبر است، از ارسال فرم جلوگیری کنید
                if (!isValid) {
                    e.preventDefault();
                    $('#add-item-response').text('لطفاً همه فیلدها را پر کنید').css({
                        'color': 'red',
                        'font-size': '30px', // اندازه فونت
                        'text-align': 'center',
                        'margin-top': '10px' // فاصله از بالا

                    });

                }
            });
        });
        
</script>
<?php

?>
<script>
    if (window.location.search.includes('success')) {
        const url = new URL(window.location.href);
        url.searchParams.delete('success');
        window.history.replaceState({}, document.title, url.toString());
    }
</script>




<p class="back-to-home"> 
    <a href="<?php echo home_url(); ?>" class="button">بازگشت به صفحه اصلی</a> 
</p>

<?php
get_footer();


?>

