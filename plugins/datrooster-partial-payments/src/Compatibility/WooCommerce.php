<?php
/**
 * WooCommerce compatibility helpers.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments\Compatibility;

defined( 'ABSPATH' ) || exit;

final class WooCommerce {
	/**
	 * Checks whether WooCommerce is available.
	 */
	public static function is_active(): bool {
		return class_exists( '\WooCommerce' ) || defined( 'WC_ABSPATH' );
	}

	/**
	 * Renders an admin notice when WooCommerce is missing.
	 */
	public static function render_missing_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'DatRooster Partial Payments requires WooCommerce to be installed and active.', 'datrooster-partial-payments' )
		);
	}

	/**
	 * Returns available payment gateways.
	 *
	 * @return array<string,\WC_Payment_Gateway>
	 */
	public static function get_payment_gateways(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return array();
		}

		$gateways = WC()->payment_gateways()->payment_gateways();

		return is_array( $gateways ) ? $gateways : array();
	}

	/**
	 * Returns available order statuses.
	 *
	 * @return array<string,string>
	 */
	public static function get_order_statuses(): array {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}

		return wc_get_order_statuses();
	}
}
