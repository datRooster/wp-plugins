<?php
/**
 * Plugin admin settings page.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments\Admin;

use DatRooster\PartialPayments\Compatibility\WooCommerce;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {
	public const GENERAL_OPTION = 'drpp_general_settings';
	public const LABELS_OPTION  = 'drpp_label_settings';

	/**
	 * Registers the settings page hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Default values for general settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_default_general_settings(): array {
		return array(
			'enabled'             => 0,
			'require_login'       => 0,
			'deposit_type'        => 'percentage',
			'deposit_amount'      => '50',
			'default_selection'   => 'deposit',
			'fully_paid_status'   => 'wc-completed',
			'tax_handling'        => 'proportional',
			'shipping_handling'   => 'upfront',
			'coupon_handling'     => 'initial_payment',
			'disabled_gateways'   => array(),
		);
	}

	/**
	 * Default values for label settings.
	 *
	 * @return array<string,string>
	 */
	public static function get_default_label_settings(): array {
		return array(
			'pay_deposit_text'     => __( 'Pay deposit', 'datrooster-partial-payments' ),
			'pay_full_amount_text' => __( 'Pay full amount', 'datrooster-partial-payments' ),
			'deposit_text'         => __( 'Deposit', 'datrooster-partial-payments' ),
			'to_pay_text'          => __( 'To pay', 'datrooster-partial-payments' ),
			'future_payments_text' => __( 'Future payments', 'datrooster-partial-payments' ),
			'deposit_amount_text'  => __( 'Deposit amount', 'datrooster-partial-payments' ),
		);
	}

	/**
	 * Registers the submenu page.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Partial Payments', 'datrooster-partial-payments' ),
			__( 'Partial Payments', 'datrooster-partial-payments' ),
			'manage_woocommerce',
			'datrooster-partial-payments',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers plugin settings.
	 */
	public function register_settings(): void {
		register_setting(
			'drpp_general_group',
			self::GENERAL_OPTION,
			array(
				'type'              => 'array',
				'default'           => self::get_default_general_settings(),
				'sanitize_callback' => array( $this, 'sanitize_general_settings' ),
			)
		);

		register_setting(
			'drpp_labels_group',
			self::LABELS_OPTION,
			array(
				'type'              => 'array',
				'default'           => self::get_default_label_settings(),
				'sanitize_callback' => array( $this, 'sanitize_label_settings' ),
			)
		);

		add_settings_section(
			'drpp_general_section',
			__( 'General settings', 'datrooster-partial-payments' ),
			array( $this, 'render_general_section' ),
			'drpp_general'
		);

		add_settings_field(
			'enabled',
			__( 'Enable deposits', 'datrooster-partial-payments' ),
			array( $this, 'render_checkbox_field' ),
			'drpp_general',
			'drpp_general_section',
			array(
				'option_name' => self::GENERAL_OPTION,
				'key'         => 'enabled',
				'label'       => __( 'Allow store-wide deposit features.', 'datrooster-partial-payments' ),
			)
		);

		add_settings_field(
			'require_login',
			__( 'Require login', 'datrooster-partial-payments' ),
			array( $this, 'render_checkbox_field' ),
			'drpp_general',
			'drpp_general_section',
			array(
				'option_name' => self::GENERAL_OPTION,
				'key'         => 'require_login',
				'label'       => __( 'Restrict deposit purchases to authenticated customers.', 'datrooster-partial-payments' ),
			)
		);

		add_settings_field(
			'deposit_type',
			__( 'Deposit type', 'datrooster-partial-payments' ),
			array( $this, 'render_select_field' ),
			'drpp_general',
			'drpp_general_section',
			array(
				'option_name' => self::GENERAL_OPTION,
				'key'         => 'deposit_type',
				'choices'     => array(
					'percentage' => __( 'Percentage', 'datrooster-partial-payments' ),
					'fixed'      => __( 'Fixed amount', 'datrooster-partial-payments' ),
				),
				'description' => __( 'Defines whether the default deposit is calculated as a percentage or a fixed amount.', 'datrooster-partial-payments' ),
			)
		);

		add_settings_field(
			'deposit_amount',
			__( 'Deposit amount', 'datrooster-partial-payments' ),
			array( $this, 'render_number_field' ),
			'drpp_general',
			'drpp_general_section',
			array(
				'option_name' => self::GENERAL_OPTION,
				'key'         => 'deposit_amount',
				'min'         => '0',
				'step'        => '0.01',
				'description' => __( 'Default global amount used when no product-level rule is present.', 'datrooster-partial-payments' ),
			)
		);

		add_settings_field(
			'default_selection',
			__( 'Default selection', 'datrooster-partial-payments' ),
			array( $this, 'render_select_field' ),
			'drpp_general',
			'drpp_general_section',
			array(
				'option_name' => self::GENERAL_OPTION,
				'key'         => 'default_selection',
				'choices'     => array(
					'deposit' => __( 'Pay deposit', 'datrooster-partial-payments' ),
					'full'    => __( 'Pay in full', 'datrooster-partial-payments' ),
				),
			)
		);

		add_settings_field(
			'fully_paid_status',
			__( 'Order fully paid status', 'datrooster-partial-payments' ),
			array( $this, 'render_select_field' ),
			'drpp_general',
			'drpp_general_section',
			array(
				'option_name' => self::GENERAL_OPTION,
				'key'         => 'fully_paid_status',
				'choices'     => $this->get_order_status_choices(),
			)
		);

		add_settings_field(
			'disabled_gateways',
			__( 'Disabled payment gateways', 'datrooster-partial-payments' ),
			array( $this, 'render_multiselect_field' ),
			'drpp_general',
			'drpp_general_section',
			array(
				'option_name' => self::GENERAL_OPTION,
				'key'         => 'disabled_gateways',
				'choices'     => $this->get_payment_gateway_choices(),
				'description' => __( 'Gateways selected here will be excluded when a deposit flow is active.', 'datrooster-partial-payments' ),
			)
		);

		add_settings_field(
			'tax_handling',
			__( 'Tax handling', 'datrooster-partial-payments' ),
			array( $this, 'render_select_field' ),
			'drpp_general',
			'drpp_general_section',
			array(
				'option_name' => self::GENERAL_OPTION,
				'key'         => 'tax_handling',
				'choices'     => array(
					'proportional' => __( 'Charge taxes proportionally with the deposit', 'datrooster-partial-payments' ),
				),
				'description' => __( 'This release keeps product taxes proportional to the portion paid today and tracks the remaining tax balance separately.', 'datrooster-partial-payments' ),
			)
		);

		add_settings_field(
			'shipping_handling',
			__( 'Shipping handling', 'datrooster-partial-payments' ),
			array( $this, 'render_select_field' ),
			'drpp_general',
			'drpp_general_section',
			array(
				'option_name' => self::GENERAL_OPTION,
				'key'         => 'shipping_handling',
				'choices'     => array(
					'upfront'      => __( 'Charge full shipping with the initial payment', 'datrooster-partial-payments' ),
					'proportional' => __( 'Split shipping proportionally with the deposit', 'datrooster-partial-payments' ),
				),
				'description' => __( 'Use proportional shipping when you want the customer to pay only part of the shipping amount today and defer the rest with the balance.', 'datrooster-partial-payments' ),
			)
		);

		add_settings_field(
			'coupon_handling',
			__( 'Coupon handling', 'datrooster-partial-payments' ),
			array( $this, 'render_select_field' ),
			'drpp_general',
			'drpp_general_section',
			array(
				'option_name' => self::GENERAL_OPTION,
				'key'         => 'coupon_handling',
				'choices'     => array(
					'initial_payment'       => __( 'Allow coupons on the initial payment only', 'datrooster-partial-payments' ),
					'exclude_deposit_items' => __( 'Exclude deposit items from coupons', 'datrooster-partial-payments' ),
				),
				'description' => __( 'The default policy lets WooCommerce discounts affect the amount paid today, while the remaining balance keeps its own tracked amount.', 'datrooster-partial-payments' ),
			)
		);

		add_settings_section(
			'drpp_labels_selection_section',
			__( 'Deposit selection', 'datrooster-partial-payments' ),
			array( $this, 'render_labels_selection_section' ),
			'drpp_labels'
		);

		add_settings_field(
			'pay_deposit_text',
			__( 'Pay deposit text', 'datrooster-partial-payments' ),
			array( $this, 'render_text_field' ),
			'drpp_labels',
			'drpp_labels_selection_section',
			array(
				'option_name' => self::LABELS_OPTION,
				'key'         => 'pay_deposit_text',
			)
		);

		add_settings_field(
			'pay_full_amount_text',
			__( 'Pay full amount text', 'datrooster-partial-payments' ),
			array( $this, 'render_text_field' ),
			'drpp_labels',
			'drpp_labels_selection_section',
			array(
				'option_name' => self::LABELS_OPTION,
				'key'         => 'pay_full_amount_text',
			)
		);

		add_settings_field(
			'deposit_text',
			__( 'Deposit text', 'datrooster-partial-payments' ),
			array( $this, 'render_text_field' ),
			'drpp_labels',
			'drpp_labels_selection_section',
			array(
				'option_name' => self::LABELS_OPTION,
				'key'         => 'deposit_text',
			)
		);

		add_settings_section(
			'drpp_labels_order_section',
			__( 'Checkout and order', 'datrooster-partial-payments' ),
			array( $this, 'render_labels_order_section' ),
			'drpp_labels'
		);

		add_settings_field(
			'to_pay_text',
			__( 'To pay text', 'datrooster-partial-payments' ),
			array( $this, 'render_text_field' ),
			'drpp_labels',
			'drpp_labels_order_section',
			array(
				'option_name' => self::LABELS_OPTION,
				'key'         => 'to_pay_text',
			)
		);

		add_settings_field(
			'future_payments_text',
			__( 'Future payments text', 'datrooster-partial-payments' ),
			array( $this, 'render_text_field' ),
			'drpp_labels',
			'drpp_labels_order_section',
			array(
				'option_name' => self::LABELS_OPTION,
				'key'         => 'future_payments_text',
			)
		);

		add_settings_field(
			'deposit_amount_text',
			__( 'Deposit amount text', 'datrooster-partial-payments' ),
			array( $this, 'render_text_field' ),
			'drpp_labels',
			'drpp_labels_order_section',
			array(
				'option_name' => self::LABELS_OPTION,
				'key'         => 'deposit_amount_text',
			)
		);
	}

	/**
	 * Sanitizes general settings.
	 *
	 * @param mixed $input Raw submitted values.
	 * @return array<string,mixed>
	 */
	public function sanitize_general_settings( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::get_default_general_settings();
		$statuses = array_keys( $this->get_order_status_choices() );
		$gateways = array_keys( $this->get_payment_gateway_choices() );

		$sanitized = array(
			'enabled'           => ! empty( $input['enabled'] ) ? 1 : 0,
			'require_login'     => ! empty( $input['require_login'] ) ? 1 : 0,
			'deposit_type'      => in_array( $input['deposit_type'] ?? '', array( 'percentage', 'fixed' ), true ) ? $input['deposit_type'] : $defaults['deposit_type'],
			'deposit_amount'    => isset( $input['deposit_amount'] ) ? (string) max( 0, (float) $input['deposit_amount'] ) : $defaults['deposit_amount'],
			'default_selection' => in_array( $input['default_selection'] ?? '', array( 'deposit', 'full' ), true ) ? $input['default_selection'] : $defaults['default_selection'],
			'fully_paid_status' => in_array( $input['fully_paid_status'] ?? '', $statuses, true ) ? $input['fully_paid_status'] : $defaults['fully_paid_status'],
			'tax_handling'      => 'proportional',
			'shipping_handling' => in_array( $input['shipping_handling'] ?? '', array( 'upfront', 'proportional' ), true ) ? $input['shipping_handling'] : $defaults['shipping_handling'],
			'coupon_handling'   => in_array( $input['coupon_handling'] ?? '', array( 'initial_payment', 'exclude_deposit_items' ), true ) ? $input['coupon_handling'] : $defaults['coupon_handling'],
			'disabled_gateways' => array(),
		);

		if ( ! empty( $input['disabled_gateways'] ) && is_array( $input['disabled_gateways'] ) ) {
			$sanitized['disabled_gateways'] = array_values( array_intersect( $gateways, array_map( 'sanitize_text_field', $input['disabled_gateways'] ) ) );
		}

		return $sanitized;
	}

	/**
	 * Sanitizes label settings.
	 *
	 * @param mixed $input Raw submitted values.
	 * @return array<string,string>
	 */
	public function sanitize_label_settings( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::get_default_label_settings();

		return array(
			'pay_deposit_text'     => sanitize_text_field( $input['pay_deposit_text'] ?? $defaults['pay_deposit_text'] ),
			'pay_full_amount_text' => sanitize_text_field( $input['pay_full_amount_text'] ?? $defaults['pay_full_amount_text'] ),
			'deposit_text'         => sanitize_text_field( $input['deposit_text'] ?? $defaults['deposit_text'] ),
			'to_pay_text'          => sanitize_text_field( $input['to_pay_text'] ?? $defaults['to_pay_text'] ),
			'future_payments_text' => sanitize_text_field( $input['future_payments_text'] ?? $defaults['future_payments_text'] ),
			'deposit_amount_text'  => sanitize_text_field( $input['deposit_amount_text'] ?? $defaults['deposit_amount_text'] ),
		);
	}

	/**
	 * Renders the settings page.
	 */
	public function render_page(): void {
		$tab = $this->get_current_tab();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Partial Payments', 'datrooster-partial-payments' ); ?></h1>
			<p><?php esc_html_e( 'Current milestone adds deposit settings, product overrides, classic checkout calculations, and remaining balance tracking. Cart and Checkout Blocks integration is planned for a future release.', 'datrooster-partial-payments' ); ?></p>

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings tabs', 'datrooster-partial-payments' ); ?>">
				<a class="nav-tab <?php echo esc_attr( 'general' === $tab ? 'nav-tab-active' : '' ); ?>" href="<?php echo esc_url( $this->get_tab_url( 'general' ) ); ?>">
					<?php esc_html_e( 'General settings', 'datrooster-partial-payments' ); ?>
				</a>
				<a class="nav-tab <?php echo esc_attr( 'labels' === $tab ? 'nav-tab-active' : '' ); ?>" href="<?php echo esc_url( $this->get_tab_url( 'labels' ) ); ?>">
					<?php esc_html_e( 'Labels & text', 'datrooster-partial-payments' ); ?>
				</a>
			</nav>

			<form action="options.php" method="post">
				<?php if ( 'labels' === $tab ) : ?>
					<?php settings_fields( 'drpp_labels_group' ); ?>
					<?php do_settings_sections( 'drpp_labels' ); ?>
				<?php else : ?>
					<?php settings_fields( 'drpp_general_group' ); ?>
					<?php do_settings_sections( 'drpp_general' ); ?>
				<?php endif; ?>

				<?php submit_button( __( 'Save changes', 'datrooster-partial-payments' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the general settings section description.
	 */
	public function render_general_section(): void {
		echo '<p>' . esc_html__( 'Start with sensible store-wide defaults. Product, category, cart, and installment rules will build on top of these settings.', 'datrooster-partial-payments' ) . '</p>';
	}

	/**
	 * Renders the labels selection section description.
	 */
	public function render_labels_selection_section(): void {
		echo '<p>' . esc_html__( 'Customize the labels customers will see when choosing between a deposit and a full payment.', 'datrooster-partial-payments' ) . '</p>';
	}

	/**
	 * Renders the order section description.
	 */
	public function render_labels_order_section(): void {
		echo '<p>' . esc_html__( 'These strings are used around the order summary and future balance communication.', 'datrooster-partial-payments' ) . '</p>';
	}

	/**
	 * Renders a checkbox field.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_checkbox_field( array $args ): void {
		$settings = $this->get_option_value( $args['option_name'], $args['key'] );
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( $args['option_name'] ); ?>[<?php echo esc_attr( $args['key'] ); ?>]"
				value="1"
				<?php checked( ! empty( $settings ) ); ?>
			/>
			<?php if ( ! empty( $args['label'] ) ) : ?>
				<?php echo esc_html( $args['label'] ); ?>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Renders a select field.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_select_field( array $args ): void {
		$current = (string) $this->get_option_value( $args['option_name'], $args['key'] );
		$choices = is_array( $args['choices'] ) ? $args['choices'] : array();
		?>
		<select name="<?php echo esc_attr( $args['option_name'] ); ?>[<?php echo esc_attr( $args['key'] ); ?>]">
			<?php foreach ( $choices as $value => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $current, (string) $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders a multiselect field.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_multiselect_field( array $args ): void {
		$current = $this->get_option_value( $args['option_name'], $args['key'] );
		$current = is_array( $current ) ? $current : array();
		$choices = is_array( $args['choices'] ) ? $args['choices'] : array();

		if ( empty( $choices ) ) {
			echo '<p class="description">' . esc_html__( 'No payment gateways are currently available to configure.', 'datrooster-partial-payments' ) . '</p>';
			return;
		}
		?>
		<select
			name="<?php echo esc_attr( $args['option_name'] ); ?>[<?php echo esc_attr( $args['key'] ); ?>][]"
			multiple
			size="6"
			style="min-width: 18rem;"
		>
			<?php foreach ( $choices as $value => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( in_array( (string) $value, $current, true ) ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders a numeric field.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_number_field( array $args ): void {
		$current = (string) $this->get_option_value( $args['option_name'], $args['key'] );
		?>
		<input
			type="number"
			name="<?php echo esc_attr( $args['option_name'] ); ?>[<?php echo esc_attr( $args['key'] ); ?>]"
			value="<?php echo esc_attr( $current ); ?>"
			min="<?php echo esc_attr( $args['min'] ?? '0' ); ?>"
			step="<?php echo esc_attr( $args['step'] ?? '1' ); ?>"
			class="regular-text"
		/>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders a text field.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_text_field( array $args ): void {
		$current = (string) $this->get_option_value( $args['option_name'], $args['key'] );
		?>
		<input
			type="text"
			name="<?php echo esc_attr( $args['option_name'] ); ?>[<?php echo esc_attr( $args['key'] ); ?>]"
			value="<?php echo esc_attr( $current ); ?>"
			class="regular-text"
		/>
		<?php
	}

	/**
	 * Returns a single option value with defaults applied.
	 *
	 * @param string $option_name Option key.
	 * @param string $setting_key Setting key within the option.
	 * @return mixed
	 */
	private function get_option_value( string $option_name, string $setting_key ) {
		$defaults = self::GENERAL_OPTION === $option_name ? self::get_default_general_settings() : self::get_default_label_settings();
		$options  = get_option( $option_name, $defaults );
		$options  = is_array( $options ) ? $options : $defaults;

		return $options[ $setting_key ] ?? ( $defaults[ $setting_key ] ?? '' );
	}

	/**
	 * Returns the current tab slug.
	 */
	private function get_current_tab(): string {
		$tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'general' ) );

		return in_array( $tab, array( 'general', 'labels' ), true ) ? $tab : 'general';
	}

	/**
	 * Builds a tab URL.
	 *
	 * @param string $tab Tab slug.
	 */
	private function get_tab_url( string $tab ): string {
		return add_query_arg(
			array(
				'page' => 'datrooster-partial-payments',
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Returns order status choices.
	 *
	 * @return array<string,string>
	 */
	private function get_order_status_choices(): array {
		$statuses = WooCommerce::get_order_statuses();

		if ( empty( $statuses ) ) {
			return array(
				'wc-completed' => __( 'Completed', 'datrooster-partial-payments' ),
			);
		}

		return $statuses;
	}

	/**
	 * Returns payment gateway choices.
	 *
	 * @return array<string,string>
	 */
	private function get_payment_gateway_choices(): array {
		$choices = array();

		foreach ( WooCommerce::get_payment_gateways() as $gateway_id => $gateway ) {
			$title               = is_object( $gateway ) && isset( $gateway->method_title ) ? $gateway->method_title : $gateway_id;
			$choices[ $gateway_id ] = $title;
		}

		return $choices;
	}
}
