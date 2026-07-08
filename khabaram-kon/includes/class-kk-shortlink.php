<?php
/**
 * مدیریت لینک کوتاه داخلی و ریدایرکت آن.
 *
 * @package KhabaramKon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KK_Shortlink {

	/**
	 * راه‌اندازی هوک‌ها.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_redirect' ) );
	}

	/**
	 * افزودن قانون بازنویسی.
	 */
	public static function add_rewrite_rules() {
		$slug = KK_Settings::get( 'shortlink_slug', 'kh' );
		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			$slug = 'kh';
		}
		add_rewrite_rule( '^' . $slug . '/([A-Za-z0-9]+)/?$', 'index.php?kk_token=$matches[1]', 'top' );
	}

	/**
	 * ثبت متغیر کوئری.
	 *
	 * @param array $vars متغیرها.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'kk_token';
		return $vars;
	}

	/**
	 * پردازش ریدایرکت لینک کوتاه.
	 */
	public static function handle_redirect() {
		$token = get_query_var( 'kk_token' );
		if ( empty( $token ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . KK_TABLE;
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, product_id, variation_id, clicked_at FROM {$table} WHERE token = %s LIMIT 1", $token ) ); // phpcs:ignore WordPress.DB

		if ( ! $row ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$target = self::product_url( (int) $row->product_id, (int) $row->variation_id );

		// افزودن UTM و ثبت کوکی نسبت‌دهی برای گزارش تبدیل.
		$target = KK_Conversion::add_utm( $target );
		KK_Conversion::set_click_cookie( $token );

		// ثبت اولین کلیک روی این درخواست.
		if ( empty( $row->clicked_at ) || '0000-00-00 00:00:00' === $row->clicked_at ) {
			$wpdb->update( $table, array( 'clicked_at' => current_time( 'mysql' ) ), array( 'id' => $row->id ) ); // phpcs:ignore WordPress.DB
		}

		// شمارش کلیک کل (برای گزارش‌گیری سریع).
		$clicks = (int) get_option( 'kk_shortlink_clicks', 0 );
		update_option( 'kk_shortlink_clicks', $clicks + 1, false );

		wp_redirect( $target, 302 ); // phpcs:ignore WordPress.Security.SafeRedirect -- URL محصول خودمان است.
		exit;
	}

	/**
	 * ساخت لینک کوتاه از توکن.
	 *
	 * @param string $token توکن.
	 * @return string
	 */
	public static function build( $token ) {
		$slug = sanitize_title( KK_Settings::get( 'shortlink_slug', 'kh' ) );
		if ( empty( $slug ) ) {
			$slug = 'kh';
		}
		// اگر پیوند یکتا خام است، از پارامتر استفاده کن.
		if ( '' === get_option( 'permalink_structure' ) ) {
			return add_query_arg( 'kk_token', $token, home_url( '/' ) );
		}
		return home_url( trailingslashit( $slug ) . $token );
	}

	/**
	 * آدرس محصول یا متغیر.
	 *
	 * @param int $product_id   شناسه محصول.
	 * @param int $variation_id شناسه متغیر.
	 * @return string
	 */
	public static function product_url( $product_id, $variation_id = 0 ) {
		if ( $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation ) {
				return $variation->get_permalink();
			}
		}
		$permalink = get_permalink( $product_id );
		return $permalink ? $permalink : home_url( '/' );
	}
}
