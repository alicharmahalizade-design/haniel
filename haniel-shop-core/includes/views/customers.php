<?php
/**
 * نمای لیست مشتریان.
 *
 * @package HanielShopCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$list = new HSC_Customers_Table();
$list->prepare_items();

$segment = isset( $_REQUEST['segment'] ) ? sanitize_key( $_REQUEST['segment'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$source  = isset( $_REQUEST['source'] ) ? sanitize_key( $_REQUEST['source'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
?>
<div class="wrap hsc-wrap" dir="rtl">
	<h1 class="hsc-title"><span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'مدیریت مشتریان', 'haniel-shop-core' ); ?></h1>
	<p class="hsc-subtitle"><?php esc_html_e( 'فهرست کامل مشتریان با فیلتر دسته و منبع؛ روی نام هر مشتری بزنید تا پروفایل کامل باز شود.', 'haniel-shop-core' ); ?></p>

	<?php
	// چیپ‌های میان‌بر دسته‌ها.
	$seg_keys = array( 'champion', 'loyal', 'big_spender', 'promising', 'new', 'needs_attention', 'at_risk', 'low_value', 'lost' );
	?>
	<div class="hsc-chips">
		<a class="hsc-chip <?php echo '' === $segment ? 'active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=haniel-shop-core-customers' ) ); ?>"><?php esc_html_e( 'همه', 'haniel-shop-core' ); ?></a>
		<?php foreach ( $seg_keys as $key ) : ?>
			<?php $info = HSC_Aggregator::segment_label( $key ); ?>
			<a class="hsc-chip <?php echo $segment === $key ? 'active' : ''; ?>" style="<?php echo $segment === $key ? 'background:' . esc_attr( $info['color'] ) . ';color:#fff;border-color:' . esc_attr( $info['color'] ) : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=haniel-shop-core-customers&segment=' . $key ) ); ?>">
				<?php echo esc_html( $info['emoji'] . ' ' . $info['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<form method="get">
		<input type="hidden" name="page" value="haniel-shop-core-customers">
		<?php
		$list->search_box( __( 'جستجوی نام، ایمیل یا موبایل', 'haniel-shop-core' ), 'hsc-search' );
		$list->display();
		?>
	</form>
</div>
