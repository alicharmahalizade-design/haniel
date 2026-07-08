<?php
/**
 * کلاس اصلی افزونه؛ راه‌اندازی همه بخش‌ها.
 *
 * @package KhabaramKon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Khabaram_Kon {

	/**
	 * نمونه یکتا.
	 *
	 * @var Khabaram_Kon|null
	 */
	private static $instance = null;

	/**
	 * دریافت نمونه.
	 *
	 * @return Khabaram_Kon
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

		KK_Settings::init();
		KK_Shortlink::init();
		KK_Frontend::init();
		KK_Ajax::init();
		KK_Stock::init();
		KK_Conversion::init();
		KK_Alerts::init();
	}

	/**
	 * بررسی و اجرای مهاجرت دیتابیس در صورت تغییر نسخه.
	 */
	private function maybe_upgrade() {
		$saved = get_option( 'kk_db_version' );
		if ( $saved !== KK_VERSION ) {
			KK_Install::create_tables();
			update_option( 'kk_db_version', KK_VERSION );
		}
	}
}
