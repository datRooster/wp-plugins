<?php
/**
 * Resolves whether deposits are currently eligible for a product context.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments\Deposits;

use DatRooster\PartialPayments\Cart\DepositCartManager;

defined( 'ABSPATH' ) || exit;

final class EligibilityChecker {
	public const REASON_ENABLED           = 'enabled';
	public const REASON_DISABLED          = 'disabled';
	public const REASON_UNSUPPORTED       = 'unsupported_product';
	public const REASON_THRESHOLD_NOT_MET = 'threshold_not_met';

	/**
	 * Shared settings resolver.
	 *
	 * @var SettingsResolver
	 */
	private SettingsResolver $settings_resolver;

	/**
	 * @param SettingsResolver $settings_resolver Shared settings resolver.
	 */
	public function __construct( SettingsResolver $settings_resolver ) {
		$this->settings_resolver = $settings_resolver;
	}

	/**
	 * Returns deposit eligibility details for a product and quantity context.
	 *
	 * @param \WC_Product $product  WooCommerce product object.
	 * @param int         $quantity Requested quantity.
	 * @return array<string,mixed>
	 */
	public function get_product_eligibility( \WC_Product $product, int $quantity = 1 ): array {
		$quantity = max( 1, $quantity );
		$settings = $this->settings_resolver->get_effective_product_settings( $product );

		$eligibility = array(
			'eligible'             => false,
			'reason'               => self::REASON_DISABLED,
			'settings'             => $settings,
			'threshold_amount'     => max( 0, (float) ( $settings['minimum_deposit_eligible_amount'] ?? 0 ) ),
			'product_total'        => 0.0,
			'projected_cart_total' => 0.0,
			'cart_product_total'   => 0.0,
			'uses_cart_threshold'  => false,
			'is_variable_product'  => 'variable' === $product->get_type(),
		);

		if ( ! $this->settings_resolver->product_supports_deposits( $product ) ) {
			$eligibility['reason'] = self::REASON_UNSUPPORTED;
			return $eligibility;
		}

		if ( empty( $settings['enabled'] ) ) {
			return $eligibility;
		}

		$eligibility['product_total']        = $this->round_amount( $this->get_product_reference_total( $product, $quantity ) );
		$eligibility['projected_cart_total'] = $this->round_amount( $this->get_projected_cart_product_total( $product, $quantity ) );
		$eligibility['cart_product_total']   = $this->round_amount( max( 0, $eligibility['projected_cart_total'] - $eligibility['product_total'] ) );

		if ( $eligibility['threshold_amount'] <= 0 ) {
			$eligibility['eligible'] = true;
			$eligibility['reason']   = self::REASON_ENABLED;
			return $eligibility;
		}

		if ( $eligibility['projected_cart_total'] >= $eligibility['threshold_amount'] ) {
			$eligibility['eligible']            = true;
			$eligibility['reason']              = self::REASON_ENABLED;
			$eligibility['uses_cart_threshold'] = $eligibility['cart_product_total'] > 0;
			return $eligibility;
		}

		$eligibility['reason'] = self::REASON_THRESHOLD_NOT_MET;

		return $eligibility;
	}

	/**
	 * Returns the full-price product total currently in the cart plus this request.
	 *
	 * @param \WC_Product $product  WooCommerce product object.
	 * @param int         $quantity Requested quantity.
	 */
	private function get_projected_cart_product_total( \WC_Product $product, int $quantity ): float {
		return $this->get_current_cart_product_total() + $this->get_product_reference_total( $product, $quantity );
	}

	/**
	 * Returns the reference total used for threshold evaluation.
	 *
	 * Variable products use the maximum variation price on the product page and
	 * the exact variation price once a specific variation is being added to cart.
	 *
	 * @param \WC_Product $product  WooCommerce product object.
	 * @param int         $quantity Requested quantity.
	 */
	private function get_product_reference_total( \WC_Product $product, int $quantity ): float {
		$quantity   = max( 1, $quantity );
		$unit_price = 0.0;

		if ( $product->is_type( 'variable' ) ) {
			$prices = $product->get_variation_prices( false );
			$values = isset( $prices['price'] ) && is_array( $prices['price'] ) ? array_map( 'floatval', $prices['price'] ) : array();

			if ( ! empty( $values ) ) {
				$unit_price = max( $values );
			}
		} else {
			$unit_price = (float) $product->get_price( 'edit' );
		}

		return max( 0, $unit_price ) * $quantity;
	}

	/**
	 * Returns the current full-price product subtotal of the cart.
	 */
	private function get_current_cart_product_total(): float {
		$total = 0.0;

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $total;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$quantity = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;

			if ( ! empty( $cart_item[ DepositCartManager::CART_KEY ]['original_unit_price'] ) ) {
				$total += (float) $cart_item[ DepositCartManager::CART_KEY ]['original_unit_price'] * $quantity;
				continue;
			}

			if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof \WC_Product ) {
				continue;
			}

			$total += (float) $cart_item['data']->get_price( 'edit' ) * $quantity;
		}

		return $total;
	}

	/**
	 * Rounds a threshold-related amount to WooCommerce precision.
	 *
	 * @param float $amount Raw amount.
	 */
	private function round_amount( float $amount ): float {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;

		return round( $amount, $decimals );
	}
}
