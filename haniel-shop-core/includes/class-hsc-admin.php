<?php
/**
 * صفحات پیشخوان: داشبورد، مشتریان، منابع و تنظیمات.
 *
 * @package HanielShopCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSC_Admin {

	/**
	 * راه‌اندازی هوک‌ها.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_hsc_rebuild', array( __CLASS__, 'ajax_rebuild' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( HSC_PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * لینک‌های میان‌بر در فهرست افزونه‌ها.
	 *
	 * @param array $links لینک‌ها.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url  = admin_url( 'admin.php?page=haniel-shop-core' );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'داشبورد', 'haniel-shop-core' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	/**
	 * افزودن منوها.
	 */
	public static function add_menu() {
		add_menu_page(
			__( 'هسته هنیل شاپ', 'haniel-shop-core' ),
			__( 'هنیل شاپ', 'haniel-shop-core' ),
			'manage_woocommerce',
			'haniel-shop-core',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-chart-area',
			56
		);

		add_submenu_page(
			'haniel-shop-core',
			__( 'داشبورد تحلیلی', 'haniel-shop-core' ),
			__( 'داشبورد', 'haniel-shop-core' ),
			'manage_woocommerce',
			'haniel-shop-core',
			array( __CLASS__, 'render_dashboard' )
		);

		add_submenu_page(
			'haniel-shop-core',
			__( 'مشتریان', 'haniel-shop-core' ),
			__( 'مشتریان', 'haniel-shop-core' ),
			'manage_woocommerce',
			'haniel-shop-core-customers',
			array( __CLASS__, 'render_customers' )
		);

		add_submenu_page(
			'haniel-shop-core',
			__( 'منابع ترافیک', 'haniel-shop-core' ),
			__( 'منابع ترافیک', 'haniel-shop-core' ),
			'manage_woocommerce',
			'haniel-shop-core-sources',
			array( __CLASS__, 'render_sources' )
		);

		add_submenu_page(
			'haniel-shop-core',
			__( 'تنظیمات', 'haniel-shop-core' ),
			__( 'تنظیمات', 'haniel-shop-core' ),
			'manage_woocommerce',
			'haniel-shop-core-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * بارگذاری استایل و اسکریپت (فقط در صفحات افزونه).
	 *
	 * @param string $hook هوک صفحه.
	 */
	public static function enqueue( $hook ) {
		if ( false === strpos( $hook, 'haniel-shop-core' ) ) {
			return;
		}
		wp_enqueue_style( 'hsc-admin', HSC_PLUGIN_URL . 'assets/css/admin.css', array(), HSC_VERSION );
		wp_enqueue_script( 'hsc-admin', HSC_PLUGIN_URL . 'assets/js/admin.js', array(), HSC_VERSION, true );

		$data = HSC_Analytics::dashboard();
		wp_localize_script(
			'hsc-admin',
			'hscData',
			array(
				'nonce'    => wp_create_nonce( 'hsc_admin' ),
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'currency' => get_woocommerce_currency_symbol(),
				'charts'   => array(
					'segments' => $data['segments'],
					'sources'  => $data['sources'],
					'monthly'  => $data['monthly'],
				),
				'i18n'     => array(
					'rebuilding' => __( 'در حال محاسبه…', 'haniel-shop-core' ),
					'done'       => __( 'محاسبه کامل شد ✅', 'haniel-shop-core' ),
					'error'      => __( 'خطا در محاسبه', 'haniel-shop-core' ),
					'customers'  => __( 'مشتری', 'haniel-shop-core' ),
					'revenue'    => __( 'درآمد', 'haniel-shop-core' ),
				),
			)
		);
	}

	/**
	 * AJAX: بازسازی دستی جمع‌بندی.
	 */
	public static function ajax_rebuild() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی مجاز نیست.', 'haniel-shop-core' ) ), 403 );
		}
		check_ajax_referer( 'hsc_admin', 'nonce' );

		HSC_Aggregator::run_full_rebuild();

		wp_send_json_success(
			array(
				'message'      => __( 'محاسبه کامل شد.', 'haniel-shop-core' ),
				'last_rebuild' => get_option( 'hsc_last_rebuild', '' ),
			)
		);
	}

	/**
	 * رندر داشبورد.
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$data = HSC_Analytics::dashboard();
		require HSC_PLUGIN_DIR . 'includes/views/dashboard.php';
	}

	/**
	 * رندر صفحهٔ مشتریان (لیست یا پروفایل).
	 */
	public static function render_customers() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'profile' === $view && isset( $_GET['customer'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$customer_id = absint( $_GET['customer'] ); // phpcs:ignore WordPress.Security.NonceVerification
			$customer    = self::get_customer( $customer_id );
			require HSC_PLUGIN_DIR . 'includes/views/customer-profile.php';
			return;
		}
		require HSC_PLUGIN_DIR . 'includes/views/customers.php';
	}

	/**
	 * رندر صفحهٔ منابع ترافیک.
	 */
	public static function render_sources() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$data = HSC_Analytics::dashboard();
		require HSC_PLUGIN_DIR . 'includes/views/sources.php';
	}

	/**
	 * رندر صفحهٔ تنظیمات.
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s = HSC_Settings::all();
		require HSC_PLUGIN_DIR . 'includes/views/settings.php';
	}

	/**
	 * دریافت یک مشتری از جدول جمع‌بندی.
	 *
	 * @param int $id شناسه.
	 * @return object|null
	 */
	public static function get_customer( $id ) {
		global $wpdb;
		$t = $wpdb->prefix . HSC_TABLE;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB
	}
}
