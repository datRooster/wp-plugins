/**
 * Frontend interactions for Treasure Coupons.
 *
 * @package DatRoosterTreasureCoupons
 */

( function () {
	function getText( value, fallback ) {
		return value || fallback;
	}

	function getQueryPopupConfig() {
		var defaults = window.datroosterTreasureCouponPopupDefaults || {};
		var params;
		var couponCode;

		if ( window.datroosterTreasureCouponPopup && window.datroosterTreasureCouponPopup.couponCode ) {
			return window.datroosterTreasureCouponPopup;
		}

		if ( ! window.URLSearchParams ) {
			return null;
		}

		params = new window.URLSearchParams( window.location.search );

		if ( 'completed' !== params.get( 'datrooster_treasure_status' ) ) {
			return null;
		}

		couponCode = params.get( 'datrooster_treasure_coupon' ) || '';

		if ( ! couponCode ) {
			return null;
		}

		return {
			closeLabel: getText( defaults.closeLabel, 'Close coupon popup' ),
			closeUrl: removePopupQueryArgs( window.location.href ),
			copiedLabel: getText( defaults.copiedLabel, 'Copied' ),
			copyLabel: getText( defaults.copyLabel, 'Copy code' ),
			couponCode: couponCode,
			eyebrow: getText( defaults.eyebrow, 'Treasure reward unlocked' ),
			hint: getText( defaults.hint, 'Save it and use it at checkout when you are ready.' ),
			message: getText( defaults.message, 'You unlocked your reward.' ),
			minimumHint: '',
			rewardSummary: '',
			title: getText( defaults.title, 'Your coupon code is ready' ),
		};
	}

	function removePopupQueryArgs( url ) {
		var nextUrl;

		try {
			nextUrl = new window.URL( url );
			nextUrl.searchParams.delete( 'datrooster_treasure_status' );
			nextUrl.searchParams.delete( 'datrooster_treasure_coupon' );

			return nextUrl.toString();
		} catch ( error ) {
			return window.location.href;
		}
	}

	function createElement( tagName, className, text ) {
		var element = document.createElement( tagName );

		if ( className ) {
			element.className = className;
		}

		if ( 'undefined' !== typeof text && null !== text ) {
			element.textContent = text;
		}

		return element;
	}

	function injectFallbackStyles() {
		var style;

		if ( document.getElementById( 'datrooster-treasure-popup-fallback-styles' ) ) {
			return;
		}

		style = document.createElement( 'style' );
		style.id = 'datrooster-treasure-popup-fallback-styles';
		style.textContent = '.datrooster-treasure-popup-open{overflow:hidden!important}.datrooster-treasure-coupon-popup{align-items:center!important;background:rgba(15,23,42,.48)!important;box-sizing:border-box!important;display:flex!important;inset:0!important;justify-content:center!important;min-height:100vh!important;min-height:100dvh!important;overflow-y:auto!important;padding:24px!important;position:fixed!important;visibility:visible!important;width:100%!important;z-index:2147483000!important}.datrooster-treasure-coupon-popup__card{background:#fff!important;border-radius:28px!important;box-shadow:0 28px 80px rgba(15,23,42,.28)!important;color:#0f172a!important;max-height:calc(100dvh - 48px)!important;max-width:min(520px,calc(100vw - 32px))!important;overflow-y:auto!important;padding:1.6rem!important;position:relative!important;width:100%!important}.datrooster-treasure-coupon-popup__close{align-items:center!important;background:#052e2b!important;border-radius:999px!important;color:#fff!important;display:inline-flex!important;font-size:1.5rem!important;height:36px!important;justify-content:center!important;line-height:1!important;position:absolute!important;right:16px!important;text-decoration:none!important;top:16px!important;width:36px!important}.datrooster-treasure-coupon-popup__eyebrow{color:#0f766e!important;font-size:.78rem!important;font-weight:800!important;letter-spacing:.08em!important;margin:0 44px .4rem 0!important;text-transform:uppercase!important}.datrooster-treasure-coupon-popup h2{color:#052e2b!important;font-size:clamp(1.6rem,5vw,2.25rem)!important;line-height:1.05!important;margin:0 44px .75rem 0!important}.datrooster-treasure-coupon-popup__reward{background:rgba(15,118,110,.1)!important;border-radius:999px!important;color:#0f766e!important;display:inline-flex!important;font-weight:800!important;margin:.25rem 0 .1rem!important;padding:.62rem .9rem!important}.datrooster-treasure-coupon-popup__code-row{align-items:center!important;background:#052e2b!important;border-radius:20px!important;display:flex!important;gap:.75rem!important;justify-content:space-between!important;margin:1rem 0 .8rem!important;padding:.75rem!important}.datrooster-treasure-coupon-popup__code-row code{background:#fef3c7!important;border-radius:14px!important;color:#713f12!important;display:block!important;font-size:clamp(1rem,4vw,1.2rem)!important;font-weight:900!important;letter-spacing:.08em!important;padding:.72rem .9rem!important;text-align:center!important;width:100%!important}.datrooster-treasure-coupon-popup__copy{background:#0f766e!important;border:0!important;border-radius:14px!important;color:#fff!important;cursor:pointer!important;flex:0 0 auto!important;font-weight:800!important;padding:.78rem 1rem!important}.datrooster-treasure-coupon-popup__hint{color:#475569!important;font-size:.95rem!important;margin:0!important}.datrooster-treasure-coupon-popup__minimum{color:#92400e!important;font-size:.9rem!important;font-weight:700!important;margin:.65rem 0 0!important}@media(max-width:560px){.datrooster-treasure-coupon-popup{align-items:flex-end!important;padding:16px!important}.datrooster-treasure-coupon-popup__card{border-radius:24px!important;max-height:calc(100dvh - 32px)!important;padding:1.25rem!important}.datrooster-treasure-coupon-popup__code-row{align-items:stretch!important;flex-direction:column!important}}';
		document.head.appendChild( style );
	}

	function appendTextElement( parent, tagName, className, text ) {
		var element;

		if ( ! text ) {
			return null;
		}

		element = createElement( tagName, className, text );
		parent.appendChild( element );

		return element;
	}

	function buildPopup( config ) {
		var popup = createElement( 'div', 'datrooster-treasure-coupon-popup' );
		var card = createElement( 'div', 'datrooster-treasure-coupon-popup__card' );
		var close = createElement( 'a', 'datrooster-treasure-coupon-popup__close' );
		var closeIcon = createElement( 'span', '', 'x' );
		var title = createElement( 'h2', '', getText( config.title, 'Your coupon code is ready' ) );
		var row = createElement( 'div', 'datrooster-treasure-coupon-popup__code-row' );
		var code = createElement( 'code', '', config.couponCode );
		var copy = createElement( 'button', 'datrooster-treasure-coupon-popup__copy', getText( config.copyLabel, 'Copy code' ) );

		popup.setAttribute( 'role', 'dialog' );
		popup.setAttribute( 'aria-modal', 'false' );
		title.id = 'datrooster-treasure-coupon-popup-title';
		popup.setAttribute( 'aria-labelledby', title.id );

		close.href = config.closeUrl || removePopupQueryArgs( window.location.href );
		close.setAttribute( 'aria-label', getText( config.closeLabel, 'Close coupon popup' ) );
		closeIcon.setAttribute( 'aria-hidden', 'true' );
		close.appendChild( closeIcon );

		copy.type = 'button';
		copy.setAttribute( 'data-datrooster-copy-code', config.couponCode );
		copy.setAttribute( 'data-datrooster-copied-label', getText( config.copiedLabel, 'Copied' ) );

		card.appendChild( close );
		appendTextElement( card, 'p', 'datrooster-treasure-coupon-popup__eyebrow', config.eyebrow );
		card.appendChild( title );
		appendTextElement( card, 'p', '', config.message );
		appendTextElement( card, 'p', 'datrooster-treasure-coupon-popup__reward', config.rewardSummary );
		row.appendChild( code );
		row.appendChild( copy );
		card.appendChild( row );
		appendTextElement( card, 'p', 'datrooster-treasure-coupon-popup__hint', config.hint );
		appendTextElement( card, 'p', 'datrooster-treasure-coupon-popup__minimum', config.minimumHint );

		popup.appendChild( card );

		return popup;
	}

	function ensurePopup() {
		var existing = document.querySelector( '.datrooster-treasure-coupon-popup' );
		var config;
		var popup;

		if ( existing ) {
			document.body.classList.add( 'datrooster-treasure-popup-open' );
			return;
		}

		config = getQueryPopupConfig();

		if ( ! config || ! config.couponCode || ! document.body ) {
			return;
		}

		injectFallbackStyles();
		popup = buildPopup( config );
		document.body.appendChild( popup );
		document.body.classList.add( 'datrooster-treasure-popup-open' );
	}

	function closePopup( link ) {
		var popup = link.closest( '.datrooster-treasure-coupon-popup' );

		if ( popup ) {
			popup.parentNode.removeChild( popup );
		}

		document.body.classList.remove( 'datrooster-treasure-popup-open' );

		if ( window.history && window.history.replaceState ) {
			window.history.replaceState( {}, document.title, link.href || removePopupQueryArgs( window.location.href ) );
		}
	}

	document.addEventListener(
		'click',
		function ( event ) {
			var target = event.target;
			var button;
			var closeLink;
			var copiedLabel;
			var originalLabel;

			if ( ! target || ! target.closest ) {
				return;
			}

			closeLink = target.closest( '.datrooster-treasure-coupon-popup__close' );

			if ( closeLink ) {
				event.preventDefault();
				closePopup( closeLink );
				return;
			}

			button = target.closest( '[data-datrooster-copy-code]' );

			if ( ! button || ! navigator.clipboard ) {
				return;
			}

			copiedLabel = button.getAttribute( 'data-datrooster-copied-label' ) || 'Copied';
			originalLabel = button.textContent;

			navigator.clipboard.writeText( button.getAttribute( 'data-datrooster-copy-code' ) || '' ).then(
				function () {
					button.textContent = copiedLabel;

					window.setTimeout(
						function () {
							button.textContent = originalLabel;
						},
						1800
					);
				}
			).catch( function () {} );
		}
	);

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', ensurePopup );
	} else {
		ensurePopup();
	}
}() );
