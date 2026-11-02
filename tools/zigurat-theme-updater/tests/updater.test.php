<?php
error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ . '/fixture-wordpress/' );
define( 'WP_CONTENT_DIR', __DIR__ . '/fixture-wordpress/wp-content' );
define( 'FS_CHMOD_DIR', 0755 );
define( 'FS_CHMOD_FILE', 0644 );
define( 'MINUTE_IN_SECONDS', 60 );

class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}

class WP_Test_Die extends RuntimeException {}

class WP_Filesystem_Direct {
	public $fail_once_path = '';
	public function is_dir( $path ) { return is_dir( $path ); }
	public function mkdir( $path, $mode = false ) { return @mkdir( $path, $mode ?: 0755 ); }
	public function put_contents( $path, $contents, $mode = false ) {
		if ( $this->fail_once_path && basename( $path ) === $this->fail_once_path ) {
			$this->fail_once_path = '';
			return false;
		}
		return false !== @file_put_contents( $path, $contents );
	}
	public function get_contents( $path ) { return @file_get_contents( $path ); }
	public function exists( $path ) { return file_exists( $path ); }
	public function is_file( $path ) { return is_file( $path ); }
	public function delete( $path ) { return @unlink( $path ); }
	public function dirlist( $path ) {
		$result = array();
		foreach ( array_diff( scandir( $path ), array( '.', '..' ) ) as $name ) {
			$result[ $name ] = array( 'type' => is_dir( $path . '/' . $name ) ? 'd' : 'f' );
		}
		return $result;
	}
}

function add_action() { $GLOBALS['test_actions'][] = func_get_args(); }
function add_theme_page( $page_title, $menu_title, $capability, $menu_slug, $callback ) { $GLOBALS['test_theme_page'] = compact( 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback' ); return $menu_slug; }
function current_user_can( $capability ) { return ! empty( $GLOBALS['test_can_manage'] ); }
function wp_die( $message = '' ) { throw new WP_Test_Die( $message ); }
function esc_html__( $text ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' ); }
function admin_url( $path = '' ) { return '/wp-admin/' . $path; }
function get_current_user_id() { return 7; }
function get_transient( $key ) { return false; }
function delete_transient( $key ) {}
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce">'; }
function submit_button( $text, $type = 'primary', $name = 'submit', $wrap = true ) { echo '<button type="submit">' . esc_html( $text ) . '</button>'; }
function wp_date( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_get_theme() { return new class { public function get_stylesheet() { return $GLOBALS['test_active_theme']; } }; }
function get_stylesheet_directory() { return $GLOBALS['test_theme_root']; }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . DIRECTORY_SEPARATOR; }
function untrailingslashit( $path ) { return rtrim( $path, '/\\' ); }
function wp_generate_password( $length = 12 ) { return substr( bin2hex( random_bytes( (int) ceil( $length / 2 ) ) ), 0, $length ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function get_option( $key ) { return $GLOBALS['test_options'][ $key ] ?? false; }
function update_option( $key, $value ) { $GLOBALS['test_options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['test_options'][ $key ] ); }
function WP_Filesystem() { $GLOBALS['wp_filesystem'] = $GLOBALS['test_fs']; return true; }

$GLOBALS['test_options'] = array();
$GLOBALS['test_fs'] = new WP_Filesystem_Direct();
$GLOBALS['test_active_theme'] = 'zigurat';
$GLOBALS['test_actions'] = array();
$GLOBALS['test_can_manage'] = true;
$base = __DIR__ . '/fixture-run-' . bin2hex( random_bytes( 5 ) );
$GLOBALS['test_theme_root'] = $base . '/theme';
mkdir( $GLOBALS['test_theme_root'], 0755, true );
require dirname( __DIR__ ) . '/zigurat-theme-updater.php';

Zigurat_Theme_Updater::register_page();
check( 'zigurat-theme-updater' === $GLOBALS['test_theme_page']['menu_slug'] && 'manage_options' === $GLOBALS['test_theme_page']['capability'], 'Appearance page is restricted to site administrators' );
ob_start();
Zigurat_Theme_Updater::render_page();
$page_html = ob_get_clean();
check( false !== strpos( $page_html, 'enctype="multipart/form-data"' ) && false !== strpos( $page_html, 'به‌روزرسانی پوستهٔ زیگورات' ), 'admin page renders the package upload form' );
$GLOBALS['test_can_manage'] = false;
try {
	Zigurat_Theme_Updater::render_page();
	check( false, 'unauthorized users cannot render the page' );
} catch ( WP_Test_Die $error ) {
	check( true, 'unauthorized users cannot render the page' );
}
$GLOBALS['test_can_manage'] = true;

function check( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $label\n" );
		exit( 1 );
	}
	echo "PASS: $label\n";
}

function private_call( $method, ...$args ) {
	$reflection = new ReflectionMethod( 'Zigurat_Theme_Updater', $method );
	$reflection->setAccessible( true );
	return $reflection->invokeArgs( null, $args );
}

function make_patch( $path, $files, $extra = array() ) {
	$zip = new ZipArchive();
	$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$names = array_keys( $files );
	$zip->addFromString( 'zigurat-theme-patch.json', json_encode( array( 'theme' => 'zigurat', 'files' => $names ) ) );
	foreach ( $files as $name => $contents ) { $zip->addFromString( $name, $contents ); }
	foreach ( $extra as $name => $contents ) { $zip->addFromString( $name, $contents ); }
	$zip->close();
}

mkdir( $GLOBALS['test_theme_root'] . '/assets', 0755, true );
file_put_contents( $GLOBALS['test_theme_root'] . '/assets/old.css', 'old-css' );
$patch = $base . '/patch.zip';
make_patch( $patch, array( 'assets/old.css' => 'new-css', 'inc/new.php' => '<?php echo 1;' ) );
$result = private_call( 'apply_package', $patch );
check( ! is_wp_error( $result ), 'valid patch applies to active theme' );
check( 'new-css' === file_get_contents( $GLOBALS['test_theme_root'] . '/assets/old.css' ), 'existing file is replaced' );
check( '<?php echo 1;' === file_get_contents( $GLOBALS['test_theme_root'] . '/inc/new.php' ), 'new file is created' );
$backup = get_option( 'zigurat_theme_updater_last_backup' );
check( is_array( $backup ) && is_file( WP_CONTENT_DIR . '/.zigurat-theme-updater/backups/' . $backup['id'] . '.zip' ), 'private backup archive is created' );
$restore = private_call( 'rollback_last_backup' );
check( true === $restore, 'last patch can be rolled back' . ( is_wp_error( $restore ) ? ': ' . $restore->get_error_message() : '' ) );
check( 'old-css' === file_get_contents( $GLOBALS['test_theme_root'] . '/assets/old.css' ), 'rollback restores original file' );
check( ! file_exists( $GLOBALS['test_theme_root'] . '/inc/new.php' ), 'rollback removes files that did not exist before patch' );

$bad_path = $base . '/bad-path.zip';
make_patch( $bad_path, array( '../outside.php' => '<?php' ) );
$bad = private_call( 'inspect_package', $bad_path );
check( is_wp_error( $bad ), 'path traversal is rejected' );
$extra_path = $base . '/extra-file.zip';
make_patch( $extra_path, array( 'assets/old.css' => 'new' ), array( 'extra.php' => '<?php' ) );
$bad = private_call( 'inspect_package', $extra_path );
check( is_wp_error( $bad ), 'unlisted archive entries are rejected' );
$GLOBALS['test_active_theme'] = 'other-theme';
$bad = private_call( 'inspect_package', $patch );
check( is_wp_error( $bad ), 'patch is rejected when another theme is active' );
$GLOBALS['test_active_theme'] = 'zigurat';

file_put_contents( $GLOBALS['test_theme_root'] . '/assets/old.css', 'baseline' );
$failure_patch = $base . '/failure.zip';
make_patch( $failure_patch, array( 'assets/old.css' => 'partial', 'assets/fail.css' => 'fail' ) );
$GLOBALS['test_fs']->fail_once_path = 'fail.css';
$failed = private_call( 'apply_package', $failure_patch );
check( is_wp_error( $failed ), 'write failure is reported' );
check( 'baseline' === file_get_contents( $GLOBALS['test_theme_root'] . '/assets/old.css' ), 'partial write failure automatically restores prior file' );
check( ! file_exists( $GLOBALS['test_theme_root'] . '/assets/fail.css' ), 'partial write failure leaves no new file behind' );

$rollback_failure_patch = $base . '/rollback-failure.zip';
make_patch( $rollback_failure_patch, array( 'assets/old.css' => 'updated-again' ) );
$applied = private_call( 'apply_package', $rollback_failure_patch );
check( ! is_wp_error( $applied ), 'second patch applies for rollback failure check' );
$GLOBALS['test_fs']->fail_once_path = 'old.css';
$failed_restore = private_call( 'rollback_last_backup' );
check( is_wp_error( $failed_restore ), 'rollback write failure is reported' );
check( 'updated-again' === file_get_contents( $GLOBALS['test_theme_root'] . '/assets/old.css' ), 'failed rollback restores the pre-rollback file state' );
$restore = private_call( 'rollback_last_backup' );
check( true === $restore, 'rollback can be retried after a write failure' );
check( 'baseline' === file_get_contents( $GLOBALS['test_theme_root'] . '/assets/old.css' ), 'retry restores the original file' );

function remove_tree( $path ) {
	if ( ! file_exists( $path ) ) { return; }
	if ( is_dir( $path ) && ! is_link( $path ) ) {
		foreach ( array_diff( scandir( $path ), array( '.', '..' ) ) as $name ) { remove_tree( $path . DIRECTORY_SEPARATOR . $name ); }
		rmdir( $path );
	} else { unlink( $path ); }
}
remove_tree( $base );
remove_tree( ABSPATH );
echo "All updater tests passed.\n";
