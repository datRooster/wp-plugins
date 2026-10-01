/**
 * Registers DatRooster Treasure Coupons editor blocks.
 *
 * @package DatRoosterTreasureCoupons
 */

( function ( blocks, blockEditor, components, element ) {
	if ( ! blocks || ! blockEditor || ! components || ! element ) {
		return;
	}

	var config = window.datroosterTreasureCouponsBlocks || {};
	var text = config.i18n || {};
	var defaults = config.defaults || {};
	var createElement = element.createElement;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var Notice = components.Notice;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;

	function getText( key, fallback ) {
		return text[ key ] || fallback;
	}

	function setAttribute( props, key ) {
		return function ( value ) {
			var nextAttributes = {};

			nextAttributes[ key ] = value;
			props.setAttributes( nextAttributes );
		};
	}

	function renderControls( props, includeClueControls ) {
		var attributes = props.attributes;
		var controls = [
			createElement(
				TextControl,
				{
					key: 'hunt',
					label: getText( 'hunt', 'Hunt slug' ),
					value: attributes.hunt || defaults.hunt || 'main-hunt',
					onChange: setAttribute( props, 'hunt' ),
				}
			),
		];

		if ( includeClueControls ) {
			controls.push(
				createElement(
					TextControl,
					{
						key: 'clue',
						help: getText( 'clueHelp', 'Use a unique ID for each clue inside the same hunt.' ),
						label: getText( 'clue', 'Clue ID' ),
						value: attributes.clue || '',
						onChange: setAttribute( props, 'clue' ),
					}
				),
				createElement(
					TextControl,
					{
						key: 'label',
						label: getText( 'label', 'Visible label' ),
						value: attributes.label || defaults.label || 'Collect clue',
						onChange: setAttribute( props, 'label' ),
					}
				),
				createElement(
					SelectControl,
					{
						key: 'display',
						label: getText( 'display', 'Display style' ),
						value: attributes.display || 'button',
						options: [
						{ label: getText( 'button', 'Button' ), value: 'button' },
						{ label: getText( 'text', 'Hidden text link' ), value: 'text' },
						{ label: getText( 'image', 'Image marker' ), value: 'image' },
						],
						onChange: setAttribute( props, 'display' ),
					}
				)
			);

			if ( 'image' === attributes.display ) {
				controls.push(
					createElement(
						SelectControl,
						{
							key: 'image-mode',
							label: getText( 'imagePreset', 'Image marker' ),
							value: attributes.imageMode || 'default:key',
							options: [
							{ label: getText( 'defaultKey', 'Default key' ), value: 'default:key' },
							{ label: getText( 'defaultGem', 'Default gem' ), value: 'default:gem' },
							{ label: getText( 'defaultMap', 'Default map' ), value: 'default:map' },
							{ label: getText( 'customImage', 'Custom image URL' ), value: 'custom' },
							],
							onChange: setAttribute( props, 'imageMode' ),
						}
					)
				);

				if ( 'custom' === attributes.imageMode ) {
					controls.push(
						createElement(
							TextControl,
							{
								key: 'custom-image-url',
								label: getText( 'imageUrl', 'Custom image URL' ),
								type: 'url',
								value: attributes.customImageUrl || '',
								onChange: setAttribute( props, 'customImageUrl' ),
							}
						)
					);
				}

				controls.push(
					createElement(
						TextControl,
						{
							key: 'image-alt',
							label: getText( 'imageAlt', 'Image alternative text' ),
							value: attributes.imageAlt || '',
							onChange: setAttribute( props, 'imageAlt' ),
						}
					)
				);
			}
		}

		return createElement(
			InspectorControls,
			null,
			createElement(
				PanelBody,
				{
					initialOpen: true,
					title: getText( 'settings', 'Treasure settings' ),
				},
				controls
			)
		);
	}

	blocks.registerBlockType(
		'datrooster/treasure-clue',
		{
			apiVersion: 2,
			title: getText( 'clueTitle', 'Treasure clue' ),
			description: getText( 'clueDescription', 'Place a collectible clue anywhere in page content.' ),
			category: 'widgets',
			icon: 'hidden',
			attributes: {
				hunt: {
					type: 'string',
					default: defaults.hunt || 'main-hunt',
				},
				clue: {
					type: 'string',
					default: '',
				},
				label: {
					type: 'string',
					default: defaults.label || 'Collect clue',
				},
				display: {
					type: 'string',
					default: 'button',
				},
				imageMode: {
					type: 'string',
					default: 'default:key',
				},
				customImageUrl: {
					type: 'string',
					default: '',
				},
				imageAlt: {
					type: 'string',
					default: 'Hidden clue',
				},
			},
			edit: function ( props ) {
				var attributes = props.attributes;
				var blockProps = useBlockProps(
					{
						className: 'datrooster-treasure-editor-card datrooster-treasure-editor-card--clue',
					}
				);
				var display = attributes.display || 'button';
				var previewText = getText( 'buttonPreview', 'Customers will click this button to collect the clue.' );

				if ( 'text' === display ) {
					previewText = getText( 'textPreview', 'This can be hidden inside normal page copy.' );
				}

				if ( 'image' === display ) {
					previewText = getText( 'imagePreview', 'The selected PNG marker will be clickable on the storefront.' );
				}

				return createElement(
					'div',
					blockProps,
					renderControls( props, true ),
					! attributes.clue &&
					createElement(
						Notice,
						{
							isDismissible: false,
							status: 'warning',
							},
						getText( 'missingClue', 'Add a clue ID before publishing this block.' )
					),
					createElement( 'strong', null, getText( 'clueTitle', 'Treasure clue' ) ),
					createElement( 'p', null, previewText ),
					createElement( 'code', null, attributes.clue || getText( 'clue', 'Clue ID' ) )
				);
			},
			save: function () {
				return null;
			},
		}
	);

	blocks.registerBlockType(
		'datrooster/treasure-progress',
		{
			apiVersion: 2,
			title: getText( 'progressTitle', 'Treasure progress' ),
			description: getText( 'progressDescription', 'Show collected clues and the unlocked coupon code.' ),
			category: 'widgets',
			icon: 'chart-bar',
			attributes: {
				hunt: {
					type: 'string',
					default: defaults.hunt || 'main-hunt',
				},
			},
			edit: function ( props ) {
				var blockProps = useBlockProps(
					{
						className: 'datrooster-treasure-editor-card datrooster-treasure-editor-card--progress',
					}
				);

				return createElement(
					'div',
					blockProps,
					renderControls( props, false ),
					createElement( 'strong', null, getText( 'progressTitle', 'Treasure progress' ) ),
					createElement(
						'p',
						null,
						getText( 'progressPreview', 'This block shows hunt progress and the unlocked coupon code.' )
					)
				);
			},
			save: function () {
				return null;
			},
		}
	);
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element );
