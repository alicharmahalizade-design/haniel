<?php
/**
 * نصب، حذف و ساخت جدول جمع‌بندی مشتریان.
 *
 * @package HanielShopCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSC_Install {

	/**
	 * هنگام فعال‌سازی افزونه.
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();

		// زمان‌بندی کرون جمع‌بندی شبانه (اجرا در پس‌زمینه؛ بدون فشار روی بازدیدها).
		if ( ! wp_next_scheduled( 'hsc_daily_aggregate' ) ) {
			// اجرای اولین نوبت حدود ۳ بامداد.
			$ts = strtotime( 'tomorrow 3:00am' );
			wp_schedule_event( $ts, 'daily', 'hsc_daily_aggregate' );
		}

		// یک جمع‌بندی اولیه در پس‌زمینه، کمی بعد از فعال‌سازی.
		if ( ! wp_next_scheduled( 'hsc_full_rebuild' ) ) {
			wp_schedule_single_event( time() + 60, 'hsc_full_rebuild' );
		}

		update_option( 'hsc_db_version', HSC_VERSION );
	}

	/**
	 * هنگام غیرفعال‌سازی.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'hsc_daily_aggregate' );
		wp_clear_scheduled_hook( 'hsc_full_rebuild' );
		wp_clear_scheduled_hook( 'hsc_recompute_customer' );
	}

	/**
	 * ساخت جدول جمع‌بندی مشتریان.
	 *
	 * این جدول «آینهٔ محاسبه‌شده» است؛ داده‌ی خام سفارش‌ها دست‌نخورده می‌ماند و
	 * همهٔ گزارش‌ها از این جدول سبک خوانده می‌شوند تا هیچ کوئری سنگینی روی
	 * بارگذاری صفحات اجرا نشود.
	 */
	public static function create_tables() {
		global $wpdb;

		$table           = $wpdb->prefix . HSC_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(191) NOT NULL DEFAULT '',
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			display_name VARCHAR(191) NOT NULL DEFAULT '',
			phone VARCHAR(32) NOT NULL DEFAULT '',
			city VARCHAR(100) NOT NULL DEFAULT '',
			state VARCHAR(100) NOT NULL DEFAULT '',
			orders_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			total_spent DECIMAL(18,2) NOT NULL DEFAULT 0,
			avg_order_value DECIMAL(18,2) NOT NULL DEFAULT 0,
			first_order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			first_order_date DATETIME NULL DEFAULT NULL,
			last_order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			last_order_date DATETIME NULL DEFAULT NULL,
			recency_days INT(11) NOT NULL DEFAULT 0,
			source VARCHAR(40) NOT NULL DEFAULT 'direct',
			source_detail VARCHAR(191) NOT NULL DEFAULT '',
			rfm_r TINYINT(1) NOT NULL DEFAULT 0,
			rfm_f TINYINT(1) NOT NULL DEFAULT 0,
			rfm_m TINYINT(1) NOT NULL DEFAULT 0,
			segment VARCHAR(30) NOT NULL DEFAULT 'new',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY user_id (user_id),
			KEY orders_count (orders_count),
			KEY total_spent (total_spent),
			KEY last_order_date (last_order_date),
			KEY source (source),
			KEY segment (segment)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * مقادیر پیش‌فرض تنظیمات.
	 */
	public static function set_default_options() {
		$existing = get_option( 'hsc_settings', array() );
		$defaults = HSC_Settings::get_defaults();
		update_option( 'hsc_settings', wp_parse_args( $existing, $defaults ) );
	}
}
