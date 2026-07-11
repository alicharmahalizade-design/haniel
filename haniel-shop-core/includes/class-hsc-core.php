<?php
/**
 * کلاس اصلی افزونه؛ راه‌اندازی همهٔ بخش‌ها.
 *
 * @package HanielShopCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HSC_Core {

	/**
	 * نمونهٔ یکتا.
	 *
	 * @var HSC_Core|null
	 */
	private static $instance = null;

	/**
	 * دریافت نمونه.
	 *
	 * @return HSC_Core
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * سازنده.
	 */
	private function __construct() {
		$this->maybe_upgrade();

		HSC_Settings::init();
		HSC_Source::init();
		HSC_Aggregator::init();
		HSC_Admin::init();
	}

	/**
	 * اجرای مهاجرت دیتابیس در صورت تغییر نسخه.
	 */
	private function maybe_upgrade() {
		$saved = get_option( 'hsc_db_version' );
		if ( $saved !== HSC_VERSION ) {
			HSC_Install::create_tables();

			// اطمینان از زمان‌بندی کرون شبانه.
			if ( ! wp_next_scheduled( 'hsc_daily_aggregate' ) ) {
				wp_schedule_event( strtotime( 'tomorrow 3:00am' ), 'daily', 'hsc_daily_aggregate' );
			}

			update_option( 'hsc_db_version', HSC_VERSION );
		}
	}
}
