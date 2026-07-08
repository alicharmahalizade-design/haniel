<?php
/**
 * Plugin Name:       خبرم کن
 * Plugin URI:        https://haniel.example/khabaram-kon
 * Description:       نمایش دکمه «خبرم کن» روی محصولات ناموجود ووکامرس (ساده و متغیر). کاربر شماره‌اش را ثبت می‌کند و به‌محض موجود شدن محصول، پیامک همراه با لینک کوتاه دریافت می‌کند.
 * Version:           1.5.1
 * Author:            Haniel
 * Author URI:        https://haniel.example
 * Text Domain:       khabaram-kon
 * Domain Path:       /languages
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * WC requires at least: 4.0
 * WC tested up to:   9.0
 *
 * @package KhabaramKon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // جلوگیری از دسترسی مستقیم
}

define( 'KK_VERSION', '1.5.1' );
define( 'KK_PLUGIN_FILE', __FILE__ );
define( 'KK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KK_TABLE', 'kk_subscriptions' );

/**
 * اعلام سازگاری با HPOS ووکامرس.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

// بارگذاری کلاس‌ها.
require_once KK_PLUGIN_DIR . 'includes/class-kk-install.php';
require_once KK_PLUGIN_DIR . 'includes/class-kk-settings.php';
require_once KK_PLUGIN_DIR . 'includes/class-kk-sms.php';
require_once KK_PLUGIN_DIR . 'includes/class-kk-shortlink.php';
require_once KK_PLUGIN_DIR . 'includes/class-kk-frontend.php';
require_once KK_PLUGIN_DIR . 'includes/class-kk-ajax.php';
require_once KK_PLUGIN_DIR . 'includes/class-kk-stock.php';
require_once KK_PLUGIN_DIR . 'includes/class-kk-conversion.php';
require_once KK_PLUGIN_DIR . 'includes/class-kk-alerts.php';
require_once KK_PLUGIN_DIR . 'includes/class-kk-admin-list.php';
require_once KK_PLUGIN_DIR . 'includes/class-khabaram-kon.php';

// نصب و حذف.
register_activation_hook( __FILE__, array( 'KK_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'KK_Install', 'deactivate' ) );

/**
 * راه‌اندازی افزونه پس از بارگذاری همه افزونه‌ها.
 */
function kk_run() {
	// نیازمند ووکامرس.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'افزونه «خبرم کن» برای کار کردن به ووکامرس نیاز دارد. لطفاً ابتدا ووکامرس را نصب و فعال کنید.', 'khabaram-kon' );
				echo '</p></div>';
			}
		);
		return;
	}

	Khabaram_Kon::instance();
}
add_action( 'plugins_loaded', 'kk_run', 20 );

/**
 * بارگذاری فایل ترجمه.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'khabaram-kon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);
