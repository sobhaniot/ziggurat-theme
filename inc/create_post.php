<?php

function create_post($item_category,$item_name, $action, $quantity, $project='') {
    // بررسی وجود مقادیر برای متغیرهای ورودی 
    if (empty($item_category) || empty($item_name) || empty($action) || empty($quantity)) { 
        echo '<div id="transaction-response">لطفاً تمام فیلدهای مورد نیاز را پر کنید.</div>'; 
        return; 
    }
    
    // ایجاد پست جدید 
    $username = $_COOKIE['ziguser'];
    // تبدیل تاریخ میلادی به شمسی و تنظیم زمان به وقت ایران 
    $miladi_date = new DateTime('now', new DateTimeZone('UTC')); 
    $miladi_date->setTimezone(new DateTimeZone('Asia/Tehran')); 
    $jalali_date = gregorian_to_jalali($miladi_date->format('Y'), $miladi_date->format('m'), $miladi_date->format('d'));

    // ایجاد یک پست جدید
    $post_id = wp_insert_post(array(
        'post_title' => implode('-', $jalali_date) . ' ' . $miladi_date->format('H:i:s'),
        'post_content' => $quantity,
        'post_status' => 'publish',
        'post_type' => 'post',
        
    ));
    // echo $post_id;
    if ($post_id) {
        $term_cat = get_term_by('name', $item_category, 'item_name');
        $term_name = get_term_by('name', $item_name, 'item_name');

        wp_set_post_terms($post_id, array($term_cat->term_id,$term_name->term_id), 'item_name');

        $term_action = get_term_by('name', $action, 'category');
        wp_set_post_terms($post_id, array($term_action->term_id), 'category');

        $term_user = get_term_by('name', ucfirst($username), 'employee');
        
        wp_set_post_terms($post_id, array($term_user->term_id), 'employee');

        if($project){
            $term_project = get_term_by('name', ucfirst($project), 'project_item');
    
            wp_set_post_terms($post_id, array($term_project->term_id), 'project_item');
        }

        return TRUE;
    } else {
        return FALSE;
    }
}

// تابع تبدیل تاریخ میلادی به شمسی 
function gregorian_to_jalali($gy, $gm, $gd, $mod = '') { 
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334); 
    $jy = ($gy <= 1600) ? 0 : 979; 
    $gy -= ($gy <= 1600) ? 621 : 1600; 
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy; 
    $days = (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) - 80 + $gd + $g_d_m[$gm - 1]; 
    $jy += 33 * (int)($days / 12053); 
    $days %= 12053; 
    $jy += 4 * (int)($days / 1461); 
    $days %= 1461; 
    $jy += (int)(($days - 1) / 365); 
    if ($days > 365) $days = ($days - 1) % 365; 
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30); 
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30)); 
    return ($mod == '') ? array($jy, $jm, $jd) : $jy . $mod . $jm . $mod . $jd; 
}
?>