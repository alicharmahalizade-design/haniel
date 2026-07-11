<?php
/**
 * نمای تنظیمات.
 *
 * @package HanielShopCore
 * @var array $s تنظیمات فعلی.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wc_statuses   = HSC_Settings::wc_statuses();
$paid_statuses = (array) $s['paid_statuses'];
$next_cron     = wp_next_scheduled( 'hsc_daily_aggregate' );
?>
<div class="wrap hsc-wrap" dir="rtl">
	<h1 class="hsc-title"><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'تنظیمات هسته هنیل شاپ', 'haniel-shop-core' ); ?></h1>
	<p class="hsc-subtitle"><?php esc_html_e( 'رفتار تحلیل مشتریان را این‌جا تنظیم کنید. تغییرات در محاسبهٔ بعدی اعمال می‌شوند.', 'haniel-shop-core' ); ?></p>

	<form method="post" action="options.php" class="hsc-form">
		<?php settings_fields( 'hsc_settings_group' ); ?>

		<div class="hsc-card">
			<h2 class="hsc-card-title"><?php esc_html_e( 'محاسبهٔ خرید', 'haniel-shop-core' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'وضعیت‌های معتبر سفارش', 'haniel-shop-core' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( $wc_statuses as $key => $label ) : ?>
								<label style="display:inline-block;margin:0 0 8px 16px">
									<input type="checkbox" name="hsc_settings[paid_statuses][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $paid_statuses, true ) ); ?>>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'فقط سفارش‌هایی با این وضعیت‌ها به‌عنوان «خرید واقعی» در تحلیل شمرده می‌شوند.', 'haniel-shop-core' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="hsc-card">
			<h2 class="hsc-card-title"><?php esc_html_e( 'آستانه‌های دسته‌بندی', 'haniel-shop-core' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="hsc_loyal"><?php esc_html_e( 'حداقل خرید برای «وفادار»', 'haniel-shop-core' ); ?></label></th>
					<td>
						<input type="number" min="1" id="hsc_loyal" name="hsc_settings[loyal_min_orders]" value="<?php echo esc_attr( $s['loyal_min_orders'] ); ?>" class="small-text">
						<span class="description"><?php esc_html_e( 'تعداد سفارش', 'haniel-shop-core' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hsc_churn"><?php esc_html_e( 'مرز «در معرض ریزش»', 'haniel-shop-core' ); ?></label></th>
					<td>
						<input type="number" min="7" id="hsc_churn" name="hsc_settings[churn_days]" value="<?php echo esc_attr( $s['churn_days'] ); ?>" class="small-text">
						<span class="description"><?php esc_html_e( 'روز بی‌خریدی', 'haniel-shop-core' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hsc_new"><?php esc_html_e( 'بازهٔ «تازه‌وارد»', 'haniel-shop-core' ); ?></label></th>
					<td>
						<input type="number" min="1" id="hsc_new" name="hsc_settings[new_days]" value="<?php echo esc_attr( $s['new_days'] ); ?>" class="small-text">
						<span class="description"><?php esc_html_e( 'روز از اولین خرید', 'haniel-shop-core' ); ?></span>
					</td>
				</tr>
			</table>
		</div>

		<div class="hsc-card">
			<h2 class="hsc-card-title"><?php esc_html_e( 'ردیابی منبع ترافیک', 'haniel-shop-core' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'فعال‌سازی ردیابی منبع', 'haniel-shop-core' ); ?></th>
					<td>
						<label class="hsc-switch">
							<input type="checkbox" name="hsc_settings[track_source]" value="yes" <?php checked( 'yes', $s['track_source'] ); ?>>
							<span></span>
						</label>
						<p class="description"><?php esc_html_e( 'با یک کوکی سبک، منبع اولین ورودِ هر بازدیدکننده ثبت و هنگام خرید به سفارش الصاق می‌شود.', 'haniel-shop-core' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hsc_cookie"><?php esc_html_e( 'ماندگاری کوکی منبع', 'haniel-shop-core' ); ?></label></th>
					<td>
						<input type="number" min="1" id="hsc_cookie" name="hsc_settings[source_cookie_days]" value="<?php echo esc_attr( $s['source_cookie_days'] ); ?>" class="small-text">
						<span class="description"><?php esc_html_e( 'روز', 'haniel-shop-core' ); ?></span>
					</td>
				</tr>
			</table>
		</div>

		<div class="hsc-card">
			<h2 class="hsc-card-title"><?php esc_html_e( 'حریم خصوصی', 'haniel-shop-core' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'حذف داده‌ها هنگام حذف افزونه', 'haniel-shop-core' ); ?></th>
					<td>
						<label class="hsc-switch">
							<input type="checkbox" name="hsc_settings[delete_on_uninstall]" value="yes" <?php checked( 'yes', $s['delete_on_uninstall'] ); ?>>
							<span></span>
						</label>
						<p class="description"><?php esc_html_e( 'در صورت فعال بودن، هنگام حذف افزونه جدول جمع‌بندی و تنظیمات نیز پاک می‌شوند (سفارش‌های ووکامرس دست‌نخورده می‌مانند).', 'haniel-shop-core' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button( __( 'ذخیرهٔ تنظیمات', 'haniel-shop-core' ) ); ?>
	</form>

	<div class="hsc-card">
		<h2 class="hsc-card-title"><?php esc_html_e( 'وضعیت پس‌زمینه', 'haniel-shop-core' ); ?></h2>
		<p>
			<?php esc_html_e( 'آخرین محاسبهٔ کامل:', 'haniel-shop-core' ); ?>
			<strong><?php echo esc_html( get_option( 'hsc_last_rebuild' ) ? wp_date( 'Y/m/d H:i', strtotime( get_option( 'hsc_last_rebuild' ) ) ) : __( 'هنوز انجام نشده', 'haniel-shop-core' ) ); ?></strong>
		</p>
		<p>
			<?php esc_html_e( 'محاسبهٔ خودکار بعدی:', 'haniel-shop-core' ); ?>
			<strong><?php echo esc_html( $next_cron ? wp_date( 'Y/m/d H:i', $next_cron ) : __( 'زمان‌بندی نشده', 'haniel-shop-core' ) ); ?></strong>
		</p>
		<p class="description"><?php esc_html_e( 'محاسبهٔ کامل هر شب به‌صورت خودکار انجام می‌شود و داده‌های تک‌تک مشتریان هم بلافاصله پس از هر سفارش به‌روز می‌شوند.', 'haniel-shop-core' ); ?></p>
	</div>
</div>
