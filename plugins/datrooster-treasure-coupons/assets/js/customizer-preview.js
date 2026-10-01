/**
 * Enables drag positioning for universal placements inside the Customizer preview.
 *
 * @package DatRoosterTreasureCoupons
 */

( function ( customize ) {
	var config = window.datroosterTreasureCustomizerPreview || {};
	var settingIds = config.settingIds || {};
	var i18n = config.i18n || {};
	var coordinateMode = config.coordinateMode || 'legacy_pixel';
	var lastPosition = null;
	var saveTimer = null;

	function clamp( value, min, max ) {
		return Math.max( min, Math.min( max, value ) );
	}

	function toCoordinate( value, max ) {
		return Math.round( clamp( value, 0, max ) * 100 ) / 100;
	}

	function toPercent( value, total ) {
		return Math.round( clamp( ( value / total ) * 100, 0, 100 ) * 100 ) / 100;
	}

	function isResponsiveMode() {
		return 'responsive_percent' === coordinateMode || 'anchored' === coordinateMode;
	}

	function getCurrentDevice() {
		var device;

		try {
			device = window.parent && window.parent.wp && window.parent.wp.customize && window.parent.wp.customize.previewedDevice
				? window.parent.wp.customize.previewedDevice.get()
				: '';
		} catch ( error ) {
			device = '';
		}

		if ( 'desktop' === device || 'tablet' === device || 'mobile' === device ) {
			return device;
		}

		if ( getViewportWidth() <= 480 ) {
			return 'mobile';
		}

		if ( getViewportWidth() <= 782 ) {
			return 'tablet';
		}

		return 'desktop';
	}

	function getFrameMetrics() {
		var frameRect;

		try {
			frameRect = window.frameElement ? window.frameElement.getBoundingClientRect() : null;

			if ( window.parent && window.parent !== window && frameRect ) {
				return {
					left: frameRect.left,
					width: Math.max( window.parent.innerWidth || 0, frameRect.width, getViewportWidth() ),
				};
			}
		} catch ( error ) {
			return {
				left: 0,
				width: getViewportWidth(),
			};
		}

		return {
			left: 0,
			width: getViewportWidth(),
		};
	}

	function getSavedX( event, device ) {
		var metrics;

		if ( 'desktop' === device ) {
			metrics = getFrameMetrics();
			return toPercent( metrics.left + event.clientX, metrics.width );
		}

		return toPercent( event.clientX, getViewportWidth() );
	}

	function getLocalXFromSavedX( x, device ) {
		var metrics;

		if ( ! isResponsiveMode() ) {
			return parseFloat( x );
		}

		if ( 'desktop' === device ) {
			metrics = getFrameMetrics();
			return ( parseFloat( x ) / 100 * metrics.width ) - metrics.left;
		}

		return parseFloat( x ) / 100 * getViewportWidth();
	}

	function applyPosition( element, x, y, device ) {
		var localX = getLocalXFromSavedX( x, device || getCurrentDevice() );

		element.style.position = 'absolute';
		element.style.left = 'clamp(16px, ' + localX + 'px, calc(100vw - 16px))';
		element.style.top = y + 'px';
		element.style.right = 'auto';
		element.style.bottom = 'auto';
		element.style.transform = 'translate(-50%, -50%)';
	}

	function getViewportWidth() {
		return Math.max(
			document.documentElement ? document.documentElement.clientWidth : 0,
			window.innerWidth
		);
	}

	function getDocumentHeight() {
		return Math.max(
			document.documentElement ? document.documentElement.scrollHeight : 0,
			document.body ? document.body.scrollHeight : 0,
			window.innerHeight
		);
	}

	function notifyControls( x, y, device, anchor ) {
		var position = {
			anchor: anchor || null,
			coordinateMode: anchor ? 'anchored' : 'responsive_percent',
			device: device,
			x: x,
			y: y,
		};

		lastPosition = position;

		if ( customize && customize.preview ) {
			customize.preview.send( 'datrooster-treasure-position', position );
		}

		updateParentCustomizer( position );

		if ( window.parent && window.parent !== window ) {
			window.parent.postMessage(
				{
					type: 'datroosterTreasurePosition',
					position: position,
				},
				window.location.origin
			);
		}
	}

	function updateParentCustomizer( position ) {
		var parentCustomize;

		if ( ! window.parent || window.parent === window || ! settingIds.x || ! settingIds.y ) {
			return;
		}

		try {
			parentCustomize = window.parent.wp && window.parent.wp.customize;
		} catch ( error ) {
			return;
		}

		if ( ! parentCustomize ) {
			return;
		}

		updateParentSetting( parentCustomize, settingIds.x, position.x );
		updateParentSetting( parentCustomize, settingIds.y, position.y );
	}

	function updateParentSetting( parentCustomize, settingId, value ) {
		var control;
		var input;
		var setting = parentCustomize( settingId );

		value = String( value );

		if ( setting ) {
			setting.set( value );
		}

		if ( ! parentCustomize.control ) {
			return;
		}

		control = parentCustomize.control( settingId );

		if ( ! control || ! control.container ) {
			return;
		}

		input = control.container.find( 'input' );

		if ( input.length ) {
			input.val( value ).trigger( 'input' ).trigger( 'change' );
		}
	}

	function updateSaveState( element, state ) {
		if ( ! state ) {
			element.removeAttribute( 'data-datrooster-save-state' );
			return;
		}

		element.setAttribute( 'data-datrooster-save-state', state );
	}

	function savePosition( element ) {
		var body;

		if ( ! config.ajaxUrl || ! config.action || ! config.nonce || ! lastPosition ) {
			return;
		}

		updateSaveState( element, i18n.saving || 'Saving position...' );

		body = new window.FormData();
		body.append( 'action', config.action );
		body.append( 'nonce', config.nonce );
		body.append( 'x', lastPosition.x );
		body.append( 'y', lastPosition.y );
		body.append( 'device', lastPosition.device || getCurrentDevice() );
		body.append( 'coordinate_mode', lastPosition.coordinateMode || 'responsive_percent' );

		if ( lastPosition.anchor ) {
			body.append( 'anchor_selector', lastPosition.anchor.selector );
			body.append( 'anchor_x', lastPosition.anchor.x );
			body.append( 'anchor_y', lastPosition.anchor.y );
		}

		window.fetch(
			config.ajaxUrl,
			{
				body: body,
				credentials: 'same-origin',
				method: 'POST',
			}
		)
			.then(
				function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'Request failed.' );
					}

					return response.json();
				}
			)
			.then(
				function ( result ) {
					if ( ! result || ! result.success ) {
						throw new Error( 'Save failed.' );
					}

					updateSaveState( element, i18n.saved || 'Position saved.' );

					window.setTimeout(
						function () {
							updateSaveState( element, '' );
						},
						2200
					);
				}
			)
			.catch(
				function () {
					updateSaveState( element, i18n.failed || 'Could not save the position. Please try again.' );
				}
			);
	}

	function scheduleFallbackSave( element ) {
		if ( saveTimer ) {
			window.clearTimeout( saveTimer );
		}

		saveTimer = window.setTimeout(
			function () {
				saveTimer = null;
				savePosition( element );
			},
			900
		);
	}

	function clearFallbackSave() {
		if ( ! saveTimer ) {
			return;
		}

		window.clearTimeout( saveTimer );
		saveTimer = null;
	}

	function getPrimaryPlacement() {
		return document.querySelector( '[data-datrooster-draggable="1"]' );
	}

	function escapeSelectorPart( value ) {
		if ( window.CSS && window.CSS.escape ) {
			return window.CSS.escape( value );
		}

		return String( value ).replace( /[^a-zA-Z0-9_-]/g, '\\$&' );
	}

	function getSafeClasses( element ) {
		return Array.prototype.filter.call(
			element.classList || [],
			function ( className ) {
				return ! /^datrooster-treasure/.test( className ) &&
					! /^customize-/.test( className ) &&
					! /^wp-customizer/.test( className ) &&
					! /^ui-/.test( className );
			}
		).slice( 0, 3 );
	}

	function isUniqueSelector( selector ) {
		try {
			return 1 === document.querySelectorAll( selector ).length;
		} catch ( error ) {
			return false;
		}
	}

	function getNthOfType( element ) {
		var index = 1;
		var sibling = element;

		while ( sibling.previousElementSibling ) {
			sibling = sibling.previousElementSibling;

			if ( sibling.tagName === element.tagName ) {
				index++;
			}
		}

		return index;
	}

	function getSelectorSegment( element, includeNth ) {
		var classes = getSafeClasses( element );
		var tagName = element.tagName.toLowerCase();
		var segment = tagName;

		if ( classes.length ) {
			segment += '.' + classes.map( escapeSelectorPart ).join( '.' );
		}

		if ( includeNth ) {
			segment += ':nth-of-type(' + getNthOfType( element ) + ')';
		}

		return segment;
	}

	function getUniqueSelector( element ) {
		var path = [];
		var selector;
		var current = element;
		var depth = 0;

		while ( current && current.nodeType === 1 && current !== document.documentElement && depth < 7 ) {
			if ( current.id ) {
				selector = '#' + escapeSelectorPart( current.id );

				if ( isUniqueSelector( selector ) ) {
					return selector;
				}
			}

			selector = getSelectorSegment( current, false );

			if ( isUniqueSelector( selector ) ) {
				return selector;
			}

			path.unshift( getSelectorSegment( current, true ) );
			selector = path.join( ' > ' );

			if ( isUniqueSelector( selector ) ) {
				return selector;
			}

			current = current.parentElement;
			depth++;
		}

		return '';
	}

	function isUsableAnchorElement( element, draggedElement ) {
		var rect;

		if ( ! element || element === document.body || element === document.documentElement ) {
			return false;
		}

		if ( draggedElement.contains( element ) || element.closest( '[data-datrooster-draggable="1"]' ) ) {
			return false;
		}

		rect = element.getBoundingClientRect();

		return rect.width >= 12 && rect.height >= 12;
	}

	function getAnchorTarget( event, draggedElement ) {
		var oldPointerEvents = draggedElement.style.pointerEvents;
		var oldVisibility = draggedElement.style.visibility;
		var target;

		draggedElement.style.pointerEvents = 'none';
		draggedElement.style.visibility = 'hidden';
		target = document.elementFromPoint( event.clientX, event.clientY );
		draggedElement.style.pointerEvents = oldPointerEvents;
		draggedElement.style.visibility = oldVisibility;

		while ( target && ! isUsableAnchorElement( target, draggedElement ) ) {
			target = target.parentElement;
		}

		return target;
	}

	function getAnchorData( event, draggedElement ) {
		var target = getAnchorTarget( event, draggedElement );
		var rect;
		var selector;

		while ( target && target !== document.body && target !== document.documentElement ) {
			selector = getUniqueSelector( target );

			if ( selector ) {
				rect = target.getBoundingClientRect();

				return {
					selector: selector,
					x: toPercent( event.clientX - rect.left, rect.width ),
					y: toPercent( event.clientY - rect.top, rect.height ),
				};
			}

			target = target.parentElement;
		}

		return null;
	}

	function moveToBody( element ) {
		if ( document.body && element.parentNode !== document.body ) {
			document.body.appendChild( element );
		}
	}

	function bindSettingPreviewUpdates( element ) {
		if ( ! customize || ! settingIds.x || ! settingIds.y ) {
			return;
		}

		function refreshFromSettings() {
			var x = customize( settingIds.x ) ? customize( settingIds.x ).get() : '';
			var y = customize( settingIds.y ) ? customize( settingIds.y ).get() : '';

			if ( '' === x || '' === y ) {
				return;
			}

			applyPosition( element, parseFloat( x ), parseFloat( y ), getCurrentDevice() );
		}

		customize(
			settingIds.x,
			function ( value ) {
				value.bind( refreshFromSettings );
			}
		);

		customize(
			settingIds.y,
			function ( value ) {
				value.bind( refreshFromSettings );
			}
		);
	}

	function makeDraggable( element ) {
		var active = false;
		var moved = false;
		var pointerId = null;

		function stopDragging( event ) {
			if ( ! active || event.pointerId !== pointerId ) {
				return;
			}

			active = false;
			pointerId = null;
			element.classList.remove( 'datrooster-treasure-universal--is-dragging' );

			if ( moved ) {
				clearFallbackSave();
				savePosition( element );
			}

			event.preventDefault();
		}

		element.setAttribute( 'title', i18n.dragHint || element.getAttribute( 'data-datrooster-drag-hint' ) || '' );
		element.classList.add( 'datrooster-treasure-universal--drag-ready' );
		bindSettingPreviewUpdates( element );

		element.addEventListener(
			'pointerdown',
			function ( event ) {
				active = true;
				moved = false;
				pointerId = event.pointerId;

				element.classList.add( 'datrooster-treasure-universal--is-dragging' );
				if ( element.setPointerCapture ) {
					element.setPointerCapture( pointerId );
				}
				event.preventDefault();
			}
		);

		element.addEventListener(
			'pointermove',
			function ( event ) {
				var anchor;
				var x;
				var y;
				var device;

				if ( ! active || event.pointerId !== pointerId ) {
					return;
				}

				moved = true;
				device = getCurrentDevice();
				anchor = getAnchorData( event, element );
				coordinateMode = anchor ? 'anchored' : 'responsive_percent';
				x = getSavedX( event, device );
				y = toCoordinate( event.pageY || event.clientY + window.scrollY, getDocumentHeight() );

				applyPosition( element, x, y, device );
				notifyControls( x, y, device, anchor );
				scheduleFallbackSave( element );
				event.preventDefault();
			}
		);

		element.addEventListener(
			'pointerup',
			stopDragging
		);

		document.addEventListener( 'pointerup', stopDragging );

		element.addEventListener(
			'click',
			function ( event ) {
				moved = false;
				event.preventDefault();
				event.stopPropagation();
			},
			true
		);

		element.addEventListener(
			'pointercancel',
			function () {
				active = false;
				pointerId = null;
				clearFallbackSave();
				element.classList.remove( 'datrooster-treasure-universal--is-dragging' );
			}
		);
	}

	function init() {
		var element = getPrimaryPlacement();

		if ( ! element ) {
			return;
		}

		moveToBody( element );
		makeDraggable( element );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )( window.wp && window.wp.customize );
