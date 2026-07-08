<?php
/**
 * صفحه مدیریت لیست درخواست‌ها.
 *
 * @package KhabaramKon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class KK_Admin_List extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'kk_subscription',
				'plural'   => 'kk_subscriptions',
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
			'cb'          => '<input type="checkbox" />',
			'product'     => __( 'محصول', 'khabaram-kon' ),
			'phone'       => __( 'شماره موبایل', 'khabaram-kon' ),
			'status'      => __( 'وضعیت', 'khabaram-kon' ),
			'created_at'  => __( 'تاریخ ثبت', 'khabaram-kon' ),
			'notified_at' => __( 'تاریخ ارسال', 'khabaram-kon' ),
		);
	}

	/**
	 * ستون‌های قابل مرتب‌سازی.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ),
			'status'     => array( 'status', false ),
		);
	}

	/**
	 * اکشن‌های دسته‌ای.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'حذف', 'khabaram-kon' ),
			'resend' => __( 'ارسال مجدد پیامک', 'khabaram-kon' ),
		);
	}

	/**
	 * چک‌باکس ردیف.
	 *
	 * @param object $item ردیف.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', $item->id );
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
			case 'phone':
				return '<span dir="ltr">' . esc_html( $item->phone ) . '</span>';
			case 'created_at':
				return esc_html( $item->created_at );
			case 'notified_at':
				return $item->notified_at && '0000-00-00 00:00:00' !== $item->notified_at ? esc_html( $item->notified_at ) : '—';
			default:
				return '';
		}
	}

	/**
	 * ستون محصول.
	 *
	 * @param object $item ردیف.
	 * @return string
	 */
	protected function column_product( $item ) {
		$id   = $item->variation_id ? $item->variation_id : $item->product_id;
		$name = get_the_title( $item->product_id );
		if ( ! $name ) {
			$name = '#' . $item->product_id;
		}
		if ( $item->variation_id ) {
			$name .= ' <small>(#' . $item->variation_id . ')</small>';
		}
		$edit = get_edit_post_link( $item->product_id );
		return $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html( wp_strip_all_tags( $name ) ) . '</a>' : esc_html( wp_strip_all_tags( $name ) );
	}

	/**
	 * ستون وضعیت.
	 *
	 * @param object $item ردیف.
	 * @return string
	 */
	protected function column_status( $item ) {
		$labels = array(
			'pending'  => array( __( 'در انتظار', 'khabaram-kon' ), '#b45309' ),
			'queued'   => array( __( 'در صف ارسال', 'khabaram-kon' ), '#2563eb' ),
			'notified' => array( __( 'ارسال شد', 'khabaram-kon' ), '#15803d' ),
			'failed'   => array( __( 'ناموفق', 'khabaram-kon' ), '#b91c1c' ),
		);
		$info = isset( $labels[ $item->status ] ) ? $labels[ $item->status ] : array( $item->status, '#6b7280' );
		return '<span class="kk-badge" style="background:' . esc_attr( $info[1] ) . '">' . esc_html( $info[0] ) . '</span>';
	}

	/**
	 * آماده‌سازی آیتم‌ها.
	 */
	public function prepare_items() {
		global $wpdb;
		$table = $wpdb->prefix . KK_TABLE;

		$this->process_bulk_action();

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : 'created_at'; // phpcs:ignore WordPress.Security.NonceVerification
		$order   = isset( $_REQUEST['order'] ) && 'asc' === strtolower( $_REQUEST['order'] ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification
		$allowed = array( 'created_at', 'status', 'id' );
		if ( ! in_array( $orderby, $allowed, true ) ) {
			$orderby = 'created_at';
		}

		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$where  = '1=1';
		if ( '' !== $search ) {
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$where = $wpdb->prepare( 'phone LIKE %s', $like );
		}

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore WordPress.DB
		$items = $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT {$per_page} OFFSET {$offset}" ); // phpcs:ignore WordPress.DB

		$this->items = $items;
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
	 * پردازش اکشن دسته‌ای.
	 */
	public function process_bulk_action() {
		$action = $this->current_action();
		if ( ! $action ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		$ids = isset( $_REQUEST['ids'] ) ? array_map( 'absint', (array) $_REQUEST['ids'] ) : array();
		if ( empty( $ids ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . KK_TABLE;
		$in    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		if ( 'delete' === $action ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$in})", $ids ) ); // phpcs:ignore WordPress.DB
		} elseif ( 'resend' === $action ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id IN ({$in})", $ids ) ); // phpcs:ignore WordPress.DB
			foreach ( $rows as $row ) {
				KK_Notifier::notify_row( $row );
			}
		}
	}

	/**
	 * پیام خالی بودن.
	 */
	public function no_items() {
		esc_html_e( 'هنوز درخواستی ثبت نشده است.', 'khabaram-kon' );
	}

	/**
	 * رندر صفحه.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$list = new self();
		$list->prepare_items();

		global $wpdb;
		$table   = $wpdb->prefix . KK_TABLE;
		$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB
		$pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status IN ('pending','queued')" ); // phpcs:ignore WordPress.DB
		$sent    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'notified'" ); // phpcs:ignore WordPress.DB
		$clicks  = (int) get_option( 'kk_shortlink_clicks', 0 );
		?>
		<div class="wrap kk-wrap" dir="rtl">
			<h1 class="kk-title"><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'درخواست‌های خبرم کن', 'khabaram-kon' ); ?></h1>

			<div class="kk-stats">
				<div class="kk-stat"><span class="kk-stat-num"><?php echo esc_html( number_format_i18n( $total ) ); ?></span><span class="kk-stat-label"><?php esc_html_e( 'کل درخواست‌ها', 'khabaram-kon' ); ?></span></div>
				<div class="kk-stat"><span class="kk-stat-num"><?php echo esc_html( number_format_i18n( $pending ) ); ?></span><span class="kk-stat-label"><?php esc_html_e( 'در انتظار', 'khabaram-kon' ); ?></span></div>
				<div class="kk-stat"><span class="kk-stat-num"><?php echo esc_html( number_format_i18n( $sent ) ); ?></span><span class="kk-stat-label"><?php esc_html_e( 'ارسال‌شده', 'khabaram-kon' ); ?></span></div>
				<div class="kk-stat"><span class="kk-stat-num"><?php echo esc_html( number_format_i18n( $clicks ) ); ?></span><span class="kk-stat-label"><?php esc_html_e( 'کلیک لینک کوتاه', 'khabaram-kon' ); ?></span></div>
			</div>

			<form method="get">
				<input type="hidden" name="page" value="khabaram-kon-subscriptions">
				<?php
				$list->search_box( __( 'جستجوی شماره', 'khabaram-kon' ), 'kk-search' );
				$list->display();
				?>
			</form>
		</div>
		<?php
	}
}
