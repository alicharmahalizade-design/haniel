/* خبرم کن - اسکریپت مدیریت */
( function ( $ ) {
	'use strict';

	$( function () {
		// رنگ‌پیکر
		$( '.kk-color' ).wpColorPicker( {
			change: function () {
				setTimeout( updatePreview, 30 );
			}
		} );

		// پیش‌نمایش زنده دکمه
		function updatePreview() {
			var $btn = $( '#kk-preview-btn' );
			if ( ! $btn.length ) { return; }
			$btn.css( {
				background: $( 'input[name="kk_settings[btn_bg]"]' ).val(),
				color: $( 'input[name="kk_settings[btn_color]"]' ).val(),
				borderRadius: ( $( 'input[name="kk_settings[btn_radius]"]' ).val() || 10 ) + 'px'
			} );
			var txt = $( '#kk_button_text' ).val();
			if ( txt ) { $btn.text( txt ); }
		}

		$( '#kk_button_text, #kk_btn_radius' ).on( 'input', updatePreview );
		$( '#kk-preview-btn' ).on( 'mouseenter', function () {
			$( this ).css( 'background', $( 'input[name="kk_settings[btn_bg_hover]"]' ).val() );
		} ).on( 'mouseleave', updatePreview );
		updatePreview();

		// نمایش/مخفی کردن فیلد نام متغیرهای پترن بسته به داشتن کد پترن
		function togglePatternVars() {
			var hasPattern = $.trim( $( '#kk_sms_pattern' ).val() ) !== '';
			$( '.kk-pattern-var' ).toggle( hasPattern );
		}
		if ( $( '#kk_sms_pattern' ).length ) {
			togglePatternVars();
			$( '#kk_sms_pattern' ).on( 'input', togglePatternVars );
		}

		// ارسال پیامک آزمایشی
		$( '#kk-test-sms' ).on( 'click', function () {
			var $btn = $( this );
			var phone = $( '#kk-test-phone' ).val();
			var $res = $( '#kk-test-result' ).removeClass( 'ok err' ).text( '' );

			if ( ! phone ) {
				$res.addClass( 'err' ).text( 'شماره را وارد کنید.' );
				return;
			}
			$btn.prop( 'disabled', true );
			$res.text( 'در حال ارسال...' );

			$.post( ajaxurl, {
				action: 'kk_test_sms',
				nonce: kkAdmin.nonce,
				phone: phone
			} ).done( function ( r ) {
				if ( r && r.success ) {
					$res.addClass( 'ok' ).text( r.data.message );
				} else {
					$res.addClass( 'err' ).text( ( r && r.data && r.data.message ) || 'خطا در ارسال.' );
				}
			} ).fail( function () {
				$res.addClass( 'err' ).text( 'خطا در ارتباط.' );
			} ).always( function () {
				$btn.prop( 'disabled', false );
			} );
		} );
	} );

} )( jQuery );
