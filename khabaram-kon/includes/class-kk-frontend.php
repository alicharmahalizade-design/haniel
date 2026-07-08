<?php
/**
 * نمایش دکمه و فرم «خبرم کن» در فرانت.
 *
 * @package KhabaramKon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KK_Frontend {

	/**
	 * محل‌های نمایش قابل انتخاب.
	 *
	 * @return array
	 */
	public static function positions() {
		return array(
			'woocommerce_single_product_summary'       => __( 'صفحه محصول (کنار عنوان/قیمت)', 'khabaram-kon' ),
			'woocommerce_before_add_to_cart_form'      => __( 'قبل از فرم افزودن به سبد', 'khabaram-kon' ),
			'woocommerce_after_add_to_cart_form'       => __( 'بعد از فرم افزودن به سبد', 'khabaram-kon' ),
			'woocommerce_product_meta_start'           => __( 'ابتدای بخش مشخصات محصول', 'khabaram-kon' ),
			'woocommerce_after_single_product_summary' => __( 'بعد از خلاصه محصول', 'khabaram-kon' ),
			'shortcode'                                => __( 'فقط با شورت‌کد [khabaram_kon] (دستی)', 'khabaram-kon' ),
		);
	}

	/**
	 * راه‌اندازی هوک‌ها.
	 */
	public static function init() {
		if ( ! KK_Settings::is( 'enabled' ) ) {
			return;
		}

		$position = KK_Settings::get( 'button_position', 'woocommerce_single_product_summary' );
		$priority = (int) KK_Settings::get( 'button_priority', 31 );
		$allowed  = array_keys( self::positions() );

		// اتصال به هوک انتخاب‌شده (مگر حالت فقط-شورت‌کد).
		if ( in_array( $position, $allowed, true ) && 'shortcode' !== $position ) {
			add_action( $position, array( __CLASS__, 'render' ), $priority );
		}

		// شورت‌کد همیشه در دسترس است تا در هر جای قالب قابل استفاده باشد.
		add_shortcode( 'khabaram_kon', array( __CLASS__, 'shortcode' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		// مخفی کردن دکمه افزودن به سبد برای محصولات ناموجود.
		if ( KK_Settings::is( 'hide_add_to_cart' ) ) {
			add_action( 'wp', array( __CLASS__, 'maybe_hide_add_to_cart' ) );
		}
	}

	/**
	 * تعیین اینکه آیا برای محصول باید دکمه نمایش داده شود.
	 *
	 * @param WC_Product $product محصول.
	 * @return bool
	 */
	public static function should_display( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}
		if ( $product->is_type( 'simple' ) ) {
			return KK_Settings::is( 'enable_simple' ) && ! $product->is_in_stock();
		}
		if ( $product->is_type( 'variable' ) ) {
			// برای متغیر: با جاوااسکریپت هنگام انتخاب متغیر ناموجود نمایش داده می‌شود.
			return KK_Settings::is( 'enable_variable' );
		}
		return false;
	}

	/**
	 * مخفی کردن دکمه افزودن به سبد محصولات ساده ناموجود.
	 */
	public static function maybe_hide_add_to_cart() {
		if ( ! is_product() ) {
			return;
		}
		global $product;
		if ( $product instanceof WC_Product && $product->is_type( 'simple' ) && ! $product->is_in_stock() ) {
			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
		}
	}

	/**
	 * رندر روی هوک ووکامرس.
	 */
	public static function render() {
		global $product;
		echo self::get_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی داخلی و امن‌سازی‌شده است.
	}

	/**
	 * هندلر شورت‌کد [khabaram_kon].
	 *
	 * @param array $atts ویژگی‌ها.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts    = shortcode_atts( array( 'id' => 0 ), $atts, 'khabaram_kon' );
		$product = null;

		if ( ! empty( $atts['id'] ) ) {
			$product = wc_get_product( (int) $atts['id'] );
		} elseif ( ! empty( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof WC_Product ) {
			$product = $GLOBALS['product'];
		} elseif ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_the_ID() );
		}

		return self::get_html( $product );
	}

	/**
	 * ساخت HTML دکمه/فرم برای یک محصول.
	 *
	 * @param WC_Product|null $product محصول.
	 * @return string
	 */
	public static function get_html( $product ) {
		if ( ! self::should_display( $product ) ) {
			return '';
		}

		self::enqueue_assets();

		$s           = KK_Settings::all();
		$is_variable = $product->is_type( 'variable' );
		$product_id  = $product->get_id();
		$known_phone = self::get_known_phone( wp_get_current_user() );

		$wrapper_class  = 'kk-wrapper';
		$wrapper_class .= 'popup' === $s['form_style'] ? ' kk-style-popup' : ' kk-style-inline';
		if ( $is_variable ) {
			$wrapper_class .= ' kk-variable kk-hidden';
		}

		// متغیرهای رنگ/طراحی به‌صورت inline تا در هر جای سایت (از جمله شورت‌کد) کار کند.
		$style = sprintf(
			'--kk-bg:%1$s;--kk-color:%2$s;--kk-bg-hover:%3$s;--kk-accent:%4$s;--kk-radius:%5$dpx;--kk-width:%6$s;',
			$s['btn_bg'],
			$s['btn_color'],
			$s['btn_bg_hover'],
			$s['accent_color'],
			(int) $s['btn_radius'],
			'yes' === $s['btn_full_width'] ? '100%' : 'auto'
		);

		$icon = KK_Settings::is( 'button_icon' ) ? '<svg class="kk-bell" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>' : '';

		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>" style="<?php echo esc_attr( $style ); ?>" data-product="<?php echo esc_attr( $product_id ); ?>">
			<button type="button" class="kk-button" data-known-phone="<?php echo $known_phone ? '1' : '0'; ?>">
				<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="kk-button-text"><?php echo esc_html( $s['button_text'] ); ?></span>
			</button>

			<div class="kk-form<?php echo 'popup' === $s['form_style'] ? ' kk-form--popup' : ''; ?>" data-product="<?php echo esc_attr( $product_id ); ?>" hidden>
				<div class="kk-form-inner">
					<button type="button" class="kk-close" aria-label="<?php esc_attr_e( 'بستن', 'khabaram-kon' ); ?>">&times;</button>
					<?php if ( ! empty( $s['form_title'] ) ) : ?>
						<h4 class="kk-form-title"><?php echo esc_html( $s['form_title'] ); ?></h4>
					<?php endif; ?>
					<?php if ( ! empty( $s['form_desc'] ) ) : ?>
						<p class="kk-form-desc"><?php echo esc_html( $s['form_desc'] ); ?></p>
					<?php endif; ?>

					<div class="kk-field">
						<label class="kk-label" for="kk-phone-<?php echo esc_attr( $product_id ); ?>"><?php echo esc_html( $s['phone_label'] ); ?></label>
						<input type="tel" id="kk-phone-<?php echo esc_attr( $product_id ); ?>" class="kk-phone" inputmode="numeric"
							value="<?php echo esc_attr( $known_phone ); ?>"
							placeholder="<?php echo esc_attr( $s['phone_placeholder'] ); ?>" dir="ltr">
					</div>

					<input type="hidden" class="kk-variation-id" value="0">
					<button type="button" class="kk-submit"><?php echo esc_html( $s['submit_text'] ); ?></button>

					<?php if ( ! empty( $s['privacy_note'] ) ) : ?>
						<p class="kk-privacy"><?php echo esc_html( $s['privacy_note'] ); ?></p>
					<?php endif; ?>
					<div class="kk-message" role="status" aria-live="polite"></div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * دریافت شماره ذخیره‌شده کاربر (در صورت فعال بودن).
	 *
	 * @param WP_User $user کاربر.
	 * @return string
	 */
	public static function get_known_phone( $user ) {
		if ( ! KK_Settings::is( 'store_user_phone' ) || ! $user || ! $user->exists() ) {
			return '';
		}
		$phone = get_user_meta( $user->ID, 'kk_phone', true );
		if ( empty( $phone ) ) {
			$phone = get_user_meta( $user->ID, 'billing_phone', true );
		}
		return KK_SMS::normalize_phone( $phone );
	}

	/**
	 * بارگذاری دارایی‌ها روی صفحه محصول (هوک).
	 */
	public static function enqueue() {
		if ( is_product() ) {
			self::enqueue_assets();
		}
	}

	/**
	 * ثبت واقعی اسکریپت و استایل (idempotent؛ برای شورت‌کد هم قابل فراخوانی).
	 */
	public static function enqueue_assets() {
		if ( wp_script_is( 'kk-frontend', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_style( 'kk-frontend', KK_PLUGIN_URL . 'assets/css/frontend.css', array(), KK_VERSION );
		wp_enqueue_script( 'kk-frontend', KK_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), KK_VERSION, true );
		wp_localize_script(
			'kk-frontend',
			'kkData',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'kk_subscribe' ),
				'successMessage' => KK_Settings::get( 'success_message' ),
				'alreadyMessage' => KK_Settings::get( 'already_message' ),
				'invalidPhone'   => __( 'شماره موبایل معتبر نیست. مثال: 09123456789', 'khabaram-kon' ),
				'genericError'   => __( 'خطایی رخ داد. دوباره تلاش کنید.', 'khabaram-kon' ),
				'sending'        => __( 'در حال ثبت...', 'khabaram-kon' ),
			)
		);
	}
}
