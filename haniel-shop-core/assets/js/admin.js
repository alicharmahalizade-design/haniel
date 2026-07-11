/* هسته هنیل شاپ — اسکریپت پنل مدیریت (بدون وابستگی خارجی) */
( function () {
	'use strict';

	var D = window.hscData || {};

	/**
	 * رسم نمودار دونات.
	 */
	function drawDonut( canvas, items ) {
		if ( ! canvas || ! items || ! items.length ) {
			return;
		}
		var ctx = canvas.getContext( '2d' );
		var dpr = window.devicePixelRatio || 1;
		var size = 240;
		canvas.width = size * dpr;
		canvas.height = size * dpr;
		canvas.style.width = size + 'px';
		canvas.style.height = size + 'px';
		ctx.scale( dpr, dpr );

		var cx = size / 2, cy = size / 2;
		var outer = 100, inner = 62;
		var total = 0;
		items.forEach( function ( it ) { total += Number( it.count ) || 0; } );
		if ( total <= 0 ) {
			return;
		}

		var start = -Math.PI / 2;
		items.forEach( function ( it ) {
			var val = Number( it.count ) || 0;
			if ( val <= 0 ) { return; }
			var angle = ( val / total ) * Math.PI * 2;
			ctx.beginPath();
			ctx.moveTo( cx, cy );
			ctx.arc( cx, cy, outer, start, start + angle );
			ctx.closePath();
			ctx.fillStyle = it.color || '#cbd5e1';
			ctx.fill();
			start += angle;
		} );

		// سوراخ وسط.
		ctx.globalCompositeOperation = 'destination-out';
		ctx.beginPath();
		ctx.arc( cx, cy, inner, 0, Math.PI * 2 );
		ctx.fill();
		ctx.globalCompositeOperation = 'source-over';

		// عدد مرکز.
		ctx.fillStyle = '#111827';
		ctx.textAlign = 'center';
		ctx.textBaseline = 'middle';
		ctx.font = '700 26px Tahoma, sans-serif';
		ctx.fillText( formatNum( total ), cx, cy - 6 );
		ctx.fillStyle = '#9ca3af';
		ctx.font = '400 12px Tahoma, sans-serif';
		ctx.fillText( ( D.i18n && D.i18n.customers ) || '', cx, cy + 16 );
	}

	/**
	 * رسم نمودار میله‌ای ماهانه.
	 */
	function drawBars( canvas, items ) {
		if ( ! canvas || ! items || ! items.length ) {
			return;
		}
		var ctx = canvas.getContext( '2d' );
		var dpr = window.devicePixelRatio || 1;
		var W = canvas.parentElement ? canvas.parentElement.clientWidth - 40 : 800;
		if ( W < 320 ) { W = 320; }
		var H = 220;
		canvas.width = W * dpr;
		canvas.height = H * dpr;
		canvas.style.width = W + 'px';
		canvas.style.height = H + 'px';
		ctx.scale( dpr, dpr );

		var padB = 34, padT = 16, padR = 10, padL = 10;
		var chartH = H - padB - padT;
		var chartW = W - padL - padR;
		var n = items.length;
		var gap = 14;
		var barW = ( chartW - gap * ( n - 1 ) ) / n;

		var max = 0;
		items.forEach( function ( it ) { max = Math.max( max, Number( it.count ) || 0 ); } );
		if ( max <= 0 ) { max = 1; }

		// خطوط شبکه.
		ctx.strokeStyle = '#f3f4f6';
		ctx.lineWidth = 1;
		for ( var g = 0; g <= 4; g++ ) {
			var y = padT + ( chartH / 4 ) * g;
			ctx.beginPath();
			ctx.moveTo( padL, y );
			ctx.lineTo( W - padR, y );
			ctx.stroke();
		}

		items.forEach( function ( it, i ) {
			var val = Number( it.count ) || 0;
			var h = ( val / max ) * chartH;
			// در چیدمان راست‌به‌چپ، از راست شروع می‌کنیم.
			var x = W - padR - barW - i * ( barW + gap );
			var y = padT + chartH - h;

			// میله با گوشهٔ گرد.
			var grad = ctx.createLinearGradient( 0, y, 0, y + h );
			grad.addColorStop( 0, '#8b5cf6' );
			grad.addColorStop( 1, '#c4b5fd' );
			ctx.fillStyle = grad;
			roundRect( ctx, x, y, barW, h, 6 );
			ctx.fill();

			// مقدار.
			if ( val > 0 ) {
				ctx.fillStyle = '#6d28d9';
				ctx.font = '700 11px Tahoma, sans-serif';
				ctx.textAlign = 'center';
				ctx.fillText( formatNum( val ), x + barW / 2, y - 5 );
			}

			// برچسب.
			ctx.fillStyle = '#9ca3af';
			ctx.font = '400 10px Tahoma, sans-serif';
			ctx.textAlign = 'center';
			ctx.fillText( it.label || '', x + barW / 2, H - 12 );
		} );
	}

	function roundRect( ctx, x, y, w, h, r ) {
		if ( h < r ) { r = h; }
		if ( r < 0 ) { r = 0; }
		ctx.beginPath();
		ctx.moveTo( x + r, y );
		ctx.arcTo( x + w, y, x + w, y + h, r );
		ctx.arcTo( x + w, y + h, x, y + h, 0 );
		ctx.arcTo( x, y + h, x, y, 0 );
		ctx.arcTo( x, y, x + w, y, r );
		ctx.closePath();
	}

	function formatNum( n ) {
		try {
			return Number( n ).toLocaleString( 'fa-IR' );
		} catch ( e ) {
			return String( n );
		}
	}

	/**
	 * دکمهٔ محاسبهٔ مجدد.
	 */
	function bindRebuild() {
		var btn = document.getElementById( 'hsc-rebuild' );
		if ( ! btn ) { return; }
		btn.addEventListener( 'click', function () {
			btn.classList.add( 'is-busy' );
			btn.disabled = true;
			var label = btn.querySelector( 'span:last-child' );
			var original = btn.innerHTML;
			btn.innerHTML = '<span class="dashicons dashicons-update"></span> ' + ( ( D.i18n && D.i18n.rebuilding ) || '...' );

			var body = new URLSearchParams();
			body.append( 'action', 'hsc_rebuild' );
			body.append( 'nonce', D.nonce || '' );

			fetch( D.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			} ).then( function ( r ) {
				return r.json();
			} ).then( function ( res ) {
				if ( res && res.success ) {
					window.location.reload();
				} else {
					btn.innerHTML = '<span class="dashicons dashicons-warning"></span> ' + ( ( D.i18n && D.i18n.error ) || 'Error' );
					btn.classList.remove( 'is-busy' );
					btn.disabled = false;
				}
			} ).catch( function () {
				btn.innerHTML = original;
				btn.classList.remove( 'is-busy' );
				btn.disabled = false;
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var charts = D.charts || {};
		drawDonut( document.getElementById( 'hsc-chart-segments' ), charts.segments );
		drawDonut( document.getElementById( 'hsc-chart-sources' ), charts.sources );
		drawBars( document.getElementById( 'hsc-chart-monthly' ), charts.monthly );
		bindRebuild();

		// رسم مجدد میله‌ها هنگام تغییر اندازهٔ پنجره.
		var t;
		window.addEventListener( 'resize', function () {
			clearTimeout( t );
			t = setTimeout( function () {
				drawBars( document.getElementById( 'hsc-chart-monthly' ), charts.monthly );
			}, 200 );
		} );
	} );
} )();
