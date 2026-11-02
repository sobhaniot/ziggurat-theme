<?php
/**
 * Optional integration check against the local XAMPP WordPress install.
 * Run with C:\xampp\php\php.exe from this file's directory.
 */
$wordpress_root = 'C:/xampp/htdocs/wordpress';
$plugin_slug = 'zigurat-theme-updater/zigurat-theme-updater.php';
$plugin_path = $wordpress_root . '/wp-content/plugins/' . $plugin_slug;
if ( ! is_file( $plugin_path ) ) {
	fwrite( STDERR, "Copy the plugin into local wp-content/plugins before running this check.\n" );
	exit( 2 );
}
require $wordpress_root . '/wp-load.php';

function local_check( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $label\n" );
	exit( 1 );
	}
	echo "PASS: $label\n";
}

if ( 'zigurat' !== wp_get_theme()->get_stylesheet() ) {
	fwrite( STDERR, "The local WordPress theme is not zigurat; no integration patch was applied.\n" );
	exit( 2 );
}
$test_relative = 'zgtu-local-integration-check.txt';
$test_target = get_stylesheet_directory() . '/' . $test_relative;
if ( file_exists( $test_target ) ) {
	fwrite( STDERR, "The integration fixture already exists; refusing to overwrite it.\n" );
	exit( 2 );
}
$package = __DIR__ . '/local-integration-patch.zip';
$activated_here = ! in_array( $plugin_slug, (array) get_option( 'active_plugins', array() ), true );

try {
	if ( $activated_here ) {
		$activation_error = activate_plugin( $plugin_slug );
		local_check( ! is_wp_error( $activation_error ), 'plugin activates in local WordPress' );
	}
	do_action( 'admin_menu' );
$found_menu = false;
foreach ( (array) ( $GLOBALS['submenu']['themes.php'] ?? array() ) as $item ) {
	if ( isset( $item[2] ) && 'zigurat-theme-updater' === $item[2] ) { $found_menu = true; }
}
	local_check( $found_menu, 'Appearance submenu is registered in local WordPress' );

	$zip = new ZipArchive();
	$zip->open( $package, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$zip->addFromString( 'zigurat-theme-patch.json', wp_json_encode( array( 'theme' => 'zigurat', 'files' => array( $test_relative ) ) ) );
	$zip->addFromString( $test_relative, 'local integration fixture' );
	$zip->close();
	$reflection = new ReflectionMethod( 'Zigurat_Theme_Updater', 'apply_package' );
	$reflection->setAccessible( true );
	$applied = $reflection->invoke( null, $package );
	local_check( ! is_wp_error( $applied ), 'patch applies through the real WordPress filesystem' );
	local_check( 'local integration fixture' === file_get_contents( $test_target ), 'patched file is visible in the active theme' );

	$rollback = new ReflectionMethod( 'Zigurat_Theme_Updater', 'rollback_last_backup' );
	$rollback->setAccessible( true );
	$restored = $rollback->invoke( null );
	local_check( true === $restored, 'local WordPress restores the generated backup' );
	local_check( ! file_exists( $test_target ), 'rollback removes the temporary fixture from the active theme' );
	delete_option( 'zigurat_theme_updater_last_backup' );
} finally {
	if ( file_exists( $package ) ) { unlink( $package ); }
	if ( file_exists( $test_target ) ) { unlink( $test_target ); }
	$private_storage = WP_CONTENT_DIR . '/.zigurat-theme-updater';
	if ( is_dir( $private_storage ) ) {
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $private_storage, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $iterator as $item ) { $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); }
		rmdir( $private_storage );
	}
	if ( $activated_here ) { deactivate_plugins( $plugin_slug, true ); }
}

echo "Local WordPress integration check passed.\n";
