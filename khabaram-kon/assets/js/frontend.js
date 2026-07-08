/* خبرم کن - اسکریپت فرانت */
( function ( $ ) {
	'use strict';

	function normalizePhone( val ) {
		// تبدیل ارقام فارسی/عربی به لاتین
		var map = { '۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9','٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9' };
		val = ( val || '' ).replace( /[۰-۹٠-٩]/g, function ( d ) { return map[ d ]; } );
		return val.replace( /[^0-9]/g, '' );
	}

	function isValidPhone( val ) {
		var p = normalizePhone( val );
		return /^09\d{9}$/.test( p );
	}

	$( function () {

		// نگه‌داری ارجاع فرم هر wrapper (چون در حالت پاپ‌آپ به body منتقل می‌شود)
		function formOf( $wrap ) {
			var $f = $wrap.data( 'kkForm' );
			if ( ! $f || ! $f.length ) {
				$f = $wrap.find( '.kk-form' );
				$wrap.data( 'kkForm', $f );
			}
			return $f;
		}

		function openForm( $wrap ) {
			var $form = formOf( $wrap );
			$form.data( 'kkHome', $wrap );
			// در حالت پاپ‌آپ، فرم را به body منتقل کن تا از والدهای دارای transform خارج شده و واقعاً تمام‌صفحه شود
			if ( $form.hasClass( 'kk-form--popup' ) ) {
				$( 'body' ).append( $form );
				$( 'html' ).addClass( 'kk-modal-open' );
			}
			$form.prop( 'hidden', false );
			$wrap.find( '.kk-button' ).hide();
			$form.find( '.kk-phone' ).trigger( 'focus' );
		}

		function closeForm( $form ) {
			var $home = $form.data( 'kkHome' );
			$form.prop( 'hidden', true );
			// اگر به body منتقل شده، به جای اصلی‌اش برگردان
			if ( $form.parent().is( 'body' ) && $home && $home.length ) {
				$home.append( $form );
			}
			if ( $home && $home.length ) {
				$home.find( '.kk-button' ).show();
			}
			$( 'html' ).removeClass( 'kk-modal-open' );
		}

		// باز کردن فرم
		$( document ).on( 'click', '.kk-button', function () {
			openForm( $( this ).closest( '.kk-wrapper' ) );
		} );

		// بستن با دکمه بستن
		$( document ).on( 'click', '.kk-close', function () {
			closeForm( $( this ).closest( '.kk-form' ) );
		} );

		// بستن با کلیک روی پس‌زمینه پاپ‌آپ
		$( document ).on( 'click', '.kk-form--popup', function ( e ) {
			if ( e.target === this ) {
				closeForm( $( this ) );
			}
		} );

		// بستن با کلید Escape
		$( document ).on( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				$( '.kk-form--popup' ).each( function () {
					if ( ! this.hidden ) {
						closeForm( $( this ) );
					}
				} );
			}
		} );

		// ثبت با Enter
		$( document ).on( 'keydown', '.kk-phone', function ( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				$( this ).closest( '.kk-form' ).find( '.kk-submit' ).trigger( 'click' );
			}
		} );

		// ارسال درخواست
		$( document ).on( 'click', '.kk-submit', function () {
			var $btn   = $( this );
			var $form  = $btn.closest( '.kk-form' );
			var $msg   = $form.find( '.kk-message' );
			var phone  = normalizePhone( $form.find( '.kk-phone' ).val() );
			// شناسه محصول از خود فرم خوانده می‌شود (چون ممکن است به body منتقل شده باشد)
			var product = $form.attr( 'data-product' ) || ( $form.data( 'kkHome' ) && $form.data( 'kkHome' ).data( 'product' ) );
			var variation = $form.find( '.kk-variation-id' ).val() || 0;

			$msg.removeClass( 'kk-ok kk-err' ).text( '' );

			if ( ! isValidPhone( phone ) ) {
				$msg.addClass( 'kk-err' ).text( kkData.invalidPhone );
				return;
			}

			$btn.prop( 'disabled', true ).data( 'label', $btn.text() ).text( kkData.sending );

			$.post( kkData.ajaxUrl, {
				action: 'kk_subscribe',
				nonce: kkData.nonce,
				product_id: product,
				variation_id: variation,
				phone: phone
			} ).done( function ( res ) {
				if ( res && res.success ) {
					$msg.addClass( 'kk-ok' ).text( res.data.message );
					$form.find( '.kk-field, .kk-submit, .kk-privacy' ).slideUp( 150 );
				} else {
					$msg.addClass( 'kk-err' ).text( ( res && res.data && res.data.message ) || kkData.genericError );
					$btn.prop( 'disabled', false ).text( $btn.data( 'label' ) );
				}
			} ).fail( function () {
				$msg.addClass( 'kk-err' ).text( kkData.genericError );
				$btn.prop( 'disabled', false ).text( $btn.data( 'label' ) );
			} );
		} );

		// --- پشتیبانی محصولات متغیر ---
		var $variForm = $( 'form.variations_form' );
		if ( $variForm.length ) {
			var $kkVar = $( '.kk-wrapper.kk-variable' );

			// وقتی متغیری انتخاب شد و اطلاعاتش لود شد
			$variForm.on( 'found_variation', function ( event, variation ) {
				var $form = formOf( $kkVar );
				if ( ! variation.is_in_stock && ! variation.backorders_allowed ) {
					$kkVar.removeClass( 'kk-hidden' );
					$form.find( '.kk-variation-id' ).val( variation.variation_id );
				} else {
					hideVari();
				}
			} );

			// وقتی انتخاب پاک شد
			$variForm.on( 'reset_data hide_variation', function () {
				hideVari();
			} );

			function hideVari() {
				var $form = formOf( $kkVar );
				// اگر پاپ‌آپ باز و به body منتقل شده، ببند و برگردان
				if ( $form.parent().is( 'body' ) ) {
					closeForm( $form );
				}
				$kkVar.addClass( 'kk-hidden' );
				$form.prop( 'hidden', true );
				$kkVar.find( '.kk-button' ).show();
				$form.find( '.kk-variation-id' ).val( 0 );
				$form.find( '.kk-message' ).removeClass( 'kk-ok kk-err' ).text( '' );
				$form.find( '.kk-field, .kk-submit, .kk-privacy' ).show();
				$( 'html' ).removeClass( 'kk-modal-open' );
			}
		}
	} );

} )( jQuery );
