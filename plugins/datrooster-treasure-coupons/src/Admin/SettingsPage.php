<?php
/**
 * Plugin admin settings page.
 *
 * @package DatRoosterTreasureCoupons
 */

namespace DatRoosterTreasureCoupons\Admin;

use DatRoosterTreasureCoupons\Progress\ProgressStore;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {
	public const OPTION_NAME = 'datrooster_treasure_coupons_settings';
	private const RESET_ACTION = 'datrooster_treasure_reset_progress';

	/**
	 * Registers the settings page hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_' . self::RESET_ACTION, array( $this, 'handle_reset_progress' ) );
	}

	/**
	 * Default values for plugin settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_default_settings(): array {
		return array(
			'enabled'            => 0,
			'hunt_slug'          => 'main-hunt',
			'required_clues'     => 3,
			'require_login'      => 0,
			'auto_apply_coupon'  => 1,
			'coupon_prefix'      => 'TREASURE',
			'discount_type'      => 'percent',
			'discount_amount'    => '10',
			'minimum_spend'      => '0',
			'coupon_valid_days'  => 7,
			'clue_button_label'  => __( 'Collect clue', 'datrooster-treasure-coupons' ),
			'collected_label'    => __( 'Clue collected', 'datrooster-treasure-coupons' ),
			'completion_message' => __( 'You unlocked your reward.', 'datrooster-treasure-coupons' ),
			'applied_message'    => __( 'Your treasure coupon has been applied to the cart.', 'datrooster-treasure-coupons' ),
		);
	}

	/**
	 * Source-language defaults used to detect unchanged stored label values.
	 *
	 * @return array<string,string>
	 */
	private static function get_source_label_defaults(): array {
		return array(
			'clue_button_label'  => 'Collect clue',
			'collected_label'    => 'Clue collected',
			'completion_message' => 'You unlocked your reward.',
			'applied_message'    => 'Your treasure coupon has been applied to the cart.',
		);
	}

	/**
	 * Returns normalized settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings(): array {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = self::normalize_source_label_defaults( $settings );

		return array_merge( self::get_default_settings(), $settings );
	}

	/**
	 * Registers the WooCommerce submenu page.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Treasure Coupons', 'datrooster-treasure-coupons' ),
			__( 'Treasure Coupons', 'datrooster-treasure-coupons' ),
			'manage_woocommerce',
			'datrooster-treasure-coupons',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers settings fields.
	 */
	public function register_settings(): void {
		register_setting(
			'datrooster_treasure_coupons_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => self::get_default_settings(),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'datrooster_treasure_coupons_campaign_section',
			__( 'Campaign settings', 'datrooster-treasure-coupons' ),
			array( $this, 'render_campaign_section' ),
			'datrooster_treasure_coupons'
		);

		$this->add_field(
			'enabled',
			__( 'Enable treasure hunt', 'datrooster-treasure-coupons' ),
			'render_checkbox_field',
			array(
				'label' => __( 'Allow visitors to collect clues and unlock coupons.', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_field(
			'hunt_slug',
			__( 'Hunt slug', 'datrooster-treasure-coupons' ),
			'render_text_field',
			array(
				'description' => __( 'Internal identifier used by the shortcodes. Use lowercase letters, numbers, and hyphens.', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_field(
			'required_clues',
			__( 'Required clues', 'datrooster-treasure-coupons' ),
			'render_number_field',
			array(
				'min'         => '1',
				'step'        => '1',
				'description' => __( 'Number of unique clues required before the reward coupon is generated.', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_field(
			'require_login',
			__( 'Require login', 'datrooster-treasure-coupons' ),
			'render_checkbox_field',
			array(
				'label' => __( 'Restrict campaigns to authenticated customers.', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_field(
			'auto_apply_coupon',
			__( 'Auto-apply coupon', 'datrooster-treasure-coupons' ),
			'render_checkbox_field',
			array(
				'label' => __( 'Automatically apply the unlocked coupon when the customer visits the cart.', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_field(
			'coupon_prefix',
			__( 'Coupon prefix', 'datrooster-treasure-coupons' ),
			'render_text_field',
			array(
				'description' => __( 'Prefix used for generated WooCommerce coupon codes.', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_field(
			'discount_type',
			__( 'Reward type', 'datrooster-treasure-coupons' ),
			'render_select_field',
			array(
				'choices'     => array(
					'percent'       => __( 'Percentage discount', 'datrooster-treasure-coupons' ),
					'fixed_cart'    => __( 'Fixed cart discount', 'datrooster-treasure-coupons' ),
					'free_shipping' => __( 'Free shipping', 'datrooster-treasure-coupons' ),
				),
				'description' => __( 'Defines the WooCommerce reward generated at completion.', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_field(
			'discount_amount',
			__( 'Discount amount', 'datrooster-treasure-coupons' ),
			'render_number_field',
			array(
				'min'         => '0',
				'step'        => '0.01',
				'description' => __( 'Use 10 for 10% when the reward type is percentage. Ignored for free shipping rewards.', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_field(
			'minimum_spend',
			__( 'Minimum spend', 'datrooster-treasure-coupons' ),
			'render_number_field',
			array(
				'min'         => '0',
				'step'        => '0.01',
				'description' => __( 'Optional minimum cart subtotal required by the generated coupon.', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_field(
			'coupon_valid_days',
			__( 'Coupon validity days', 'datrooster-treasure-coupons' ),
			'render_number_field',
			array(
				'min'         => '1',
				'step'        => '1',
				'description' => __( 'Number of days the unlocked coupon remains valid.', 'datrooster-treasure-coupons' ),
			)
		);

		$this->add_field(
			'clue_button_label',
			__( 'Clue button label', 'datrooster-treasure-coupons' ),
			'render_text_field'
		);

		$this->add_field(
			'collected_label',
			__( 'Collected label', 'datrooster-treasure-coupons' ),
			'render_text_field'
		);

		$this->add_field(
			'completion_message',
			__( 'Completion message', 'datrooster-treasure-coupons' ),
			'render_text_field'
		);

		$this->add_field(
			'applied_message',
			__( 'Applied coupon message', 'datrooster-treasure-coupons' ),
			'render_text_field',
			array(
				'description' => __( 'Shown in the temporary storefront notice when the unlocked coupon is applied automatically.', 'datrooster-treasure-coupons' ),
			)
		);
	}

	/**
	 * Renders the settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Treasure Coupons.', 'datrooster-treasure-coupons' ) );
		}

		$settings = self::get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'DatRooster Treasure Coupons', 'datrooster-treasure-coupons' ); ?></h1>
			<?php $this->render_reset_notice(); ?>
			<p>
				<?php esc_html_e( 'Create a simple treasure hunt across pages, posts, and products. Visitors collect clues and unlock a unique WooCommerce coupon.', 'datrooster-treasure-coupons' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'datrooster_treasure_coupons_group' );
				do_settings_sections( 'datrooster_treasure_coupons' );
				submit_button();
				?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Shortcode examples', 'datrooster-treasure-coupons' ); ?></h2>
			<p><?php esc_html_e( 'Place clues anywhere on the site. Each clue ID should be unique inside the same hunt.', 'datrooster-treasure-coupons' ); ?></p>
			<code>[datrooster_treasure_clue hunt="<?php echo esc_attr( $settings['hunt_slug'] ); ?>" clue="first-clue"]</code>
			<br />
			<code>[datrooster_treasure_clue hunt="<?php echo esc_attr( $settings['hunt_slug'] ); ?>" clue="secret-key" image="default:key" image_alt="<?php esc_attr_e( 'Hidden key clue', 'datrooster-treasure-coupons' ); ?>"]</code>
			<br />
			<code>[datrooster_treasure_clue hunt="<?php echo esc_attr( $settings['hunt_slug'] ); ?>" clue="secret-gem" image="default:gem" image_alt="<?php esc_attr_e( 'Hidden gem clue', 'datrooster-treasure-coupons' ); ?>"]</code>
			<br />
			<code>[datrooster_treasure_clue hunt="<?php echo esc_attr( $settings['hunt_slug'] ); ?>" clue="secret-map" image="default:map" image_alt="<?php esc_attr_e( 'Hidden map clue', 'datrooster-treasure-coupons' ); ?>"]</code>
			<br />
			<code>[datrooster_treasure_clue hunt="<?php echo esc_attr( $settings['hunt_slug'] ); ?>" clue="hidden-image" image="https://example.com/clue.png" image_alt="<?php esc_attr_e( 'Hidden clue', 'datrooster-treasure-coupons' ); ?>"]</code>
			<br />
			<code>[datrooster_treasure_progress hunt="<?php echo esc_attr( $settings['hunt_slug'] ); ?>"]</code>

			<hr />
			<h2><?php esc_html_e( 'Testing tools', 'datrooster-treasure-coupons' ); ?></h2>
			<p><?php esc_html_e( 'Reset your current treasure hunt progress when testing the same clues multiple times.', 'datrooster-treasure-coupons' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::RESET_ACTION ); ?>" />
				<?php wp_nonce_field( self::RESET_ACTION ); ?>
				<?php submit_button( __( 'Reset my test progress', 'datrooster-treasure-coupons' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handles current participant progress reset.
	 */
	public function handle_reset_progress(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to reset Treasure Coupons progress.', 'datrooster-treasure-coupons' ) );
		}

		check_admin_referer( self::RESET_ACTION );

		$progress_store = new ProgressStore();
		$progress_store->reset_current_participant();

		wp_safe_redirect(
			add_query_arg(
				'datrooster_treasure_reset',
				'1',
				admin_url( 'admin.php?page=datrooster-treasure-coupons' )
			)
		);
		exit;
	}

	/**
	 * Renders a reset confirmation notice.
	 */
	private function render_reset_notice(): void {
		$reset = filter_input( INPUT_GET, 'datrooster_treasure_reset', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( '1' !== $reset ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'Your Treasure Coupons test progress has been reset.', 'datrooster-treasure-coupons' )
		);
	}

	/**
	 * Sanitizes submitted settings.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize_settings( array $input ): array {
		$defaults = self::get_default_settings();

		$settings = array(
			'enabled'            => empty( $input['enabled'] ) ? 0 : 1,
			'hunt_slug'          => sanitize_title( $input['hunt_slug'] ?? $defaults['hunt_slug'] ),
			'required_clues'     => max( 1, min( 50, absint( $input['required_clues'] ?? $defaults['required_clues'] ) ) ),
			'require_login'      => empty( $input['require_login'] ) ? 0 : 1,
			'auto_apply_coupon'  => empty( $input['auto_apply_coupon'] ) ? 0 : 1,
			'coupon_prefix'      => $this->sanitize_coupon_prefix( $input['coupon_prefix'] ?? $defaults['coupon_prefix'] ),
			'discount_type'      => $this->sanitize_discount_type( $input['discount_type'] ?? $defaults['discount_type'] ),
			'discount_amount'    => $this->sanitize_decimal( $input['discount_amount'] ?? $defaults['discount_amount'] ),
			'minimum_spend'      => $this->sanitize_decimal( $input['minimum_spend'] ?? $defaults['minimum_spend'] ),
			'coupon_valid_days'  => max( 1, min( 365, absint( $input['coupon_valid_days'] ?? $defaults['coupon_valid_days'] ) ) ),
			'clue_button_label'  => sanitize_text_field( $input['clue_button_label'] ?? $defaults['clue_button_label'] ),
			'collected_label'    => sanitize_text_field( $input['collected_label'] ?? $defaults['collected_label'] ),
			'completion_message' => sanitize_text_field( $input['completion_message'] ?? $defaults['completion_message'] ),
			'applied_message'    => sanitize_text_field( $input['applied_message'] ?? $defaults['applied_message'] ),
		);

		if ( '' === $settings['hunt_slug'] ) {
			$settings['hunt_slug'] = $defaults['hunt_slug'];
		}

		return $settings;
	}

	/**
	 * Renders the campaign section description.
	 */
	public function render_campaign_section(): void {
		echo '<p>' . esc_html__( 'Configure the first active treasure hunt. Future milestones can extend this into multiple campaigns.', 'datrooster-treasure-coupons' ) . '</p>';
	}

	/**
	 * Renders a checkbox field.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_checkbox_field( array $args ): void {
		$settings = self::get_settings();
		$key      = (string) $args['key'];
		$label    = isset( $args['label'] ) ? (string) $args['label'] : '';
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>"
				value="1"
				<?php checked( 1, absint( $settings[ $key ] ?? 0 ) ); ?>
			/>
			<?php echo esc_html( $label ); ?>
		</label>
		<?php
	}

	/**
	 * Renders a text field.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_text_field( array $args ): void {
		$settings    = self::get_settings();
		$key         = (string) $args['key'];
		$description = isset( $args['description'] ) ? (string) $args['description'] : '';
		?>
		<input
			type="text"
			class="regular-text"
			name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>"
			value="<?php echo esc_attr( (string) ( $settings[ $key ] ?? '' ) ); ?>"
		/>
		<?php $this->render_description( $description ); ?>
		<?php
	}

	/**
	 * Renders a number field.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_number_field( array $args ): void {
		$settings    = self::get_settings();
		$key         = (string) $args['key'];
		$description = isset( $args['description'] ) ? (string) $args['description'] : '';
		?>
		<input
			type="number"
			name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>"
			value="<?php echo esc_attr( (string) ( $settings[ $key ] ?? '' ) ); ?>"
			min="<?php echo esc_attr( (string) ( $args['min'] ?? '0' ) ); ?>"
			step="<?php echo esc_attr( (string) ( $args['step'] ?? '1' ) ); ?>"
		/>
		<?php $this->render_description( $description ); ?>
		<?php
	}

	/**
	 * Renders a select field.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_select_field( array $args ): void {
		$settings    = self::get_settings();
		$key         = (string) $args['key'];
		$choices     = isset( $args['choices'] ) && is_array( $args['choices'] ) ? $args['choices'] : array();
		$description = isset( $args['description'] ) ? (string) $args['description'] : '';
		?>
		<select name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>">
			<?php foreach ( $choices as $value => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) $value, (string) ( $settings[ $key ] ?? '' ) ); ?>>
					<?php echo esc_html( (string) $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php $this->render_description( $description ); ?>
		<?php
	}

	/**
	 * Adds a settings field to the campaign section.
	 *
	 * @param string              $key Field key.
	 * @param string              $label Field label.
	 * @param string              $callback Callback method.
	 * @param array<string,mixed> $args Additional field arguments.
	 */
	private function add_field( string $key, string $label, string $callback, array $args = array() ): void {
		$args['key'] = $key;

		add_settings_field(
			$key,
			$label,
			array( $this, $callback ),
			'datrooster_treasure_coupons',
			'datrooster_treasure_coupons_campaign_section',
			$args
		);
	}

	/**
	 * Renders a field description.
	 *
	 * @param string $description Description text.
	 */
	private function render_description( string $description ): void {
		if ( '' === $description ) {
			return;
		}

		printf(
			'<p class="description">%s</p>',
			esc_html( $description )
		);
	}

	/**
	 * Removes stored source-language labels so translated defaults can be used.
	 *
	 * @param array<string,mixed> $settings Stored settings.
	 * @return array<string,mixed>
	 */
	private static function normalize_source_label_defaults( array $settings ): array {
		$source_defaults = self::get_source_label_defaults();

		foreach ( $source_defaults as $key => $source_value ) {
			if ( isset( $settings[ $key ] ) && $source_value === $settings[ $key ] ) {
				unset( $settings[ $key ] );
			}
		}

		return $settings;
	}

	/**
	 * Sanitizes a discount type.
	 *
	 * @param mixed $value Raw value.
	 */
	private function sanitize_discount_type( mixed $value ): string {
		$value   = sanitize_key( (string) $value );
		$allowed = array( 'percent', 'fixed_cart', 'free_shipping' );

		return in_array( $value, $allowed, true ) ? $value : 'percent';
	}

	/**
	 * Sanitizes a decimal amount.
	 *
	 * @param mixed $value Raw value.
	 */
	private function sanitize_decimal( mixed $value ): string {
		if ( function_exists( 'wc_format_decimal' ) ) {
			return wc_format_decimal( $value, 2 );
		}

		return number_format( max( 0, (float) $value ), 2, '.', '' );
	}

	/**
	 * Sanitizes the generated coupon prefix.
	 *
	 * @param mixed $value Raw value.
	 */
	private function sanitize_coupon_prefix( mixed $value ): string {
		$prefix = strtoupper( preg_replace( '/[^A-Z0-9_-]/', '', (string) $value ) ?? '' );

		return '' !== $prefix ? substr( $prefix, 0, 20 ) : 'TREASURE';
	}
}
