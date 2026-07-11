<?php
/**
 * نمای تحلیل منابع ترافیک.
 *
 * @package HanielShopCore
 * @var array $data خروجی HSC_Analytics::dashboard().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sources = $data['sources'];
$total   = 0;
foreach ( $sources as $s ) {
	$total += $s['count'];
}
?>
<div class="wrap hsc-wrap" dir="rtl">
	<h1 class="hsc-title"><span class="dashicons dashicons-share"></span> <?php esc_html_e( 'تحلیل منابع ترافیک', 'haniel-shop-core' ); ?></h1>
	<p class="hsc-subtitle"><?php esc_html_e( 'هر مشتری از کدام کانال وارد شده و کدام کانال بیشترین درآمد را می‌سازد.', 'haniel-shop-core' ); ?></p>

	<div class="hsc-card">
		<h2 class="hsc-card-title"><span class="dashicons dashicons-chart-pie"></span> <?php esc_html_e( 'سهم هر منبع', 'haniel-shop-core' ); ?></h2>
		<div class="hsc-chart-row">
			<div class="hsc-chart-donut"><canvas id="hsc-chart-sources" width="240" height="240"></canvas></div>
			<div class="hsc-source-table-wrap">
				<table class="hsc-source-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'منبع', 'haniel-shop-core' ); ?></th>
							<th><?php esc_html_e( 'مشتری', 'haniel-shop-core' ); ?></th>
							<th><?php esc_html_e( 'سهم', 'haniel-shop-core' ); ?></th>
							<th><?php esc_html_e( 'درآمد', 'haniel-shop-core' ); ?></th>
							<th><?php esc_html_e( 'میانگین هر مشتری', 'haniel-shop-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sources as $s ) : ?>
							<?php $pct = $total > 0 ? round( $s['count'] / $total * 100, 1 ) : 0; ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=haniel-shop-core-customers&source=' . $s['key'] ) ); ?>">
										<span class="hsc-dot" style="background:<?php echo esc_attr( $s['color'] ); ?>"></span>
										<?php echo esc_html( $s['icon'] . ' ' . $s['label'] ); ?>
									</a>
								</td>
								<td><?php echo esc_html( number_format_i18n( $s['count'] ) ); ?></td>
								<td>
									<div class="hsc-progress"><span style="width:<?php echo esc_attr( $pct ); ?>%;background:<?php echo esc_attr( $s['color'] ); ?>"></span></div>
									<small><?php echo esc_html( $pct ); ?>%</small>
								</td>
								<td><?php echo wp_kses_post( wc_price( $s['revenue'] ) ); ?></td>
								<td><?php echo wp_kses_post( wc_price( $s['count'] > 0 ? $s['revenue'] / $s['count'] : 0 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						<?php if ( empty( $sources ) ) : ?>
							<tr><td colspan="5" class="hsc-muted"><?php esc_html_e( 'هنوز داده‌ای ثبت نشده است.', 'haniel-shop-core' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<p class="hsc-footnote">
		<span class="dashicons dashicons-info"></span>
		<?php esc_html_e( 'منبع بر اساس «اولین ورودِ» هر مشتری ثبت می‌شود (کوکی سبک، بدون نوشتن در دیتابیس روی بازدیدهای عادی). برای دقت بیشتر می‌توانید در لینک‌های تبلیغاتی از پارامتر utm_source استفاده کنید.', 'haniel-shop-core' ); ?>
	</p>
</div>
