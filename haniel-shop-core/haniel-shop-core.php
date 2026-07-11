<?php
/**
 * Plugin Name:       هسته هنیل شاپ
 * Plugin URI:        https://haniel.example/haniel-shop-core
 * Description:       هستهٔ فروشگاهی هنیل؛ پنل حرفه‌ای مدیریت و آنالیز مشتریان ووکامرس. تحلیل RFM (وفاداری، تازگی و ارزش خرید)، تفکیک منابع ترافیک (چند مشتری از گوگل، اینستاگرام و…)، شناسایی مشتریان وفادار و پرخرید و کم‌خرید و در معرض ریزش — بدون کوچک‌ترین فشار روی سایت (همه‌ی محاسبات سنگین در پس‌زمینه انجام می‌شود).
 * Version:           1.0.0
 * Author:            Haniel
 * Author URI:        https://haniel.example
 * Text Domain:       haniel-shop-core
 * Domain Path:       /languages
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * WC requires at least: 4.0
 * WC tested up to:   9.0
 *
 * @package HanielShopCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // جلوگیری از دسترسی مستقیم.
}

define( 'HSC_VERSION', '1.0.0' );
define( 'HSC_PLUGIN_FILE', __FILE__ );
define( 'HSC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HSC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HSC_TABLE', 'hsc_customers' );

/**
 * اعلام سازگاری با HPOS (جداول سفارش سفارشی) ووکامرس.
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
require_once HSC_PLUGIN_DIR . 'includes/class-hsc-install.php';
require_once HSC_PLUGIN_DIR . 'includes/class-hsc-settings.php';
require_once HSC_PLUGIN_DIR . 'includes/class-hsc-source.php';
require_once HSC_PLUGIN_DIR . 'includes/class-hsc-aggregator.php';
require_once HSC_PLUGIN_DIR . 'includes/class-hsc-analytics.php';
require_once HSC_PLUGIN_DIR . 'includes/class-hsc-customers-table.php';
require_once HSC_PLUGIN_DIR . 'includes/class-hsc-admin.php';
require_once HSC_PLUGIN_DIR . 'includes/class-hsc-core.php';

// نصب و حذف.
register_activation_hook( __FILE__, array( 'HSC_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'HSC_Install', 'deactivate' ) );

/**
 * راه‌اندازی افزونه پس از بارگذاری همه افزونه‌ها.
 */
function hsc_run() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'افزونه «هسته هنیل شاپ» برای کار کردن به ووکامرس نیاز دارد. لطفاً ابتدا ووکامرس را نصب و فعال کنید.', 'haniel-shop-core' );
				echo '</p></div>';
			}
		);
		return;
	}

	HSC_Core::instance();
}
add_action( 'plugins_loaded', 'hsc_run', 20 );

/**
 * بارگذاری فایل ترجمه.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'haniel-shop-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);
