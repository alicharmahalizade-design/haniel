<?php
/**
 * پردازش درخواست‌های AJAX.
 *
 * @package KhabaramKon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KK_Ajax {

	/**
	 * راه‌اندازی هوک‌ها.
	 */
	public static function init() {
		add_action( 'wp_ajax_kk_subscribe', array( __CLASS__, 'subscribe' ) );
		add_action( 'wp_ajax_nopriv_kk_subscribe', array( __CLASS__, 'subscribe' ) );
		add_action( 'wp_ajax_kk_test_sms', array( __CLASS__, 'test_sms' ) );
	}

	/**
	 * ثبت درخواست اطلاع‌رسانی.
	 */
	public static function subscribe() {
		check_ajax_referer( 'kk_subscribe', 'nonce' );

		if ( ! KK_Settings::is( 'enabled' ) ) {
			wp_send_json_error( array( 'message' => __( 'این قابلیت غیرفعال است.', 'khabaram-kon' ) ) );
		}

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$phone_raw    = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$phone        = KK_SMS::normalize_phone( $phone_raw );

		if ( ! $product_id || ! wc_get_product( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'محصول نامعتبر است.', 'khabaram-kon' ) ) );
		}
		if ( '' === $phone ) {
			wp_send_json_error( array( 'message' => __( 'شماره موبایل معتبر نیست. مثال: 09123456789', 'khabaram-kon' ) ) );
		}

		// جلوگیری از اسپم ساده: حداکثر چند ثبت در دقیقه از یک IP.
		if ( self::is_rate_limited() ) {
			wp_send_json_error( array( 'message' => __( 'تعداد درخواست‌ها زیاد است. کمی بعد دوباره تلاش کنید.', 'khabaram-kon' ) ) );
		}

		global $wpdb;
		$table   = $wpdb->prefix . KK_TABLE;
		$user_id = get_current_user_id();

		// جلوگیری از درخواست تکراری در انتظار.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE product_id = %d AND variation_id = %d AND phone = %s AND status = 'pending' LIMIT 1",
				$product_id,
				$variation_id,
				$phone
			)
		); // phpcs:ignore WordPress.DB

		if ( $exists ) {
			wp_send_json_success(
				array(
					'message'   => KK_Settings::get( 'already_message' ),
					'duplicate' => true,
				)
			);
		}

		$token = self::generate_token();

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'user_id'      => $user_id,
				'phone'        => $phone,
				'status'       => 'pending',
				'token'        => $token,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			wp_send_json_error( array( 'message' => __( 'خطا در ذخیره درخواست. دوباره تلاش کنید.', 'khabaram-kon' ) ) );
		}

		// ذخیره شماره برای کاربر لاگین‌کرده.
		if ( $user_id && KK_Settings::is( 'store_user_phone' ) ) {
			update_user_meta( $user_id, 'kk_phone', $phone );
		}

		// اطلاع به مدیر.
		self::maybe_notify_admin( $product_id, $variation_id, $phone );

		/**
		 * اکشن پس از ثبت درخواست جدید.
		 *
		 * @param int    $product_id   شناسه محصول.
		 * @param int    $variation_id شناسه متغیر.
		 * @param string $phone        شماره.
		 */
		do_action( 'kk_subscription_created', $product_id, $variation_id, $phone );

		wp_send_json_success( array( 'message' => KK_Settings::get( 'success_message' ) ) );
	}

	/**
	 * ارسال پیامک آزمایشی از پنل مدیریت.
	 */
	public static function test_sms() {
		check_ajax_referer( 'kk_subscribe', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'khabaram-kon' ) ) );
		}

		$phone = isset( $_POST['phone'] ) ? KK_SMS::normalize_phone( sanitize_text_field( wp_unslash( $_POST['phone'] ) ) ) : '';
		if ( '' === $phone ) {
			wp_send_json_error( array( 'message' => __( 'شماره تست معتبر نیست.', 'khabaram-kon' ) ) );
		}

		$message = KK_Notifier::build_message(
			__( 'محصول آزمایشی', 'khabaram-kon' ),
			home_url( '/' ),
			home_url( '/' )
		);

		$result = KK_SMS::send(
			$phone,
			$message,
			array(
				'product'   => __( 'محصول آزمایشی', 'khabaram-kon' ),
				'shortlink' => home_url( '/' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'پیامک آزمایشی ارسال شد ✅', 'khabaram-kon' ) ) );
	}

	/**
	 * تولید توکن یکتا برای لینک کوتاه.
	 *
	 * @return string
	 */
	private static function generate_token() {
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
		$token = '';
		for ( $i = 0; $i < 6; $i++ ) {
			$token .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
		}
		global $wpdb;
		$table = $wpdb->prefix . KK_TABLE;
		// تضمین یکتایی.
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE token = %s", $token ) ); // phpcs:ignore WordPress.DB
		if ( $exists ) {
			return self::generate_token();
		}
		return $token;
	}

	/**
	 * محدودسازی نرخ درخواست بر اساس IP.
	 *
	 * @return bool
	 */
	private static function is_rate_limited() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
		$key = 'kk_rl_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 10 ) {
			return true;
		}
		set_transient( $key, $n + 1, MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * اطلاع ایمیلی به مدیر در صورت تنظیم.
	 *
	 * @param int    $product_id   شناسه محصول.
	 * @param int    $variation_id شناسه متغیر.
	 * @param string $phone        شماره.
	 */
	private static function maybe_notify_admin( $product_id, $variation_id, $phone ) {
		$email = KK_Settings::get( 'notify_admin_email' );
		if ( empty( $email ) || ! is_email( $email ) ) {
			return;
		}
		$product = wc_get_product( $variation_id ? $variation_id : $product_id );
		$name    = $product ? $product->get_name() : '#' . $product_id;
		/* translators: 1: product name, 2: phone */
		$body = sprintf( __( 'درخواست جدید «خبرم کن»:%1$sمحصول: %2$s%1$sشماره: %3$s', 'khabaram-kon' ), "\n", $name, $phone );
		wp_mail( $email, __( 'درخواست جدید خبرم کن', 'khabaram-kon' ), $body );
	}
}
