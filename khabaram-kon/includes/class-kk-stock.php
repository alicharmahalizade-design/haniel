<?php
/**
 * تشخیص موجود شدن محصول و صف اطلاع‌رسانی پیامکی.
 *
 * @package KhabaramKon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KK_Stock {

	/**
	 * راه‌اندازی هوک‌ها.
	 */
	public static function init() {
		// تغییر وضعیت موجودی محصول ساده/والد.
		add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'on_stock_status' ), 10, 3 );
		// تغییر وضعیت موجودی متغیر.
		add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'on_variation_stock_status' ), 10, 3 );
		// پشتیبان: هنگام ذخیره محصول.
		add_action( 'woocommerce_update_product', array( __CLASS__, 'on_update_product' ), 20 );

		// پردازش صف در پس‌زمینه.
		add_action( 'kk_process_notifications', array( __CLASS__, 'process_queue' ) );
	}

	/**
	 * وقتی وضعیت موجودی محصول تغییر می‌کند.
	 *
	 * @param int    $product_id شناسه محصول.
	 * @param string $status     وضعیت جدید.
	 * @param object $product    محصول.
	 */
	public static function on_stock_status( $product_id, $status, $product = null ) {
		if ( 'instock' !== $status && 'onbackorder' !== $status ) {
			return;
		}
		self::queue_for_product( (int) $product_id, 0 );
	}

	/**
	 * وقتی وضعیت موجودی متغیر تغییر می‌کند.
	 *
	 * @param int    $variation_id شناسه متغیر.
	 * @param string $status       وضعیت جدید.
	 * @param object $variation    متغیر.
	 */
	public static function on_variation_stock_status( $variation_id, $status, $variation = null ) {
		if ( 'instock' !== $status && 'onbackorder' !== $status ) {
			return;
		}
		$parent_id = wp_get_post_parent_id( $variation_id );
		self::queue_for_product( (int) $parent_id, (int) $variation_id );
	}

	/**
	 * پشتیبان هنگام آپدیت محصول: بررسی درخواست‌های در انتظار و موجود بودن.
	 *
	 * @param int $product_id شناسه محصول.
	 */
	public static function on_update_product( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . KK_TABLE;
		$pending = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT variation_id FROM {$table} WHERE product_id = %d AND status = 'pending'", $product_id ) ); // phpcs:ignore WordPress.DB
		if ( ! $pending ) {
			return;
		}

		foreach ( $pending as $p ) {
			$vid = (int) $p->variation_id;
			if ( $vid ) {
				$variation = wc_get_product( $vid );
				if ( $variation && $variation->is_in_stock() ) {
					self::queue_for_product( $product_id, $vid );
				}
			} elseif ( $product->is_in_stock() ) {
				self::queue_for_product( $product_id, 0 );
			}
		}
	}

	/**
	 * علامت‌گذاری درخواست‌ها برای ارسال و زمان‌بندی پردازش.
	 *
	 * @param int $product_id   شناسه محصول.
	 * @param int $variation_id شناسه متغیر (۰ = ساده/همه).
	 */
	public static function queue_for_product( $product_id, $variation_id ) {
		if ( ! $product_id ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . KK_TABLE;

		// شمارش درخواست‌های در انتظار.
		if ( $variation_id ) {
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND variation_id = %d AND status = 'pending'", $product_id, $variation_id ) ); // phpcs:ignore WordPress.DB
		} else {
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND variation_id = 0 AND status = 'pending'", $product_id ) ); // phpcs:ignore WordPress.DB
		}

		if ( $count < 1 ) {
			return;
		}

		// علامت‌گذاری به «queued» تا از ارسال دوباره جلوگیری شود.
		if ( $variation_id ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'queued' WHERE product_id = %d AND variation_id = %d AND status = 'pending'", $product_id, $variation_id ) ); // phpcs:ignore WordPress.DB
		} else {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'queued' WHERE product_id = %d AND variation_id = 0 AND status = 'pending'", $product_id ) ); // phpcs:ignore WordPress.DB
		}

		// زمان‌بندی پردازش فوری در پس‌زمینه.
		if ( ! wp_next_scheduled( 'kk_process_notifications' ) ) {
			wp_schedule_single_event( time() + 5, 'kk_process_notifications' );
		}
		// در صورت در دسترس بودن، فراخوانی غیرمسدودکننده cron.
		spawn_cron();
	}

	/**
	 * پردازش صف اطلاع‌رسانی و ارسال پیامک.
	 */
	public static function process_queue() {
		global $wpdb;
		$table = $wpdb->prefix . KK_TABLE;

		// پردازش دسته‌ای برای جلوگیری از تایم‌اوت.
		$rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'queued' ORDER BY id ASC LIMIT 40" ); // phpcs:ignore WordPress.DB

		if ( ! $rows ) {
			return;
		}

		foreach ( $rows as $row ) {
			KK_Notifier::notify_row( $row );
		}

		// اگر باقی مانده، دوباره زمان‌بندی کن.
		$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'queued'" ); // phpcs:ignore WordPress.DB
		if ( $remaining > 0 ) {
			wp_schedule_single_event( time() + 30, 'kk_process_notifications' );
		}
	}
}

/**
 * ساخت پیام و ارسال اطلاع‌رسانی برای یک ردیف.
 */
class KK_Notifier {

	/**
	 * ارسال اطلاع‌رسانی برای یک ردیف اشتراک.
	 *
	 * @param object $row ردیف دیتابیس.
	 * @return bool
	 */
	public static function notify_row( $row ) {
		global $wpdb;
		$table = $wpdb->prefix . KK_TABLE;

		$product = wc_get_product( $row->variation_id ? $row->variation_id : $row->product_id );
		if ( ! $product ) {
			// محصول حذف شده؛ درخواست را ناموفق علامت بزن.
			$wpdb->update( $table, array( 'status' => 'failed' ), array( 'id' => $row->id ) ); // phpcs:ignore WordPress.DB
			return false;
		}

		$name      = get_the_title( $row->product_id );
		$full_link = KK_Shortlink::product_url( (int) $row->product_id, (int) $row->variation_id );
		$shortlink = KK_Settings::is( 'shortlink_enabled' ) && ! empty( $row->token )
			? KK_Shortlink::build( $row->token )
			: $full_link;

		$message = self::build_message( $name, $full_link, $shortlink );

		$result = KK_SMS::send(
			$row->phone,
			$message,
			array(
				'product'   => $name,
				'shortlink' => $shortlink,
			)
		);

		if ( is_wp_error( $result ) ) {
			// نگه‌داشتن برای تلاش مجدد؛ بازگرداندن به pending با شمارنده تلاش نیست (ساده نگه می‌داریم: failed).
			$wpdb->update( $table, array( 'status' => 'failed' ), array( 'id' => $row->id ) ); // phpcs:ignore WordPress.DB

			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error(
					sprintf( 'KhabaramKon SMS failed for %s: %s', $row->phone, $result->get_error_message() ),
					array( 'source' => 'khabaram-kon' )
				);
			}
			return false;
		}

		$wpdb->update( // phpcs:ignore WordPress.DB
			$table,
			array(
				'status'      => 'notified',
				'notified_at' => current_time( 'mysql' ),
			),
			array( 'id' => $row->id )
		);

		do_action( 'kk_notification_sent', $row, $message );
		return true;
	}

	/**
	 * ساخت متن پیام از قالب.
	 *
	 * @param string $product   نام محصول.
	 * @param string $link      لینک کامل.
	 * @param string $shortlink لینک کوتاه.
	 * @return string
	 */
	public static function build_message( $product, $link, $shortlink ) {
		$template = KK_Settings::get( 'sms_message' );
		$replace  = array(
			'{product}'   => $product,
			'{link}'      => $link,
			'{shortlink}' => $shortlink,
			'{site}'      => get_bloginfo( 'name' ),
		);
		return strtr( $template, $replace );
	}
}
