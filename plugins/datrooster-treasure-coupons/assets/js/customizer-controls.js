/**
 * Stores drag coordinates received from the Customizer preview.
 *
 * @package DatRoosterTreasureCoupons
 */

( function ( customize ) {
	var config = window.datroosterTreasureCustomizerControls || {};
	var settingIds = config.settingIds || {};
	var i18n = config.i18n || {};
	var boundPreviewer = false;

	if ( ! customize || ! settingIds.x || ! settingIds.y ) {
		return;
	}

	function updateControlInput( settingId, value ) {
		var control = customize.control( settingId );
		var input;

		if ( ! control || ! control.container ) {
			return;
		}

		input = control.container.find( 'input' );

		if ( ! input.length ) {
			return;
		}

		input.val( value ).trigger( 'input' ).trigger( 'change' );
	}

	function updateSetting( settingId, value ) {
		var setting = customize( settingId );

		value = String( value );

		if ( setting ) {
			setting.set( value );
		}

		updateControlInput( settingId, value );
	}

	function receivePosition( position ) {
		if ( ! position || 'undefined' === typeof position.x || 'undefined' === typeof position.y ) {
			return;
		}

		updateSetting( settingIds.x, position.x );
		updateSetting( settingIds.y, position.y );

		if ( customize.notifications && i18n.positionReceived ) {
			customize.notifications.add(
				'datrooster-treasure-position',
				new customize.Notification(
					'datrooster-treasure-position',
					{
						message: i18n.positionReceived,
						type: 'info',
					}
				)
			);
		}
	}

	function bindPreviewer() {
		if ( boundPreviewer ) {
			return;
		}

		if ( ! customize.previewer ) {
			return;
		}

		boundPreviewer = true;
		customize.previewer.bind( 'datrooster-treasure-position', receivePosition );
	}

	customize.bind( 'ready', bindPreviewer );
	bindPreviewer();

	window.addEventListener(
		'message',
		function ( event ) {
			if ( event.origin !== window.location.origin ) {
				return;
			}

			if ( ! event.data || 'datroosterTreasurePosition' !== event.data.type ) {
				return;
			}

			receivePosition( event.data.position );
		}
	);
} )( window.wp && window.wp.customize );
