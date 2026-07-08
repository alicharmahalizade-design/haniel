<?php
/**
 * گزارش تبدیل: اتصال کلیک لینک کوتاه به سفارش‌های ووکامرس.
 *
 * @package KhabaramKon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KK_Conversion {

	const COOKIE = 'kk_click';

	/**
	 * راه‌اندازی هوک‌ها.
	 */
	public static function init() {
		if ( ! KK_Settings::is( 'conversion_enabled' ) ) {
			return;
		}
		// سفارش کلاسیک (چک‌اوت معمولی).
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'attribute' ), 20, 1 );
		// سفارش بلاک/Store API.
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'attribute_from_order' ), 20, 1 );
		// پشتیبان عمومی (ثبت سفارش از هر مسیر).
		add_action( 'woocommerce_new_order', array( __CLASS__, 'attribute' ), 20, 1 );
	}

	/**
	 * افزودن پارامترهای UTM به آدرس مقصد لینک کوتاه.
	 *
	 * @param string $url آدرس مقصد.
	 * @return string
	 */
	public static function add_utm( $url ) {
		if ( ! KK_Settings::is( 'conversion_enabled' ) ) {
			return $url;
		}
		return add_query_arg(
			array(
				'utm_source'   => rawurlencode( KK_Settings::get( 'utm_source', 'sms' ) ),
				'utm_medium'   => rawurlencode( KK_Settings::get( 'utm_medium', 'khabaram-kon' ) ),
				'utm_campaign' => rawurlencode( KK_Settings::get( 'utm_campaign', 'back-in-stock' ) ),
			),
			$url
		);
	}

	/**
	 * ثبت کوکی نسبت‌دهی هنگام کلیک روی لینک کوتاه.
	 *
	 * @param string $token توکن درخواست.
	 */
	public static function set_click_cookie( $token ) {
		if ( ! KK_Settings::is( 'conversion_enabled' ) || headers_sent() ) {
			return;
		}
		$token = preg_replace( '/[^A-Za-z0-9]/', '', $token );
		if ( '' === $token ) {
			return;
		}
		$path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		setcookie( self::COOKIE, $token, time() + 30 * DAY_IN_SECONDS, $path, $domain, is_ssl(), true );
	}

	/**
	 * دریافت شیء سفارش و نسبت‌دهی.
	 *
	 * @param WC_Order $order سفارش.
	 */
	public static function attribute_from_order( $order ) {
		if ( $order instanceof WC_Order ) {
			self::attribute( $order->get_id() );
		}
	}

	/**
	 * نسبت‌دهی سفارش به درخواست‌های اطلاع‌رسانی‌شده.
	 *
	 * @param int $order_id شناسه سفارش.
	 */
	public static function attribute( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . KK_TABLE;

		$phone        = KK_SMS::normalize_phone( $order->get_billing_phone() );
		$cookie_token = isset( $_COOKIE[ self::COOKIE ] ) ? preg_replace( '/[^A-Za-z0-9]/', '', wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';

		// اگر هیچ نشانه‌ای برای نسبت‌دهی نداریم، خارج شو.
		if ( '' === $phone && '' === $cookie_token ) {
			return;
		}

		// جمع مبلغ هر محصول در سفارش (بر اساس شناسه محصول والد).
		$product_totals = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$pid = (int) $item->get_product_id();
			if ( ! $pid ) {
				continue;
			}
			$product_totals[ $pid ] = ( isset( $product_totals[ $pid ] ) ? $product_totals[ $pid ] : 0 ) + (float) $item->get_total();
		}
		if ( empty( $product_totals ) ) {
			return;
		}

		$pids = array_map( 'intval', array_keys( $product_totals ) );
		$in   = implode( ',', array_fill( 0, count( $pids ), '%d' ) );

		$window = (int) KK_Settings::get( 'attribution_window_days', 14 );
		$window = $window > 0 ? $window : 14;
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - $window * DAY_IN_SECONDS );

		// شرط نسبت‌دهی: شماره یا توکن کوکی.
		$or   = array();
		$args = $pids;
		if ( '' !== $phone ) {
			$or[]   = 'phone = %s';
			$args[] = $phone;
		}
		if ( '' !== $cookie_token ) {
			$or[]   = 'token = %s';
			$args[] = $cookie_token;
		}
		$args[] = $cutoff;

		$where = "status = 'notified' AND converted_at IS NULL AND product_id IN ({$in}) AND (" . implode( ' OR ', $or ) . ') AND notified_at >= %s';

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, product_id FROM {$table} WHERE {$where}", $args ) ); // phpcs:ignore WordPress.DB

		if ( ! $rows ) {
			return;
		}

		$now = current_time( 'mysql' );
		foreach ( $rows as $row ) {
			$revenue = isset( $product_totals[ (int) $row->product_id ] ) ? $product_totals[ (int) $row->product_id ] : 0;
			$wpdb->update( // phpcs:ignore WordPress.DB
				$table,
				array(
					'order_id'     => $order_id,
					'revenue'      => $revenue,
					'converted_at' => $now,
				),
				array( 'id' => $row->id ),
				array( '%d', '%f', '%s' ),
				array( '%d' )
			);

			/**
			 * اکشن پس از نسبت‌دهی یک تبدیل.
			 *
			 * @param int   $row_id   شناسه درخواست.
			 * @param int   $order_id شناسه سفارش.
			 * @param float $revenue  مبلغ.
			 */
			do_action( 'kk_conversion_attributed', (int) $row->id, (int) $order_id, (float) $revenue );
		}
	}
}
