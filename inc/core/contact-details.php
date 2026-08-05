<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_get_contact_details()
{
    $defaults = array(
        'phone'   => '09125606941',
        'email'   => 'zigguratcorporation@gmail.com',
        'address' => 'تهران، ایران',
    );

    return wp_parse_args(get_option('zigurat_contact_details', array()), $defaults);
}
