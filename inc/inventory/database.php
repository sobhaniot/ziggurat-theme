<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * ساخت جدول‌های اختصاصی انبارداری
 */
function zigurat_create_custom_tables()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    /*
    |--------------------------------------------------------------------------
    | جدول موجودی کالا
    |--------------------------------------------------------------------------
    */
    $table_inventory = 'zigurat_inventory';
    $sql_inventory = "
    CREATE TABLE $table_inventory (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        item_name varchar(255) NOT NULL,
        item_category varchar(100) DEFAULT '' NOT NULL,
        item_quantity int(11) DEFAULT 0 NOT NULL,
        PRIMARY KEY(id)
    ) $charset_collate;
    ";
    /*
    |--------------------------------------------------------------------------
    | جدول کاربران انبار
    |--------------------------------------------------------------------------
    */
    $table_users = 'zigurat_users';
    $sql_users = "
    CREATE TABLE $table_users (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        username varchar(60) NOT NULL,
        password varchar(255) NOT NULL,
        PRIMARY KEY(id)
    ) $charset_collate;
    ";
    require_once(
        ABSPATH . 'wp-admin/includes/upgrade.php'
    );
    dbDelta($sql_inventory);
    dbDelta($sql_users);
}
// add_action(
//     'after_switch_theme',
//     'zigurat_create_custom_tables'
// );
