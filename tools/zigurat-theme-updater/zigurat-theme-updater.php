<?php
/**
 * Plugin Name: Zigurat Theme Updater
 * Description: Applies validated ZIP patches to the active Zigurat theme from Appearance.
 * Version: 1.0.0
 * Author: Zigurat
 * Text Domain: zigurat-theme-updater
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Zigurat_Theme_Updater {
	const VERSION = '1.0.0';
	const PAGE = 'zigurat-theme-updater';
	const THEME = 'zigurat';
	const MAX_PACKAGE_BYTES = 20971520;
	const MAX_TOTAL_BYTES = 41943040;
	const MAX_FILES = 200;
	const MAX_BACKUPS = 5;
	const TRANSIENT_PREFIX = 'zgtu_pending_';
	const LAST_BACKUP_OPTION = 'zigurat_theme_updater_last_backup';

	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_post_zgtu_prepare', array( __CLASS__, 'handle_prepare' ) );
		add_action( 'admin_post_zgtu_apply', array( __CLASS__, 'handle_apply' ) );
		add_action( 'admin_post_zgtu_rollback', array( __CLASS__, 'handle_rollback' ) );
	}

	public static function register_page() {
		add_theme_page(
			'به‌روزرسانی پوسته زیگورات',
			'به‌روزرسانی زیگورات',
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	private static function notice_key() {
		return 'zgtu_notice_' . get_current_user_id();
	}

	private static function redirect( $args = array() ) {
		$url = add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'themes.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	private static function set_notice( $type, $message ) {
		set_transient( self::notice_key(), array( 'type' => $type, 'message' => $message ), 90 );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'اجازهٔ دسترسی به این صفحه را ندارید.', 'zigurat-theme-updater' ) );
		}

		$notice = get_transient( self::notice_key() );
		if ( $notice ) {
			delete_transient( self::notice_key() );
		}
		$token = isset( $_GET['preview'] ) ? sanitize_key( wp_unslash( $_GET['preview'] ) ) : '';
		$pending = $token ? get_transient( self::TRANSIENT_PREFIX . $token ) : false;
		$preview = false;
		if ( $pending && (int) $pending['user_id'] === get_current_user_id() ) {
			$inspection = self::inspect_package( $pending['path'] );
			if ( ! is_wp_error( $inspection ) ) {
				$preview = array( 'token' => $token, 'files' => $inspection['files'] );
			}
		}

		$last_backup = get_option( self::LAST_BACKUP_OPTION );
		?>
		<div class="wrap">
			<h1>به‌روزرسانی پوستهٔ زیگورات</h1>
			<p>بستهٔ تغییرات را بارگذاری کنید؛ ابتدا فهرست فایل‌ها بررسی می‌شود و سپس می‌توانید اعمال آن را تأیید کنید.</p>
			<?php if ( is_array( $notice ) && isset( $notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>
			<?php if ( $preview ) : ?>
				<div class="card" style="max-width:760px;padding:20px">
					<h2>فایل‌های آمادهٔ به‌روزرسانی</h2>
					<ul><?php foreach ( $preview['files'] as $file ) : ?><li><code><?php echo esc_html( $file ); ?></code></li><?php endforeach; ?></ul>
					<p>پیش از جایگزینی، از نسخهٔ فعلی این فایل‌ها نسخهٔ پشتیبان ساخته می‌شود.</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="zgtu_apply">
						<input type="hidden" name="token" value="<?php echo esc_attr( $preview['token'] ); ?>">
						<?php wp_nonce_field( 'zgtu_apply_' . $preview['token'] ); ?>
						<?php submit_button( 'اعمال به‌روزرسانی', 'primary', 'submit', false ); ?>
						<a class="button" href="<?php echo esc_url( admin_url( 'themes.php?page=' . self::PAGE ) ); ?>">انصراف</a>
					</form>
				</div>
			<?php else : ?>
				<div class="card" style="max-width:760px;padding:20px">
					<h2>بارگذاری بستهٔ تغییرات</h2>
					<p>فایل ZIP باید شامل <code>zigurat-theme-patch.json</code> و فایل‌های پوسته با مسیرهای نسبی باشد.</p>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="zgtu_prepare">
						<?php wp_nonce_field( 'zgtu_prepare' ); ?>
						<p><label for="zgtu-patch"><strong>فایل بستهٔ ZIP</strong></label><br><input id="zgtu-patch" name="patch_file" type="file" accept=".zip,application/zip" required></p>
						<?php submit_button( 'بررسی بسته' ); ?>
					</form>
				</div>
			<?php endif; ?>
			<?php if ( is_array( $last_backup ) && ! empty( $last_backup['id'] ) ) : ?>
				<div class="card" style="max-width:760px;padding:20px;margin-top:16px">
					<h2>بازگردانی آخرین نسخه</h2>
					<p>پشتیبان آخرین تغییر در <?php echo esc_html( wp_date( 'Y/m/d H:i', (int) $last_backup['created'] ) ); ?> ساخته شده است.</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('فایل‌های به‌روزرسانی‌شده با نسخهٔ پشتیبان جایگزین شوند؟');">
						<input type="hidden" name="action" value="zgtu_rollback">
						<?php wp_nonce_field( 'zgtu_rollback' ); ?>
						<?php submit_button( 'بازگردانی آخرین نسخه', 'secondary', 'submit', false ); ?>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle_prepare() {
		self::authorize( 'zgtu_prepare' );
		if ( empty( $_FILES['patch_file'] ) || ! isset( $_FILES['patch_file']['error'] ) || UPLOAD_ERR_OK !== (int) $_FILES['patch_file']['error'] ) {
			self::fail( 'بارگذاری فایل انجام نشد؛ فایل ZIP را دوباره انتخاب کنید.' );
		}
		$file = $_FILES['patch_file'];
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) || (int) $file['size'] > self::MAX_PACKAGE_BYTES ) {
			self::fail( 'حجم بسته بیشتر از ۲۰ مگابایت است یا فایل بارگذاری‌شده معتبر نیست.' );
		}
		$inspection = self::inspect_package( $file['tmp_name'] );
		if ( is_wp_error( $inspection ) ) {
			self::fail( $inspection->get_error_message() );
		}
		$token = strtolower( wp_generate_password( 32, false, false ) );
		$temp_dir = realpath( sys_get_temp_dir() );
		$web_root = realpath( ABSPATH );
		if ( ! $temp_dir || ! $web_root || 0 === strpos( wp_normalize_path( $temp_dir ) . '/', wp_normalize_path( $web_root ) . '/' ) ) {
			self::fail( 'پوشهٔ موقت امن خارج از مسیر عمومی سایت در دسترس نیست.' );
		}
		$target = trailingslashit( $temp_dir ) . 'zigurat-theme-patch-' . $token . '.zip';
		if ( ! @copy( $file['tmp_name'], $target ) ) {
			self::fail( 'ذخیرهٔ موقت بسته ممکن نشد؛ فضای موقت سرور را بررسی کنید.' );
		}
		set_transient( self::TRANSIENT_PREFIX . $token, array( 'path' => $target, 'user_id' => get_current_user_id() ), 10 * MINUTE_IN_SECONDS );
		self::redirect( array( 'preview' => $token ) );
	}

	public static function handle_apply() {
		$token = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		self::authorize( 'zgtu_apply_' . $token );
		$pending = $token ? get_transient( self::TRANSIENT_PREFIX . $token ) : false;
		if ( ! $pending || (int) $pending['user_id'] !== get_current_user_id() || ! is_file( $pending['path'] ) ) {
			self::fail( 'مهلت بررسی بسته تمام شده است؛ ZIP را دوباره بارگذاری کنید.' );
		}
		$result = self::apply_package( $pending['path'] );
		@unlink( $pending['path'] );
		delete_transient( self::TRANSIENT_PREFIX . $token );
		if ( is_wp_error( $result ) ) {
			self::fail( $result->get_error_message() );
		}
		self::set_notice( 'success', 'به‌روزرسانی با موفقیت اعمال شد و نسخهٔ قبلی پشتیبان‌گیری شد.' );
		self::redirect();
	}

	public static function handle_rollback() {
		self::authorize( 'zgtu_rollback' );
		$result = self::rollback_last_backup();
		if ( is_wp_error( $result ) ) {
			self::fail( $result->get_error_message() );
		}
		self::set_notice( 'success', 'فایل‌ها از آخرین نسخهٔ پشتیبان بازگردانی شدند.' );
		self::redirect();
	}

	private static function authorize( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'اجازهٔ انجام این عملیات را ندارید.', 'zigurat-theme-updater' ), 403 );
		}
		check_admin_referer( $nonce_action );
	}

	private static function fail( $message ) {
		self::set_notice( 'error', $message );
		self::redirect();
	}

	private static function active_theme_root() {
		if ( ! function_exists( 'wp_get_theme' ) || self::THEME !== wp_get_theme()->get_stylesheet() ) {
			return new WP_Error( 'zgtu_wrong_theme', 'پوستهٔ فعال زیگورات نیست؛ برای جلوگیری از تغییر پوستهٔ اشتباه، عملیات متوقف شد.' );
		}
		$root = realpath( get_stylesheet_directory() );
		if ( ! $root || ! is_dir( $root ) ) {
			return new WP_Error( 'zgtu_missing_theme', 'مسیر پوستهٔ فعال پیدا نشد.' );
		}
		return wp_normalize_path( $root );
	}

	private static function safe_target( $root, $relative ) {
		$root = untrailingslashit( wp_normalize_path( $root ) );
		$cursor = $root;
		$segments = explode( '/', $relative );
		foreach ( $segments as $index => $segment ) {
			$cursor .= '/' . $segment;
			if ( is_link( $cursor ) ) {
				return false;
			}
			if ( file_exists( $cursor ) ) {
				$real = realpath( $cursor );
				if ( ! $real ) {
					return false;
				}
				$real = wp_normalize_path( $real );
				if ( $index < count( $segments ) - 1 && ! is_dir( $real ) ) {
					return false;
				}
				if ( 0 !== strpos( $real, $root . '/' ) ) {
					return false;
				}
			}
		}
		return true;
	}

	private static function validate_relative_path( $path ) {
		if ( ! is_string( $path ) || '' === $path || strlen( $path ) > 240 || false !== strpos( $path, "\0" ) || false !== strpos( $path, '\\' ) || '/' === $path[0] ) {
			return false;
		}
		$segments = explode( '/', $path );
		foreach ( $segments as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment || '.' === $segment[0] || ! preg_match( '/^[A-Za-z0-9_-][A-Za-z0-9._-]*$/', $segment ) ) {
				return false;
			}
		}
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$allowed = array( 'php', 'js', 'css', 'html', 'json', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'map', 'txt' );
		if ( ! in_array( $extension, $allowed, true ) || preg_match( '/\.(?:phar|phtml|php[0-9]?|user\.ini)$/i', $path ) && 'php' !== $extension ) {
			return false;
		}
		$blocked = array( '.git', 'node_modules', 'tests', 'test', 'data', 'backups', 'vendor', '.secrets' );
		foreach ( $segments as $segment ) {
			if ( in_array( strtolower( $segment ), $blocked, true ) ) {
				return false;
			}
		}
		return true;
	}

	private static function inspect_package( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zgtu_zip_missing', 'پشتیبانی ZIP در PHP فعال نیست.' );
		}
		if ( ! is_file( $path ) || filesize( $path ) > self::MAX_PACKAGE_BYTES ) {
			return new WP_Error( 'zgtu_package_size', 'بسته پیدا نشد یا حجم آن از ۲۰ مگابایت بیشتر است.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::RDONLY ) ) {
			return new WP_Error( 'zgtu_zip_invalid', 'فایل انتخاب‌شده ZIP سالمی نیست.' );
		}
		if ( $zip->numFiles > 400 ) {
			$zip->close();
			return new WP_Error( 'zgtu_archive_entries', 'بسته تعداد ورودی‌های غیرمجاز دارد.' );
		}
		$manifest_stat = $zip->statName( 'zigurat-theme-patch.json' );
		if ( ! $manifest_stat || $manifest_stat['size'] > 65536 ) {
			$zip->close();
			return new WP_Error( 'zgtu_manifest_size', 'فهرست بسته وجود ندارد یا بیش از حد بزرگ است.' );
		}
		$manifest_json = $zip->getFromName( 'zigurat-theme-patch.json' );
		$manifest = is_string( $manifest_json ) ? json_decode( $manifest_json, true ) : null;
		if ( ! is_array( $manifest ) || self::THEME !== ( $manifest['theme'] ?? '' ) || empty( $manifest['files'] ) || ! is_array( $manifest['files'] ) ) {
			$zip->close();
			return new WP_Error( 'zgtu_manifest_invalid', 'فهرست بسته معتبر نیست یا برای پوستهٔ زیگورات ساخته نشده است.' );
		}
		if ( count( $manifest['files'] ) > self::MAX_FILES ) {
			$zip->close();
			return new WP_Error( 'zgtu_too_many_files', 'بسته بیش از ۲۰۰ فایل دارد.' );
		}
		$files = array();
		$casefold = array();
		$total = 0;
		foreach ( $manifest['files'] as $file ) {
			if ( ! self::validate_relative_path( $file ) || isset( $casefold[ strtolower( $file ) ] ) ) {
				$zip->close();
				return new WP_Error( 'zgtu_path_invalid', 'در فهرست بسته مسیر نامعتبر یا تکراری وجود دارد.' );
			}
			$casefold[ strtolower( $file ) ] = true;
			$index = $zip->locateName( $file, ZipArchive::FL_NOCASE );
			if ( false === $index || $zip->getNameIndex( $index ) !== $file ) {
				$zip->close();
				return new WP_Error( 'zgtu_file_missing', 'یکی از فایل‌های فهرست‌شده در ZIP وجود ندارد.' );
			}
			$stat = $zip->statIndex( $index );
			if ( ! $stat || empty( $stat['size'] ) || $stat['size'] > self::MAX_TOTAL_BYTES ) {
				$zip->close();
				return new WP_Error( 'zgtu_file_size', 'فایل خالی یا بیش از حد بزرگ در بسته وجود دارد.' );
			}
			$total += (int) $stat['size'];
			if ( $total > self::MAX_TOTAL_BYTES ) {
				$zip->close();
				return new WP_Error( 'zgtu_total_size', 'حجم بازشدهٔ بسته از ۴۰ مگابایت بیشتر است.' );
			}
			$mode = 0;
			if ( $zip->getExternalAttributesIndex( $index, $opsys, $attributes ) && 3 === $opsys ) {
				$mode = ( $attributes >> 16 ) & 0170000;
			}
			if ( 0120000 === $mode ) {
				$zip->close();
				return new WP_Error( 'zgtu_symlink', 'بسته نمی‌تواند شامل پیوند نمادین باشد.' );
			}
			$files[] = $file;
		}
		$entry_seen = array();
		$manifest_seen = 0;
		for ( $index = 0; $index < $zip->numFiles; $index++ ) {
			$name = $zip->getNameIndex( $index );
			if ( 'zigurat-theme-patch.json' === $name ) {
				$manifest_seen++;
				if ( 1 !== $manifest_seen ) {
					$zip->close();
					return new WP_Error( 'zgtu_duplicate_manifest', 'بسته شامل manifest تکراری است.' );
				}
				continue;
			}
			if ( substr( $name, -1 ) === '/' ) {
				continue;
			}
			if ( isset( $entry_seen[ $name ] ) || ! in_array( $name, $files, true ) ) {
				$zip->close();
				return new WP_Error( 'zgtu_unlisted_file', 'ZIP شامل فایل تکراری یا فایلی خارج از فهرست manifest است.' );
			}
			$entry_seen[ $name ] = true;
		}
		if ( count( $entry_seen ) !== count( $files ) ) {
			$zip->close();
			return new WP_Error( 'zgtu_entry_count', 'تعداد فایل‌های ZIP با فهرست manifest برابر نیست.' );
		}
		$zip->close();
		$theme_root = self::active_theme_root();
		if ( is_wp_error( $theme_root ) ) {
			return $theme_root;
		}
		foreach ( $files as $file ) {
			if ( ! self::safe_target( $theme_root, $file ) ) {
				return new WP_Error( 'zgtu_target_path', 'یکی از مسیرهای مقصد از پوشهٔ پوسته خارج می‌شود یا شامل پیوند نمادین است.' );
			}
		}
		return array( 'files' => $files, 'total' => $total );
	}

	private static function filesystem() {
		if ( ! function_exists( 'WP_Filesystem' ) || ! WP_Filesystem() ) {
			return new WP_Error( 'zgtu_filesystem', 'وردپرس به فایل‌های پوسته دسترسی نوشتن ندارد؛ تنظیمات دسترسی فایل سرور را بررسی کنید.' );
		}
		global $wp_filesystem;
		if ( ! class_exists( 'WP_Filesystem_Direct' ) || ! $wp_filesystem instanceof WP_Filesystem_Direct ) {
			return new WP_Error( 'zgtu_direct_filesystem', 'سرور باید به فایل‌های پوسته دسترسی مستقیم نوشتن داشته باشد؛ دسترسی فایل را امن تنظیم کنید.' );
		}
		return $wp_filesystem;
	}

	private static function ensure_dir( $fs, $path ) {
		$path = untrailingslashit( $path );
		if ( $fs->is_dir( $path ) ) {
			return true;
		}
		$parent = dirname( $path );
		if ( $parent !== $path && ! $fs->is_dir( $parent ) && ! self::ensure_dir( $fs, $parent ) ) {
			return false;
		}
		return $fs->mkdir( $path, FS_CHMOD_DIR );
	}

	private static function backup_dir( $fs ) {
		$private_root = trailingslashit( WP_CONTENT_DIR ) . '.zigurat-theme-updater';
		$dir = trailingslashit( $private_root ) . 'backups';
		if ( is_link( $private_root ) || is_link( $dir ) ) {
			return new WP_Error( 'zgtu_backup_symlink', 'مسیر پوشهٔ پشتیبان شامل پیوند نمادین است؛ عملیات متوقف شد.' );
		}
		if ( ! self::ensure_dir( $fs, $dir ) ) {
			return new WP_Error( 'zgtu_backup_dir', 'ساخت پوشهٔ امن پشتیبان ممکن نشد.' );
		}
		$guards = array(
			trailingslashit( $private_root ) . 'index.php' => "<?php\nhttp_response_code( 404 );\nexit;\n",
			trailingslashit( $private_root ) . '.htaccess' => "Require all denied\nDeny from all\n",
			trailingslashit( $dir ) . 'index.php' => "<?php\nhttp_response_code( 404 );\nexit;\n",
			trailingslashit( $dir ) . '.htaccess' => "Require all denied\nDeny from all\n",
		);
		foreach ( $guards as $path => $contents ) {
			if ( ! $fs->put_contents( $path, $contents, FS_CHMOD_FILE ) ) {
				return new WP_Error( 'zgtu_backup_guard', 'تنظیم محافظت پوشهٔ پشتیبان ممکن نشد؛ فایل‌های پوسته تغییر نکردند.' );
			}
		}
		return $dir;
	}

	private static function apply_package( $package ) {
		$inspection = self::inspect_package( $package );
		if ( is_wp_error( $inspection ) ) {
			return $inspection;
		}
		$fs = self::filesystem();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}
		$root = self::active_theme_root();
		if ( is_wp_error( $root ) ) {
			return $root;
		}
		$backup_dir = self::backup_dir( $fs );
		if ( is_wp_error( $backup_dir ) ) {
			return $backup_dir;
		}
		$backup_id = gmdate( 'Ymd-His' ) . '-' . strtolower( wp_generate_password( 8, false, false ) );
		$backup_path = trailingslashit( $backup_dir ) . $backup_id . '.zip';
		$zip = new ZipArchive();
		if ( true !== $zip->open( $backup_path, ZipArchive::CREATE | ZipArchive::EXCL ) ) {
			return new WP_Error( 'zgtu_backup_create', 'ساخت فایل پشتیبان ممکن نشد؛ هیچ فایلی از پوسته تغییر نکرد.' );
		}
		$absent = array();
		$present = array();
		$backup_total = 0;
		foreach ( $inspection['files'] as $relative ) {
			if ( ! self::safe_target( $root, $relative ) ) {
				$zip->close();
				@unlink( $backup_path );
				return new WP_Error( 'zgtu_target_invalid', 'مسیر یکی از فایل‌های هدف امن نیست؛ تغییری اعمال نشد.' );
			}
			$target = wp_normalize_path( $root . '/' . $relative );
			if ( file_exists( $target ) ) {
				if ( ! is_file( $target ) || is_link( $target ) ) {
					$zip->close();
					@unlink( $backup_path );
					return new WP_Error( 'zgtu_target_invalid', 'مسیر یکی از فایل‌های هدف امن نیست؛ تغییری اعمال نشد.' );
				}
				$content = $fs->get_contents( $target );
				$backup_total += is_string( $content ) ? strlen( $content ) : 0;
				if ( false === $content || $backup_total > self::MAX_TOTAL_BYTES || ! $zip->addFromString( $relative, $content ) ) {
					$zip->close();
					@unlink( $backup_path );
					return new WP_Error( 'zgtu_backup_read', 'پشتیبان‌گیری فایل‌های فعلی ناموفق بود یا حجم آن از ۴۰ مگابایت بیشتر شد؛ تغییری اعمال نشد.' );
				}
				$present[] = $relative;
			} else {
				$absent[] = $relative;
			}
		}
		$manifest = wp_json_encode( array( 'theme' => self::THEME, 'files' => $present, 'absent' => $absent ) );
		if ( ! $manifest || ! $zip->addFromString( 'zigurat-theme-backup.json', $manifest ) || ! $zip->close() ) {
			@unlink( $backup_path );
			return new WP_Error( 'zgtu_backup_write', 'ثبت پشتیبان ناموفق بود؛ تغییری اعمال نشد.' );
		}

		$patch = new ZipArchive();
		if ( true !== $patch->open( $package, ZipArchive::RDONLY ) ) {
			@unlink( $backup_path );
			return new WP_Error( 'zgtu_zip_reopen', 'خواندن دوبارهٔ بسته ناموفق بود؛ تغییری اعمال نشد.' );
		}
		$written = array();
		$failure = false;
		foreach ( $inspection['files'] as $relative ) {
			$content = $patch->getFromName( $relative );
			$target = wp_normalize_path( $root . '/' . $relative );
			if ( ! is_string( $content ) || ! self::ensure_dir( $fs, dirname( $target ) ) || ! $fs->put_contents( $target, $content, FS_CHMOD_FILE ) ) {
				$failure = true;
				break;
			}
			$written[] = $relative;
			if ( function_exists( 'opcache_invalidate' ) ) {
				@opcache_invalidate( $target, true );
			}
		}
		$patch->close();
		if ( $failure ) {
			$restored = self::restore_archive( $backup_path, $fs, $root );
			if ( is_wp_error( $restored ) ) {
				return new WP_Error( 'zgtu_partial_rollback', 'اعمال بسته کامل نشد و بازگردانی خودکار نیز خطا داد. شناسهٔ پشتیبان برای بازیابی دستی: ' . $backup_id );
			}
			@unlink( $backup_path );
			return new WP_Error( 'zgtu_apply_failed', 'نوشتن یکی از فایل‌ها ناموفق بود؛ تغییرات انجام‌شده به‌صورت خودکار بازگردانی شدند.' );
		}
		update_option( self::LAST_BACKUP_OPTION, array( 'id' => $backup_id, 'created' => time() ), false );
		self::prune_backups( $fs, $backup_dir );
		return array( 'backup_id' => $backup_id, 'files' => $written );
	}

	private static function restore_archive( $archive_path, $fs, $root ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $archive_path, ZipArchive::RDONLY ) ) {
			return new WP_Error( 'zgtu_rollback_open', 'بازکردن فایل پشتیبان ناموفق بود.' );
		}
		if ( $zip->numFiles > self::MAX_FILES + 1 ) {
			$zip->close();
			return new WP_Error( 'zgtu_rollback_entries', 'فایل پشتیبان تعداد ورودی‌های غیرمجاز دارد.' );
		}
		$manifest_stat = $zip->statName( 'zigurat-theme-backup.json' );
		if ( ! $manifest_stat || $manifest_stat['size'] > 65536 ) {
			$zip->close();
			return new WP_Error( 'zgtu_rollback_manifest', 'فهرست پشتیبان وجود ندارد یا بیش از حد بزرگ است.' );
		}
		$manifest_data = $zip->getFromName( 'zigurat-theme-backup.json' );
		$manifest = is_string( $manifest_data ) ? json_decode( $manifest_data, true ) : null;
		if ( ! is_array( $manifest ) || self::THEME !== ( $manifest['theme'] ?? '' ) || ! isset( $manifest['files'] ) || ! is_array( $manifest['files'] ) ) {
			$zip->close();
			return new WP_Error( 'zgtu_rollback_manifest', 'فهرست پشتیبان معتبر نیست.' );
		}
		if ( count( $manifest['files'] ) > self::MAX_FILES ) {
			$zip->close();
			return new WP_Error( 'zgtu_rollback_entries', 'فهرست پشتیبان تعداد فایل‌های غیرمجاز دارد.' );
		}
		$all_paths = array();
		foreach ( $manifest['files'] as $relative ) {
			if ( ! self::validate_relative_path( $relative ) || isset( $all_paths[ strtolower( $relative ) ] ) ) {
				$zip->close();
				return new WP_Error( 'zgtu_rollback_path', 'مسیر فایل در پشتیبان معتبر نیست.' );
			}
			$all_paths[ strtolower( $relative ) ] = true;
		}
		$absent = isset( $manifest['absent'] ) && is_array( $manifest['absent'] ) ? $manifest['absent'] : array();
		if ( count( $absent ) > self::MAX_FILES || count( $manifest['files'] ) + count( $absent ) > self::MAX_FILES ) {
			$zip->close();
			return new WP_Error( 'zgtu_rollback_entries', 'فهرست بازگردانی تعداد مسیرهای غیرمجاز دارد.' );
		}
		foreach ( $absent as $relative ) {
			if ( ! self::validate_relative_path( $relative ) || isset( $all_paths[ strtolower( $relative ) ] ) ) {
				$zip->close();
				return new WP_Error( 'zgtu_rollback_path', 'مسیر فایل در فهرست بازگردانی معتبر نیست.' );
			}
			$all_paths[ strtolower( $relative ) ] = true;
		}
		if ( empty( $all_paths ) ) {
			$zip->close();
			return new WP_Error( 'zgtu_rollback_manifest', 'فهرست پشتیبان فایل قابل‌بازگردانی ندارد.' );
		}
		$contents = array();
		$total = 0;
		foreach ( $manifest['files'] as $relative ) {
			if ( ! self::safe_target( $root, $relative ) ) {
				$zip->close();
				return new WP_Error( 'zgtu_rollback_path', 'مسیر مقصد هنگام بازگردانی امن نیست.' );
			}
			$content = $zip->getFromName( $relative );
			$total += is_string( $content ) ? strlen( $content ) : 0;
			if ( ! is_string( $content ) || $total > self::MAX_TOTAL_BYTES ) {
				$zip->close();
				return new WP_Error( 'zgtu_rollback_read', 'خواندن فایل‌های پشتیبان ناموفق بود یا حجم آن‌ها بیش از حد مجاز است.' );
			}
			$contents[ $relative ] = $content;
		}
		$archive_entries = array();
		for ( $index = 0; $index < $zip->numFiles; $index++ ) {
			$name = $zip->getNameIndex( $index );
			if ( 'zigurat-theme-backup.json' === $name ) { continue; }
			if ( isset( $archive_entries[ $name ] ) || ! isset( $contents[ $name ] ) ) {
				$zip->close();
				return new WP_Error( 'zgtu_rollback_entries', 'محتویات آرشیو با فهرست پشتیبان تطبیق ندارد.' );
			}
			$archive_entries[ $name ] = true;
		}
		if ( count( $archive_entries ) !== count( $contents ) ) {
			$zip->close();
			return new WP_Error( 'zgtu_rollback_entries', 'تعداد فایل‌های آرشیو با فهرست پشتیبان برابر نیست.' );
		}
		$current_state = array();
		foreach ( array_merge( $manifest['files'], $absent ) as $relative ) {
			$target = wp_normalize_path( $root . '/' . $relative );
			$current_state[ $relative ] = $fs->exists( $target ) ? $fs->get_contents( $target ) : false;
			if ( false === $current_state[ $relative ] && $fs->exists( $target ) ) {
				$zip->close();
				return new WP_Error( 'zgtu_rollback_read', 'خواندن وضعیت فعلی پوسته برای بازگردانی امن ناموفق بود.' );
			}
		}
		$zip->close();
		$result = self::write_file_state( $contents, $absent, $fs, $root );
		if ( is_wp_error( $result ) ) {
			$recovery = self::write_file_state(
				array_filter( $current_state, 'is_string' ),
				array_keys( array_filter( $current_state, static function ( $value ) { return false === $value; } ) ),
				$fs,
				$root
			);
			if ( is_wp_error( $recovery ) ) {
				return new WP_Error( 'zgtu_rollback_recovery', 'بازگردانی ناقص شد و حفظ وضعیت فعلی نیز خطا داشت؛ فایل پوسته را بررسی کنید.' );
			}
			return $result;
		}
		return true;
	}

	private static function write_file_state( $contents, $absent, $fs, $root ) {
		foreach ( $contents as $relative => $content ) {
			$target = wp_normalize_path( $root . '/' . $relative );
			if ( ! self::safe_target( $root, $relative ) || ! self::ensure_dir( $fs, dirname( $target ) ) || ! $fs->put_contents( $target, $content, FS_CHMOD_FILE ) ) {
				return new WP_Error( 'zgtu_rollback_write', 'نوشتن فایل در بازگردانی ناموفق بود.' );
			}
			if ( function_exists( 'opcache_invalidate' ) ) { @opcache_invalidate( $target, true ); }
		}
		foreach ( $absent as $relative ) {
			if ( ! self::safe_target( $root, $relative ) ) {
				return new WP_Error( 'zgtu_rollback_path', 'مسیر مقصد هنگام بازگردانی امن نیست.' );
			}
			$target = wp_normalize_path( $root . '/' . $relative );
			if ( $fs->exists( $target ) && ! $fs->delete( $target ) ) {
				return new WP_Error( 'zgtu_rollback_delete', 'پاک‌کردن فایل جدید هنگام بازگردانی ناموفق بود.' );
			}
		}
		return true;
	}

	private static function rollback_last_backup() {
		$last = get_option( self::LAST_BACKUP_OPTION );
		if ( ! is_array( $last ) || empty( $last['id'] ) || ! preg_match( '/^[0-9]{8}-[0-9]{6}-[a-z0-9]{8}$/', $last['id'] ) ) {
			return new WP_Error( 'zgtu_no_backup', 'نسخهٔ پشتیبان معتبری برای بازگردانی پیدا نشد.' );
		}
		$fs = self::filesystem();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}
		$root = self::active_theme_root();
		if ( is_wp_error( $root ) ) {
			return $root;
		}
		$backup = trailingslashit( WP_CONTENT_DIR ) . '.zigurat-theme-updater/backups/' . $last['id'] . '.zip';
		if ( ! $fs->is_file( $backup ) ) {
			return new WP_Error( 'zgtu_backup_missing', 'فایل پشتیبان پیدا نشد؛ احتمالاً فایل‌های موقت سرور پاک شده‌اند.' );
		}
		$result = self::restore_archive( $backup, $fs, $root );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		delete_option( self::LAST_BACKUP_OPTION );
		return true;
	}

	private static function prune_backups( $fs, $dir ) {
		$list = $fs->dirlist( $dir );
		if ( ! is_array( $list ) ) {
			return;
		}
		$archives = array();
		foreach ( $list as $name => $item ) {
			if ( 'f' === ( $item['type'] ?? '' ) && preg_match( '/^[0-9]{8}-[0-9]{6}-[a-z0-9]{8}\.zip$/', $name ) ) {
				$archives[] = $name;
			}
		}
		rsort( $archives, SORT_STRING );
		foreach ( array_slice( $archives, self::MAX_BACKUPS ) as $old ) {
			$fs->delete( trailingslashit( $dir ) . $old );
		}
	}
}

Zigurat_Theme_Updater::boot();
