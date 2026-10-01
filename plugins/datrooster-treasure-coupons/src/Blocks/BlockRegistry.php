<?php
/**
 * Block editor integration.
 *
 * @package DatRoosterTreasureCoupons
 */

namespace DatRoosterTreasureCoupons\Blocks;

use DatRoosterTreasureCoupons\Admin\SettingsPage;
use DatRoosterTreasureCoupons\Frontend\Shortcodes;

defined( 'ABSPATH' ) || exit;

final class BlockRegistry {
	private const EDITOR_SCRIPT_HANDLE = 'datrooster-treasure-coupons-blocks-editor';
	private const EDITOR_STYLE_HANDLE  = 'datrooster-treasure-coupons-blocks-editor-style';

	/**
	 * Frontend shortcode renderer reused by dynamic blocks.
	 *
	 * @var Shortcodes
	 */
	private Shortcodes $shortcodes;

	/**
	 * Constructor.
	 *
	 * @param Shortcodes $shortcodes Frontend shortcode renderer.
	 */
	public function __construct( Shortcodes $shortcodes ) {
		$this->shortcodes = $shortcodes;
	}

	/**
	 * Registers block hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Registers editor assets and dynamic blocks.
	 */
	public function register_blocks(): void {
		wp_register_script(
			self::EDITOR_SCRIPT_HANDLE,
			DATROOSTER_TREASURE_COUPONS_URL . 'assets/js/editor-blocks.js',
			array( 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n' ),
			DATROOSTER_TREASURE_COUPONS_VERSION,
			true
		);

		wp_add_inline_script(
			self::EDITOR_SCRIPT_HANDLE,
			'window.datroosterTreasureCouponsBlocks = ' . wp_json_encode( $this->get_editor_config() ) . ';',
			'before'
		);

		wp_register_style(
			self::EDITOR_STYLE_HANDLE,
			DATROOSTER_TREASURE_COUPONS_URL . 'assets/css/editor.css',
			array(),
			DATROOSTER_TREASURE_COUPONS_VERSION
		);

		register_block_type(
			'datrooster/treasure-clue',
			array(
				'api_version'     => 2,
				'title'           => __( 'Treasure clue', 'datrooster-treasure-coupons' ),
				'description'     => __( 'Place a collectible clue anywhere in page content.', 'datrooster-treasure-coupons' ),
				'category'        => 'widgets',
				'icon'            => 'hidden',
				'editor_script'   => self::EDITOR_SCRIPT_HANDLE,
				'editor_style'    => self::EDITOR_STYLE_HANDLE,
				'render_callback' => array( $this, 'render_clue_block' ),
				'attributes'      => array(
					'hunt'           => array(
						'type'    => 'string',
						'default' => (string) SettingsPage::get_settings()['hunt_slug'],
					),
					'clue'           => array(
						'type'    => 'string',
						'default' => '',
					),
					'label'          => array(
						'type'    => 'string',
						'default' => (string) SettingsPage::get_settings()['clue_button_label'],
					),
					'display'        => array(
						'type'    => 'string',
						'default' => 'button',
					),
					'imageMode'      => array(
						'type'    => 'string',
						'default' => 'default:key',
					),
					'customImageUrl' => array(
						'type'    => 'string',
						'default' => '',
					),
					'imageAlt'       => array(
						'type'    => 'string',
						'default' => __( 'Hidden clue', 'datrooster-treasure-coupons' ),
					),
				),
			)
		);

		register_block_type(
			'datrooster/treasure-progress',
			array(
				'api_version'     => 2,
				'title'           => __( 'Treasure progress', 'datrooster-treasure-coupons' ),
				'description'     => __( 'Show collected clues and the unlocked coupon code.', 'datrooster-treasure-coupons' ),
				'category'        => 'widgets',
				'icon'            => 'chart-bar',
				'editor_script'   => self::EDITOR_SCRIPT_HANDLE,
				'editor_style'    => self::EDITOR_STYLE_HANDLE,
				'render_callback' => array( $this, 'render_progress_block' ),
				'attributes'      => array(
					'hunt' => array(
						'type'    => 'string',
						'default' => (string) SettingsPage::get_settings()['hunt_slug'],
					),
				),
			)
		);
	}

	/**
	 * Renders a dynamic clue block.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 */
	public function render_clue_block( array $attributes ): string {
		$display = sanitize_key( (string) ( $attributes['display'] ?? 'button' ) );
		$image   = '';

		if ( 'image' === $display ) {
			$image_mode = (string) ( $attributes['imageMode'] ?? 'default:key' );
			$image      = 'custom' === $image_mode ? (string) ( $attributes['customImageUrl'] ?? '' ) : $image_mode;
		}

		return $this->shortcodes->render_clue_shortcode(
			array(
				'hunt'      => (string) ( $attributes['hunt'] ?? '' ),
				'clue'      => (string) ( $attributes['clue'] ?? '' ),
				'label'     => (string) ( $attributes['label'] ?? '' ),
				'display'   => $display,
				'image'     => $image,
				'image_alt' => (string) ( $attributes['imageAlt'] ?? '' ),
			)
		);
	}

	/**
	 * Renders a dynamic progress block.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 */
	public function render_progress_block( array $attributes ): string {
		return $this->shortcodes->render_progress_shortcode(
			array(
				'hunt' => (string) ( $attributes['hunt'] ?? '' ),
			)
		);
	}

	/**
	 * Returns translated editor labels consumed by the no-build block script.
	 *
	 * @return array<string,mixed>
	 */
	private function get_editor_config(): array {
		$settings = SettingsPage::get_settings();

		return array(
			'defaults' => array(
				'hunt'  => (string) $settings['hunt_slug'],
				'label' => (string) $settings['clue_button_label'],
			),
			'i18n'     => array(
				'clueTitle'           => __( 'Treasure clue', 'datrooster-treasure-coupons' ),
				'clueDescription'     => __( 'Place a collectible clue anywhere in page content.', 'datrooster-treasure-coupons' ),
				'progressTitle'       => __( 'Treasure progress', 'datrooster-treasure-coupons' ),
				'progressDescription' => __( 'Show collected clues and the unlocked coupon code.', 'datrooster-treasure-coupons' ),
				'settings'            => __( 'Treasure settings', 'datrooster-treasure-coupons' ),
				'hunt'                => __( 'Hunt slug', 'datrooster-treasure-coupons' ),
				'clue'                => __( 'Clue ID', 'datrooster-treasure-coupons' ),
				'clueHelp'            => __( 'Use a unique ID for each clue inside the same hunt.', 'datrooster-treasure-coupons' ),
				'label'               => __( 'Visible label', 'datrooster-treasure-coupons' ),
				'display'             => __( 'Display style', 'datrooster-treasure-coupons' ),
				'button'              => __( 'Button', 'datrooster-treasure-coupons' ),
				'text'                => __( 'Hidden text link', 'datrooster-treasure-coupons' ),
				'image'               => __( 'Image marker', 'datrooster-treasure-coupons' ),
				'imagePreset'         => __( 'Image marker', 'datrooster-treasure-coupons' ),
				'defaultKey'          => __( 'Default key', 'datrooster-treasure-coupons' ),
				'defaultGem'          => __( 'Default gem', 'datrooster-treasure-coupons' ),
				'defaultMap'          => __( 'Default map', 'datrooster-treasure-coupons' ),
				'customImage'         => __( 'Custom image URL', 'datrooster-treasure-coupons' ),
				'imageUrl'            => __( 'Custom image URL', 'datrooster-treasure-coupons' ),
				'imageAlt'            => __( 'Image alternative text', 'datrooster-treasure-coupons' ),
				'missingClue'         => __( 'Add a clue ID before publishing this block.', 'datrooster-treasure-coupons' ),
				'buttonPreview'       => __( 'Customers will click this button to collect the clue.', 'datrooster-treasure-coupons' ),
				'textPreview'         => __( 'This can be hidden inside normal page copy.', 'datrooster-treasure-coupons' ),
				'imagePreview'        => __( 'The selected PNG marker will be clickable on the storefront.', 'datrooster-treasure-coupons' ),
				'progressPreview'     => __( 'This block shows hunt progress and the unlocked coupon code.', 'datrooster-treasure-coupons' ),
			),
		);
	}
}
