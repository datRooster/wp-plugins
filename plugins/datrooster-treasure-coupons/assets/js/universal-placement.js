/**
 * Moves universal Treasure Coupons placements into configured CSS selectors.
 *
 * @package DatRoosterTreasureCoupons
 */

( function () {
	var refreshTimer = null;

	function placeElement( element ) {
		var selector = element.getAttribute( 'data-datrooster-placement-selector' );
		var method = element.getAttribute( 'data-datrooster-placement-method' ) || 'append';
		var target;

		if ( ! selector ) {
			return;
		}

		try {
			target = document.querySelector( selector );
		} catch ( error ) {
			return;
		}

		if ( ! target ) {
			return;
		}

		if ( 'prepend' === method ) {
			target.insertBefore( element, target.firstChild );
		} else if ( 'before' === method ) {
			target.parentNode.insertBefore( element, target );
		} else if ( 'after' === method ) {
			target.parentNode.insertBefore( element, target.nextSibling );
		} else {
			target.appendChild( element );
		}

		element.classList.add( 'datrooster-treasure-universal--placed' );
	}

	function getDevice() {
		if ( window.matchMedia( '(max-width: 480px)' ).matches ) {
			return 'mobile';
		}

		if ( window.matchMedia( '(max-width: 782px)' ).matches ) {
			return 'tablet';
		}

		return 'desktop';
	}

	function getDeviceAttribute( element, name, device ) {
		var value = '';

		if ( 'mobile' === device ) {
			value = element.getAttribute( name + '-mobile' ) || element.getAttribute( name + '-tablet' ) || '';
		} else if ( 'tablet' === device ) {
			value = element.getAttribute( name + '-tablet' ) || '';
		}

		return value || element.getAttribute( name ) || '';
	}

	function getAnchorPosition( element ) {
		var device = getDevice();
		var selector = getDeviceAttribute( element, 'data-datrooster-anchor-selector', device );
		var anchorX = parseFloat( getDeviceAttribute( element, 'data-datrooster-anchor-x', device ) );
		var anchorY = parseFloat( getDeviceAttribute( element, 'data-datrooster-anchor-y', device ) );
		var rect;
		var target;

		if ( ! selector || Number.isNaN( anchorX ) || Number.isNaN( anchorY ) ) {
			return null;
		}

		try {
			target = document.querySelector( selector );
		} catch ( error ) {
			return null;
		}

		if ( ! target ) {
			return null;
		}

		rect = target.getBoundingClientRect();

		if ( rect.width <= 0 || rect.height <= 0 ) {
			return null;
		}

		return {
			x: window.scrollX + rect.left + ( rect.width * anchorX / 100 ),
			y: window.scrollY + rect.top + ( rect.height * anchorY / 100 ),
		};
	}

	function applyAnchorPosition( element ) {
		var position = getAnchorPosition( element );

		if ( ! position ) {
			return;
		}

		element.style.bottom = 'auto';
		element.style.left = position.x + 'px';
		element.style.position = 'absolute';
		element.style.right = 'auto';
		element.style.top = position.y + 'px';
		element.style.transform = 'translate(-50%, -50%)';
	}

	function anchorDocumentElement( element ) {
		if ( element.classList.contains( 'datrooster-treasure-universal--placed' ) ) {
			return;
		}

		if ( document.body && element.parentNode !== document.body ) {
			document.body.appendChild( element );
		}

		applyAnchorPosition( element );
	}

	function refreshAnchorPositions() {
		var documentPlacements = document.querySelectorAll( '[data-datrooster-document-positioned="1"]' );

		documentPlacements.forEach( applyAnchorPosition );
	}

	function scheduleRefreshAnchorPositions() {
		if ( refreshTimer ) {
			window.clearTimeout( refreshTimer );
		}

		refreshTimer = window.setTimeout(
			function () {
				refreshTimer = null;
				refreshAnchorPositions();
			},
			120
		);
	}

	function init() {
		var placements = document.querySelectorAll( '[data-datrooster-placement-selector]' );
		var documentPlacements = document.querySelectorAll( '[data-datrooster-document-positioned="1"]' );

		placements.forEach( placeElement );
		documentPlacements.forEach( anchorDocumentElement );
		refreshAnchorPositions();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	window.addEventListener( 'load', refreshAnchorPositions );
	window.addEventListener( 'resize', scheduleRefreshAnchorPositions );
	window.addEventListener( 'orientationchange', scheduleRefreshAnchorPositions );
}() );
