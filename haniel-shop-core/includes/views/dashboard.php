<?php
/**
 * نمای داشبورد تحلیلی.
 *
 * @package HanielShopCore
 * @var array $data خروجی HSC_Analytics::dashboard().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kpis     = $data['kpis'];
$segments = $data['segments'];
$sources  = $data['sources'];
$is_empty = HSC_Analytics::is_empty();
?>
<div class="wrap hsc-wrap" dir="rtl">

	<div class="hsc-topbar">
		<div>
			<h1 class="hsc-title"><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'داشبورد تحلیل مشتریان', 'haniel-shop-core' ); ?></h1>
			<p class="hsc-subtitle"><?php esc_html_e( 'شناخت مشتری‌ها: چه کسانی وفادارند، چه کسانی پرخرید یا کم‌خرید هستند و از کجا آمده‌اند.', 'haniel-shop-core' ); ?></p>
		</div>
		<div class="hsc-topbar-actions">
			<span class="hsc-rebuild-time">
				<?php
				if ( $data['last_rebuild'] ) {
					/* translators: %s: date */
					echo esc_html( sprintf( __( 'آخرین محاسبه: %s', 'haniel-shop-core' ), wp_date( 'Y/m/d H:i', strtotime( $data['last_rebuild'] ) ) ) );
				} else {
					esc_html_e( 'هنوز محاسبه‌ای انجام نشده', 'haniel-shop-core' );
				}
				?>
			</span>
			<button type="button" class="button button-primary hsc-rebuild-btn" id="hsc-rebuild">
				<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'محاسبهٔ مجدد', 'haniel-shop-core' ); ?>
			</button>
		</div>
	</div>

	<?php if ( $is_empty ) : ?>
		<div class="hsc-empty-note">
			<span class="dashicons dashicons-info"></span>
			<?php esc_html_e( 'هنوز داده‌ای جمع‌بندی نشده است. محاسبهٔ اولیه به‌صورت خودکار در پس‌زمینه اجرا می‌شود؛ می‌توانید با دکمهٔ «محاسبهٔ مجدد» آن را همین حالا اجرا کنید.', 'haniel-shop-core' ); ?>
		</div>
	<?php endif; ?>

	<!-- شاخص‌های کلیدی -->
	<div class="hsc-kpis">
		<div class="hsc-kpi">
			<span class="hsc-kpi-icon" style="background:#dbeafe;color:#2563eb"><span class="dashicons dashicons-groups"></span></span>
			<div>
				<span class="hsc-kpi-num"><?php echo esc_html( number_format_i18n( $kpis['total_customers'] ) ); ?></span>
				<span class="hsc-kpi-label"><?php esc_html_e( 'کل مشتریان', 'haniel-shop-core' ); ?></span>
			</div>
		</div>
		<div class="hsc-kpi">
			<span class="hsc-kpi-icon" style="background:#dcfce7;color:#16a34a"><span class="dashicons dashicons-money-alt"></span></span>
			<div>
				<span class="hsc-kpi-num"><?php echo wp_kses_post( wc_price( $kpis['total_revenue'] ) ); ?></span>
				<span class="hsc-kpi-label"><?php esc_html_e( 'مجموع درآمد', 'haniel-shop-core' ); ?></span>
			</div>
		</div>
		<div class="hsc-kpi">
			<span class="hsc-kpi-icon" style="background:#fef3c7;color:#d97706"><span class="dashicons dashicons-cart"></span></span>
			<div>
				<span class="hsc-kpi-num"><?php echo esc_html( number_format_i18n( $kpis['total_orders'] ) ); ?></span>
				<span class="hsc-kpi-label"><?php esc_html_e( 'کل سفارش‌ها', 'haniel-shop-core' ); ?></span>
			</div>
		</div>
		<div class="hsc-kpi">
			<span class="hsc-kpi-icon" style="background:#ede9fe;color:#7c3aed"><span class="dashicons dashicons-chart-line"></span></span>
			<div>
				<span class="hsc-kpi-num"><?php echo wp_kses_post( wc_price( $kpis['avg_ltv'] ) ); ?></span>
				<span class="hsc-kpi-label"><?php esc_html_e( 'ارزش عمر هر مشتری (LTV)', 'haniel-shop-core' ); ?></span>
			</div>
		</div>
		<div class="hsc-kpi">
			<span class="hsc-kpi-icon" style="background:#cffafe;color:#0891b2"><span class="dashicons dashicons-update"></span></span>
			<div>
				<span class="hsc-kpi-num"><?php echo esc_html( number_format_i18n( $kpis['repeat_customers'] ) ); ?> <small>(<?php echo esc_html( $kpis['repeat_rate'] ); ?>%)</small></span>
				<span class="hsc-kpi-label"><?php esc_html_e( 'مشتریان تکرارکننده', 'haniel-shop-core' ); ?></span>
			</div>
		</div>
		<div class="hsc-kpi">
			<span class="hsc-kpi-icon" style="background:#fee2e2;color:#dc2626"><span class="dashicons dashicons-warning"></span></span>
			<div>
				<span class="hsc-kpi-num"><?php echo esc_html( number_format_i18n( $kpis['at_risk'] ) ); ?></span>
				<span class="hsc-kpi-label"><?php esc_html_e( 'در معرض ریزش', 'haniel-shop-core' ); ?></span>
			</div>
		</div>
	</div>

	<div class="hsc-grid">
		<!-- دسته‌بندی مشتریان -->
		<div class="hsc-card hsc-col-2">
			<h2 class="hsc-card-title"><span class="dashicons dashicons-chart-pie"></span> <?php esc_html_e( 'دسته‌بندی هوشمند مشتریان (RFM)', 'haniel-shop-core' ); ?></h2>
			<p class="hsc-card-hint"><?php esc_html_e( 'بر اساس تازگی خرید، تعداد خرید و مبلغ خرید.', 'haniel-shop-core' ); ?></p>
			<div class="hsc-chart-row">
				<div class="hsc-chart-donut">
					<canvas id="hsc-chart-segments" width="240" height="240"></canvas>
				</div>
				<div class="hsc-legend">
					<?php foreach ( $segments as $seg ) : ?>
						<a class="hsc-legend-item" href="<?php echo esc_url( admin_url( 'admin.php?page=haniel-shop-core-customers&segment=' . $seg['key'] ) ); ?>" title="<?php echo esc_attr( $seg['desc'] ); ?>">
							<span class="hsc-dot" style="background:<?php echo esc_attr( $seg['color'] ); ?>"></span>
							<span class="hsc-legend-label"><?php echo esc_html( $seg['emoji'] . ' ' . $seg['label'] ); ?></span>
							<span class="hsc-legend-val"><?php echo esc_html( number_format_i18n( $seg['count'] ) ); ?></span>
						</a>
					<?php endforeach; ?>
					<?php if ( empty( $segments ) ) : ?>
						<p class="hsc-muted"><?php esc_html_e( 'داده‌ای موجود نیست.', 'haniel-shop-core' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- منابع ترافیک -->
		<div class="hsc-card hsc-col-2">
			<h2 class="hsc-card-title"><span class="dashicons dashicons-share"></span> <?php esc_html_e( 'مشتری‌ها از کجا آمده‌اند؟', 'haniel-shop-core' ); ?></h2>
			<p class="hsc-card-hint"><?php esc_html_e( 'منبع اولین ورودِ هر مشتری (گوگل، اینستاگرام، مستقیم و…).', 'haniel-shop-core' ); ?></p>
			<div class="hsc-chart-row">
				<div class="hsc-chart-donut">
					<canvas id="hsc-chart-sources" width="240" height="240"></canvas>
				</div>
				<div class="hsc-legend">
					<?php foreach ( array_slice( $sources, 0, 8 ) as $src ) : ?>
						<a class="hsc-legend-item" href="<?php echo esc_url( admin_url( 'admin.php?page=haniel-shop-core-customers&source=' . $src['key'] ) ); ?>">
							<span class="hsc-dot" style="background:<?php echo esc_attr( $src['color'] ); ?>"></span>
							<span class="hsc-legend-label"><?php echo esc_html( $src['icon'] . ' ' . $src['label'] ); ?></span>
							<span class="hsc-legend-val"><?php echo esc_html( number_format_i18n( $src['count'] ) ); ?></span>
						</a>
					<?php endforeach; ?>
					<?php if ( empty( $sources ) ) : ?>
						<p class="hsc-muted"><?php esc_html_e( 'داده‌ای موجود نیست.', 'haniel-shop-core' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- روند جذب مشتری -->
		<div class="hsc-card hsc-col-4">
			<h2 class="hsc-card-title"><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'روند جذب مشتری در ۱۲ ماه گذشته', 'haniel-shop-core' ); ?></h2>
			<canvas id="hsc-chart-monthly" height="90"></canvas>
		</div>

		<!-- برترین مشتریان -->
		<div class="hsc-card hsc-col-2">
			<h2 class="hsc-card-title"><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'پرخریدترین مشتریان', 'haniel-shop-core' ); ?></h2>
			<table class="hsc-mini-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'مشتری', 'haniel-shop-core' ); ?></th>
						<th><?php esc_html_e( 'خرید', 'haniel-shop-core' ); ?></th>
						<th><?php esc_html_e( 'مجموع', 'haniel-shop-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $data['top_customers'] as $c ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=haniel-shop-core-customers&view=profile&customer=' . (int) $c['id'] ) ); ?>">
									<?php echo esc_html( $c['display_name'] ? $c['display_name'] : $c['email'] ); ?>
								</a>
							</td>
							<td><?php echo esc_html( number_format_i18n( $c['orders_count'] ) ); ?></td>
							<td><?php echo wp_kses_post( wc_price( $c['total_spent'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $data['top_customers'] ) ) : ?>
						<tr><td colspan="3" class="hsc-muted"><?php esc_html_e( 'داده‌ای موجود نیست.', 'haniel-shop-core' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- شهرها -->
		<div class="hsc-card hsc-col-2">
			<h2 class="hsc-card-title"><span class="dashicons dashicons-location"></span> <?php esc_html_e( 'پرمشتری‌ترین شهرها', 'haniel-shop-core' ); ?></h2>
			<table class="hsc-mini-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'شهر', 'haniel-shop-core' ); ?></th>
						<th><?php esc_html_e( 'مشتری', 'haniel-shop-core' ); ?></th>
						<th><?php esc_html_e( 'درآمد', 'haniel-shop-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $data['top_cities'] as $city ) : ?>
						<tr>
							<td><?php echo esc_html( $city['city'] ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $city['c'] ) ); ?></td>
							<td><?php echo wp_kses_post( wc_price( $city['revenue'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $data['top_cities'] ) ) : ?>
						<tr><td colspan="3" class="hsc-muted"><?php esc_html_e( 'داده‌ای موجود نیست.', 'haniel-shop-core' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<p class="hsc-footnote">
		<span class="dashicons dashicons-shield"></span>
		<?php esc_html_e( 'همهٔ محاسبات در پس‌زمینه و روی جدول جداگانه انجام می‌شود؛ باز کردن این صفحه هیچ فشاری روی سایت نمی‌آورد.', 'haniel-shop-core' ); ?>
	</p>
</div>
