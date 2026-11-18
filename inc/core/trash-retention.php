<?php
if (!defined('ABSPATH')) {
    exit;
}

// Project archives use WordPress trash, so retain them until an explicit deletion.
remove_action('wp_scheduled_delete', 'wp_scheduled_delete');
