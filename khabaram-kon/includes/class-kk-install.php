<?php
/**
 * نصب، حذف و ساخت جداول دیتابیس.
 *
 * @package KhabaramKon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KK_Install {

	/**
	 * هنگام فعال‌سازی افزونه.
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();

		// افزودن قوانین بازنویسی برای لینک کوتاه و فلاش کردن.
		KK_Shortlink::add_rewrite_rules();
		flush_rewrite_rules();

		// نسخه ذخیره‌شده برای مهاجرت‌های آینده.
		update_option( 'kk_db_version', KK_VERSION );
	}

	/**
	 * هنگام غیرفعال‌سازی.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
		wp_clear_scheduled_hook( 'kk_process_notifications' );
	}

	/**
	 * ساخت جدول اشتراک‌ها.
	 */
	public static function create_tables() {
		global $wpdb;

		$table           = $wpdb->prefix . KK_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			variation_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			phone VARCHAR(20) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			token VARCHAR(32) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			notified_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY variation_id (variation_id),
			KEY status (status),
			KEY phone (phone),
			KEY token (token)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * مقادیر پیش‌فرض تنظیمات.
	 */
	public static function set_default_options() {
		$existing = get_option( 'kk_settings', array() );
		$defaults = KK_Settings::get_defaults();
		update_option( 'kk_settings', wp_parse_args( $existing, $defaults ) );
	}
}
