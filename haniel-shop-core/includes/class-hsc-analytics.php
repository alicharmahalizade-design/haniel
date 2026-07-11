<?php
/**
 * لایهٔ گزارش‌گیری؛ همهٔ کوئری‌ها روی جدول جمع‌بندی سبک و با کش اجرا می‌شوند.
 *
 * هیچ‌کدام از این متدها به جداول سفارش ووکامرس دست نمی‌زنند، بنابراین باز کردن
 * داشبورد هیچ فشاری روی سایت نمی‌آورد.
 *
 * @package HanielShopCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSC_Analytics {

	const CACHE_KEY   = 'hsc_dashboard_cache';
	const CACHE_GROUP = 'hsc';

	/**
	 * نام جدول جمع‌بندی.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . HSC_TABLE;
	}

	/**
	 * پاک‌کردن کش داشبورد.
	 */
	public static function flush() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * خلاصهٔ کامل داشبورد (با کش ۶ ساعته).
	 *
	 * @param bool $force نادیده‌گرفتن کش.
	 * @return array
	 */
	public static function dashboard( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$data = array(
			'kpis'          => self::kpis(),
			'segments'      => self::segment_breakdown(),
			'sources'       => self::source_breakdown(),
			'monthly'       => self::monthly_acquisition(),
			'top_customers' => self::top_customers( 10 ),
			'top_cities'    => self::top_cities( 8 ),
			'last_rebuild'  => get_option( 'hsc_last_rebuild', '' ),
			'generated_at'  => current_time( 'mysql' ),
		);

		set_transient( self::CACHE_KEY, $data, 6 * HOUR_IN_SECONDS );
		return $data;
	}

	/**
	 * شاخص‌های کلیدی (KPI).
	 *
	 * @return array
	 */
	public static function kpis() {
		global $wpdb;
		$t     = self::table();
		$churn = (int) HSC_Settings::get( 'churn_days', 120 );
		$new   = (int) HSC_Settings::get( 'new_days', 30 );

		$total_customers = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ); // phpcs:ignore WordPress.DB
		$total_revenue   = (float) $wpdb->get_var( "SELECT COALESCE(SUM(total_spent),0) FROM {$t}" ); // phpcs:ignore WordPress.DB
		$total_orders    = (int) $wpdb->get_var( "SELECT COALESCE(SUM(orders_count),0) FROM {$t}" ); // phpcs:ignore WordPress.DB
		$avg_ltv         = $total_customers > 0 ? $total_revenue / $total_customers : 0;
		$repeat          = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE orders_count >= 2" ); // phpcs:ignore WordPress.DB
		$new_count       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE first_order_date >= %s", gmdate( 'Y-m-d H:i:s', time() - $new * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB
		$at_risk         = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE recency_days > %d", $churn ) ); // phpcs:ignore WordPress.DB
		$avg_order       = $total_orders > 0 ? $total_revenue / $total_orders : 0;

		return array(
			'total_customers'  => $total_customers,
			'total_revenue'    => $total_revenue,
			'total_orders'     => $total_orders,
			'avg_ltv'          => $avg_ltv,
			'avg_order_value'  => $avg_order,
			'repeat_customers' => $repeat,
			'repeat_rate'      => $total_customers > 0 ? round( $repeat / $total_customers * 100, 1 ) : 0,
			'new_customers'    => $new_count,
			'at_risk'          => $at_risk,
		);
	}

	/**
	 * پراکندگی مشتریان بین دسته‌ها.
	 *
	 * @return array
	 */
	public static function segment_breakdown() {
		global $wpdb;
		$t    = self::table();
		$rows = $wpdb->get_results( "SELECT segment, COUNT(*) AS c, COALESCE(SUM(total_spent),0) AS revenue FROM {$t} GROUP BY segment", ARRAY_A ); // phpcs:ignore WordPress.DB
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$info  = HSC_Aggregator::segment_label( $r['segment'] );
			$out[] = array(
				'key'     => $r['segment'],
				'label'   => $info['label'],
				'color'   => $info['color'],
				'emoji'   => $info['emoji'],
				'desc'    => $info['desc'],
				'count'   => (int) $r['c'],
				'revenue' => (float) $r['revenue'],
			);
		}
		// مرتب‌سازی بر اساس تعداد نزولی.
		usort( $out, function ( $a, $b ) {
			return $b['count'] <=> $a['count'];
		} );
		return $out;
	}

	/**
	 * پراکندگی مشتریان بین منابع ترافیک (چند مشتری از گوگل و…).
	 *
	 * @return array
	 */
	public static function source_breakdown() {
		global $wpdb;
		$t    = self::table();
		$rows = $wpdb->get_results( "SELECT source, COUNT(*) AS c, COALESCE(SUM(total_spent),0) AS revenue FROM {$t} GROUP BY source ORDER BY c DESC", ARRAY_A ); // phpcs:ignore WordPress.DB
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$info  = HSC_Source::label( $r['source'] );
			$out[] = array(
				'key'     => $r['source'],
				'label'   => $info['label'],
				'color'   => $info['color'],
				'icon'    => $info['icon'],
				'count'   => (int) $r['c'],
				'revenue' => (float) $r['revenue'],
			);
		}
		return $out;
	}

	/**
	 * جذب مشتری جدید در ۱۲ ماه گذشته (بر اساس اولین خرید).
	 *
	 * @return array
	 */
	public static function monthly_acquisition() {
		global $wpdb;
		$t     = self::table();
		$since = gmdate( 'Y-m-01 00:00:00', strtotime( '-11 months' ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT DATE_FORMAT(first_order_date, '%%Y-%%m') AS ym, COUNT(*) AS c, COALESCE(SUM(total_spent),0) AS revenue FROM {$t} WHERE first_order_date >= %s GROUP BY ym ORDER BY ym ASC", $since ), ARRAY_A ); // phpcs:ignore WordPress.DB

		$map = array();
		foreach ( (array) $rows as $r ) {
			$map[ $r['ym'] ] = array( 'count' => (int) $r['c'], 'revenue' => (float) $r['revenue'] );
		}

		// پرکردن ماه‌های خالی برای نمودار پیوسته.
		$out = array();
		for ( $i = 11; $i >= 0; $i-- ) {
			$ym    = gmdate( 'Y-m', strtotime( "-{$i} months" ) );
			$out[] = array(
				'ym'      => $ym,
				'label'   => self::jalali_month_label( $ym ),
				'count'   => isset( $map[ $ym ] ) ? $map[ $ym ]['count'] : 0,
				'revenue' => isset( $map[ $ym ] ) ? $map[ $ym ]['revenue'] : 0,
			);
		}
		return $out;
	}

	/**
	 * برچسب ماه (میلادی؛ در صورت وجود توابع جلالی می‌توان توسعه داد).
	 *
	 * @param string $ym قالب Y-m.
	 * @return string
	 */
	private static function jalali_month_label( $ym ) {
		$ts = strtotime( $ym . '-01' );
		return date_i18n( 'M y', $ts );
	}

	/**
	 * برترین مشتریان بر اساس مجموع خرید.
	 *
	 * @param int $limit تعداد.
	 * @return array
	 */
	public static function top_customers( $limit = 10 ) {
		global $wpdb;
		$t    = self::table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, email, display_name, orders_count, total_spent, segment, source, last_order_date FROM {$t} ORDER BY total_spent DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * پرتکرارترین شهرها.
	 *
	 * @param int $limit تعداد.
	 * @return array
	 */
	public static function top_cities( $limit = 8 ) {
		global $wpdb;
		$t    = self::table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT city, COUNT(*) AS c, COALESCE(SUM(total_spent),0) AS revenue FROM {$t} WHERE city <> '' GROUP BY city ORDER BY c DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * آیا جدول جمع‌بندی خالی است؟ (برای نمایش پیام «هنوز محاسبه نشده»).
	 *
	 * @return bool
	 */
	public static function is_empty() {
		global $wpdb;
		$t = self::table();
		return 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ); // phpcs:ignore WordPress.DB
	}
}
