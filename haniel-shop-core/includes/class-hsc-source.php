<?php
/**
 * ردیابی منبع ورود مشتری (گوگل، اینستاگرام، مستقیم و…).
 *
 * طراحی سبک: روی بازدید عادی هیچ چیزی در دیتابیس نوشته نمی‌شود؛ فقط یک کوکی
 * «اولین‌لمس» (first-touch) ست می‌شود. منبع واقعی تنها یک‌بار و هنگام ثبت سفارش
 * به‌صورت متای سفارش ذخیره می‌گردد.
 *
 * @package HanielShopCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSC_Source {

	const COOKIE = 'hsc_src';

	/**
	 * راه‌اندازی هوک‌ها.
	 */
	public static function init() {
		if ( ! HSC_Settings::is( 'track_source' ) ) {
			return;
		}
		// ست‌کردن کوکی منبع فقط برای بازدیدکننده‌های غیرمدیر و فقط اگر قبلاً ست نشده باشد.
		add_action( 'wp', array( __CLASS__, 'maybe_set_cookie' ) );

		// ذخیرهٔ منبع روی سفارش هنگام ساخت آن.
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'attach_to_order' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'attach_to_order_blocks' ), 10, 2 );
	}

	/**
	 * در صورت نبود کوکی، منبع اولین‌لمس را محاسبه و ذخیره می‌کند.
	 */
	public static function maybe_set_cookie() {
		if ( is_admin() || headers_sent() ) {
			return;
		}
		// اگر قبلاً ثبت شده، دست نزن (first-touch حفظ شود).
		if ( isset( $_COOKIE[ self::COOKIE ] ) && '' !== $_COOKIE[ self::COOKIE ] ) {
			return;
		}
		// ربات‌ها را رها کن.
		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return;
		}

		$data    = self::detect();
		$days    = (int) HSC_Settings::get( 'source_cookie_days', 180 );
		$value   = wp_json_encode( $data );
		$expires = time() + DAY_IN_SECONDS * max( 1, $days );
		$path    = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain  = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$secure  = is_ssl();

		if ( PHP_VERSION_ID >= 70300 ) {
			// فرم آرایه‌ای با SameSite (PHP 7.3+).
			setcookie(
				self::COOKIE,
				$value,
				array(
					'expires'  => $expires,
					'path'     => $path,
					'domain'   => $domain,
					'secure'   => $secure,
					'httponly' => false,
					'samesite' => 'Lax',
				)
			);
		} else {
			// فرم قدیمی سازگار با PHP 7.2 (SameSite از طریق مسیر).
			setcookie( self::COOKIE, $value, $expires, $path . '; samesite=Lax', $domain, $secure, false );
		}
		$_COOKIE[ self::COOKIE ] = $value;
	}

	/**
	 * تشخیص منبع از UTM و ارجاع‌دهنده (referrer).
	 *
	 * @return array{source:string,detail:string}
	 */
	public static function detect() {
		$utm_source = isset( $_GET['utm_source'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$utm_medium = isset( $_GET['utm_medium'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_medium'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$referrer   = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';

		return self::classify( $referrer, $utm_source, $utm_medium );
	}

	/**
	 * طبقه‌بندی منبع به یک کلید استاندارد.
	 *
	 * @param string $referrer   آدرس ارجاع‌دهنده.
	 * @param string $utm_source مقدار utm_source.
	 * @param string $utm_medium مقدار utm_medium.
	 * @return array{source:string,detail:string}
	 */
	public static function classify( $referrer = '', $utm_source = '', $utm_medium = '' ) {
		// اولویت با UTM است (کمپین‌های مشخص).
		if ( '' !== $utm_source ) {
			$src = self::normalize_key( $utm_source );
			return array(
				'source' => self::map_known( $src, $utm_source ),
				'detail' => trim( $utm_source . ( $utm_medium ? ' / ' . $utm_medium : '' ) ),
			);
		}

		if ( '' === $referrer ) {
			return array( 'source' => 'direct', 'detail' => '' );
		}

		$host = wp_parse_url( $referrer, PHP_URL_HOST );
		$host = $host ? strtolower( preg_replace( '/^www\./', '', $host ) ) : '';

		// ارجاع داخلی خودِ سایت = مستقیم.
		$self = wp_parse_url( home_url(), PHP_URL_HOST );
		$self = $self ? strtolower( preg_replace( '/^www\./', '', $self ) ) : '';
		if ( $host && $host === $self ) {
			return array( 'source' => 'direct', 'detail' => '' );
		}

		$map = self::host_map();
		foreach ( $map as $needle => $key ) {
			if ( false !== strpos( $host, $needle ) ) {
				return array( 'source' => $key, 'detail' => $host );
			}
		}

		// سایر سایت‌ها = ارجاعی.
		return array( 'source' => 'referral', 'detail' => $host );
	}

	/**
	 * نگاشت دامنه‌ها به منبع.
	 *
	 * @return array
	 */
	private static function host_map() {
		return array(
			'google.'      => 'google',
			'bing.'        => 'bing',
			'yahoo.'       => 'yahoo',
			'duckduckgo.'  => 'search',
			'yandex.'      => 'search',
			'instagram.'   => 'instagram',
			'l.instagram'  => 'instagram',
			'facebook.'    => 'facebook',
			'fb.'          => 'facebook',
			'lm.facebook'  => 'facebook',
			't.co'         => 'twitter',
			'twitter.'     => 'twitter',
			'x.com'        => 'twitter',
			't.me'         => 'telegram',
			'telegram.'    => 'telegram',
			'whatsapp'     => 'whatsapp',
			'youtube.'     => 'youtube',
			'youtu.be'     => 'youtube',
			'aparat.'      => 'aparat',
			'linkedin.'    => 'linkedin',
			'pinterest.'   => 'pinterest',
			'torob.'       => 'torob',
			'emalls.'      => 'emalls',
			'digikala.'    => 'digikala',
			'basalam.'     => 'basalam',
		);
	}

	/**
	 * تبدیل utm_source متن‌آزاد به کلید شناخته‌شده در صورت امکان.
	 *
	 * @param string $key کلید نرمال‌شده.
	 * @param string $raw مقدار خام.
	 * @return string
	 */
	private static function map_known( $key, $raw ) {
		$known = array( 'google', 'bing', 'yahoo', 'instagram', 'facebook', 'twitter', 'telegram', 'whatsapp', 'youtube', 'aparat', 'linkedin', 'pinterest', 'torob', 'emalls', 'digikala', 'basalam', 'email', 'sms' );
		if ( in_array( $key, $known, true ) ) {
			return $key;
		}
		// نگاشت مترادف‌ها.
		$aliases = array(
			'insta'    => 'instagram',
			'ig'       => 'instagram',
			'fb'       => 'facebook',
			'tg'       => 'telegram',
			'wa'       => 'whatsapp',
			'yt'       => 'youtube',
			'adwords'  => 'google',
			'googleads' => 'google',
		);
		if ( isset( $aliases[ $key ] ) ) {
			return $aliases[ $key ];
		}
		return 'campaign';
	}

	/**
	 * نرمال‌سازی کلید.
	 *
	 * @param string $val مقدار.
	 * @return string
	 */
	private static function normalize_key( $val ) {
		return sanitize_key( strtolower( trim( $val ) ) );
	}

	/**
	 * الصاق منبع به سفارش کلاسیک (چک‌اوت معمولی).
	 *
	 * @param WC_Order $order سفارش.
	 * @param array    $data  دادهٔ چک‌اوت.
	 */
	public static function attach_to_order( $order, $data = array() ) {
		self::write_meta( $order );
	}

	/**
	 * الصاق منبع به سفارش چک‌اوت بلوکی.
	 *
	 * @param WC_Order        $order   سفارش.
	 * @param WP_REST_Request $request درخواست.
	 */
	public static function attach_to_order_blocks( $order, $request = null ) {
		self::write_meta( $order );
	}

	/**
	 * نوشتن متای منبع روی سفارش (فقط اگر خالی باشد).
	 *
	 * @param WC_Order $order سفارش.
	 */
	private static function write_meta( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		if ( $order->get_meta( '_hsc_source' ) ) {
			return; // قبلاً ثبت شده.
		}

		$data = array( 'source' => 'direct', 'detail' => '' );
		if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			$decoded = json_decode( wp_unslash( $_COOKIE[ self::COOKIE ] ), true );
			if ( is_array( $decoded ) && ! empty( $decoded['source'] ) ) {
				$data['source'] = self::normalize_key( $decoded['source'] );
				$data['detail'] = isset( $decoded['detail'] ) ? sanitize_text_field( $decoded['detail'] ) : '';
			}
		} else {
			// اگر کوکی نبود، از referrer همان لحظه استفاده کن.
			$data = self::detect();
		}

		$order->update_meta_data( '_hsc_source', $data['source'] );
		if ( $data['detail'] ) {
			$order->update_meta_data( '_hsc_source_detail', $data['detail'] );
		}
	}

	/**
	 * برچسب فارسی و رنگ هر منبع برای نمایش.
	 *
	 * @param string $key کلید منبع.
	 * @return array{label:string,color:string,icon:string}
	 */
	public static function label( $key ) {
		$map = array(
			'google'    => array( 'گوگل', '#ea4335', '🔍' ),
			'bing'      => array( 'بینگ', '#0d7c6b', '🔍' ),
			'yahoo'     => array( 'یاهو', '#6001d2', '🔍' ),
			'search'    => array( 'موتور جستجو', '#4b5563', '🔍' ),
			'instagram' => array( 'اینستاگرام', '#e1306c', '📷' ),
			'facebook'  => array( 'فیسبوک', '#1877f2', '👍' ),
			'twitter'   => array( 'توییتر / X', '#111827', '𝕏' ),
			'telegram'  => array( 'تلگرام', '#229ED4', '✈️' ),
			'whatsapp'  => array( 'واتساپ', '#25d366', '💬' ),
			'youtube'   => array( 'یوتیوب', '#ff0000', '▶️' ),
			'aparat'    => array( 'آپارات', '#ed145b', '▶️' ),
			'linkedin'  => array( 'لینکدین', '#0a66c2', '💼' ),
			'pinterest' => array( 'پینترست', '#e60023', '📌' ),
			'torob'     => array( 'ترب', '#e4002b', '🛒' ),
			'emalls'    => array( 'ایمالز', '#00477d', '🛒' ),
			'digikala'  => array( 'دیجی‌کالا', '#ef4056', '🛒' ),
			'basalam'   => array( 'باسلام', '#ff6f00', '🛒' ),
			'email'     => array( 'ایمیل', '#0891b2', '✉️' ),
			'sms'       => array( 'پیامک', '#7c3aed', '💬' ),
			'campaign'  => array( 'کمپین', '#d97706', '🎯' ),
			'referral'  => array( 'ارجاعی', '#6366f1', '🔗' ),
			'direct'    => array( 'مستقیم', '#64748b', '⌨️' ),
		);
		$info = isset( $map[ $key ] ) ? $map[ $key ] : array( $key, '#64748b', '🔗' );
		return array( 'label' => $info[0], 'color' => str_replace( ' ', '', $info[1] ), 'icon' => $info[2] );
	}
}
