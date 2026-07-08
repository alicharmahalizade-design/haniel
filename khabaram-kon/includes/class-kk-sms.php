<?php
/**
 * لایه ارسال پیامک با پشتیبانی از چند درگاه ایرانی.
 *
 * @package KhabaramKon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KK_SMS {

	/**
	 * پرچم اجبار به ارسال متن ساده (نادیده گرفتن پترن) برای اعلان‌های مدیر.
	 *
	 * @var bool
	 */
	private static $force_plain = false;

	/**
	 * درگاه‌های پشتیبانی‌شده.
	 *
	 * @return array
	 */
	public static function gateways() {
		return array(
			'farazsms'    => array( 'label' => 'فراز اس‌ام‌اس / ایران‌پیامک (IPPanel)' ),
			'kavenegar'   => array( 'label' => 'کاوه‌نگار (Kavenegar)' ),
			'melipayamak' => array( 'label' => 'ملی پیامک (Melipayamak)' ),
			'smsir'       => array( 'label' => 'اس‌ام‌اس‌آی‌آر (SMS.ir)' ),
			'ghasedak'    => array( 'label' => 'قاصدک (Ghasedak)' ),
			'generic'     => array( 'label' => 'درگاه دلخواه (Webhook/HTTP GET)' ),
		);
	}

	/**
	 * ارسال پیامک.
	 *
	 * @param string $phone   شماره گیرنده.
	 * @param string $message متن پیام.
	 * @param array  $params  متغیرهای پترن (product, shortlink...).
	 * @return true|WP_Error
	 */
	public static function send( $phone, $message, $params = array() ) {
		$phone = self::normalize_phone( $phone );
		if ( '' === $phone ) {
			return new WP_Error( 'kk_invalid_phone', __( 'شماره موبایل نامعتبر است.', 'khabaram-kon' ) );
		}

		$gateway = KK_Settings::get( 'sms_gateway', 'kavenegar' );

		switch ( $gateway ) {
			case 'farazsms':
				return self::send_farazsms( $phone, $message, $params );
			case 'kavenegar':
				return self::send_kavenegar( $phone, $message, $params );
			case 'melipayamak':
				return self::send_melipayamak( $phone, $message, $params );
			case 'smsir':
				return self::send_smsir( $phone, $message, $params );
			case 'ghasedak':
				return self::send_ghasedak( $phone, $message, $params );
			case 'generic':
				return self::send_generic( $phone, $message, $params );
			default:
				return new WP_Error( 'kk_no_gateway', __( 'درگاه پیامک نامعتبر است.', 'khabaram-kon' ) );
		}
	}

	/**
	 * ارسال متن ساده بدون پترن (برای اعلان‌های مدیر).
	 *
	 * @param string $phone   شماره.
	 * @param string $message متن.
	 * @return true|WP_Error
	 */
	public static function send_plain( $phone, $message ) {
		self::$force_plain = true;
		$result            = self::send( $phone, $message );
		self::$force_plain = false;
		return $result;
	}

	/**
	 * آیا پترن باید اعمال شود؟
	 *
	 * @param string $pattern کد پترن.
	 * @return bool
	 */
	private static function use_pattern( $pattern ) {
		return ! empty( $pattern ) && ! self::$force_plain;
	}

	/**
	 * تبدیل ارقام فارسی/عربی به لاتین و حذف فاصله‌های اضافی.
	 * برای فیلدهایی مثل کلید API و شماره فرستنده که باید ASCII باشند.
	 *
	 * @param string $str ورودی.
	 * @return string
	 */
	public static function en_digits( $str ) {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		return trim( str_replace( $fa, $en, (string) $str ) );
	}

	/**
	 * ساخت پیام خطای دقیق شامل کد HTTP و بخشی از پاسخ خام درگاه.
	 *
	 * @param array $res پاسخ wp_remote_*.
	 * @return WP_Error
	 */
	private static function response_error( $res ) {
		$code = wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );
		$data = json_decode( $body, true );

		$msg = self::extract_error( $data );
		// اگر پیام قابل‌فهمی پیدا نشد، بخشی از پاسخ خام را نشان بده.
		if ( __( 'ارسال پیامک ناموفق بود. پاسخ درگاه نامشخص است.', 'khabaram-kon' ) === $msg ) {
			$snippet = trim( wp_strip_all_tags( (string) $body ) );
			if ( function_exists( 'mb_substr' ) && mb_strlen( $snippet ) > 220 ) {
				$snippet = mb_substr( $snippet, 0, 220 ) . '…';
			}
			$msg = '' !== $snippet ? $snippet : $msg;
		}

		return new WP_Error(
			'kk_sms_failed',
			sprintf(
				/* translators: 1: HTTP status code, 2: gateway message */
				__( 'خطای درگاه (کد HTTP %1$s): %2$s', 'khabaram-kon' ),
				$code ? $code : '—',
				$msg
			)
		);
	}

	/**
	 * فراز اس‌ام‌اس / ایران‌پیامک (IPPanel REST v1).
	 *
	 * @param string $phone   شماره.
	 * @param string $message متن.
	 * @param array  $params  متغیرها.
	 * @return true|WP_Error
	 */
	private static function send_farazsms( $phone, $message, $params ) {
		$api     = self::en_digits( KK_Settings::get( 'sms_api_key' ) );
		$sender  = self::en_digits( KK_Settings::get( 'sms_sender' ) );
		$pattern = self::en_digits( KK_Settings::get( 'sms_pattern' ) );

		if ( empty( $api ) ) {
			return new WP_Error( 'kk_no_api', __( 'کلید API (apikey) فراز اس‌ام‌اس/ایران‌پیامک تنظیم نشده است.', 'khabaram-kon' ) );
		}
		if ( empty( $sender ) ) {
			return new WP_Error( 'kk_no_sender', __( 'شماره خط فرستنده فراز اس‌ام‌اس تنظیم نشده است.', 'khabaram-kon' ) );
		}

		$base = 'https://api2.ippanel.com/api/v1';

		if ( self::use_pattern( $pattern ) ) {
			// نام متغیرهای پترن (پیش‌فرض product و link).
			$var_product = KK_Settings::get( 'pattern_var_product', 'product' );
			$var_link    = KK_Settings::get( 'pattern_var_link', 'link' );

			$url  = $base . '/sms/pattern/normal/send';
			$body = wp_json_encode(
				array(
					'code'      => $pattern,
					'sender'    => $sender,
					'recipient' => $phone,
					'variable'  => array(
						$var_product => isset( $params['product'] ) ? self::pattern_safe( $params['product'] ) : '',
						$var_link    => isset( $params['shortlink'] ) ? self::pattern_safe( $params['shortlink'] ) : '',
					),
				)
			);
		} else {
			$url  = $base . '/sms/send/webservice/single';
			$body = wp_json_encode(
				array(
					'sender'    => $sender,
					'recipient' => array( $phone ),
					'message'   => $message,
				)
			);
		}

		$res = wp_remote_post(
			$url,
			array(
				'timeout' => 25,
				'headers' => array(
					'apikey'       => $api,
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = wp_remote_retrieve_response_code( $res );
		$data = json_decode( wp_remote_retrieve_body( $res ), true );

		if ( $code >= 200 && $code < 300 ) {
			// برخی پاسخ‌ها فیلد status متنی دارند؛ اگر خطا بود گزارش کن.
			if ( isset( $data['status'] ) && is_string( $data['status'] ) && 'OK' !== strtoupper( $data['status'] ) ) {
				return self::response_error( $res );
			}
			return true;
		}
		return self::response_error( $res );
	}

	/**
	 * کاوه‌نگار.
	 *
	 * @param string $phone   شماره.
	 * @param string $message متن.
	 * @param array  $params  متغیرها.
	 * @return true|WP_Error
	 */
	private static function send_kavenegar( $phone, $message, $params ) {
		$api     = KK_Settings::get( 'sms_api_key' );
		$sender  = KK_Settings::get( 'sms_sender' );
		$pattern = KK_Settings::get( 'sms_pattern' );

		if ( empty( $api ) ) {
			return new WP_Error( 'kk_no_api', __( 'کلید API کاوه‌نگار تنظیم نشده است.', 'khabaram-kon' ) );
		}

		if ( self::use_pattern( $pattern ) ) {
			// ارسال پترن‌دار (verify/lookup).
			$url  = "https://api.kavenegar.com/v1/{$api}/verify/lookup.json";
			$body = array(
				'receptor' => $phone,
				'template' => $pattern,
				'token'    => isset( $params['product'] ) ? self::pattern_safe( $params['product'] ) : '',
				'token2'   => isset( $params['shortlink'] ) ? self::pattern_safe( $params['shortlink'] ) : '',
			);
		} else {
			$url  = "https://api.kavenegar.com/v1/{$api}/sms/send.json";
			$body = array(
				'receptor' => $phone,
				'message'  => $message,
			);
			if ( ! empty( $sender ) ) {
				$body['sender'] = $sender;
			}
		}

		$res = wp_remote_post( $url, array( 'timeout' => 20, 'body' => $body ) );
		return self::handle_json_response( $res, 'return.status', 200 );
	}

	/**
	 * ملی پیامک (REST).
	 *
	 * @param string $phone   شماره.
	 * @param string $message متن.
	 * @param array  $params  متغیرها.
	 * @return true|WP_Error
	 */
	private static function send_melipayamak( $phone, $message, $params ) {
		$user   = KK_Settings::get( 'sms_username' );
		$pass   = KK_Settings::get( 'sms_password' );
		$sender = KK_Settings::get( 'sms_sender' );

		if ( empty( $user ) || empty( $pass ) ) {
			return new WP_Error( 'kk_no_creds', __( 'نام کاربری یا رمز ملی پیامک تنظیم نشده است.', 'khabaram-kon' ) );
		}

		$url  = 'https://rest.payamak-panel.com/api/SendSMS/SendSMS';
		$body = array(
			'username' => $user,
			'password' => $pass,
			'to'       => $phone,
			'from'     => $sender,
			'text'     => $message,
		);

		$res = wp_remote_post( $url, array( 'timeout' => 20, 'body' => $body ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		// RetStatus == 1 یعنی موفق.
		if ( isset( $data['RetStatus'] ) && 1 === (int) $data['RetStatus'] ) {
			return true;
		}
		return new WP_Error( 'kk_sms_failed', self::extract_error( $data ) );
	}

	/**
	 * SMS.ir (نسخه v1).
	 *
	 * @param string $phone   شماره.
	 * @param string $message متن.
	 * @param array  $params  متغیرها.
	 * @return true|WP_Error
	 */
	private static function send_smsir( $phone, $message, $params ) {
		$api     = KK_Settings::get( 'sms_api_key' );
		$sender  = KK_Settings::get( 'sms_sender' );
		$pattern = KK_Settings::get( 'sms_pattern' );

		if ( empty( $api ) ) {
			return new WP_Error( 'kk_no_api', __( 'کلید API سرویس SMS.ir تنظیم نشده است.', 'khabaram-kon' ) );
		}

		if ( self::use_pattern( $pattern ) ) {
			$url  = 'https://api.sms.ir/v1/send/verify';
			$body = wp_json_encode(
				array(
					'mobile'     => $phone,
					'templateId' => (int) $pattern,
					'parameters' => array(
						array( 'name' => 'PRODUCT', 'value' => isset( $params['product'] ) ? self::pattern_safe( $params['product'] ) : '' ),
						array( 'name' => 'LINK', 'value' => isset( $params['shortlink'] ) ? self::pattern_safe( $params['shortlink'] ) : '' ),
					),
				)
			);
		} else {
			$url  = 'https://api.sms.ir/v1/send/bulk';
			$body = wp_json_encode(
				array(
					'lineNumber'  => $sender,
					'messageText' => $message,
					'mobiles'     => array( $phone ),
				)
			);
		}

		$res = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'x-api-key'    => $api,
				),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( isset( $data['status'] ) && 1 === (int) $data['status'] ) {
			return true;
		}
		return new WP_Error( 'kk_sms_failed', self::extract_error( $data ) );
	}

	/**
	 * قاصدک.
	 *
	 * @param string $phone   شماره.
	 * @param string $message متن.
	 * @param array  $params  متغیرها.
	 * @return true|WP_Error
	 */
	private static function send_ghasedak( $phone, $message, $params ) {
		$api    = KK_Settings::get( 'sms_api_key' );
		$sender = KK_Settings::get( 'sms_sender' );

		if ( empty( $api ) ) {
			return new WP_Error( 'kk_no_api', __( 'کلید API قاصدک تنظیم نشده است.', 'khabaram-kon' ) );
		}

		$url = 'https://api.ghasedak.me/v2/sms/send/simple';
		$res = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array( 'apikey' => $api ),
				'body'    => array(
					'message'  => $message,
					'receptor' => $phone,
					'linenumber' => $sender,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( isset( $data['result']['code'] ) && 200 === (int) $data['result']['code'] ) {
			return true;
		}
		return new WP_Error( 'kk_sms_failed', self::extract_error( $data ) );
	}

	/**
	 * درگاه دلخواه با HTTP GET و جایگذاری متغیرها در URL.
	 * از فیلد «کلید API» به‌عنوان قالب URL استفاده می‌شود؛ مثال:
	 * https://example.com/send?to={phone}&text={text}
	 *
	 * @param string $phone   شماره.
	 * @param string $message متن.
	 * @param array  $params  متغیرها.
	 * @return true|WP_Error
	 */
	private static function send_generic( $phone, $message, $params ) {
		$template = KK_Settings::get( 'sms_api_key' );
		if ( empty( $template ) ) {
			return new WP_Error( 'kk_no_url', __( 'قالب URL درگاه دلخواه در فیلد «کلید API» وارد نشده است.', 'khabaram-kon' ) );
		}

		$replace = array(
			'{phone}'  => rawurlencode( $phone ),
			'{text}'   => rawurlencode( $message ),
			'{sender}' => rawurlencode( KK_Settings::get( 'sms_sender' ) ),
			'{user}'   => rawurlencode( KK_Settings::get( 'sms_username' ) ),
			'{pass}'   => rawurlencode( KK_Settings::get( 'sms_password' ) ),
		);
		$url = strtr( $template, $replace );

		$res = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}
		return new WP_Error( 'kk_sms_failed', sprintf( /* translators: %d: http code */ __( 'خطای درگاه دلخواه (کد %d).', 'khabaram-kon' ), $code ) );
	}

	/**
	 * بررسی پاسخ JSON با کلید تودرتو.
	 *
	 * @param array|WP_Error $res      پاسخ.
	 * @param string         $path     مسیر کلید مثل return.status.
	 * @param int            $expected مقدار موردانتظار.
	 * @return true|WP_Error
	 */
	private static function handle_json_response( $res, $path, $expected ) {
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data  = json_decode( wp_remote_retrieve_body( $res ), true );
		$value = $data;
		foreach ( explode( '.', $path ) as $seg ) {
			$value = is_array( $value ) && isset( $value[ $seg ] ) ? $value[ $seg ] : null;
		}
		if ( (int) $value === (int) $expected ) {
			return true;
		}
		return new WP_Error( 'kk_sms_failed', self::extract_error( $data ) );
	}

	/**
	 * استخراج پیام خطا از پاسخ.
	 *
	 * @param mixed $data داده.
	 * @return string
	 */
	private static function extract_error( $data ) {
		if ( is_array( $data ) ) {
			// خطای IPPanel/فراز اس‌ام‌اس معمولاً در meta.message یا error_message است.
			if ( isset( $data['meta']['message'] ) && ! empty( $data['meta']['message'] ) ) {
				return (string) $data['meta']['message'];
			}
			foreach ( array( 'message', 'Message', 'error_message', 'errorMessage', 'error', 'detail', 'StrRetStatus', 'return' ) as $k ) {
				if ( ! empty( $data[ $k ] ) ) {
					return is_array( $data[ $k ] ) && isset( $data[ $k ]['message'] ) ? (string) $data[ $k ]['message'] : (string) ( is_scalar( $data[ $k ] ) ? $data[ $k ] : wp_json_encode( $data[ $k ] ) );
				}
			}
		}
		return __( 'ارسال پیامک ناموفق بود. پاسخ درگاه نامشخص است.', 'khabaram-kon' );
	}

	/**
	 * حذف کاراکترهای مشکل‌ساز برای پترن‌ها (بعضی درگاه‌ها فاصله/خط جدید را رد می‌کنند).
	 *
	 * @param string $val مقدار.
	 * @return string
	 */
	private static function pattern_safe( $val ) {
		return str_replace( array( "\n", "\r" ), ' ', trim( $val ) );
	}

	/**
	 * دریافت اعتبار پنل پیامک (و تست اتصال).
	 *
	 * @return array|WP_Error آرایه شامل credit و unit یا خطا.
	 */
	public static function get_credit() {
		$gateway = KK_Settings::get( 'sms_gateway', 'farazsms' );
		switch ( $gateway ) {
			case 'farazsms':
				return self::credit_farazsms();
			case 'kavenegar':
				return self::credit_kavenegar();
			case 'smsir':
				return self::credit_smsir();
			case 'melipayamak':
				return self::credit_melipayamak();
			default:
				return new WP_Error( 'kk_not_supported', __( 'نمایش اعتبار برای این درگاه پشتیبانی نمی‌شود.', 'khabaram-kon' ) );
		}
	}

	/**
	 * اعتبار فراز اس‌ام‌اس / ایران‌پیامک.
	 *
	 * @return array|WP_Error
	 */
	private static function credit_farazsms() {
		$api = self::en_digits( KK_Settings::get( 'sms_api_key' ) );
		if ( empty( $api ) ) {
			return new WP_Error( 'kk_no_api', __( 'کلید API (apikey) تنظیم نشده است.', 'khabaram-kon' ) );
		}
		$res = wp_remote_get(
			'https://api2.ippanel.com/api/v1/sms/accounting/credit/show',
			array(
				'timeout' => 20,
				'headers' => array(
					'apikey' => $api,
					'Accept' => 'application/json',
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );

		$credit = null;
		if ( isset( $data['data']['credit'] ) && is_numeric( $data['data']['credit'] ) ) {
			$credit = $data['data']['credit'];
		} elseif ( isset( $data['data'] ) && is_numeric( $data['data'] ) ) {
			$credit = $data['data'];
		}
		if ( null === $credit ) {
			return self::response_error( $res );
		}
		return array( 'credit' => (float) $credit, 'unit' => __( 'ریال', 'khabaram-kon' ) );
	}

	/**
	 * اعتبار کاوه‌نگار.
	 *
	 * @return array|WP_Error
	 */
	private static function credit_kavenegar() {
		$api = KK_Settings::get( 'sms_api_key' );
		if ( empty( $api ) ) {
			return new WP_Error( 'kk_no_api', __( 'کلید API تنظیم نشده است.', 'khabaram-kon' ) );
		}
		$res = wp_remote_get( "https://api.kavenegar.com/v1/{$api}/account/info.json", array( 'timeout' => 20 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( isset( $data['entries']['remaincredit'] ) ) {
			return array( 'credit' => (float) $data['entries']['remaincredit'], 'unit' => __( 'ریال', 'khabaram-kon' ) );
		}
		return new WP_Error( 'kk_credit_failed', self::extract_error( $data ) );
	}

	/**
	 * اعتبار SMS.ir.
	 *
	 * @return array|WP_Error
	 */
	private static function credit_smsir() {
		$api = KK_Settings::get( 'sms_api_key' );
		if ( empty( $api ) ) {
			return new WP_Error( 'kk_no_api', __( 'کلید API تنظیم نشده است.', 'khabaram-kon' ) );
		}
		$res = wp_remote_get(
			'https://api.sms.ir/v1/credit',
			array(
				'timeout' => 20,
				'headers' => array(
					'x-api-key' => $api,
					'Accept'    => 'application/json',
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( isset( $data['data'] ) && is_numeric( $data['data'] ) ) {
			return array( 'credit' => (float) $data['data'], 'unit' => __( 'پیامک', 'khabaram-kon' ) );
		}
		return new WP_Error( 'kk_credit_failed', self::extract_error( $data ) );
	}

	/**
	 * اعتبار ملی پیامک.
	 *
	 * @return array|WP_Error
	 */
	private static function credit_melipayamak() {
		$user = KK_Settings::get( 'sms_username' );
		$pass = KK_Settings::get( 'sms_password' );
		if ( empty( $user ) || empty( $pass ) ) {
			return new WP_Error( 'kk_no_creds', __( 'نام کاربری یا رمز تنظیم نشده است.', 'khabaram-kon' ) );
		}
		$res = wp_remote_post(
			'https://rest.payamak-panel.com/api/SmsCredit/GetCredit',
			array(
				'timeout' => 20,
				'body'    => array(
					'username' => $user,
					'password' => $pass,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( isset( $data['Value'] ) && is_numeric( $data['Value'] ) && ( ! isset( $data['RetStatus'] ) || 1 === (int) $data['RetStatus'] ) ) {
			return array( 'credit' => (float) $data['Value'], 'unit' => __( 'پیامک', 'khabaram-kon' ) );
		}
		return new WP_Error( 'kk_credit_failed', self::extract_error( $data ) );
	}

	/**
	 * نرمال‌سازی شماره موبایل ایران به فرمت 09xxxxxxxxx.
	 *
	 * @param string $phone شماره خام.
	 * @return string شماره معتبر یا رشته خالی.
	 */
	public static function normalize_phone( $phone ) {
		// تبدیل ارقام فارسی/عربی به لاتین.
		$fa    = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en    = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$phone = str_replace( $fa, $en, (string) $phone );
		$phone = preg_replace( '/[^0-9+]/', '', $phone );

		if ( 0 === strpos( $phone, '+98' ) ) {
			$phone = '0' . substr( $phone, 3 );
		} elseif ( 0 === strpos( $phone, '0098' ) ) {
			$phone = '0' . substr( $phone, 4 );
		} elseif ( 0 === strpos( $phone, '98' ) && 12 === strlen( $phone ) ) {
			$phone = '0' . substr( $phone, 2 );
		} elseif ( 10 === strlen( $phone ) && 0 === strpos( $phone, '9' ) ) {
			$phone = '0' . $phone;
		}

		if ( preg_match( '/^09\d{9}$/', $phone ) ) {
			return $phone;
		}
		return '';
	}
}
