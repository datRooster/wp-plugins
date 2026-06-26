<?php
/**
 * WooCommerce compatibility helpers.
 *
 * @package DatRoosterTreasureCoupons
 */

namespace DatRoosterTreasureCoupons\Compatibility;

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
			esc_html__( 'DatRooster Treasure Coupons requires WooCommerce to be installed and active.', 'datrooster-treasure-coupons' )
		);
	}
}
