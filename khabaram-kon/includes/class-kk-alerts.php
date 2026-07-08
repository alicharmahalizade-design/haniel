<?php
/**
 * اعلان به مدیر هنگام تقاضای بالا برای محصولات ناموجود.
 * کانال‌ها: تلگرام، پیامک، ایمیل.
 *
 * @package KhabaramKon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KK_Alerts {

	const META_KEY = '_kk_demand_alerted';

	/**
	 * راه‌اندازی هوک‌ها.
	 */
	public static function init() {
		add_action( 'kk_subscription_created', array( __CLASS__, 'on_new_subscription' ), 20, 3 );
	}

	/**
	 * بررسی آستانه تقاضا هنگام ثبت درخواست جدید.
	 *
	 * @param int    $product_id   شناسه محصول.
	 * @param int    $variation_id شناسه متغیر.
	 * @param string $phone        شماره.
	 */
	public static function on_new_subscription( $product_id, $variation_id, $phone ) {
		if ( ! KK_Settings::is( 'demand_alert_enabled' ) ) {
			return;
		}

		$threshold = max( 1, (int) KK_Settings::get( 'demand_threshold', 10 ) );

		global $wpdb;
		$table = $wpdb->prefix . KK_TABLE;

		// تعداد افراد یکتای در انتظار برای این محصول.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT phone) FROM {$table} WHERE product_id = %d AND status = 'pending'",
				$product_id
			)
		); // phpcs:ignore WordPress.DB

		if ( $count < $threshold ) {
			return;
		}

		// اعلان پلکانی: در هر ضریب از آستانه فقط یک‌بار (۱۰، ۲۰، ۳۰ ...).
		$current_multiple = intdiv( $count, $threshold );
		$last_multiple    = (int) get_post_meta( $product_id, self::META_KEY, true );

		if ( $current_multiple <= $last_multiple ) {
			return;
		}

		update_post_meta( $product_id, self::META_KEY, $current_multiple );
		self::dispatch( $product_id, $count );
	}

	/**
	 * ریست شمارنده اعلان هنگام موجود شدن محصول.
	 *
	 * @param int $product_id شناسه محصول.
	 */
	public static function reset( $product_id ) {
		delete_post_meta( $product_id, self::META_KEY );
	}

	/**
	 * ارسال اعلان به کانال‌های فعال.
	 *
	 * @param int $product_id شناسه محصول.
	 * @param int $count      تعداد درخواست.
	 */
	public static function dispatch( $product_id, $count ) {
		$product = wc_get_product( $product_id );
		$name    = $product ? $product->get_name() : '#' . $product_id;
		$edit    = admin_url( 'post.php?post=' . (int) $product_id . '&action=edit' );

		$text = sprintf(
			/* translators: 1: product name, 2: count, 3: edit link */
			__( "📈 تقاضای بالا برای محصول ناموجود:\n%1\$s\nتعداد درخواست «خبرم کن»: %2\$s نفر\nپیشنهاد: این محصول را زودتر شارژ کنید.\n%3\$s", 'khabaram-kon' ),
			$name,
			number_format_i18n( $count ),
			$edit
		);

		self::send_all_channels( $text, $name );

		/**
		 * اکشن پس از ارسال اعلان تقاضای بالا.
		 *
		 * @param int    $product_id شناسه محصول.
		 * @param int    $count      تعداد درخواست.
		 * @param string $text       متن اعلان.
		 */
		do_action( 'kk_demand_alert_sent', $product_id, $count, $text );
	}

	/**
	 * ارسال متن به همه کانال‌های پیکربندی‌شده.
	 *
	 * @param string $text    متن.
	 * @param string $subject موضوع ایمیل.
	 * @return array نتیجه هر کانال.
	 */
	public static function send_all_channels( $text, $subject = '' ) {
		$results = array();

		// ایمیل.
		$email = KK_Settings::get( 'demand_alert_email' );
		if ( ! is_email( $email ) ) {
			$email = KK_Settings::get( 'notify_admin_email' );
		}
		if ( ! is_email( $email ) ) {
			$email = get_option( 'admin_email' );
		}
		if ( is_email( $email ) ) {
			$subject          = $subject ? $subject : __( 'اعلان خبرم کن', 'khabaram-kon' );
			$results['email'] = wp_mail( $email, sprintf( /* translators: %s: product */ __( 'تقاضای بالا: %s', 'khabaram-kon' ), $subject ), $text );
		}

		// پیامک به مدیر (متن ساده، بدون پترن).
		$admin_phone = KK_SMS::normalize_phone( KK_Settings::get( 'demand_alert_sms_phone' ) );
		if ( '' !== $admin_phone ) {
			$results['sms'] = KK_SMS::send_plain( $admin_phone, $text );
		}

		// تلگرام.
		$results['telegram'] = self::send_telegram( $text );

		return $results;
	}

	/**
	 * ارسال پیام به تلگرام از طریق بات.
	 *
	 * @param string $text متن.
	 * @return true|WP_Error|null null اگر پیکربندی نشده باشد.
	 */
	public static function send_telegram( $text ) {
		$token = trim( KK_Settings::get( 'telegram_bot_token' ) );
		$chat  = trim( KK_Settings::get( 'telegram_chat_id' ) );
		if ( empty( $token ) || empty( $chat ) ) {
			return null;
		}

		$url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
		$res = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => array(
					'chat_id'                  => $chat,
					'text'                     => $text,
					'disable_web_page_preview' => true,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( isset( $data['ok'] ) && $data['ok'] ) {
			return true;
		}
		$desc = isset( $data['description'] ) ? $data['description'] : __( 'ارسال تلگرام ناموفق بود.', 'khabaram-kon' );
		return new WP_Error( 'kk_telegram_failed', $desc );
	}
}
