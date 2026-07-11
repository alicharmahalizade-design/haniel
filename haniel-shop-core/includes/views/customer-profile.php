<?php
/**
 * نمای پروفایل تک‌مشتری.
 *
 * @package HanielShopCore
 * @var object|null $customer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$back = admin_url( 'admin.php?page=haniel-shop-core-customers' );

if ( ! $customer ) {
	echo '<div class="wrap hsc-wrap" dir="rtl"><h1>' . esc_html__( 'مشتری یافت نشد', 'haniel-shop-core' ) . '</h1>';
	echo '<p><a href="' . esc_url( $back ) . '">' . esc_html__( '« بازگشت به فهرست', 'haniel-shop-core' ) . '</a></p></div>';
	return;
}

$seg    = HSC_Aggregator::segment_label( $customer->segment );
$srcinf = HSC_Source::label( $customer->source );

// سفارش‌های اخیر همین مشتری (فقط هنگام باز کردن پروفایل؛ سبک و محدود).
$recent_orders = array();
if ( function_exists( 'wc_get_orders' ) ) {
	$recent_orders = wc_get_orders(
		array(
			'billing_email' => $customer->email,
			'limit'         => 10,
			'orderby'       => 'date',
			'order'         => 'DESC',
		)
	);
}
?>
<div class="wrap hsc-wrap hsc-profile" dir="rtl">
	<p class="hsc-back"><a href="<?php echo esc_url( $back ); ?>">&raquo; <?php esc_html_e( 'بازگشت به فهرست مشتریان', 'haniel-shop-core' ); ?></a></p>

	<div class="hsc-profile-head">
		<div class="hsc-avatar" style="background:<?php echo esc_attr( $seg['color'] ); ?>">
			<?php echo esc_html( mb_substr( $customer->display_name ? $customer->display_name : $customer->email, 0, 1 ) ); ?>
		</div>
		<div class="hsc-profile-meta">
			<h1><?php echo esc_html( $customer->display_name ? $customer->display_name : $customer->email ); ?></h1>
			<div class="hsc-profile-tags">
				<span class="hsc-badge" style="background:<?php echo esc_attr( $seg['color'] ); ?>"><?php echo esc_html( $seg['emoji'] . ' ' . $seg['label'] ); ?></span>
				<span class="hsc-src" style="color:<?php echo esc_attr( $srcinf['color'] ); ?>"><?php echo esc_html( $srcinf['icon'] . ' ' . $srcinf['label'] ); ?></span>
			</div>
			<p class="hsc-profile-contact">
				<span dir="ltr"><?php echo esc_html( $customer->email ); ?></span>
				<?php if ( $customer->phone ) : ?> · <span dir="ltr"><?php echo esc_html( $customer->phone ); ?></span><?php endif; ?>
				<?php if ( $customer->city ) : ?> · <?php echo esc_html( $customer->city ); ?><?php endif; ?>
			</p>
		</div>
		<?php if ( $customer->user_id ) : ?>
			<a class="button" href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . (int) $customer->user_id ) ); ?>"><?php esc_html_e( 'پروفایل کاربری', 'haniel-shop-core' ); ?></a>
		<?php endif; ?>
	</div>

	<p class="hsc-seg-desc"><?php echo esc_html( $seg['desc'] ); ?></p>

	<!-- شاخص‌های مشتری -->
	<div class="hsc-kpis hsc-kpis-4">
		<div class="hsc-kpi"><div><span class="hsc-kpi-num"><?php echo esc_html( number_format_i18n( $customer->orders_count ) ); ?></span><span class="hsc-kpi-label"><?php esc_html_e( 'تعداد خرید', 'haniel-shop-core' ); ?></span></div></div>
		<div class="hsc-kpi"><div><span class="hsc-kpi-num"><?php echo wp_kses_post( wc_price( $customer->total_spent ) ); ?></span><span class="hsc-kpi-label"><?php esc_html_e( 'مجموع خرید', 'haniel-shop-core' ); ?></span></div></div>
		<div class="hsc-kpi"><div><span class="hsc-kpi-num"><?php echo wp_kses_post( wc_price( $customer->avg_order_value ) ); ?></span><span class="hsc-kpi-label"><?php esc_html_e( 'میانگین سبد', 'haniel-shop-core' ); ?></span></div></div>
		<div class="hsc-kpi"><div><span class="hsc-kpi-num"><?php echo esc_html( number_format_i18n( $customer->recency_days ) ); ?> <small><?php esc_html_e( 'روز', 'haniel-shop-core' ); ?></small></span><span class="hsc-kpi-label"><?php esc_html_e( 'از آخرین خرید', 'haniel-shop-core' ); ?></span></div></div>
	</div>

	<div class="hsc-grid">
		<!-- امتیاز RFM -->
		<div class="hsc-card hsc-col-2">
			<h2 class="hsc-card-title"><span class="dashicons dashicons-analytics"></span> <?php esc_html_e( 'امتیاز RFM', 'haniel-shop-core' ); ?></h2>
			<div class="hsc-rfm">
				<?php
				$rfm = array(
					array( __( 'تازگی (R)', 'haniel-shop-core' ), (int) $customer->rfm_r, '#2563eb' ),
					array( __( 'تعداد (F)', 'haniel-shop-core' ), (int) $customer->rfm_f, '#16a34a' ),
					array( __( 'ارزش (M)', 'haniel-shop-core' ), (int) $customer->rfm_m, '#7c3aed' ),
				);
				foreach ( $rfm as $r ) :
					?>
					<div class="hsc-rfm-item">
						<span class="hsc-rfm-label"><?php echo esc_html( $r[0] ); ?></span>
						<span class="hsc-rfm-score" style="color:<?php echo esc_attr( $r[2] ); ?>"><?php echo esc_html( $r[1] ); ?><small>/۵</small></span>
						<div class="hsc-rfm-bar"><span style="width:<?php echo esc_attr( $r[1] * 20 ); ?>%;background:<?php echo esc_attr( $r[2] ); ?>"></span></div>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="hsc-card-hint">
				<?php
				printf(
					/* translators: 1: first order date, 2: last order date */
					esc_html__( 'اولین خرید: %1$s — آخرین خرید: %2$s', 'haniel-shop-core' ),
					esc_html( $customer->first_order_date ? wp_date( 'Y/m/d', strtotime( $customer->first_order_date ) ) : '—' ),
					esc_html( $customer->last_order_date ? wp_date( 'Y/m/d', strtotime( $customer->last_order_date ) ) : '—' )
				);
				?>
			</p>
		</div>

		<!-- سفارش‌های اخیر -->
		<div class="hsc-card hsc-col-2">
			<h2 class="hsc-card-title"><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'سفارش‌های اخیر', 'haniel-shop-core' ); ?></h2>
			<table class="hsc-mini-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'سفارش', 'haniel-shop-core' ); ?></th>
						<th><?php esc_html_e( 'تاریخ', 'haniel-shop-core' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'haniel-shop-core' ); ?></th>
						<th><?php esc_html_e( 'مبلغ', 'haniel-shop-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_orders as $order ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a></td>
							<td><?php echo esc_html( wp_date( 'Y/m/d', $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time() ) ); ?></td>
							<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
							<td><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $recent_orders ) ) : ?>
						<tr><td colspan="4" class="hsc-muted"><?php esc_html_e( 'سفارشی یافت نشد.', 'haniel-shop-core' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
