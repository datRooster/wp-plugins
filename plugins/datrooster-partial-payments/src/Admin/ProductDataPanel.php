<?php
/**
 * Product-level deposit settings UI.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments\Admin;

use DatRooster\PartialPayments\Deposits\ProductSettings;
use DatRooster\PartialPayments\Deposits\SettingsResolver;

defined( 'ABSPATH' ) || exit;

final class ProductDataPanel {
	/**
	 * Shared settings resolver.
	 *
	 * @var SettingsResolver
	 */
	private SettingsResolver $settings_resolver;

	/**
	 * Class constructor.
	 *
	 * @param SettingsResolver $settings_resolver Shared settings resolver.
	 */
	public function __construct( SettingsResolver $settings_resolver ) {
		$this->settings_resolver = $settings_resolver;
	}

	/**
	 * Registers the product edit hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'register_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_settings' ) );
	}

	/**
	 * Registers the product data tab.
	 *
	 * @param array<string,array<string,mixed>> $tabs Existing tabs.
	 * @return array<string,array<string,mixed>>
	 */
	public function register_tab( array $tabs ): array {
		$tabs['drpp_partial_payments'] = array(
			'label'    => __( 'Partial Payments', 'datrooster-partial-payments' ),
			'target'   => 'drpp_partial_payments_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 75,
		);

		return $tabs;
	}

	/**
	 * Renders the product data panel.
	 */
	public function render_panel(): void {
		global $product_object;

		if ( ! $product_object instanceof \WC_Product ) {
			return;
		}

		$global_settings = $this->settings_resolver->get_global_settings();
		$overrides       = $this->settings_resolver->get_product_overrides( $product_object );
		$effective       = $this->settings_resolver->get_effective_product_settings( $product_object );
		?>
		<div id="drpp_partial_payments_product_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<p class="form-field">
					<strong><?php esc_html_e( 'Product-level deposit rules', 'datrooster-partial-payments' ); ?></strong><br />
					<span class="description">
						<?php esc_html_e( 'Use these fields to override the store-wide deposit configuration for this specific product.', 'datrooster-partial-payments' ); ?>
					</span>
				</p>
				<p class="form-field">
					<span class="description">
						<?php echo esc_html( $this->get_context_message( $global_settings, $effective ) ); ?>
					</span>
				</p>
				<?php
				woocommerce_wp_select(
					array(
						'id'          => ProductSettings::META_BEHAVIOR,
						'label'       => __( 'Deposit behavior', 'datrooster-partial-payments' ),
						'value'       => $overrides['behavior'],
						'options'     => array(
							ProductSettings::BEHAVIOR_INHERIT => __( 'Inherit store setting', 'datrooster-partial-payments' ),
							ProductSettings::BEHAVIOR_ENABLE  => __( 'Enable for this product', 'datrooster-partial-payments' ),
							ProductSettings::BEHAVIOR_DISABLE => __( 'Disable for this product', 'datrooster-partial-payments' ),
						),
						'description' => __( 'Choose whether this product should use the global deposit availability or override it.', 'datrooster-partial-payments' ),
						'desc_tip'    => true,
					)
				);

				woocommerce_wp_select(
					array(
						'id'          => ProductSettings::META_DEPOSIT_TYPE,
						'label'       => __( 'Deposit type override', 'datrooster-partial-payments' ),
						'value'       => $overrides['deposit_type'],
						'options'     => array(
							'inherit'    => __( 'Inherit global type', 'datrooster-partial-payments' ),
							'percentage' => __( 'Percentage', 'datrooster-partial-payments' ),
							'fixed'      => __( 'Fixed amount', 'datrooster-partial-payments' ),
						),
						'description' => __( 'Leave this on inherit unless the product needs a different deposit type.', 'datrooster-partial-payments' ),
						'desc_tip'    => true,
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => ProductSettings::META_DEPOSIT_AMOUNT,
						'label'             => __( 'Deposit amount override', 'datrooster-partial-payments' ),
						'value'             => $overrides['deposit_amount'],
						'type'              => 'number',
						'custom_attributes' => array(
							'min'  => '0',
							'step' => '0.01',
						),
						'description'       => __( 'Leave empty to use the global amount. Percentages are stored as plain numbers, for example 30 means 30%.', 'datrooster-partial-payments' ),
						'desc_tip'          => true,
					)
				);

				woocommerce_wp_select(
					array(
						'id'          => ProductSettings::META_DEFAULT_SELECTION,
						'label'       => __( 'Default selection override', 'datrooster-partial-payments' ),
						'value'       => $overrides['default_selection'],
						'options'     => array(
							'inherit' => __( 'Inherit global selection', 'datrooster-partial-payments' ),
							'deposit' => __( 'Preselect deposit', 'datrooster-partial-payments' ),
							'full'    => __( 'Preselect full payment', 'datrooster-partial-payments' ),
						),
						'description' => __( 'Controls what the customer will see as the default choice when both payment options are available.', 'datrooster-partial-payments' ),
						'desc_tip'    => true,
					)
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Saves product-level deposit settings.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public function save_product_settings( \WC_Product $product ): void {
		$behavior          = ProductSettings::sanitize_behavior( $this->get_posted_value( ProductSettings::META_BEHAVIOR ) );
		$deposit_type      = ProductSettings::sanitize_type_override( $this->get_posted_value( ProductSettings::META_DEPOSIT_TYPE ) );
		$deposit_amount    = ProductSettings::sanitize_amount_override( $this->get_posted_value( ProductSettings::META_DEPOSIT_AMOUNT ) );
		$default_selection = ProductSettings::sanitize_default_selection_override( $this->get_posted_value( ProductSettings::META_DEFAULT_SELECTION ) );

		$this->persist_meta_value( $product, ProductSettings::META_BEHAVIOR, ProductSettings::BEHAVIOR_INHERIT === $behavior ? '' : $behavior );
		$this->persist_meta_value( $product, ProductSettings::META_DEPOSIT_TYPE, 'inherit' === $deposit_type ? '' : $deposit_type );
		$this->persist_meta_value( $product, ProductSettings::META_DEPOSIT_AMOUNT, $deposit_amount );
		$this->persist_meta_value( $product, ProductSettings::META_DEFAULT_SELECTION, 'inherit' === $default_selection ? '' : $default_selection );
	}

	/**
	 * Persists a meta value using WooCommerce CRUD.
	 *
	 * @param \WC_Product $product   Product object.
	 * @param string      $meta_key  Meta key.
	 * @param string      $meta_value Meta value.
	 */
	private function persist_meta_value( \WC_Product $product, string $meta_key, string $meta_value ): void {
		if ( '' === $meta_value ) {
			$product->delete_meta_data( $meta_key );
			return;
		}

		$product->update_meta_data( $meta_key, $meta_value );
	}

	/**
	 * Returns a short context message for the product editor.
	 *
	 * @param array<string,mixed> $global_settings   Store-wide settings.
	 * @param array<string,mixed> $effective_settings Effective settings.
	 */
	private function get_context_message( array $global_settings, array $effective_settings ): string {
		if ( ! $global_settings['enabled'] && $effective_settings['enabled'] ) {
			return __( 'Store-wide deposits are currently disabled, but this product will still be able to offer them because of its override.', 'datrooster-partial-payments' );
		}

		if ( $global_settings['enabled'] && ! $effective_settings['enabled'] ) {
			return __( 'Store-wide deposits are enabled, but this product will not offer them because of its override.', 'datrooster-partial-payments' );
		}

		if ( ! $global_settings['enabled'] ) {
			return __( 'Store-wide deposits are currently disabled. Leaving this product on inherit will keep deposits unavailable here too.', 'datrooster-partial-payments' );
		}

		return sprintf(
			/* translators: 1: deposit amount label, 2: default selection label. */
			__( 'Current effective configuration for this product resolves to %1$s with %2$s preselected.', 'datrooster-partial-payments' ),
			$this->format_deposit_amount( $effective_settings ),
			$this->get_selection_label( (string) $effective_settings['default_selection'] )
		);
	}

	/**
	 * Formats the amount summary for UI messages.
	 *
	 * @param array<string,mixed> $settings Effective settings.
	 */
	private function format_deposit_amount( array $settings ): string {
		$amount = (string) $settings['deposit_amount'];
		$type   = (string) $settings['deposit_type'];

		if ( 'fixed' === $type && function_exists( 'wc_price' ) ) {
			return sprintf(
				/* translators: %s: formatted amount. */
				__( 'a fixed deposit of %s', 'datrooster-partial-payments' ),
				wp_strip_all_tags( wc_price( (float) $amount ) )
			);
		}

		return sprintf(
			/* translators: %s: deposit percentage. */
			__( 'a %s%% deposit', 'datrooster-partial-payments' ),
			$amount
		);
	}

	/**
	 * Returns the localized label for the default selection.
	 *
	 * @param string $selection Selection key.
	 */
	private function get_selection_label( string $selection ): string {
		if ( 'full' === $selection ) {
			return __( 'full payment', 'datrooster-partial-payments' );
		}

		return __( 'deposit payment', 'datrooster-partial-payments' );
	}

	/**
	 * Reads a posted value from the product form.
	 *
	 * @param string $key Posted field name.
	 */
	private function get_posted_value( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies product edit requests before this hook runs.
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies product edit requests before this hook runs.
		return wc_clean( wp_unslash( (string) $_POST[ $key ] ) );
	}
}
