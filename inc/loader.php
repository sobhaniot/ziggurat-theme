<?php
if (!defined('ABSPATH')) {
    exit;
}
function zigurat_load_directory($directory)
{
    $path = get_template_directory() . '/inc/' . $directory;
    if (!is_dir($path)) {
        return;
    }
    $files = glob($path . '/*.php');
    if (!$files) {
        return;
    }
    sort($files);
    foreach ($files as $file) {
        if (
            basename($file) === 'loader.php'
        ) {
            continue;
        }
        require_once $file;
    }
}
$load_order = array(
    'setup',
    'core',
    'admin',
    'frontend',
    'inventory',
    'invoices'
);
foreach ($load_order as $folder) {
    zigurat_load_directory($folder);
}
