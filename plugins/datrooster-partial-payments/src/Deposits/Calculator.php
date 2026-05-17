<?php
/**
 * Deposit amount calculations.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments\Deposits;

defined( 'ABSPATH' ) || exit;

final class Calculator {
	/**
	 * Returns a normalized price breakdown for a given quantity.
	 *
	 * @param float              $unit_price Product unit price before deposit logic.
	 * @param int                $quantity   Product quantity.
	 * @param array<string,mixed> $settings   Effective deposit settings.
	 * @return array<string,float>
	 */
	public function get_breakdown( float $unit_price, int $quantity, array $settings ): array {
		$quantity           = max( 1, $quantity );
		$full_unit_price    = max( 0, $unit_price );
		$deposit_unit_price = $this->calculate_deposit_unit_price( $full_unit_price, $settings );
		$balance_unit_price = max( 0, $full_unit_price - $deposit_unit_price );

		return array(
			'full_unit_price'    => $this->round_amount( $full_unit_price ),
			'deposit_unit_price' => $this->round_amount( $deposit_unit_price ),
			'balance_unit_price' => $this->round_amount( $balance_unit_price ),
			'full_line_total'    => $this->round_amount( $full_unit_price * $quantity ),
			'deposit_line_total' => $this->round_amount( $deposit_unit_price * $quantity ),
			'balance_line_total' => $this->round_amount( $balance_unit_price * $quantity ),
		);
	}

	/**
	 * Calculates the deposit amount for a single product unit.
	 *
	 * @param float              $unit_price Product unit price.
	 * @param array<string,mixed> $settings   Effective deposit settings.
	 */
	public function calculate_deposit_unit_price( float $unit_price, array $settings ): float {
		$unit_price = max( 0, $unit_price );
		$type       = ProductSettings::sanitize_effective_type( $settings['deposit_type'] ?? 'percentage' );
		$amount     = max( 0, (float) ( $settings['deposit_amount'] ?? 0 ) );

		if ( 'fixed' === $type ) {
			return $this->round_amount( min( $unit_price, $amount ) );
		}

		$percentage = min( 100, $amount );

		return $this->round_amount( $unit_price * ( $percentage / 100 ) );
	}

	/**
	 * Rounds a monetary amount to WooCommerce price precision.
	 *
	 * @param float $amount Raw amount.
	 */
	private function round_amount( float $amount ): float {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;

		return round( $amount, $decimals );
	}
}
