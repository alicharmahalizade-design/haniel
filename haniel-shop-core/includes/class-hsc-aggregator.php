<?php
/**
 * موتور جمع‌بندی و تحلیل مشتریان.
 *
 * همهٔ کار سنگین اینجا و در «پس‌زمینه» انجام می‌شود:
 *   ۱) کرون شبانه: بازسازی کامل جدول جمع‌بندی از روی سفارش‌ها (با SQL گروهی سبک).
 *   ۲) به‌روزرسانی افزایشی: هنگام تغییر وضعیت سفارش، فقط همان یک مشتری بازمحاسبه می‌شود.
 *   ۳) امتیازدهی RFM (تازگی/تعداد/ارزش) و دسته‌بندی مشتریان.
 *
 * @package HanielShopCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSC_Aggregator {

	/**
	 * راه‌اندازی هوک‌ها.
	 */
	public static function init() {
		add_action( 'hsc_daily_aggregate', array( __CLASS__, 'run_full_rebuild' ) );
		add_action( 'hsc_full_rebuild', array( __CLASS__, 'run_full_rebuild' ) );
		add_action( 'hsc_recompute_customer', array( __CLASS__, 'recompute_single' ), 10, 1 );

		// به‌روزرسانی افزایشی هنگام تغییر سفارش (بدون فشار روی سایت؛ به‌صورت زمان‌بندی‌شده).
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'queue_from_order' ), 20, 1 );
		add_action( 'woocommerce_new_order', array( __CLASS__, 'queue_from_order' ), 20, 1 );
	}

	/**
	 * آیا HPOS (جداول سفارش سفارشی) فعال است؟
	 *
	 * @return bool
	 */
	public static function is_hpos() {
		return class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * زمان‌بندی بازمحاسبهٔ یک مشتری بر اساس سفارش.
	 *
	 * @param int $order_id شناسهٔ سفارش.
	 */
	public static function queue_from_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$email = strtolower( trim( $order->get_billing_email() ) );
		if ( '' === $email ) {
			return;
		}
		$args = array( $email );
		if ( ! wp_next_scheduled( 'hsc_recompute_customer', $args ) ) {
			wp_schedule_single_event( time() + 20, 'hsc_recompute_customer', $args );
		}
	}

	/**
	 * بازمحاسبهٔ یک مشتری منفرد (سبک؛ یک کوئری گروهی روی همان ایمیل).
	 *
	 * @param string $email ایمیل مشتری.
	 */
	public static function recompute_single( $email ) {
		global $wpdb;
		$email = strtolower( trim( (string) $email ) );
		if ( '' === $email ) {
			return;
		}

		$row = self::fetch_aggregates( array( $email ) );
		$table = $wpdb->prefix . HSC_TABLE;

		if ( empty( $row ) ) {
			// دیگر سفارشی ندارد؛ رکورد را پاک کن.
			$wpdb->delete( $table, array( 'email' => $email ), array( '%s' ) ); // phpcs:ignore WordPress.DB
			return;
		}

		$data = $row[0];
		self::enrich_row( $data );

		// امتیاز RFM بر اساس آستانه‌های آخرین بازسازی کامل.
		$th = get_option( 'hsc_rfm_thresholds', array() );
		$data['rfm_r'] = self::score_recency( $data['recency_days'], $th );
		$data['rfm_f'] = self::score_by_thresholds( $data['orders_count'], isset( $th['f'] ) ? $th['f'] : array() );
		$data['rfm_m'] = self::score_by_thresholds( $data['total_spent'], isset( $th['m'] ) ? $th['m'] : array() );
		$data['segment'] = self::segment( $data );

		self::upsert_row( $data );
		self::clear_cache();
	}

	/**
	 * بازسازی کامل جدول جمع‌بندی از روی همهٔ سفارش‌ها.
	 */
	public static function run_full_rebuild() {
		// قفل برای جلوگیری از اجرای هم‌زمان.
		if ( get_transient( 'hsc_rebuild_lock' ) ) {
			return;
		}
		set_transient( 'hsc_rebuild_lock', 1, 15 * MINUTE_IN_SECONDS );

		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		global $wpdb;
		$table = $wpdb->prefix . HSC_TABLE;

		$rows = self::fetch_aggregates();
		if ( empty( $rows ) ) {
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB
			update_option( 'hsc_last_rebuild', current_time( 'mysql' ) );
			delete_transient( 'hsc_rebuild_lock' );
			self::clear_cache();
			return;
		}

		// غنی‌سازی همهٔ ردیف‌ها (نام، شهر، منبع).
		foreach ( $rows as &$r ) {
			self::enrich_row( $r );
		}
		unset( $r );

		// محاسبهٔ آستانه‌های چندک برای امتیاز RFM.
		$thresholds = self::compute_thresholds( $rows );
		update_option( 'hsc_rfm_thresholds', $thresholds );

		// امتیازدهی و دسته‌بندی.
		foreach ( $rows as &$r ) {
			$r['rfm_r']   = self::score_recency( $r['recency_days'], $thresholds );
			$r['rfm_f']   = self::score_by_thresholds( $r['orders_count'], $thresholds['f'] );
			$r['rfm_m']   = self::score_by_thresholds( $r['total_spent'], $thresholds['m'] );
			$r['segment'] = self::segment( $r );
		}
		unset( $r );

		// جایگزینی اتمی: نوشتن در جدول موقت و سپس تعویض نام.
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB
		self::bulk_insert( $rows );

		update_option( 'hsc_last_rebuild', current_time( 'mysql' ) );
		delete_transient( 'hsc_rebuild_lock' );
		self::clear_cache();
	}

	/**
	 * دریافت جمع‌بندی سفارش‌ها به تفکیک ایمیل مشتری.
	 *
	 * @param array $emails فیلتر اختیاری بر اساس ایمیل‌ها (برای بازمحاسبهٔ منفرد).
	 * @return array فهرست ردیف‌های خام.
	 */
	private static function fetch_aggregates( $emails = array() ) {
		global $wpdb;
		$statuses = HSC_Settings::paid_statuses_prefixed();
		$status_in = "'" . implode( "','", array_map( 'esc_sql', $statuses ) ) . "'";

		$email_clause = '';
		if ( ! empty( $emails ) ) {
			$safe = array();
			foreach ( $emails as $e ) {
				$safe[] = "'" . esc_sql( strtolower( trim( $e ) ) ) . "'";
			}
			$email_clause = ' AND LOWER(o.billing_email) IN (' . implode( ',', $safe ) . ')';
		}

		if ( self::is_hpos() ) {
			$orders = $wpdb->prefix . 'wc_orders';
			// جایگزینی نام ستون o. برای شرط ایمیل در حالت HPOS.
			$sql = "SELECT
					LOWER(o.billing_email) AS email,
					MAX(o.customer_id) AS user_id,
					COUNT(*) AS orders_count,
					COALESCE(SUM(o.total_amount),0) AS total_spent,
					MIN(o.date_created_gmt) AS first_order_date,
					MAX(o.date_created_gmt) AS last_order_date,
					MIN(o.id) AS first_order_id,
					MAX(o.id) AS last_order_id
				FROM {$orders} o
				WHERE o.type = 'shop_order'
					AND o.status IN ({$status_in})
					AND o.billing_email <> ''
					{$email_clause}
				GROUP BY LOWER(o.billing_email)";
		} else {
			$posts    = $wpdb->posts;
			$postmeta = $wpdb->postmeta;
			// در حالت کلاسیک شرط ایمیل روی متای ایمیل اعمال می‌شود.
			$email_clause_legacy = '';
			if ( ! empty( $emails ) ) {
				$safe = array();
				foreach ( $emails as $e ) {
					$safe[] = "'" . esc_sql( strtolower( trim( $e ) ) ) . "'";
				}
				$email_clause_legacy = ' AND LOWER(pm_email.meta_value) IN (' . implode( ',', $safe ) . ')';
			}
			$sql = "SELECT
					LOWER(pm_email.meta_value) AS email,
					MAX(CAST(NULLIF(pm_user.meta_value,'') AS UNSIGNED)) AS user_id,
					COUNT(*) AS orders_count,
					COALESCE(SUM(CAST(pm_total.meta_value AS DECIMAL(18,2))),0) AS total_spent,
					MIN(p.post_date_gmt) AS first_order_date,
					MAX(p.post_date_gmt) AS last_order_date,
					MIN(p.ID) AS first_order_id,
					MAX(p.ID) AS last_order_id
				FROM {$posts} p
				INNER JOIN {$postmeta} pm_email ON pm_email.post_id = p.ID AND pm_email.meta_key = '_billing_email'
				LEFT JOIN {$postmeta} pm_total ON pm_total.post_id = p.ID AND pm_total.meta_key = '_order_total'
				LEFT JOIN {$postmeta} pm_user ON pm_user.post_id = p.ID AND pm_user.meta_key = '_customer_user'
				WHERE p.post_type = 'shop_order'
					AND p.post_status IN ({$status_in})
					AND pm_email.meta_value <> ''
					{$email_clause_legacy}
				GROUP BY LOWER(pm_email.meta_value)";
		}

		$results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( ! is_array( $results ) ) {
			return array();
		}

		$now = current_time( 'timestamp', true ); // GMT.
		foreach ( $results as &$r ) {
			$r['user_id']         = (int) $r['user_id'];
			$r['orders_count']    = (int) $r['orders_count'];
			$r['total_spent']     = round( (float) $r['total_spent'], 2 );
			$r['avg_order_value'] = $r['orders_count'] > 0 ? round( $r['total_spent'] / $r['orders_count'], 2 ) : 0;
			$last_ts              = $r['last_order_date'] ? strtotime( $r['last_order_date'] . ' UTC' ) : 0;
			$r['recency_days']    = $last_ts ? max( 0, (int) floor( ( $now - $last_ts ) / DAY_IN_SECONDS ) ) : 9999;
		}
		unset( $r );

		return $results;
	}

	/**
	 * افزودن اطلاعات نمایشی (نام، تلفن، شهر، منبع) به یک ردیف.
	 *
	 * @param array $r ردیف (با ارجاع).
	 */
	private static function enrich_row( &$r ) {
		$billing = self::fetch_billing( array( (int) $r['last_order_id'] ) );
		$b       = isset( $billing[ (int) $r['last_order_id'] ] ) ? $billing[ (int) $r['last_order_id'] ] : array();

		$name = trim( ( isset( $b['first_name'] ) ? $b['first_name'] : '' ) . ' ' . ( isset( $b['last_name'] ) ? $b['last_name'] : '' ) );
		if ( '' === $name && $r['user_id'] ) {
			$u = get_userdata( $r['user_id'] );
			if ( $u ) {
				$name = $u->display_name;
			}
		}
		$r['display_name'] = $name ? $name : $r['email'];
		$r['phone']        = isset( $b['phone'] ) ? $b['phone'] : '';
		$r['city']         = isset( $b['city'] ) ? $b['city'] : '';
		$r['state']        = isset( $b['state'] ) ? $b['state'] : '';

		// منبع اولین‌لمس از متای اولین سفارش.
		$src = self::fetch_source( (int) $r['first_order_id'] );
		$r['source']        = $src['source'];
		$r['source_detail'] = $src['detail'];
	}

	/**
	 * دریافت اطلاعات صورت‌حساب چند سفارش.
	 *
	 * @param array $order_ids شناسه‌ها.
	 * @return array نگاشت order_id => اطلاعات.
	 */
	private static function fetch_billing( $order_ids ) {
		global $wpdb;
		$order_ids = array_filter( array_map( 'absint', $order_ids ) );
		if ( empty( $order_ids ) ) {
			return array();
		}
		$in  = implode( ',', $order_ids );
		$out = array();

		if ( self::is_hpos() ) {
			$addr = $wpdb->prefix . 'wc_order_addresses';
			$rows = $wpdb->get_results( "SELECT order_id, first_name, last_name, city, state, phone FROM {$addr} WHERE address_type='billing' AND order_id IN ({$in})", ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( (array) $rows as $row ) {
				$out[ (int) $row['order_id'] ] = array(
					'first_name' => $row['first_name'],
					'last_name'  => $row['last_name'],
					'city'       => $row['city'],
					'state'      => $row['state'],
					'phone'      => $row['phone'],
				);
			}
		} else {
			$postmeta = $wpdb->postmeta;
			$keys     = "'_billing_first_name','_billing_last_name','_billing_city','_billing_state','_billing_phone'";
			$rows     = $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$postmeta} WHERE post_id IN ({$in}) AND meta_key IN ({$keys})", ARRAY_A ); // phpcs:ignore WordPress.DB
			$map      = array(
				'_billing_first_name' => 'first_name',
				'_billing_last_name'  => 'last_name',
				'_billing_city'       => 'city',
				'_billing_state'      => 'state',
				'_billing_phone'      => 'phone',
			);
			foreach ( (array) $rows as $row ) {
				$oid = (int) $row['post_id'];
				if ( ! isset( $out[ $oid ] ) ) {
					$out[ $oid ] = array();
				}
				if ( isset( $map[ $row['meta_key'] ] ) ) {
					$out[ $oid ][ $map[ $row['meta_key'] ] ] = $row['meta_value'];
				}
			}
		}
		return $out;
	}

	/**
	 * دریافت منبع ثبت‌شده روی یک سفارش.
	 *
	 * @param int $order_id شناسهٔ سفارش.
	 * @return array{source:string,detail:string}
	 */
	private static function fetch_source( $order_id ) {
		$order_id = absint( $order_id );
		$default  = array( 'source' => 'direct', 'detail' => '' );
		if ( ! $order_id ) {
			return $default;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return $default;
		}
		$source = $order->get_meta( '_hsc_source' );
		if ( ! $source ) {
			return $default;
		}
		return array(
			'source' => sanitize_key( $source ),
			'detail' => (string) $order->get_meta( '_hsc_source_detail' ),
		);
	}

	/**
	 * محاسبهٔ آستانه‌های چندک برای امتیازدهی RFM.
	 *
	 * @param array $rows ردیف‌ها.
	 * @return array
	 */
	private static function compute_thresholds( $rows ) {
		$recency = array();
		$freq    = array();
		$money   = array();
		foreach ( $rows as $r ) {
			$recency[] = (int) $r['recency_days'];
			$freq[]    = (int) $r['orders_count'];
			$money[]   = (float) $r['total_spent'];
		}
		sort( $recency, SORT_NUMERIC );
		sort( $freq, SORT_NUMERIC );
		sort( $money, SORT_NUMERIC );

		return array(
			// برای تازگی، مقدار کمتر بهتر است؛ آستانه‌ها همان چندک‌ها هستند و امتیازدهی معکوس می‌شود.
			'r' => self::quintiles( $recency ),
			'f' => self::quintiles( $freq ),
			'm' => self::quintiles( $money ),
		);
	}

	/**
	 * محاسبهٔ مرزهای چندک (۲۰٪، ۴۰٪، ۶۰٪، ۸۰٪).
	 *
	 * @param array $sorted آرایهٔ مرتب‌شدهٔ صعودی.
	 * @return array چهار مرز.
	 */
	private static function quintiles( $sorted ) {
		$n = count( $sorted );
		if ( 0 === $n ) {
			return array( 0, 0, 0, 0 );
		}
		$q = array();
		foreach ( array( 0.2, 0.4, 0.6, 0.8 ) as $p ) {
			$idx = (int) floor( $p * ( $n - 1 ) );
			$q[] = $sorted[ $idx ];
		}
		return $q;
	}

	/**
	 * امتیاز ۱ تا ۵ بر اساس آستانه‌ها (مقدار بیشتر = امتیاز بیشتر).
	 *
	 * @param float $value مقدار.
	 * @param array $th    چهار مرز.
	 * @return int
	 */
	private static function score_by_thresholds( $value, $th ) {
		if ( empty( $th ) || count( $th ) < 4 ) {
			return 3;
		}
		if ( $value <= $th[0] ) {
			return 1;
		}
		if ( $value <= $th[1] ) {
			return 2;
		}
		if ( $value <= $th[2] ) {
			return 3;
		}
		if ( $value <= $th[3] ) {
			return 4;
		}
		return 5;
	}

	/**
	 * امتیاز تازگی (مقدار کمتر = امتیاز بیشتر).
	 *
	 * @param int   $days       روزهای سپری‌شده.
	 * @param array $thresholds آستانه‌ها.
	 * @return int
	 */
	private static function score_recency( $days, $thresholds ) {
		$th = isset( $thresholds['r'] ) ? $thresholds['r'] : array();
		if ( empty( $th ) || count( $th ) < 4 ) {
			return 3;
		}
		// معکوس: تازگی کمتر بهتر است.
		if ( $days <= $th[0] ) {
			return 5;
		}
		if ( $days <= $th[1] ) {
			return 4;
		}
		if ( $days <= $th[2] ) {
			return 3;
		}
		if ( $days <= $th[3] ) {
			return 2;
		}
		return 1;
	}

	/**
	 * تعیین دستهٔ مشتری بر اساس RFM و آستانه‌های کسب‌وکار.
	 *
	 * @param array $r ردیف مشتری.
	 * @return string کلید دسته.
	 */
	public static function segment( $r ) {
		$r_score = (int) $r['rfm_r'];
		$f_score = (int) $r['rfm_f'];
		$m_score = (int) $r['rfm_m'];
		$orders  = (int) $r['orders_count'];
		$recency = (int) $r['recency_days'];

		$loyal_min = (int) HSC_Settings::get( 'loyal_min_orders', 3 );
		$churn     = (int) HSC_Settings::get( 'churn_days', 120 );
		$new_days  = (int) HSC_Settings::get( 'new_days', 30 );

		$first_ts = ! empty( $r['first_order_date'] ) ? strtotime( $r['first_order_date'] . ' UTC' ) : 0;
		$age_days = $first_ts ? max( 0, (int) floor( ( current_time( 'timestamp', true ) - $first_ts ) / DAY_IN_SECONDS ) ) : 9999;

		// خیلی خاموش شده.
		if ( $recency > $churn ) {
			if ( $orders >= $loyal_min || $m_score >= 4 ) {
				return 'at_risk'; // ارزشمند بوده و در حال ریزش.
			}
			return 'lost';
		}

		// فعال.
		if ( $age_days <= $new_days && $orders <= 1 ) {
			return 'new';
		}
		if ( $r_score >= 4 && $f_score >= 4 && $m_score >= 4 ) {
			return 'champion';
		}
		if ( $orders >= $loyal_min && $f_score >= 3 ) {
			return 'loyal';
		}
		if ( $m_score >= 4 ) {
			return 'big_spender';
		}
		if ( $r_score >= 4 && $f_score <= 2 ) {
			return 'promising';
		}
		if ( $m_score <= 2 && $f_score <= 2 ) {
			return 'low_value';
		}
		return 'needs_attention';
	}

	/**
	 * برچسب، رنگ و توضیح هر دسته.
	 *
	 * @param string $key کلید دسته.
	 * @return array{label:string,color:string,desc:string,emoji:string}
	 */
	public static function segment_label( $key ) {
		$map = array(
			'champion'        => array( 'قهرمان (طلایی)', '#f59e0b', 'اخیر خرید کرده، پرتکرار و پرخرج؛ باارزش‌ترین مشتری‌ها.', '👑' ),
			'loyal'           => array( 'وفادار', '#16a34a', 'خریدهای مکرر و پیوسته؛ ستون فقرات فروش.', '💚' ),
			'big_spender'     => array( 'پرخرید (ارزش بالا)', '#7c3aed', 'مبلغ خرید بالا؛ حتی اگر تعداد سفارش کم باشد.', '💎' ),
			'promising'       => array( 'امیدوارکننده', '#0891b2', 'به‌تازگی خرید کرده ولی هنوز تکرار نشده؛ پتانسیل وفاداری.', '🌱' ),
			'new'             => array( 'تازه‌وارد', '#2563eb', 'اولین خریدشان به‌تازگی انجام شده.', '✨' ),
			'needs_attention' => array( 'نیازمند توجه', '#d97706', 'میانگین در همه شاخص‌ها؛ با پیشنهاد مناسب فعال می‌شوند.', '🔔' ),
			'at_risk'         => array( 'در معرض ریزش', '#dc2626', 'قبلاً خوب خرید می‌کردند ولی مدتی است غیبشان زده.', '⚠️' ),
			'low_value'       => array( 'کم‌خرید', '#64748b', 'تعداد و مبلغ خرید پایین.', '🔹' ),
			'lost'            => array( 'از دست‌رفته', '#991b1b', 'مدت زیادی است خرید نکرده‌اند.', '🚫' ),
		);
		$info = isset( $map[ $key ] ) ? $map[ $key ] : array( $key, '#64748b', '', '•' );
		return array( 'label' => $info[0], 'color' => $info[1], 'desc' => $info[2], 'emoji' => $info[3] );
	}

	/**
	 * درج انبوه ردیف‌ها در جدول.
	 *
	 * @param array $rows ردیف‌ها.
	 */
	private static function bulk_insert( $rows ) {
		global $wpdb;
		$table = $wpdb->prefix . HSC_TABLE;
		$now   = current_time( 'mysql' );

		$chunks = array_chunk( $rows, 200 );
		foreach ( $chunks as $chunk ) {
			$values = array();
			foreach ( $chunk as $r ) {
				$values[] = $wpdb->prepare(
					'(%s,%d,%s,%s,%s,%s,%d,%f,%f,%d,%s,%d,%s,%d,%s,%s,%d,%d,%d,%s,%s)',
					$r['email'],
					$r['user_id'],
					mb_substr( (string) $r['display_name'], 0, 191 ),
					mb_substr( (string) $r['phone'], 0, 32 ),
					mb_substr( (string) $r['city'], 0, 100 ),
					mb_substr( (string) $r['state'], 0, 100 ),
					$r['orders_count'],
					$r['total_spent'],
					$r['avg_order_value'],
					$r['first_order_id'],
					self::null_date( $r['first_order_date'] ),
					$r['last_order_id'],
					self::null_date( $r['last_order_date'] ),
					$r['recency_days'],
					mb_substr( (string) $r['source'], 0, 40 ),
					mb_substr( (string) $r['source_detail'], 0, 191 ),
					$r['rfm_r'],
					$r['rfm_f'],
					$r['rfm_m'],
					$r['segment'],
					$now
				);
			}
			if ( $values ) {
				$sql = "INSERT INTO {$table} (email,user_id,display_name,phone,city,state,orders_count,total_spent,avg_order_value,first_order_id,first_order_date,last_order_id,last_order_date,recency_days,source,source_detail,rfm_r,rfm_f,rfm_m,segment,updated_at) VALUES " . implode( ',', $values );
				$wpdb->query( $sql ); // phpcs:ignore WordPress.DB
			}
		}
	}

	/**
	 * درج/به‌روزرسانی یک ردیف بر اساس ایمیل.
	 *
	 * @param array $r ردیف.
	 */
	private static function upsert_row( $r ) {
		global $wpdb;
		$table = $wpdb->prefix . HSC_TABLE;
		$data  = array(
			'email'            => $r['email'],
			'user_id'          => (int) $r['user_id'],
			'display_name'     => mb_substr( (string) $r['display_name'], 0, 191 ),
			'phone'            => mb_substr( (string) $r['phone'], 0, 32 ),
			'city'             => mb_substr( (string) $r['city'], 0, 100 ),
			'state'            => mb_substr( (string) $r['state'], 0, 100 ),
			'orders_count'     => (int) $r['orders_count'],
			'total_spent'      => (float) $r['total_spent'],
			'avg_order_value'  => (float) $r['avg_order_value'],
			'first_order_id'   => (int) $r['first_order_id'],
			'first_order_date' => self::null_date( $r['first_order_date'] ),
			'last_order_id'    => (int) $r['last_order_id'],
			'last_order_date'  => self::null_date( $r['last_order_date'] ),
			'recency_days'     => (int) $r['recency_days'],
			'source'           => mb_substr( (string) $r['source'], 0, 40 ),
			'source_detail'    => mb_substr( (string) $r['source_detail'], 0, 191 ),
			'rfm_r'            => (int) $r['rfm_r'],
			'rfm_f'            => (int) $r['rfm_f'],
			'rfm_m'            => (int) $r['rfm_m'],
			'segment'          => $r['segment'],
			'updated_at'       => current_time( 'mysql' ),
		);
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s", $r['email'] ) ); // phpcs:ignore WordPress.DB
		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing ) ); // phpcs:ignore WordPress.DB
		} else {
			$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB
		}
	}

	/**
	 * تبدیل تاریخ خالی به null.
	 *
	 * @param string $date تاریخ.
	 * @return string|null
	 */
	private static function null_date( $date ) {
		if ( empty( $date ) || '0000-00-00 00:00:00' === $date ) {
			return null;
		}
		return $date;
	}

	/**
	 * پاک‌کردن کش گزارش‌ها.
	 */
	public static function clear_cache() {
		HSC_Analytics::flush();
	}
}
