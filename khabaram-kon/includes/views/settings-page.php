<?php
/**
 * قالب صفحه تنظیمات.
 *
 * @package KhabaramKon
 * @var array $s        تنظیمات فعلی.
 * @var array $gateways درگاه‌های پیامک.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
$tabs       = array(
	'general' => __( 'عمومی', 'khabaram-kon' ),
	'content' => __( 'محتوا', 'khabaram-kon' ),
	'design'  => __( 'طراحی', 'khabaram-kon' ),
	'sms'        => __( 'پیامک', 'khabaram-kon' ),
	'link'       => __( 'لینک کوتاه', 'khabaram-kon' ),
	'conversion' => __( 'گزارش تبدیل', 'khabaram-kon' ),
	'alerts'     => __( 'اعلان مدیر', 'khabaram-kon' ),
	'advanced'   => __( 'پیشرفته', 'khabaram-kon' ),
);
?>
<div class="wrap kk-wrap" dir="rtl">
	<h1 class="kk-title"><span class="dashicons dashicons-megaphone"></span> <?php esc_html_e( 'خبرم کن', 'khabaram-kon' ); ?></h1>
	<p class="kk-subtitle"><?php esc_html_e( 'دکمه اطلاع‌رسانی موجود شدن محصول برای محصولات ناموجود ووکامرس.', 'khabaram-kon' ); ?></p>

	<h2 class="nav-tab-wrapper kk-tabs">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=khabaram-kon&tab=' . $tab_key ) ); ?>"
				class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</h2>

	<form method="post" action="options.php" class="kk-form">
		<?php settings_fields( 'kk_settings_group' ); ?>

		<?php // ------------- عمومی ------------- ?>
		<div class="kk-tab-panel" data-tab="general" style="<?php echo 'general' === $active_tab ? '' : 'display:none'; ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'فعال‌سازی افزونه', 'khabaram-kon' ); ?></th>
					<td><label class="kk-switch"><input type="checkbox" name="kk_settings[enabled]" value="yes" <?php checked( $s['enabled'], 'yes' ); ?>><span></span></label>
						<p class="description"><?php esc_html_e( 'نمایش دکمه خبرم کن روی سایت را کنترل می‌کند.', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'محصولات ساده', 'khabaram-kon' ); ?></th>
					<td><label class="kk-switch"><input type="checkbox" name="kk_settings[enable_simple]" value="yes" <?php checked( $s['enable_simple'], 'yes' ); ?>><span></span></label>
						<p class="description"><?php esc_html_e( 'نمایش دکمه روی محصولات ساده ناموجود.', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'محصولات متغیر', 'khabaram-kon' ); ?></th>
					<td><label class="kk-switch"><input type="checkbox" name="kk_settings[enable_variable]" value="yes" <?php checked( $s['enable_variable'], 'yes' ); ?>><span></span></label>
						<p class="description"><?php esc_html_e( 'نمایش دکمه هنگام انتخاب متغیر ناموجود.', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'مخفی کردن دکمه افزودن به سبد', 'khabaram-kon' ); ?></th>
					<td><label class="kk-switch"><input type="checkbox" name="kk_settings[hide_add_to_cart]" value="yes" <?php checked( $s['hide_add_to_cart'], 'yes' ); ?>><span></span></label>
						<p class="description"><?php esc_html_e( 'اگر محصول ناموجود است دکمه افزودن به سبد مخفی شود.', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="kk_button_priority"><?php esc_html_e( 'اولویت نمایش', 'khabaram-kon' ); ?></label></th>
					<td><input type="number" id="kk_button_priority" name="kk_settings[button_priority]" value="<?php echo esc_attr( $s['button_priority'] ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( 'محل قرارگیری دکمه در صفحه محصول (عدد کوچک‌تر = بالاتر). پیش‌فرض ۳۱.', 'khabaram-kon' ); ?></p></td>
				</tr>
			</table>
		</div>

		<?php // ------------- محتوا ------------- ?>
		<div class="kk-tab-panel" data-tab="content" style="<?php echo 'content' === $active_tab ? '' : 'display:none'; ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="kk_button_text"><?php esc_html_e( 'متن دکمه', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_button_text" name="kk_settings[button_text]" value="<?php echo esc_attr( $s['button_text'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'نمایش آیکون زنگوله', 'khabaram-kon' ); ?></th>
					<td><label class="kk-switch"><input type="checkbox" name="kk_settings[button_icon]" value="yes" <?php checked( $s['button_icon'], 'yes' ); ?>><span></span></label></td>
				</tr>
				<tr>
					<th><label for="kk_form_title"><?php esc_html_e( 'عنوان فرم', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_form_title" name="kk_settings[form_title]" value="<?php echo esc_attr( $s['form_title'] ); ?>" class="large-text"></td>
				</tr>
				<tr>
					<th><label for="kk_form_desc"><?php esc_html_e( 'توضیح فرم', 'khabaram-kon' ); ?></label></th>
					<td><textarea id="kk_form_desc" name="kk_settings[form_desc]" rows="2" class="large-text"><?php echo esc_textarea( $s['form_desc'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="kk_phone_label"><?php esc_html_e( 'برچسب شماره موبایل', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_phone_label" name="kk_settings[phone_label]" value="<?php echo esc_attr( $s['phone_label'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="kk_phone_placeholder"><?php esc_html_e( 'راهنمای داخل فیلد', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_phone_placeholder" name="kk_settings[phone_placeholder]" value="<?php echo esc_attr( $s['phone_placeholder'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="kk_submit_text"><?php esc_html_e( 'متن دکمه ثبت', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_submit_text" name="kk_settings[submit_text]" value="<?php echo esc_attr( $s['submit_text'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="kk_success_message"><?php esc_html_e( 'پیام موفقیت', 'khabaram-kon' ); ?></label></th>
					<td><textarea id="kk_success_message" name="kk_settings[success_message]" rows="2" class="large-text"><?php echo esc_textarea( $s['success_message'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="kk_already_message"><?php esc_html_e( 'پیام درخواست تکراری', 'khabaram-kon' ); ?></label></th>
					<td><textarea id="kk_already_message" name="kk_settings[already_message]" rows="2" class="large-text"><?php echo esc_textarea( $s['already_message'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="kk_privacy_note"><?php esc_html_e( 'یادداشت حریم خصوصی', 'khabaram-kon' ); ?></label></th>
					<td><textarea id="kk_privacy_note" name="kk_settings[privacy_note]" rows="2" class="large-text"><?php echo esc_textarea( $s['privacy_note'] ); ?></textarea></td>
				</tr>
			</table>
		</div>

		<?php // ------------- طراحی ------------- ?>
		<div class="kk-tab-panel" data-tab="design" style="<?php echo 'design' === $active_tab ? '' : 'display:none'; ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="kk_form_style"><?php esc_html_e( 'نحوه نمایش فرم', 'khabaram-kon' ); ?></label></th>
					<td>
						<select id="kk_form_style" name="kk_settings[form_style]">
							<option value="popup" <?php selected( $s['form_style'], 'popup' ); ?>><?php esc_html_e( 'پاپ‌آپ (مودال وسط صفحه)', 'khabaram-kon' ); ?></option>
							<option value="inline" <?php selected( $s['form_style'], 'inline' ); ?>><?php esc_html_e( 'درون‌خطی (زیر دکمه)', 'khabaram-kon' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'با کلیک روی دکمه، فرم به‌صورت پاپ‌آپ وسط صفحه باز شود یا زیر دکمه.', 'khabaram-kon' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'رنگ پس‌زمینه دکمه', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" name="kk_settings[btn_bg]" value="<?php echo esc_attr( $s['btn_bg'] ); ?>" class="kk-color"></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'رنگ متن دکمه', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" name="kk_settings[btn_color]" value="<?php echo esc_attr( $s['btn_color'] ); ?>" class="kk-color"></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'رنگ پس‌زمینه هنگام هاور', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" name="kk_settings[btn_bg_hover]" value="<?php echo esc_attr( $s['btn_bg_hover'] ); ?>" class="kk-color"></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'رنگ تاکیدی (لینک‌ها و فوکوس)', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" name="kk_settings[accent_color]" value="<?php echo esc_attr( $s['accent_color'] ); ?>" class="kk-color"></td>
				</tr>
				<tr>
					<th><label for="kk_btn_radius"><?php esc_html_e( 'گردی گوشه‌ها (px)', 'khabaram-kon' ); ?></label></th>
					<td><input type="number" id="kk_btn_radius" name="kk_settings[btn_radius]" value="<?php echo esc_attr( $s['btn_radius'] ); ?>" class="small-text" min="0" max="60"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'عرض کامل دکمه', 'khabaram-kon' ); ?></th>
					<td><label class="kk-switch"><input type="checkbox" name="kk_settings[btn_full_width]" value="yes" <?php checked( $s['btn_full_width'], 'yes' ); ?>><span></span></label></td>
				</tr>
			</table>
			<div class="kk-preview-box">
				<p class="description"><?php esc_html_e( 'پیش‌نمایش دکمه:', 'khabaram-kon' ); ?></p>
				<button type="button" id="kk-preview-btn" class="kk-preview-btn"><?php echo esc_html( $s['button_text'] ); ?></button>
			</div>
		</div>

		<?php // ------------- پیامک ------------- ?>
		<div class="kk-tab-panel" data-tab="sms" style="<?php echo 'sms' === $active_tab ? '' : 'display:none'; ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="kk_sms_gateway"><?php esc_html_e( 'درگاه پیامک', 'khabaram-kon' ); ?></label></th>
					<td>
						<select id="kk_sms_gateway" name="kk_settings[sms_gateway]">
							<?php foreach ( $gateways as $g_key => $g ) : ?>
								<option value="<?php echo esc_attr( $g_key ); ?>" <?php selected( $s['sms_gateway'], $g_key ); ?>><?php echo esc_html( $g['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'سرویس ارسال پیامک خود را انتخاب کنید.', 'khabaram-kon' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="kk_sms_api_key"><?php esc_html_e( 'کلید API / توکن', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_sms_api_key" name="kk_settings[sms_api_key]" value="<?php echo esc_attr( $s['sms_api_key'] ); ?>" class="regular-text" autocomplete="off">
						<p class="description"><?php esc_html_e( 'برای «فراز اس‌ام‌اس نسخه جدید» همان Api-Key پنل iranpayamak.com را وارد کنید.', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="kk_sms_username"><?php esc_html_e( 'نام کاربری', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_sms_username" name="kk_settings[sms_username]" value="<?php echo esc_attr( $s['sms_username'] ); ?>" class="regular-text" autocomplete="off">
						<p class="description"><?php esc_html_e( 'برای درگاه‌هایی که با نام کاربری/رمز کار می‌کنند (مثل ملی‌پیامک).', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="kk_sms_password"><?php esc_html_e( 'رمز عبور', 'khabaram-kon' ); ?></label></th>
					<td><input type="password" id="kk_sms_password" name="kk_settings[sms_password]" value="<?php echo esc_attr( $s['sms_password'] ); ?>" class="regular-text" autocomplete="off"></td>
				</tr>
				<tr>
					<th><label for="kk_sms_sender"><?php esc_html_e( 'شماره فرستنده / خط ارسال', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_sms_sender" name="kk_settings[sms_sender]" value="<?php echo esc_attr( $s['sms_sender'] ); ?>" class="regular-text" dir="ltr">
						<p class="description"><?php esc_html_e( 'برای «فراز اس‌ام‌اس نسخه جدید» همان line_number خط شما (مثلاً 3000505).', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="kk_sms_pattern"><?php esc_html_e( 'کد پترن / الگو', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_sms_pattern" name="kk_settings[sms_pattern]" value="<?php echo esc_attr( $s['sms_pattern'] ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'اختیاری. کد پترن/الگو برای ارسال پترن‌دار. اگر خالی باشد، پیامک با متن آزاد ارسال می‌شود.', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr class="kk-pattern-var">
					<th><label for="kk_pattern_var_product"><?php esc_html_e( 'نام متغیر «محصول» در پترن', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_pattern_var_product" name="kk_settings[pattern_var_product]" value="<?php echo esc_attr( $s['pattern_var_product'] ); ?>" class="regular-text" dir="ltr">
						<p class="description"><?php esc_html_e( 'برای فراز اس‌ام‌اس/ایران‌پیامک: نام دقیق متغیری که در پترن برای نام محصول تعریف کرده‌اید (مثلاً product).', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr class="kk-pattern-var">
					<th><label for="kk_pattern_var_link"><?php esc_html_e( 'نام متغیر «لینک» در پترن', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_pattern_var_link" name="kk_settings[pattern_var_link]" value="<?php echo esc_attr( $s['pattern_var_link'] ); ?>" class="regular-text" dir="ltr">
						<p class="description"><?php esc_html_e( 'نام دقیق متغیر لینک کوتاه در پترن (مثلاً link). لینک کوتاه در این متغیر قرار می‌گیرد.', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="kk_sms_message"><?php esc_html_e( 'متن پیامک', 'khabaram-kon' ); ?></label></th>
					<td>
						<textarea id="kk_sms_message" name="kk_settings[sms_message]" rows="4" class="large-text"><?php echo esc_textarea( $s['sms_message'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'شورت‌کدهای قابل استفاده:', 'khabaram-kon' ); ?>
							<code>{product}</code> <code>{link}</code> <code>{shortlink}</code> <code>{site}</code>
						</p>
					</td>
				</tr>
			</table>
			<p>
				<button type="button" class="button" id="kk-test-sms"><?php esc_html_e( 'ارسال پیامک آزمایشی', 'khabaram-kon' ); ?></button>
				<input type="text" id="kk-test-phone" placeholder="<?php esc_attr_e( 'شماره برای تست', 'khabaram-kon' ); ?>" class="regular-text">
				<span id="kk-test-result" class="kk-test-result"></span>
			</p>
			<p>
				<button type="button" class="button button-secondary" id="kk-check-credit"><?php esc_html_e( 'بررسی اعتبار و تست اتصال', 'khabaram-kon' ); ?></button>
				<span id="kk-credit-result" class="kk-test-result"></span>
			</p>
			<p class="description"><?php esc_html_e( 'ابتدا تنظیمات را ذخیره کنید، سپس تست بگیرید. (نمایش اعتبار برای فراز اس‌ام‌اس، کاوه‌نگار، SMS.ir و ملی‌پیامک در دسترس است.)', 'khabaram-kon' ); ?></p>
		</div>

		<?php // ------------- لینک کوتاه ------------- ?>
		<div class="kk-tab-panel" data-tab="link" style="<?php echo 'link' === $active_tab ? '' : 'display:none'; ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'فعال‌سازی لینک کوتاه', 'khabaram-kon' ); ?></th>
					<td><label class="kk-switch"><input type="checkbox" name="kk_settings[shortlink_enabled]" value="yes" <?php checked( $s['shortlink_enabled'], 'yes' ); ?>><span></span></label>
						<p class="description"><?php esc_html_e( 'لینک کوتاه داخلی برای هر درخواست ساخته می‌شود تا در پیامک کوتاه‌تر و قابل رهگیری باشد.', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="kk_shortlink_slug"><?php esc_html_e( 'پیشوند لینک', 'khabaram-kon' ); ?></label></th>
					<td>
						<code><?php echo esc_html( home_url( '/' ) ); ?></code>
						<input type="text" id="kk_shortlink_slug" name="kk_settings[shortlink_slug]" value="<?php echo esc_attr( $s['shortlink_slug'] ); ?>" class="small-text">
						<code>/AbC123</code>
						<p class="description"><?php esc_html_e( 'پس از تغییر پیشوند، یک‌بار به تنظیمات » پیوندهای یکتا بروید و ذخیره کنید.', 'khabaram-kon' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<?php // ------------- گزارش تبدیل ------------- ?>
		<div class="kk-tab-panel" data-tab="conversion" style="<?php echo 'conversion' === $active_tab ? '' : 'display:none'; ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'فعال‌سازی گزارش تبدیل', 'khabaram-kon' ); ?></th>
					<td><label class="kk-switch"><input type="checkbox" name="kk_settings[conversion_enabled]" value="yes" <?php checked( $s['conversion_enabled'], 'yes' ); ?>><span></span></label>
						<p class="description"><?php esc_html_e( 'با UTM روی لینک کوتاه و تطبیق شماره خریدار با سفارش‌ها، تعداد و مبلغ خریدهای انجام‌شده پس از پیامک محاسبه می‌شود.', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="kk_utm_source"><?php esc_html_e( 'UTM Source', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_utm_source" name="kk_settings[utm_source]" value="<?php echo esc_attr( $s['utm_source'] ); ?>" class="regular-text" dir="ltr"></td>
				</tr>
				<tr>
					<th><label for="kk_utm_medium"><?php esc_html_e( 'UTM Medium', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_utm_medium" name="kk_settings[utm_medium]" value="<?php echo esc_attr( $s['utm_medium'] ); ?>" class="regular-text" dir="ltr"></td>
				</tr>
				<tr>
					<th><label for="kk_utm_campaign"><?php esc_html_e( 'UTM Campaign', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_utm_campaign" name="kk_settings[utm_campaign]" value="<?php echo esc_attr( $s['utm_campaign'] ); ?>" class="regular-text" dir="ltr">
						<p class="description"><?php esc_html_e( 'این پارامترها به لینک محصول اضافه می‌شوند تا در گوگل آنالیتیکس هم قابل ردیابی باشند.', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="kk_attr_window"><?php esc_html_e( 'بازه نسبت‌دهی (روز)', 'khabaram-kon' ); ?></label></th>
					<td><input type="number" id="kk_attr_window" name="kk_settings[attribution_window_days]" value="<?php echo esc_attr( $s['attribution_window_days'] ); ?>" class="small-text" min="1" max="90">
						<p class="description"><?php esc_html_e( 'اگر خرید ظرف این تعداد روز پس از ارسال پیامک انجام شود، به‌عنوان تبدیل ثبت می‌شود.', 'khabaram-kon' ); ?></p></td>
				</tr>
			</table>
			<p class="description"><?php esc_html_e( 'آمار کامل تبدیل و درآمد را در صفحه «درخواست‌ها» می‌بینید.', 'khabaram-kon' ); ?></p>
		</div>

		<?php // ------------- اعلان مدیر ------------- ?>
		<div class="kk-tab-panel" data-tab="alerts" style="<?php echo 'alerts' === $active_tab ? '' : 'display:none'; ?>">
			<p class="description" style="max-width:640px">
				<?php esc_html_e( 'وقتی تعداد افرادی که برای یک محصول ناموجود «خبرم کن» زده‌اند از حد مشخصی بگذرد، به شما اطلاع داده می‌شود تا آن محصول را زودتر شارژ کنید. اعلان پلکانی است (در هر ضریب از آستانه یک‌بار) و پس از موجود شدن محصول ریست می‌شود.', 'khabaram-kon' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'فعال‌سازی اعلان تقاضا', 'khabaram-kon' ); ?></th>
					<td><label class="kk-switch"><input type="checkbox" name="kk_settings[demand_alert_enabled]" value="yes" <?php checked( $s['demand_alert_enabled'], 'yes' ); ?>><span></span></label></td>
				</tr>
				<tr>
					<th><label for="kk_demand_threshold"><?php esc_html_e( 'آستانه تعداد درخواست', 'khabaram-kon' ); ?></label></th>
					<td><input type="number" id="kk_demand_threshold" name="kk_settings[demand_threshold]" value="<?php echo esc_attr( $s['demand_threshold'] ); ?>" class="small-text" min="1">
						<p class="description"><?php esc_html_e( 'مثلاً ۱۰: با رسیدن به ۱۰، ۲۰، ۳۰ نفر و... اعلان ارسال می‌شود.', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="kk_demand_alert_email"><?php esc_html_e( 'ایمیل دریافت اعلان', 'khabaram-kon' ); ?></label></th>
					<td><input type="email" id="kk_demand_alert_email" name="kk_settings[demand_alert_email]" value="<?php echo esc_attr( $s['demand_alert_email'] ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'خالی = ایمیل مدیر سایت.', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="kk_demand_alert_sms_phone"><?php esc_html_e( 'شماره موبایل مدیر (پیامک)', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_demand_alert_sms_phone" name="kk_settings[demand_alert_sms_phone]" value="<?php echo esc_attr( $s['demand_alert_sms_phone'] ); ?>" class="regular-text" dir="ltr">
						<p class="description"><?php esc_html_e( 'اگر پر شود، اعلان با متن ساده (بدون پترن) پیامک می‌شود.', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="kk_telegram_bot_token"><?php esc_html_e( 'توکن بات تلگرام', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_telegram_bot_token" name="kk_settings[telegram_bot_token]" value="<?php echo esc_attr( $s['telegram_bot_token'] ); ?>" class="regular-text" dir="ltr" autocomplete="off">
						<p class="description"><?php esc_html_e( 'از @BotFather یک بات بسازید و توکن آن را اینجا بگذارید.', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="kk_telegram_chat_id"><?php esc_html_e( 'شناسه چت/کانال تلگرام', 'khabaram-kon' ); ?></label></th>
					<td><input type="text" id="kk_telegram_chat_id" name="kk_settings[telegram_chat_id]" value="<?php echo esc_attr( $s['telegram_chat_id'] ); ?>" class="regular-text" dir="ltr">
						<p class="description"><?php esc_html_e( 'مثلاً عدد chat_id شخصی، یا @username کانال (بات باید ادمین کانال باشد).', 'khabaram-kon' ); ?></p></td>
				</tr>
			</table>
			<p>
				<button type="button" class="button" id="kk-test-alert"><?php esc_html_e( 'ارسال اعلان آزمایشی', 'khabaram-kon' ); ?></button>
				<span id="kk-alert-result" class="kk-test-result"></span>
			</p>
			<p class="description"><?php esc_html_e( 'ابتدا تنظیمات را ذخیره کنید، سپس تست بگیرید.', 'khabaram-kon' ); ?></p>
		</div>

		<?php // ------------- پیشرفته ------------- ?>
		<div class="kk-tab-panel" data-tab="advanced" style="<?php echo 'advanced' === $active_tab ? '' : 'display:none'; ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'ذخیره شماره کاربر', 'khabaram-kon' ); ?></th>
					<td><label class="kk-switch"><input type="checkbox" name="kk_settings[store_user_phone]" value="yes" <?php checked( $s['store_user_phone'], 'yes' ); ?>><span></span></label>
						<p class="description"><?php esc_html_e( 'اگر کاربر قبلاً شماره داده باشد، دیگر پرسیده نمی‌شود و درخواست با یک کلیک ثبت می‌شود.', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="kk_notify_admin_email"><?php esc_html_e( 'ایمیل اطلاع به مدیر', 'khabaram-kon' ); ?></label></th>
					<td><input type="email" id="kk_notify_admin_email" name="kk_settings[notify_admin_email]" value="<?php echo esc_attr( $s['notify_admin_email'] ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'در صورت تکمیل، هنگام ثبت درخواست جدید ایمیل اطلاع‌رسانی می‌شود. خالی = غیرفعال.', 'khabaram-kon' ); ?></p></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'حذف داده‌ها هنگام حذف افزونه', 'khabaram-kon' ); ?></th>
					<td><label class="kk-switch"><input type="checkbox" name="kk_settings[delete_on_uninstall]" value="yes" <?php checked( $s['delete_on_uninstall'], 'yes' ); ?>><span></span></label>
						<p class="description"><?php esc_html_e( 'در صورت فعال بودن، هنگام حذف کامل افزونه جدول درخواست‌ها و تنظیمات پاک می‌شود.', 'khabaram-kon' ); ?></p></td>
				</tr>
			</table>
		</div>

		<?php submit_button( __( 'ذخیره تنظیمات', 'khabaram-kon' ) ); ?>
	</form>
</div>
