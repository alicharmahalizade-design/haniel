<?php
/**
 * مدیریت تنظیمات افزونه و صفحه تنظیمات مدیریت.
 *
 * @package KhabaramKon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KK_Settings {

	const OPTION = 'kk_settings';

	/**
	 * کش تنظیمات.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * مقادیر پیش‌فرض تنظیمات.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			// عمومی.
			'enabled'              => 'yes',
			'enable_simple'        => 'yes',
			'enable_variable'      => 'yes',
			'button_position'      => 'woocommerce_single_product_summary',
			'button_priority'      => 31,
			'hide_add_to_cart'     => 'yes',

			// محتوای دکمه و فرم.
			'button_text'          => 'خبرم کن',
			'button_icon'          => 'yes',
			'form_title'           => 'به‌محض موجود شدن، باخبرت می‌کنیم',
			'form_desc'            => 'شماره موبایلت را وارد کن تا وقتی این محصول موجود شد برایت پیامک بفرستیم.',
			'phone_label'          => 'شماره موبایل',
			'phone_placeholder'    => 'مثلاً 09123456789',
			'submit_text'          => 'ثبت درخواست',
			'success_message'      => 'درخواست شما ثبت شد ✅ به‌محض موجود شدن محصول برایتان پیامک می‌کنیم.',
			'already_message'      => 'شما قبلاً برای این محصول درخواست داده‌اید.',
			'privacy_note'         => 'شماره شما فقط برای اطلاع‌رسانی همین محصول استفاده می‌شود.',

			// طراحی.
			'btn_bg'               => '#111827',
			'btn_color'            => '#ffffff',
			'btn_bg_hover'         => '#374151',
			'btn_radius'           => 10,
			'btn_full_width'       => 'yes',
			'accent_color'         => '#2563eb',

			// پیامک.
			'sms_gateway'          => 'kavenegar',
			'sms_api_key'          => '',
			'sms_username'         => '',
			'sms_password'         => '',
			'sms_sender'           => '',
			'sms_pattern'          => '',
			'pattern_var_product'  => 'product',
			'pattern_var_link'     => 'link',
			'sms_message'          => 'محصول «{product}» موجود شد ✅' . "\n" . 'لینک خرید: {shortlink}',

			// گزارش تبدیل.
			'conversion_enabled'   => 'yes',
			'utm_source'           => 'sms',
			'utm_medium'           => 'khabaram-kon',
			'utm_campaign'         => 'back-in-stock',
			'attribution_window_days' => 14,

			// اعلان مدیر (تقاضای بالا).
			'demand_alert_enabled'    => 'no',
			'demand_threshold'        => 10,
			'demand_alert_email'      => '',
			'demand_alert_sms_phone'  => '',
			'telegram_bot_token'      => '',
			'telegram_chat_id'        => '',

			// لینک کوتاه.
			'shortlink_enabled'    => 'yes',
			'shortlink_slug'       => 'kh',
			'shortlink_provider'   => 'internal',

			// پیشرفته.
			'store_user_phone'     => 'yes',
			'delete_on_uninstall'  => 'no',
			'notify_admin_email'   => '',
		);
	}

	/**
	 * دریافت همه تنظیمات.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			self::$cache = wp_parse_args( get_option( self::OPTION, array() ), self::get_defaults() );
		}
		return self::$cache;
	}

	/**
	 * دریافت یک مقدار تنظیم.
	 *
	 * @param string $key     کلید.
	 * @param mixed  $default پیش‌فرض.
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	/**
	 * آیا مقدار «بله» است؟
	 *
	 * @param string $key کلید.
	 * @return bool
	 */
	public static function is( $key ) {
		return 'yes' === self::get( $key );
	}

	/**
	 * راه‌اندازی هوک‌های مدیریت.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KK_PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * لینک تنظیمات در لیست افزونه‌ها.
	 *
	 * @param array $links لینک‌ها.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url  = admin_url( 'admin.php?page=khabaram-kon' );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'تنظیمات', 'khabaram-kon' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	/**
	 * افزودن منوی مدیریت.
	 */
	public static function add_menu() {
		add_menu_page(
			__( 'خبرم کن', 'khabaram-kon' ),
			__( 'خبرم کن', 'khabaram-kon' ),
			'manage_woocommerce',
			'khabaram-kon',
			array( __CLASS__, 'render_page' ),
			'dashicons-megaphone',
			58
		);

		add_submenu_page(
			'khabaram-kon',
			__( 'تنظیمات', 'khabaram-kon' ),
			__( 'تنظیمات', 'khabaram-kon' ),
			'manage_woocommerce',
			'khabaram-kon',
			array( __CLASS__, 'render_page' )
		);

		add_submenu_page(
			'khabaram-kon',
			__( 'درخواست‌ها', 'khabaram-kon' ),
			__( 'درخواست‌ها', 'khabaram-kon' ),
			'manage_woocommerce',
			'khabaram-kon-subscriptions',
			array( 'KK_Admin_List', 'render_page' )
		);
	}

	/**
	 * ثبت تنظیمات.
	 */
	public static function register_settings() {
		register_setting(
			'kk_settings_group',
			self::OPTION,
			array( __CLASS__, 'sanitize' )
		);
	}

	/**
	 * پاک‌سازی و اعتبارسنجی ورودی‌ها.
	 *
	 * @param array $input ورودی خام.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::get_defaults();
		$output   = self::all();
		$input    = is_array( $input ) ? $input : array();

		$yes_no      = array( 'enabled', 'enable_simple', 'enable_variable', 'hide_add_to_cart', 'button_icon', 'btn_full_width', 'shortlink_enabled', 'store_user_phone', 'delete_on_uninstall', 'conversion_enabled', 'demand_alert_enabled' );
		$text_fields = array( 'button_text', 'phone_label', 'phone_placeholder', 'submit_text', 'form_title', 'sms_sender', 'sms_pattern', 'shortlink_slug', 'sms_username', 'button_position', 'pattern_var_product', 'pattern_var_link', 'utm_source', 'utm_medium', 'utm_campaign', 'demand_alert_sms_phone', 'telegram_bot_token', 'telegram_chat_id' );
		$area_fields = array( 'form_desc', 'success_message', 'already_message', 'privacy_note', 'sms_message' );
		$color_fields = array( 'btn_bg', 'btn_color', 'btn_bg_hover', 'accent_color' );

		foreach ( $defaults as $key => $default ) {
			if ( in_array( $key, $yes_no, true ) ) {
				$output[ $key ] = ( isset( $input[ $key ] ) && 'yes' === $input[ $key ] ) ? 'yes' : 'no';
			} elseif ( in_array( $key, $area_fields, true ) ) {
				$output[ $key ] = isset( $input[ $key ] ) ? sanitize_textarea_field( wp_unslash( $input[ $key ] ) ) : $default;
			} elseif ( in_array( $key, $color_fields, true ) ) {
				$output[ $key ] = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : $default;
			} elseif ( 'sms_sender' === $key || 'sms_pattern' === $key || 'demand_alert_sms_phone' === $key || 'sms_api_key' === $key ) {
				// فیلدهای باید-ASCII: ارقام فارسی/عربی به لاتین تبدیل شوند.
				$val            = isset( $input[ $key ] ) ? sanitize_text_field( wp_unslash( $input[ $key ] ) ) : $default;
				$output[ $key ] = KK_SMS::en_digits( $val );
			} elseif ( in_array( $key, $text_fields, true ) ) {
				$output[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( wp_unslash( $input[ $key ] ) ) : $default;
			} elseif ( 'btn_radius' === $key || 'button_priority' === $key || 'attribution_window_days' === $key || 'demand_threshold' === $key ) {
				$output[ $key ] = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : $default;
			} elseif ( 'sms_password' === $key ) {
				$output[ $key ] = isset( $input[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $input[ $key ] ) ) ) : $default;
			} elseif ( 'notify_admin_email' === $key || 'demand_alert_email' === $key ) {
				$output[ $key ] = isset( $input[ $key ] ) ? sanitize_email( $input[ $key ] ) : $default;
			} elseif ( 'sms_gateway' === $key ) {
				$allowed        = array_keys( KK_SMS::gateways() );
				$val            = isset( $input[ $key ] ) ? sanitize_key( $input[ $key ] ) : $default;
				$output[ $key ] = in_array( $val, $allowed, true ) ? $val : $default;
			} elseif ( 'shortlink_provider' === $key ) {
				$val            = isset( $input[ $key ] ) ? sanitize_key( $input[ $key ] ) : $default;
				$output[ $key ] = in_array( $val, array( 'internal', 'none' ), true ) ? $val : $default;
			} else {
				$output[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( wp_unslash( $input[ $key ] ) ) : $default;
			}
		}

		self::$cache = null;
		return $output;
	}

	/**
	 * بارگذاری اسکریپت و استایل مدیریت.
	 *
	 * @param string $hook هوک صفحه.
	 */
	public static function enqueue_admin( $hook ) {
		if ( false === strpos( $hook, 'khabaram-kon' ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'kk-admin', KK_PLUGIN_URL . 'assets/css/admin.css', array(), KK_VERSION );
		wp_enqueue_script( 'kk-admin', KK_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), KK_VERSION, true );
		wp_localize_script(
			'kk-admin',
			'kkAdmin',
			array( 'nonce' => wp_create_nonce( 'kk_subscribe' ) )
		);
	}

	/**
	 * رندر صفحه تنظیمات.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s        = self::all();
		$gateways = KK_SMS::gateways();
		require KK_PLUGIN_DIR . 'includes/views/settings-page.php';
	}
}
