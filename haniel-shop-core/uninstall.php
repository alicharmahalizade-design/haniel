<?php
/**
 * حذف افزونه؛ در صورت فعال بودن گزینهٔ مربوطه، داده‌ها پاک می‌شوند.
 *
 * سفارش‌های ووکامرس هرگز حذف نمی‌شوند؛ فقط جدول جمع‌بندی و تنظیمات این افزونه.
 *
 * @package HanielShopCore
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'hsc_settings', array() );
$delete   = is_array( $settings ) && isset( $settings['delete_on_uninstall'] ) && 'yes' === $settings['delete_on_uninstall'];

if ( ! $delete ) {
	return;
}

global $wpdb;

// حذف جدول جمع‌بندی.
$table = $wpdb->prefix . 'hsc_customers';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB

// حذف تنظیمات و متادیتاها.
delete_option( 'hsc_settings' );
delete_option( 'hsc_db_version' );
delete_option( 'hsc_last_rebuild' );
delete_option( 'hsc_rfm_thresholds' );
delete_transient( 'hsc_dashboard_cache' );
delete_transient( 'hsc_rebuild_lock' );

// پاک‌کردن کرون‌ها.
wp_clear_scheduled_hook( 'hsc_daily_aggregate' );
wp_clear_scheduled_hook( 'hsc_full_rebuild' );
wp_clear_scheduled_hook( 'hsc_recompute_customer' );
