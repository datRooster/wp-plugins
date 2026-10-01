<?php
/**
 * Universal Customizer placement layer.
 *
 * @package DatRoosterTreasureCoupons
 */

namespace DatRoosterTreasureCoupons\Customizer;

use DatRoosterTreasureCoupons\Admin\SettingsPage;
use DatRoosterTreasureCoupons\Frontend\Shortcodes;

defined( 'ABSPATH' ) || exit;

final class UniversalPlacement {
	public const OPTION_NAME = 'datrooster_treasure_coupons_universal_placement';
	private const SAVE_DRAG_ACTION = 'datrooster_treasure_save_drag_position';
	private const COORDINATE_MODE_LEGACY_PIXEL = 'legacy_pixel';
	private const COORDINATE_MODE_RESPONSIVE_PERCENT = 'responsive_percent';
	private const COORDINATE_MODE_ANCHORED = 'anchored';
	private const DEVICES = array( 'desktop', 'tablet', 'mobile' );

	/**
	 * Frontend shortcode renderer reused by universal placements.
	 *
	 * @var Shortcodes
	 */
	private Shortcodes $shortcodes;

	/**
	 * Tracks whether the placement has already been rendered for this request.
	 *
	 * @var bool
	 */
	private bool $rendered = false;

	/**
	 * Constructor.
	 *
	 * @param Shortcodes $shortcodes Frontend shortcode renderer.
	 */
	public function __construct( Shortcodes $shortcodes ) {
		$this->shortcodes = $shortcodes;
	}

	/**
	 * Registers Customizer and frontend hooks.
	 */
	public function register(): void {
		add_action( 'customize_register', array( $this, 'register_customizer' ) );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_customizer_controls_assets' ) );
		add_action( 'customize_preview_init', array( $this, 'enqueue_customizer_preview_assets' ) );
		add_action( 'wp_ajax_' . self::SAVE_DRAG_ACTION, array( $this, 'handle_save_drag_position' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 20 );
		add_action( 'wp_footer', array( $this, 'render_footer_placement' ), 20 );
		add_filter( 'the_content', array( $this, 'append_to_content' ), 20 );
		add_action( 'woocommerce_before_cart', array( $this, 'render_woocommerce_cart_placement' ), 8 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'render_woocommerce_checkout_placement' ), 8 );
		add_action( 'woocommerce_before_single_product_summary', array( $this, 'render_woocommerce_product_placement' ), 8 );
	}

	/**
	 * Default placement settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_default_settings(): array {
		return array(
			'enabled'          => 0,
			'target'           => 'all',
			'page_id'          => 0,
			'placement'        => 'floating',
			'position'         => 'bottom-right',
			'offset_x'         => '',
			'offset_y'         => '',
			'coordinate_mode'  => self::COORDINATE_MODE_LEGACY_PIXEL,
			'positions'        => array(
				'desktop' => array(
					'offset_x'        => '',
					'offset_y'        => '',
					'coordinate_mode' => self::COORDINATE_MODE_RESPONSIVE_PERCENT,
					'anchor_selector' => '',
					'anchor_x'        => '',
					'anchor_y'        => '',
				),
				'tablet'  => array(
					'offset_x'        => '',
					'offset_y'        => '',
					'coordinate_mode' => self::COORDINATE_MODE_RESPONSIVE_PERCENT,
					'anchor_selector' => '',
					'anchor_x'        => '',
					'anchor_y'        => '',
				),
				'mobile'  => array(
					'offset_x'        => '',
					'offset_y'        => '',
					'coordinate_mode' => self::COORDINATE_MODE_RESPONSIVE_PERCENT,
					'anchor_selector' => '',
					'anchor_x'        => '',
					'anchor_y'        => '',
				),
			),
			'css_selector'     => '',
			'css_method'       => 'append',
			'content'          => 'clue',
			'clue'             => 'universal-clue',
			'label'            => __( 'Collect clue', 'datrooster-treasure-coupons' ),
			'display'          => 'image',
			'image'            => 'default:key',
			'custom_image_url' => '',
			'image_alt'        => __( 'Hidden clue', 'datrooster-treasure-coupons' ),
		);
	}

	/**
	 * Returns normalized placement settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings(): array {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = array_merge( self::get_default_settings(), $settings );
		$settings = self::normalize_device_positions( $settings );

		return $settings;
	}

	/**
	 * Normalizes device-specific placement coordinates.
	 *
	 * @param array<string,mixed> $settings Raw merged placement settings.
	 *
	 * @return array<string,mixed>
	 */
	private static function normalize_device_positions( array $settings ): array {
		$defaults = self::get_default_settings();

		if ( ! isset( $settings['positions'] ) || ! is_array( $settings['positions'] ) ) {
			$settings['positions'] = array();
		}

		foreach ( self::DEVICES as $device ) {
			$position = isset( $settings['positions'][ $device ] ) && is_array( $settings['positions'][ $device ] )
				? $settings['positions'][ $device ]
				: array();

			$settings['positions'][ $device ] = array_merge( $defaults['positions'][ $device ], $position );
		}

		return $settings;
	}

	/**
	 * Registers the Customizer section and controls.
	 *
	 * @param \WP_Customize_Manager $customize_manager Customizer manager.
	 */
	public function register_customizer( \WP_Customize_Manager $customize_manager ): void {
		$customize_manager->add_section(
			'datrooster_treasure_coupons_universal_placement',
			array(
				'title'       => __( 'Treasure Coupons placement', 'datrooster-treasure-coupons' ),
				'description' => __( 'Place a treasure clue or progress module without editing page content. Use this for carts, checkout pages, custom templates, and theme fallback pages.', 'datrooster-treasure-coupons' ),
				'priority'    => 165,
			)
		);

		$this->add_checkbox_control(
			$customize_manager,
			'enabled',
			__( 'Enable universal placement', 'datrooster-treasure-coupons' )
		);

		$this->add_select_control(
			$customize_manager,
			'target',
			__( 'Where to show it', 'datrooster-treasure-coupons' ),
			array(
				'all'           => __( 'Entire site', 'datrooster-treasure-coupons' ),
				'front_page'    => __( 'Front page only', 'datrooster-treasure-coupons' ),
				'specific_page' => __( 'Specific page', 'datrooster-treasure-coupons' ),
				'cart'          => __( 'WooCommerce cart', 'datrooster-treasure-coupons' ),
				'checkout'      => __( 'WooCommerce checkout', 'datrooster-treasure-coupons' ),
				'product'       => __( 'WooCommerce product pages', 'datrooster-treasure-coupons' ),
				'shop'          => __( 'WooCommerce shop archive', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_dropdown_pages_control(
			$customize_manager,
			'page_id',
			__( 'Specific page', 'datrooster-treasure-coupons' )
		);

		$this->add_select_control(
			$customize_manager,
			'placement',
			__( 'Placement method', 'datrooster-treasure-coupons' ),
			array(
				'floating'              => __( 'Floating overlay', 'datrooster-treasure-coupons' ),
				'after_content'         => __( 'After page content', 'datrooster-treasure-coupons' ),
				'css_selector'          => __( 'Advanced CSS selector', 'datrooster-treasure-coupons' ),
				'woocommerce_cart'      => __( 'Before cart content', 'datrooster-treasure-coupons' ),
				'woocommerce_checkout'  => __( 'Before checkout form', 'datrooster-treasure-coupons' ),
				'woocommerce_product'   => __( 'Before product summary', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_select_control(
			$customize_manager,
			'position',
			__( 'Floating fallback position', 'datrooster-treasure-coupons' ),
			array(
				'bottom-right' => __( 'Bottom right', 'datrooster-treasure-coupons' ),
				'bottom-left'  => __( 'Bottom left', 'datrooster-treasure-coupons' ),
				'top-right'    => __( 'Top right', 'datrooster-treasure-coupons' ),
				'top-left'     => __( 'Top left', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_number_control(
			$customize_manager,
			'offset_x',
			__( 'Horizontal responsive position (%)', 'datrooster-treasure-coupons' ),
			__( 'Drag the marker in the preview, or set a manual horizontal position from 0 to 100.', 'datrooster-treasure-coupons' )
		);

		$this->add_number_control(
			$customize_manager,
			'offset_y',
			__( 'Vertical page position (px)', 'datrooster-treasure-coupons' ),
			__( 'Drag the marker in the preview, or set a manual vertical position in page pixels.', 'datrooster-treasure-coupons' )
		);

		$this->add_text_control(
			$customize_manager,
			'css_selector',
			__( 'CSS selector', 'datrooster-treasure-coupons' ),
			__( 'Advanced mode only. Example: .cart_totals or #site-footer. If the selector is not found, the floating fallback remains visible.', 'datrooster-treasure-coupons' )
		);

		$this->add_select_control(
			$customize_manager,
			'css_method',
			__( 'CSS selector insertion', 'datrooster-treasure-coupons' ),
			array(
				'append'  => __( 'Inside target, at the end', 'datrooster-treasure-coupons' ),
				'prepend' => __( 'Inside target, at the beginning', 'datrooster-treasure-coupons' ),
				'before'  => __( 'Before target', 'datrooster-treasure-coupons' ),
				'after'   => __( 'After target', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_select_control(
			$customize_manager,
			'content',
			__( 'Content to render', 'datrooster-treasure-coupons' ),
			array(
				'clue'          => __( 'Clue only', 'datrooster-treasure-coupons' ),
				'progress'      => __( 'Progress only', 'datrooster-treasure-coupons' ),
				'clue_progress' => __( 'Clue and progress', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_text_control(
			$customize_manager,
			'clue',
			__( 'Clue ID', 'datrooster-treasure-coupons' ),
			__( 'Use a unique ID for this automatically placed clue.', 'datrooster-treasure-coupons' )
		);

		$this->add_text_control(
			$customize_manager,
			'label',
			__( 'Visible label', 'datrooster-treasure-coupons' )
		);

		$this->add_select_control(
			$customize_manager,
			'display',
			__( 'Display style', 'datrooster-treasure-coupons' ),
			array(
				'button' => __( 'Button', 'datrooster-treasure-coupons' ),
				'text'   => __( 'Hidden text link', 'datrooster-treasure-coupons' ),
				'image'  => __( 'Image marker', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_select_control(
			$customize_manager,
			'image',
			__( 'Image marker', 'datrooster-treasure-coupons' ),
			array(
				'default:key' => __( 'Default key', 'datrooster-treasure-coupons' ),
				'default:gem' => __( 'Default gem', 'datrooster-treasure-coupons' ),
				'default:map' => __( 'Default map', 'datrooster-treasure-coupons' ),
				'custom'      => __( 'Custom image URL', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_url_control(
			$customize_manager,
			'custom_image_url',
			__( 'Custom image URL', 'datrooster-treasure-coupons' )
		);

		$this->add_text_control(
			$customize_manager,
			'image_alt',
			__( 'Image alternative text', 'datrooster-treasure-coupons' )
		);
	}

	/**
	 * Enqueues Customizer controls assets.
	 */
	public function enqueue_customizer_controls_assets(): void {
		wp_enqueue_script(
			'datrooster-treasure-coupons-customizer-controls',
			DATROOSTER_TREASURE_COUPONS_URL . 'assets/js/customizer-controls.js',
			array( 'customize-controls' ),
			DATROOSTER_TREASURE_COUPONS_VERSION,
			true
		);

		wp_add_inline_script(
			'datrooster-treasure-coupons-customizer-controls',
			'window.datroosterTreasureCustomizerControls = ' . wp_json_encode(
				array(
					'settingIds' => $this->get_drag_setting_ids(),
					'i18n'       => array(
						'positionReceived' => __( 'Position received. Publish or wait for autosave.', 'datrooster-treasure-coupons' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Enqueues Customizer preview assets.
	 */
	public function enqueue_customizer_preview_assets(): void {
		wp_enqueue_script(
			'datrooster-treasure-coupons-customizer-preview',
			DATROOSTER_TREASURE_COUPONS_URL . 'assets/js/customizer-preview.js',
			array( 'customize-preview' ),
			DATROOSTER_TREASURE_COUPONS_VERSION,
			true
		);

		wp_add_inline_script(
			'datrooster-treasure-coupons-customizer-preview',
			'window.datroosterTreasureCustomizerPreview = ' . wp_json_encode(
				array(
					'settingIds' => $this->get_drag_setting_ids(),
					'coordinateMode' => $this->get_coordinate_mode( self::get_settings() ),
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'action'     => self::SAVE_DRAG_ACTION,
					'nonce'      => wp_create_nonce( self::SAVE_DRAG_ACTION ),
					'i18n'       => array(
						'dragHint' => __( 'Drag to place this treasure clue. Release to save the position.', 'datrooster-treasure-coupons' ),
						'saving'   => __( 'Saving position...', 'datrooster-treasure-coupons' ),
						'saved'    => __( 'Position saved.', 'datrooster-treasure-coupons' ),
						'failed'   => __( 'Could not save the position. Please try again.', 'datrooster-treasure-coupons' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Saves visual drag coordinates from the Customizer preview.
	 */
	public function handle_save_drag_position(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to save Treasure Coupons placement.', 'datrooster-treasure-coupons' ),
				),
				403
			);
		}

		check_ajax_referer( self::SAVE_DRAG_ACTION, 'nonce' );

		$x               = $this->sanitize_percent_or_empty( $this->get_request_value( 'x' ) );
		$y               = $this->sanitize_coordinate_or_empty( $this->get_request_value( 'y' ) );
		$device          = $this->sanitize_device( $this->get_request_value( 'device' ) );
		$anchor_selector = $this->sanitize_css_selector( $this->get_request_value( 'anchor_selector' ), 260 );
		$anchor_x        = $this->sanitize_percent_or_empty( $this->get_request_value( 'anchor_x' ) );
		$anchor_y        = $this->sanitize_percent_or_empty( $this->get_request_value( 'anchor_y' ) );
		$coordinate_mode = '' !== $anchor_selector && '' !== $anchor_x && '' !== $anchor_y
			? self::COORDINATE_MODE_ANCHORED
			: self::COORDINATE_MODE_RESPONSIVE_PERCENT;

		if ( '' === $x || '' === $y ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid treasure clue position.', 'datrooster-treasure-coupons' ),
				),
				400
			);
		}

		$settings                                              = self::get_settings();
		$settings['positions'][ $device ]['offset_x']          = $x;
		$settings['positions'][ $device ]['offset_y']          = $y;
		$settings['positions'][ $device ]['coordinate_mode']   = $coordinate_mode;
		$settings['positions'][ $device ]['anchor_selector']   = self::COORDINATE_MODE_ANCHORED === $coordinate_mode ? $anchor_selector : '';
		$settings['positions'][ $device ]['anchor_x']          = self::COORDINATE_MODE_ANCHORED === $coordinate_mode ? $anchor_x : '';
		$settings['positions'][ $device ]['anchor_y']          = self::COORDINATE_MODE_ANCHORED === $coordinate_mode ? $anchor_y : '';
		$settings['offset_x']                                  = $x;
		$settings['offset_y']                                  = $y;
		$settings['coordinate_mode']                           = $coordinate_mode;

		update_option( self::OPTION_NAME, $settings );

		wp_send_json_success(
			array(
				'coordinate_mode' => $coordinate_mode,
				'device'          => $device,
				'offset_x'        => $x,
				'offset_y'        => $y,
			)
		);
	}

	/**
	 * Enqueues assets early enough for universal placements.
	 */
	public function enqueue_frontend_assets(): void {
		if ( ! $this->should_render() ) {
			return;
		}

		$settings = self::get_settings();
		$uses_document_coordinates = $this->uses_document_coordinates( $settings );

		wp_enqueue_style( 'datrooster-treasure-coupons' );

		if ( 'css_selector' !== $settings['placement'] && ! $uses_document_coordinates ) {
			return;
		}

		wp_enqueue_script(
			'datrooster-treasure-coupons-universal-placement',
			DATROOSTER_TREASURE_COUPONS_URL . 'assets/js/universal-placement.js',
			array(),
			DATROOSTER_TREASURE_COUPONS_VERSION,
			true
		);
	}

	/**
	 * Renders footer-based placements.
	 */
	public function render_footer_placement(): void {
		$settings = self::get_settings();

		if ( ! in_array( $settings['placement'], array( 'floating', 'css_selector' ), true ) ) {
			return;
		}

		$this->output_placement( (string) $settings['placement'] );
	}

	/**
	 * Appends the placement after page content.
	 *
	 * @param string $content Original content.
	 */
	public function append_to_content( string $content ): string {
		$settings = self::get_settings();

		if ( 'after_content' !== $settings['placement'] || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		return $content . $this->render_placement( 'after-content' );
	}

	/**
	 * Renders before WooCommerce cart content.
	 */
	public function render_woocommerce_cart_placement(): void {
		if ( 'woocommerce_cart' !== self::get_settings()['placement'] ) {
			return;
		}

		$this->output_placement( 'woocommerce-cart' );
	}

	/**
	 * Renders before WooCommerce checkout content.
	 */
	public function render_woocommerce_checkout_placement(): void {
		if ( 'woocommerce_checkout' !== self::get_settings()['placement'] ) {
			return;
		}

		$this->output_placement( 'woocommerce-checkout' );
	}

	/**
	 * Renders before WooCommerce product summary.
	 */
	public function render_woocommerce_product_placement(): void {
		if ( 'woocommerce_product' !== self::get_settings()['placement'] ) {
			return;
		}

		$this->output_placement( 'woocommerce-product' );
	}

	/**
	 * Outputs a placement when safe to do so.
	 *
	 * @param string $context Render context.
	 */
	private function output_placement( string $context ): void {
		$placement = $this->render_placement( $context );

		if ( '' === $placement ) {
			return;
		}

		echo $placement; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is assembled from escaped wrapper attributes and shortcode renderers.
	}

	/**
	 * Renders a placement wrapper.
	 *
	 * @param string $context Render context.
	 */
	private function render_placement( string $context ): string {
		if ( $this->rendered || ! $this->should_render() ) {
			return '';
		}

		$settings       = self::get_settings();
		$this->rendered = true;
		$content        = $this->render_content( $settings );

		if ( '' === $content ) {
			return '';
		}

		$classes = array(
			'datrooster-treasure-universal',
			'datrooster-treasure-universal--' . sanitize_html_class( $context ),
			'datrooster-treasure-universal--' . sanitize_html_class( (string) $settings['position'] ),
		);

		$attributes             = '';
		$is_visual_placement    = $this->supports_visual_coordinates( $settings );
		$is_document_positioned = $this->uses_document_coordinates( $settings );
		$style                  = $is_visual_placement ? $this->get_coordinate_style( $settings ) : '';

		if ( $is_document_positioned ) {
			$classes[]  = 'datrooster-treasure-universal--document-positioned';
			$attributes = sprintf(
				'%1$s data-datrooster-document-positioned="1"%2$s',
				$attributes,
				$this->get_anchor_attributes( $settings )
			);
		}

		if ( is_customize_preview() && $is_visual_placement ) {
			$classes[]  = 'datrooster-treasure-universal--customize-draggable';
			$attributes = sprintf(
				'%1$s data-datrooster-draggable="1" data-datrooster-drag-hint="%2$s"',
				$attributes,
				esc_attr__( 'Drag to place this treasure clue. Release to save the position.', 'datrooster-treasure-coupons' )
			);
		}

		if ( 'css_selector' === $settings['placement'] ) {
			$attributes = sprintf(
				'%1$s data-datrooster-placement-selector="%2$s" data-datrooster-placement-method="%3$s"',
				$attributes,
				esc_attr( (string) $settings['css_selector'] ),
				esc_attr( (string) $settings['css_method'] )
			);
		}

		return sprintf(
			'<div class="%1$s"%2$s%3$s>%4$s</div>',
			esc_attr( implode( ' ', $classes ) ),
			$attributes,
			'' !== $style ? ' style="' . esc_attr( $style ) . '"' : '',
			$content
		);
	}

	/**
	 * Returns frontend data attributes for anchor-based placement.
	 *
	 * @param array<string,mixed> $settings Placement settings.
	 */
	private function get_anchor_attributes( array $settings ): string {
		$attributes = '';

		foreach ( self::DEVICES as $device ) {
			$position = $this->get_device_position( $settings, $device );

			if ( ! $this->is_anchor_position( $position ) ) {
				continue;
			}

			$suffix     = 'desktop' === $device ? '' : '-' . $device;
			$attributes = sprintf(
				'%1$s data-datrooster-anchor-selector%2$s="%3$s" data-datrooster-anchor-x%2$s="%4$s" data-datrooster-anchor-y%2$s="%5$s"',
				$attributes,
				$suffix,
				esc_attr( (string) $position['anchor_selector'] ),
				esc_attr( $this->sanitize_percent_or_empty( $position['anchor_x'] ) ),
				esc_attr( $this->sanitize_percent_or_empty( $position['anchor_y'] ) )
			);
		}

		return $attributes;
	}

	/**
	 * Checks whether a position contains a valid DOM anchor.
	 *
	 * @param array<string,string> $position Device position.
	 */
	private function is_anchor_position( array $position ): bool {
		return self::COORDINATE_MODE_ANCHORED === (string) $position['coordinate_mode']
			&& '' !== (string) $position['anchor_selector']
			&& '' !== $this->sanitize_percent_or_empty( $position['anchor_x'] )
			&& '' !== $this->sanitize_percent_or_empty( $position['anchor_y'] );
	}

	/**
	 * Renders configured universal content.
	 *
	 * @param array<string,mixed> $settings Placement settings.
	 */
	private function render_content( array $settings ): string {
		$campaign_settings = SettingsPage::get_settings();
		$hunt_slug         = (string) $campaign_settings['hunt_slug'];
		$content           = (string) $settings['content'];
		$parts             = array();

		if ( in_array( $content, array( 'clue', 'clue_progress' ), true ) ) {
			$image = 'custom' === $settings['image'] ? (string) $settings['custom_image_url'] : (string) $settings['image'];

			$parts[] = $this->shortcodes->render_clue_shortcode(
				array(
					'hunt'      => $hunt_slug,
					'clue'      => (string) $settings['clue'],
					'label'     => (string) $settings['label'],
					'display'   => (string) $settings['display'],
					'image'     => $image,
					'image_alt' => (string) $settings['image_alt'],
				)
			);
		}

		if ( in_array( $content, array( 'progress', 'clue_progress' ), true ) ) {
			$parts[] = $this->shortcodes->render_progress_shortcode(
				array(
					'hunt' => $hunt_slug,
				)
			);
		}

		return implode( "\n", array_filter( $parts ) );
	}

	/**
	 * Checks whether the current request should render the universal placement.
	 */
	private function should_render(): bool {
		$settings = self::get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		switch ( (string) $settings['target'] ) {
			case 'front_page':
				return is_front_page();

			case 'specific_page':
				return absint( $settings['page_id'] ) > 0 && is_page( absint( $settings['page_id'] ) );

			case 'cart':
				return function_exists( 'is_cart' ) && is_cart();

			case 'checkout':
				return function_exists( 'is_checkout' ) && is_checkout();

			case 'product':
				return function_exists( 'is_product' ) && is_product();

			case 'shop':
				return function_exists( 'is_shop' ) && is_shop();

			case 'all':
			default:
				return true;
		}
	}

	/**
	 * Adds a checkbox Customizer control.
	 *
	 * @param \WP_Customize_Manager $customize_manager Customizer manager.
	 * @param string                $key Setting key.
	 * @param string                $label Control label.
	 */
	private function add_checkbox_control( \WP_Customize_Manager $customize_manager, string $key, string $label ): void {
		$this->add_setting(
			$customize_manager,
			$key,
			array( $this, 'sanitize_checkbox' )
		);

		$customize_manager->add_control(
			$this->get_setting_id( $key ),
			array(
				'label'   => $label,
				'section' => 'datrooster_treasure_coupons_universal_placement',
				'type'    => 'checkbox',
			)
		);
	}

	/**
	 * Adds a text Customizer control.
	 *
	 * @param \WP_Customize_Manager $customize_manager Customizer manager.
	 * @param string                $key Setting key.
	 * @param string                $label Control label.
	 * @param string                $description Optional description.
	 */
	private function add_text_control( \WP_Customize_Manager $customize_manager, string $key, string $label, string $description = '' ): void {
		$this->add_setting(
			$customize_manager,
			$key,
			'css_selector' === $key ? array( $this, 'sanitize_css_selector' ) : 'sanitize_text_field'
		);

		$customize_manager->add_control(
			$this->get_setting_id( $key ),
			array(
				'label'       => $label,
				'description' => $description,
				'section'     => 'datrooster_treasure_coupons_universal_placement',
				'type'        => 'text',
			)
		);
	}

	/**
	 * Adds a number Customizer control.
	 *
	 * @param \WP_Customize_Manager $customize_manager Customizer manager.
	 * @param string                $key Setting key.
	 * @param string                $label Control label.
	 * @param string                $description Optional description.
	 */
	private function add_number_control( \WP_Customize_Manager $customize_manager, string $key, string $label, string $description = '' ): void {
		$this->add_setting(
			$customize_manager,
			$key,
			'offset_x' === $key ? array( $this, 'sanitize_percent_or_empty' ) : array( $this, 'sanitize_coordinate_or_empty' ),
			'postMessage'
		);

		$customize_manager->add_control(
			$this->get_setting_id( $key ),
			array(
				'label'       => $label,
				'description' => $description,
				'section'     => 'datrooster_treasure_coupons_universal_placement',
				'type'        => 'number',
				'input_attrs' => array(
					'min'  => '0',
					'max'  => 'offset_x' === $key ? '100' : '100000',
					'step' => '0.1',
				),
			)
		);
	}

	/**
	 * Adds a URL Customizer control.
	 *
	 * @param \WP_Customize_Manager $customize_manager Customizer manager.
	 * @param string                $key Setting key.
	 * @param string                $label Control label.
	 */
	private function add_url_control( \WP_Customize_Manager $customize_manager, string $key, string $label ): void {
		$this->add_setting( $customize_manager, $key, 'esc_url_raw' );

		$customize_manager->add_control(
			$this->get_setting_id( $key ),
			array(
				'label'   => $label,
				'section' => 'datrooster_treasure_coupons_universal_placement',
				'type'    => 'url',
			)
		);
	}

	/**
	 * Adds a select Customizer control.
	 *
	 * @param \WP_Customize_Manager $customize_manager Customizer manager.
	 * @param string                $key Setting key.
	 * @param string                $label Control label.
	 * @param array<string,string>  $choices Select choices.
	 */
	private function add_select_control( \WP_Customize_Manager $customize_manager, string $key, string $label, array $choices ): void {
		$this->add_setting(
			$customize_manager,
			$key,
			static function ( mixed $value ) use ( $choices ): string {
				$value = sanitize_text_field( (string) $value );

				return array_key_exists( $value, $choices ) ? $value : (string) array_key_first( $choices );
			}
		);

		$customize_manager->add_control(
			$this->get_setting_id( $key ),
			array(
				'label'   => $label,
				'section' => 'datrooster_treasure_coupons_universal_placement',
				'type'    => 'select',
				'choices' => $choices,
			)
		);
	}

	/**
	 * Adds a dropdown pages Customizer control.
	 *
	 * @param \WP_Customize_Manager $customize_manager Customizer manager.
	 * @param string                $key Setting key.
	 * @param string                $label Control label.
	 */
	private function add_dropdown_pages_control( \WP_Customize_Manager $customize_manager, string $key, string $label ): void {
		$this->add_setting( $customize_manager, $key, 'absint' );

		$customize_manager->add_control(
			$this->get_setting_id( $key ),
			array(
				'label'   => $label,
				'section' => 'datrooster_treasure_coupons_universal_placement',
				'type'    => 'dropdown-pages',
			)
		);
	}

	/**
	 * Adds a Customizer setting stored inside the placement option.
	 *
	 * @param \WP_Customize_Manager $customize_manager Customizer manager.
	 * @param string                $key Setting key.
	 * @param callable|string       $sanitize_callback Sanitize callback.
	 * @param string                $transport Customizer transport.
	 */
	private function add_setting( \WP_Customize_Manager $customize_manager, string $key, callable|string $sanitize_callback, string $transport = 'refresh' ): void {
		$defaults = self::get_default_settings();

		$customize_manager->add_setting(
			$this->get_setting_id( $key ),
			array(
				'default'           => $defaults[ $key ] ?? '',
				'type'              => 'option',
				'capability'        => 'manage_woocommerce',
				'sanitize_callback' => $sanitize_callback,
				'transport'         => $transport,
			)
		);
	}

	/**
	 * Returns the Customizer setting ID for an option key.
	 *
	 * @param string $key Setting key.
	 */
	private function get_setting_id( string $key ): string {
		return self::OPTION_NAME . '[' . $key . ']';
	}

	/**
	 * Sanitizes checkbox values.
	 *
	 * @param mixed $value Raw value.
	 */
	public function sanitize_checkbox( mixed $value ): int {
		return empty( $value ) ? 0 : 1;
	}

	/**
	 * Sanitizes CSS selector text.
	 *
	 * @param mixed $value      Raw value.
	 * @param int   $max_length Maximum selector length.
	 */
	public function sanitize_css_selector( mixed $value, int $max_length = 160 ): string {
		$charset = get_bloginfo( 'charset' );

		if ( '' === $charset ) {
			$charset = 'UTF-8';
		}

		$value = sanitize_text_field( (string) $value );
		$value = html_entity_decode( $value, ENT_QUOTES, $charset );
		$value = str_replace( array( '<', '"', "'" ), '', $value );

		return substr( $value, 0, $max_length );
	}

	/**
	 * Returns a sanitized request value.
	 *
	 * @param string $key Request key.
	 */
	private function get_request_value( string $key ): string {
		$value = filter_input( INPUT_POST, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * Sanitizes a Customizer preview device name.
	 *
	 * @param mixed $value Raw value.
	 */
	private function sanitize_device( mixed $value ): string {
		$value = sanitize_key( (string) $value );

		return in_array( $value, self::DEVICES, true ) ? $value : 'desktop';
	}

	/**
	 * Sanitizes a page coordinate value, allowing an empty value to use fallback positioning.
	 *
	 * @param mixed $value Raw value.
	 */
	public function sanitize_coordinate_or_empty( mixed $value ): string {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		return (string) round( min( 100000, max( 0, (float) $value ) ), 2 );
	}

	/**
	 * Sanitizes a percentage value, allowing an empty value to use fallback positioning.
	 *
	 * @param mixed $value Raw value.
	 */
	public function sanitize_percent_or_empty( mixed $value ): string {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		return (string) round( min( 100, max( 0, (float) $value ) ), 2 );
	}

	/**
	 * Returns Customizer setting IDs used by drag scripts.
	 *
	 * @return array<string,string>
	 */
	private function get_drag_setting_ids(): array {
		return array(
			'x' => $this->get_setting_id( 'offset_x' ),
			'y' => $this->get_setting_id( 'offset_y' ),
		);
	}

	/**
	 * Checks whether visual placement coordinates were saved.
	 *
	 * @param array<string,mixed> $settings Placement settings.
	 */
	private function has_saved_coordinates( array $settings ): bool {
		foreach ( self::DEVICES as $device ) {
			$position = $this->get_raw_device_position( $settings, $device );

			if ( '' !== (string) $position['offset_x'] && '' !== (string) $position['offset_y'] ) {
				return true;
			}
		}

		return '' !== (string) $settings['offset_x'] && '' !== (string) $settings['offset_y'];
	}

	/**
	 * Checks whether the current placement can be visually positioned.
	 *
	 * @param array<string,mixed> $settings Placement settings.
	 */
	private function supports_visual_coordinates( array $settings ): bool {
		return in_array( (string) $settings['placement'], array( 'floating', 'css_selector' ), true );
	}

	/**
	 * Checks whether visual coordinates should anchor the clue to the document.
	 *
	 * @param array<string,mixed> $settings Placement settings.
	 */
	private function uses_document_coordinates( array $settings ): bool {
		return $this->supports_visual_coordinates( $settings ) && $this->has_saved_coordinates( $settings );
	}

	/**
	 * Returns the saved coordinate mode.
	 *
	 * @param array<string,mixed> $settings Placement settings.
	 */
	private function get_coordinate_mode( array $settings ): string {
		$mode = (string) $settings['coordinate_mode'];

		if ( in_array( $mode, array( self::COORDINATE_MODE_RESPONSIVE_PERCENT, self::COORDINATE_MODE_ANCHORED ), true ) ) {
			return $mode;
		}

		return self::COORDINATE_MODE_LEGACY_PIXEL;
	}

	/**
	 * Returns raw saved coordinates for a device without fallback.
	 *
	 * @param array<string,mixed> $settings Placement settings.
	 * @param string              $device Preview device.
	 *
	 * @return array<string,string>
	 */
	private function get_raw_device_position( array $settings, string $device ): array {
		$device = $this->sanitize_device( $device );

		if ( ! isset( $settings['positions'][ $device ] ) || ! is_array( $settings['positions'][ $device ] ) ) {
			return array(
				'offset_x'        => '',
				'offset_y'        => '',
				'coordinate_mode' => self::COORDINATE_MODE_RESPONSIVE_PERCENT,
				'anchor_selector' => '',
				'anchor_x'        => '',
				'anchor_y'        => '',
			);
		}

		return array(
			'offset_x'        => isset( $settings['positions'][ $device ]['offset_x'] ) ? (string) $settings['positions'][ $device ]['offset_x'] : '',
			'offset_y'        => isset( $settings['positions'][ $device ]['offset_y'] ) ? (string) $settings['positions'][ $device ]['offset_y'] : '',
			'coordinate_mode' => isset( $settings['positions'][ $device ]['coordinate_mode'] ) ? (string) $settings['positions'][ $device ]['coordinate_mode'] : self::COORDINATE_MODE_RESPONSIVE_PERCENT,
			'anchor_selector' => isset( $settings['positions'][ $device ]['anchor_selector'] ) ? (string) $settings['positions'][ $device ]['anchor_selector'] : '',
			'anchor_x'        => isset( $settings['positions'][ $device ]['anchor_x'] ) ? (string) $settings['positions'][ $device ]['anchor_x'] : '',
			'anchor_y'        => isset( $settings['positions'][ $device ]['anchor_y'] ) ? (string) $settings['positions'][ $device ]['anchor_y'] : '',
		);
	}

	/**
	 * Returns saved coordinates for a device with desktop and legacy fallback.
	 *
	 * @param array<string,mixed> $settings Placement settings.
	 * @param string              $device Preview device.
	 *
	 * @return array<string,string>
	 */
	private function get_device_position( array $settings, string $device ): array {
		$position = $this->get_raw_device_position( $settings, $device );

		if ( '' !== (string) $position['offset_x'] && '' !== (string) $position['offset_y'] ) {
			return $position;
		}

		if ( 'mobile' === $device ) {
			$tablet_position = $this->get_raw_device_position( $settings, 'tablet' );

			if ( '' !== (string) $tablet_position['offset_x'] && '' !== (string) $tablet_position['offset_y'] ) {
				return $tablet_position;
			}
		}

		if ( 'desktop' !== $device ) {
			$desktop_position = $this->get_raw_device_position( $settings, 'desktop' );

			if ( '' !== (string) $desktop_position['offset_x'] && '' !== (string) $desktop_position['offset_y'] ) {
				return $desktop_position;
			}
		}

		return array(
			'offset_x'        => (string) $settings['offset_x'],
			'offset_y'        => (string) $settings['offset_y'],
			'coordinate_mode' => $this->get_coordinate_mode( $settings ),
			'anchor_selector' => '',
			'anchor_x'        => '',
			'anchor_y'        => '',
		);
	}

	/**
	 * Converts a device position to CSS custom property values.
	 *
	 * @param array<string,string> $position Device position.
	 *
	 * @return array{x:string,y:string}
	 */
	private function get_position_css_values( array $position ): array {
		$mode = in_array( (string) $position['coordinate_mode'], array( self::COORDINATE_MODE_RESPONSIVE_PERCENT, self::COORDINATE_MODE_ANCHORED ), true )
			? self::COORDINATE_MODE_RESPONSIVE_PERCENT
			: self::COORDINATE_MODE_LEGACY_PIXEL;

		if ( self::COORDINATE_MODE_RESPONSIVE_PERCENT === $mode ) {
			$x = sprintf(
				'clamp(16px,%s%%,calc(100vw - 16px))',
				$this->sanitize_percent_or_empty( $position['offset_x'] )
			);
		} else {
			$x = sprintf(
				'clamp(16px,%spx,calc(100vw - 16px))',
				$this->sanitize_coordinate_or_empty( $position['offset_x'] )
			);
		}

		return array(
			'x' => $x,
			'y' => $this->sanitize_coordinate_or_empty( $position['offset_y'] ) . 'px',
		);
	}

	/**
	 * Returns document coordinate styles when visual placement has been saved.
	 *
	 * @param array<string,mixed> $settings Placement settings.
	 */
	private function get_coordinate_style( array $settings ): string {
		$desktop_position = $this->get_device_position( $settings, 'desktop' );

		if ( '' === (string) $desktop_position['offset_x'] || '' === (string) $desktop_position['offset_y'] ) {
			return '';
		}

		$desktop_values = $this->get_position_css_values( $desktop_position );
		$tablet_values  = $this->get_position_css_values( $this->get_device_position( $settings, 'tablet' ) );
		$mobile_values  = $this->get_position_css_values( $this->get_device_position( $settings, 'mobile' ) );

		return sprintf(
			'position:absolute;--datrooster-treasure-x:%1$s;--datrooster-treasure-y:%2$s;--datrooster-treasure-tablet-x:%3$s;--datrooster-treasure-tablet-y:%4$s;--datrooster-treasure-mobile-x:%5$s;--datrooster-treasure-mobile-y:%6$s;',
			$desktop_values['x'],
			$desktop_values['y'],
			$tablet_values['x'],
			$tablet_values['y'],
			$mobile_values['x'],
			$mobile_values['y']
		);
	}
}
