<?php
/**
 * مدیریت تنظیمات افزونه.
 *
 * @package HanielShopCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSC_Settings {

	const OPTION = 'hsc_settings';

	/**
	 * کش تنظیمات در حافظه.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * مقادیر پیش‌فرض.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			// وضعیت‌های سفارش که به‌عنوان «خرید واقعی» شمرده می‌شوند.
			'paid_statuses'     => array( 'completed', 'processing' ),

			// آستانه‌ها برای دسته‌بندی سریع (علاوه بر RFM).
			'loyal_min_orders'  => 3,   // حداقل تعداد سفارش برای «وفادار».
			'churn_days'        => 120, // بی‌خریدی بیش از این تعداد روز = «در معرض ریزش».
			'new_days'          => 30,  // اولین خرید در این بازه = «تازه‌وارد».

			// ردیابی منبع ترافیک.
			'track_source'      => 'yes',
			'source_cookie_days' => 180,

			// حریم خصوصی/حذف.
			'delete_on_uninstall' => 'no',
		);
	}

	/**
	 * دریافت همه تنظیمات.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			self::$cache = wp_parse_args( get_option( self::OPTION, array() ), self::get_defaults() );
		}
		return self::$cache;
	}

	/**
	 * دریافت یک مقدار.
	 *
	 * @param string $key     کلید.
	 * @param mixed  $default پیش‌فرض.
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	/**
	 * آیا مقدار «بله» است؟
	 *
	 * @param string $key کلید.
	 * @return bool
	 */
	public static function is( $key ) {
		return 'yes' === self::get( $key );
	}

	/**
	 * وضعیت‌های پرداخت‌شده به شکل «wc-...» برای کوئری‌ها.
	 *
	 * @return array
	 */
	public static function paid_statuses_prefixed() {
		$statuses = (array) self::get( 'paid_statuses', array( 'completed', 'processing' ) );
		$out      = array();
		foreach ( $statuses as $s ) {
			$s = sanitize_key( $s );
			if ( '' === $s ) {
				continue;
			}
			$out[] = 0 === strpos( $s, 'wc-' ) ? $s : 'wc-' . $s;
		}
		return $out ? $out : array( 'wc-completed', 'wc-processing' );
	}

	/**
	 * راه‌اندازی هوک‌های ثبت تنظیمات.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * ثبت تنظیمات.
	 */
	public static function register_settings() {
		register_setting( 'hsc_settings_group', self::OPTION, array( __CLASS__, 'sanitize' ) );
	}

	/**
	 * پاک‌سازی ورودی‌ها.
	 *
	 * @param array $input ورودی خام.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::get_defaults();
		$output   = self::all();
		$input    = is_array( $input ) ? $input : array();

		// وضعیت‌های سفارش.
		$valid_statuses = array_keys( self::wc_statuses() );
		$statuses       = isset( $input['paid_statuses'] ) ? (array) $input['paid_statuses'] : array();
		$statuses       = array_values( array_intersect( array_map( 'sanitize_key', $statuses ), $valid_statuses ) );
		$output['paid_statuses'] = $statuses ? $statuses : $defaults['paid_statuses'];

		// اعداد.
		$output['loyal_min_orders']  = isset( $input['loyal_min_orders'] ) ? max( 1, absint( $input['loyal_min_orders'] ) ) : $defaults['loyal_min_orders'];
		$output['churn_days']        = isset( $input['churn_days'] ) ? max( 7, absint( $input['churn_days'] ) ) : $defaults['churn_days'];
		$output['new_days']          = isset( $input['new_days'] ) ? max( 1, absint( $input['new_days'] ) ) : $defaults['new_days'];
		$output['source_cookie_days'] = isset( $input['source_cookie_days'] ) ? max( 1, absint( $input['source_cookie_days'] ) ) : $defaults['source_cookie_days'];

		// بله/خیر.
		foreach ( array( 'track_source', 'delete_on_uninstall' ) as $key ) {
			$output[ $key ] = ( isset( $input[ $key ] ) && 'yes' === $input[ $key ] ) ? 'yes' : 'no';
		}

		self::$cache = null;
		return $output;
	}

	/**
	 * فهرست وضعیت‌های سفارش ووکامرس (بدون پیشوند wc-).
	 *
	 * @return array
	 */
	public static function wc_statuses() {
		$out = array();
		if ( function_exists( 'wc_get_order_statuses' ) ) {
			foreach ( wc_get_order_statuses() as $key => $label ) {
				$out[ str_replace( 'wc-', '', $key ) ] = $label;
			}
		}
		return $out;
	}
}
