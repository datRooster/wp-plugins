<?php
/**
 * WooCommerce compatibility helpers.
 *
 * @package DatRoosterPartialPayments
 */

namespace DatRoosterPartialPayments\Compatibility;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce integration and compatibility helpers.
 */
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
	 * Renders a contextual admin notice when unsupported Cart or Checkout Blocks are in use.
	 */
	public static function render_blocks_incompatibility_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! self::should_render_blocks_notice() ) {
			return;
		}

		$affected_pages = self::get_affected_block_pages();

		if ( array() === $affected_pages ) {
			return;
		}

		$page_links = array();

		foreach ( $affected_pages as $page_id => $page_title ) {
			$edit_link = get_edit_post_link( $page_id, 'raw' );

			if ( false === $edit_link ) {
				$edit_link = admin_url( 'post.php?post=' . $page_id . '&action=edit' );
			}

			$page_links[] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $edit_link ),
				esc_html( $page_title )
			);
		}

		$message = sprintf(
			/* translators: 1: comma-separated list of affected admin edit page links. 2: classic WooCommerce cart shortcode. 3: classic WooCommerce checkout shortcode. */
			__( 'DatRooster Partial Payments currently supports only classic WooCommerce cart and checkout flows. Your store is using WooCommerce Blocks on %1$s. Edit those pages and replace their content with %2$s and %3$s to enable deposits correctly.', 'datrooster-partial-payments' ),
			implode( ', ', $page_links ),
			'<code>[woocommerce_cart]</code>',
			'<code>[woocommerce_checkout]</code>'
		);

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			wp_kses(
				$message,
				array(
					'a'    => array(
						'href' => array(),
					),
					'code' => array(),
				)
			)
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

	/**
	 * Returns whether the current admin screen is an appropriate place for the blocks notice.
	 */
	private static function should_render_blocks_notice(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		return in_array(
			$screen->id,
			array(
				'plugins',
				'woocommerce_page_datrooster-partial-payments',
			),
			true
		);
	}

	/**
	 * Returns the Cart or Checkout pages currently using WooCommerce Blocks.
	 *
	 * @return array<int,string>
	 */
	private static function get_affected_block_pages(): array {
		$pages = array(
			wc_get_page_id( 'cart' )     => 'woocommerce/cart',
			wc_get_page_id( 'checkout' ) => 'woocommerce/checkout',
		);

		$affected_pages = array();

		foreach ( $pages as $page_id => $block_name ) {
			$page_id = absint( $page_id );

			if ( $page_id <= 0 ) {
				continue;
			}

			$page = get_post( $page_id );

			if ( ! $page instanceof \WP_Post || ! function_exists( 'has_block' ) || ! has_block( $block_name, $page ) ) {
				continue;
			}

			$page_title = get_the_title( $page_id );
			$affected_pages[ $page_id ] = '' !== $page_title ? $page_title : '#' . $page_id;
		}

		return $affected_pages;
	}
}
