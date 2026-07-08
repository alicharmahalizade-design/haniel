<?php
/**
 * حذف داده‌ها هنگام حذف کامل افزونه (در صورت فعال بودن گزینه).
 *
 * @package KhabaramKon
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'kk_settings', array() );

if ( isset( $settings['delete_on_uninstall'] ) && 'yes' === $settings['delete_on_uninstall'] ) {
	global $wpdb;

	// حذف جدول اشتراک‌ها.
	$table = $wpdb->prefix . 'kk_subscriptions';
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB

	// حذف گزینه‌ها.
	delete_option( 'kk_settings' );
	delete_option( 'kk_db_version' );
	delete_option( 'kk_shortlink_clicks' );

	// حذف متای کاربران.
	delete_metadata( 'user', 0, 'kk_phone', '', true );
}
