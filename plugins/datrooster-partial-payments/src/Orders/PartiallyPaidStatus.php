<?php
/**
 * Partial payment order status registration.
 *
 * @package DatRoosterPartialPayments
 */

namespace DatRoosterPartialPayments\Orders;

defined( 'ABSPATH' ) || exit;

final class PartiallyPaidStatus {
	public const STATUS_KEY        = 'wc-partially-paid';
	public const STATUS_UNPREFIXED = 'partially-paid';

	/**
	 * Registers status hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_status' ) );
		add_filter( 'wc_order_statuses', array( $this, 'register_status_label' ) );
	}

	/**
	 * Registers the underlying WordPress post status.
	 */
	public function register_status(): void {
		register_post_status(
			self::STATUS_KEY,
			array(
				'label'                     => _x( 'Partially paid', 'Order status', 'datrooster-partial-payments' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders. */
				'label_count'               => _n_noop( 'Partially paid <span class="count">(%s)</span>', 'Partially paid <span class="count">(%s)</span>', 'datrooster-partial-payments' ),
			)
		);
	}

	/**
	 * Adds the status to WooCommerce order status lists.
	 *
	 * @param array<string,string> $statuses Existing statuses.
	 * @return array<string,string>
	 */
	public function register_status_label( array $statuses ): array {
		$label     = _x( 'Partially paid', 'Order status', 'datrooster-partial-payments' );
		$position  = array_search( 'wc-processing', array_keys( $statuses ), true );
		$before    = $statuses;

		if ( false === $position ) {
			$statuses[ self::STATUS_KEY ] = $label;
			return $statuses;
		}

		$statuses = array_slice( $before, 0, $position + 1, true );
		$statuses[ self::STATUS_KEY ] = $label;

		return $statuses + array_slice( $before, $position + 1, null, true );
	}
}
