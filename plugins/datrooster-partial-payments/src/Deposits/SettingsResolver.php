<?php
/**
 * Resolves the effective deposit settings for products.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments\Deposits;

use DatRooster\PartialPayments\Admin\SettingsPage;

defined( 'ABSPATH' ) || exit;

final class SettingsResolver {
	/**
	 * Returns normalized global settings.
	 *
	 * @return array<string,mixed>
	 */
	public function get_global_settings(): array {
		$defaults = SettingsPage::get_default_general_settings();
		$settings = get_option( SettingsPage::GENERAL_OPTION, $defaults );
		$settings = is_array( $settings ) ? wp_parse_args( $settings, $defaults ) : $defaults;

		$disabled_gateways = array();

		if ( ! empty( $settings['disabled_gateways'] ) && is_array( $settings['disabled_gateways'] ) ) {
			$disabled_gateways = array_values(
				array_filter(
					array_map( 'sanitize_text_field', $settings['disabled_gateways'] )
				)
			);
		}

		return array(
			'enabled'           => ! empty( $settings['enabled'] ),
			'require_login'     => ! empty( $settings['require_login'] ),
			'deposit_type'      => ProductSettings::sanitize_effective_type( $settings['deposit_type'] ?? $defaults['deposit_type'], $defaults['deposit_type'] ),
			'deposit_amount'    => ProductSettings::sanitize_effective_amount( $settings['deposit_amount'] ?? $defaults['deposit_amount'], $defaults['deposit_amount'] ),
			'default_selection' => ProductSettings::sanitize_effective_default_selection( $settings['default_selection'] ?? $defaults['default_selection'], $defaults['default_selection'] ),
			'fully_paid_status' => sanitize_text_field( (string) ( $settings['fully_paid_status'] ?? $defaults['fully_paid_status'] ) ),
			'disabled_gateways' => $disabled_gateways,
		);
	}

	/**
	 * Returns the raw product-level overrides for a product.
	 *
	 * @param \WC_Product $product WooCommerce product object.
	 * @return array<string,string>
	 */
	public function get_product_overrides( \WC_Product $product ): array {
		return array(
			'behavior'          => ProductSettings::sanitize_behavior( $product->get_meta( ProductSettings::META_BEHAVIOR, true ) ),
			'deposit_type'      => ProductSettings::sanitize_type_override( $product->get_meta( ProductSettings::META_DEPOSIT_TYPE, true ) ),
			'deposit_amount'    => ProductSettings::sanitize_amount_override( $product->get_meta( ProductSettings::META_DEPOSIT_AMOUNT, true ) ),
			'default_selection' => ProductSettings::sanitize_default_selection_override( $product->get_meta( ProductSettings::META_DEFAULT_SELECTION, true ) ),
		);
	}

	/**
	 * Returns the final settings that should apply to a product.
	 *
	 * @param \WC_Product $product WooCommerce product object.
	 * @return array<string,mixed>
	 */
	public function get_effective_product_settings( \WC_Product $product ): array {
		$global_settings   = $this->get_global_settings();
		$product_overrides = $this->get_product_overrides( $product );
		$behavior          = $product_overrides['behavior'];

		if ( ProductSettings::BEHAVIOR_ENABLE === $behavior ) {
			$enabled = true;
		} elseif ( ProductSettings::BEHAVIOR_DISABLE === $behavior ) {
			$enabled = false;
		} else {
			$enabled = $global_settings['enabled'];
		}

		return array(
			'enabled'              => $enabled,
			'require_login'        => $global_settings['require_login'],
			'deposit_type'         => 'inherit' !== $product_overrides['deposit_type'] ? $product_overrides['deposit_type'] : $global_settings['deposit_type'],
			'deposit_amount'       => '' !== $product_overrides['deposit_amount'] ? $product_overrides['deposit_amount'] : $global_settings['deposit_amount'],
			'default_selection'    => 'inherit' !== $product_overrides['default_selection'] ? $product_overrides['default_selection'] : $global_settings['default_selection'],
			'fully_paid_status'    => $global_settings['fully_paid_status'],
			'disabled_gateways'    => $global_settings['disabled_gateways'],
			'product_behavior'     => $behavior,
			'has_product_override' => ProductSettings::has_overrides( $product_overrides ),
		);
	}
}
