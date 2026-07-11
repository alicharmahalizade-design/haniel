<?php
/**
 * جدول لیست مشتریان در پیشخوان.
 *
 * @package HanielShopCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class HSC_Customers_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'hsc_customer',
				'plural'   => 'hsc_customers',
				'ajax'     => false,
			)
		);
	}

	/**
	 * ستون‌ها.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'customer'     => __( 'مشتری', 'haniel-shop-core' ),
			'segment'      => __( 'دسته', 'haniel-shop-core' ),
			'source'       => __( 'منبع ورود', 'haniel-shop-core' ),
			'orders_count' => __( 'تعداد خرید', 'haniel-shop-core' ),
			'total_spent'  => __( 'مجموع خرید', 'haniel-shop-core' ),
			'avg'          => __( 'میانگین سبد', 'haniel-shop-core' ),
			'recency'      => __( 'آخرین خرید', 'haniel-shop-core' ),
			'city'         => __( 'شهر', 'haniel-shop-core' ),
		);
	}

	/**
	 * ستون‌های قابل مرتب‌سازی.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'orders_count' => array( 'orders_count', true ),
			'total_spent'  => array( 'total_spent', true ),
			'recency'      => array( 'recency_days', false ),
			'customer'     => array( 'display_name', false ),
		);
	}

	/**
	 * ستون پیش‌فرض.
	 *
	 * @param object $item        ردیف.
	 * @param string $column_name نام ستون.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'orders_count':
				return '<strong>' . esc_html( number_format_i18n( $item->orders_count ) ) . '</strong>';
			case 'total_spent':
				return wp_kses_post( wc_price( $item->total_spent ) );
			case 'avg':
				return wp_kses_post( wc_price( $item->avg_order_value ) );
			case 'city':
				return $item->city ? esc_html( $item->city ) : '—';
			default:
				return '';
		}
	}

	/**
	 * ستون مشتری (نام + ایمیل + لینک پروفایل).
	 *
	 * @param object $item ردیف.
	 * @return string
	 */
	protected function column_customer( $item ) {
		$url = add_query_arg(
			array(
				'page'     => 'haniel-shop-core-customers',
				'view'     => 'profile',
				'customer' => (int) $item->id,
			),
			admin_url( 'admin.php' )
		);
		$name = $item->display_name ? $item->display_name : $item->email;
		$html = '<strong><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></strong>';
		$html .= '<br><small dir="ltr" style="color:#6b7280">' . esc_html( $item->email ) . '</small>';
		if ( $item->phone ) {
			$html .= '<br><small dir="ltr" style="color:#9ca3af">' . esc_html( $item->phone ) . '</small>';
		}
		return $html;
	}

	/**
	 * ستون دسته.
	 *
	 * @param object $item ردیف.
	 * @return string
	 */
	protected function column_segment( $item ) {
		$info = HSC_Aggregator::segment_label( $item->segment );
		return '<span class="hsc-badge" style="background:' . esc_attr( $info['color'] ) . '">' . esc_html( $info['emoji'] . ' ' . $info['label'] ) . '</span>';
	}

	/**
	 * ستون منبع.
	 *
	 * @param object $item ردیف.
	 * @return string
	 */
	protected function column_source( $item ) {
		$info = HSC_Source::label( $item->source );
		return '<span class="hsc-src" style="color:' . esc_attr( $info['color'] ) . '">' . esc_html( $info['icon'] . ' ' . $info['label'] ) . '</span>';
	}

	/**
	 * ستون تازگی.
	 *
	 * @param object $item ردیف.
	 * @return string
	 */
	protected function column_recency( $item ) {
		if ( empty( $item->last_order_date ) || '0000-00-00 00:00:00' === $item->last_order_date ) {
			return '—';
		}
		$days = (int) $item->recency_days;
		if ( 0 === $days ) {
			$rel = __( 'امروز', 'haniel-shop-core' );
		} elseif ( 1 === $days ) {
			$rel = __( 'دیروز', 'haniel-shop-core' );
		} else {
			/* translators: %s: number of days */
			$rel = sprintf( __( '%s روز پیش', 'haniel-shop-core' ), number_format_i18n( $days ) );
		}
		$color = $days > (int) HSC_Settings::get( 'churn_days', 120 ) ? '#dc2626' : '#374151';
		return '<span style="color:' . esc_attr( $color ) . '">' . esc_html( $rel ) . '</span>';
	}

	/**
	 * فیلترهای بالای جدول (دسته و منبع).
	 *
	 * @param string $which موقعیت.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$segment = isset( $_REQUEST['segment'] ) ? sanitize_key( $_REQUEST['segment'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$source  = isset( $_REQUEST['source'] ) ? sanitize_key( $_REQUEST['source'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="alignleft actions">
			<select name="segment">
				<option value=""><?php esc_html_e( 'همهٔ دسته‌ها', 'haniel-shop-core' ); ?></option>
				<?php foreach ( self::segment_keys() as $key ) : ?>
					<?php $info = HSC_Aggregator::segment_label( $key ); ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $segment, $key ); ?>><?php echo esc_html( $info['emoji'] . ' ' . $info['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="source">
				<option value=""><?php esc_html_e( 'همهٔ منابع', 'haniel-shop-core' ); ?></option>
				<?php foreach ( self::source_keys() as $key ) : ?>
					<?php $info = HSC_Source::label( $key ); ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $source, $key ); ?>><?php echo esc_html( $info['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'فیلتر', 'haniel-shop-core' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * کلیدهای دسته.
	 *
	 * @return array
	 */
	private static function segment_keys() {
		return array( 'champion', 'loyal', 'big_spender', 'promising', 'new', 'needs_attention', 'at_risk', 'low_value', 'lost' );
	}

	/**
	 * کلیدهای منبع موجود در داده (پویا).
	 *
	 * @return array
	 */
	private static function source_keys() {
		global $wpdb;
		$t    = $wpdb->prefix . HSC_TABLE;
		$keys = $wpdb->get_col( "SELECT DISTINCT source FROM {$t} ORDER BY source ASC" ); // phpcs:ignore WordPress.DB
		return is_array( $keys ) ? $keys : array();
	}

	/**
	 * آماده‌سازی آیتم‌ها.
	 */
	public function prepare_items() {
		global $wpdb;
		$table = $wpdb->prefix . HSC_TABLE;

		$per_page     = 30;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : 'total_spent'; // phpcs:ignore WordPress.Security.NonceVerification
		$order   = isset( $_REQUEST['order'] ) && 'asc' === strtolower( $_REQUEST['order'] ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification
		$allowed = array( 'orders_count', 'total_spent', 'recency_days', 'display_name' );
		if ( ! in_array( $orderby, $allowed, true ) ) {
			$orderby = 'total_spent';
		}

		// شرط‌ها.
		$where  = array( '1=1' );
		$params = array();

		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(display_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		$segment = isset( $_REQUEST['segment'] ) ? sanitize_key( $_REQUEST['segment'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $segment ) {
			$where[]  = 'segment = %s';
			$params[] = $segment;
		}
		$source = isset( $_REQUEST['source'] ) ? sanitize_key( $_REQUEST['source'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $source ) {
			$where[]  = 'source = %s';
			$params[] = $source;
		}

		$where_sql = implode( ' AND ', $where );

		// شمارش کل.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = $params
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) // phpcs:ignore WordPress.DB
			: (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB

		// دریافت ردیف‌ها.
		$data_sql      = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_params  = array_merge( $params, array( $per_page, $offset ) );
		$this->items   = $wpdb->get_results( $wpdb->prepare( $data_sql, $query_params ) ); // phpcs:ignore WordPress.DB

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/**
	 * پیام خالی.
	 */
	public function no_items() {
		esc_html_e( 'مشتری‌ای با این شرایط پیدا نشد. اگر افزونه تازه نصب شده، صبر کنید تا جمع‌بندی پس‌زمینه کامل شود.', 'haniel-shop-core' );
	}
}
