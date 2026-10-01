<?php
/**
 * Automatically applies unlocked coupons to the cart.
 *
 * @package DatRoosterTreasureCoupons
 */

namespace DatRoosterTreasureCoupons\Frontend;

use DatRoosterTreasureCoupons\Admin\SettingsPage;
use DatRoosterTreasureCoupons\Progress\ProgressStore;

defined( 'ABSPATH' ) || exit;

final class CouponAutoApply {
	/**
	 * Progress store.
	 *
	 * @var ProgressStore
	 */
	private ProgressStore $progress_store;

	/**
	 * Constructor.
	 *
	 * @param ProgressStore $progress_store Progress store.
	 */
	public function __construct( ProgressStore $progress_store ) {
		$this->progress_store = $progress_store;
	}

	/**
	 * Registers WooCommerce hooks.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'maybe_apply_coupon' ), 5 );
		add_action( 'wp_footer', array( $this, 'render_applied_notice' ) );
	}

	/**
	 * Ensures the temporary coupon notice has its styles on cart-like pages.
	 */
	public function maybe_enqueue_assets(): void {
		$settings = SettingsPage::get_settings();

		if ( empty( $settings['enabled'] ) || empty( $settings['auto_apply_coupon'] ) ) {
			return;
		}

		wp_enqueue_style( 'datrooster-treasure-coupons' );
	}

	/**
	 * Applies the unlocked coupon when configured.
	 */
	public function maybe_apply_coupon(): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$settings = SettingsPage::get_settings();

		if ( empty( $settings['enabled'] ) || empty( $settings['auto_apply_coupon'] ) ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$progress    = $this->progress_store->get_progress( (string) $settings['hunt_slug'] );
		$coupon_code = $progress['coupon_code'];

		if ( '' === $coupon_code || WC()->cart->has_discount( $coupon_code ) ) {
			return;
		}

		if ( $this->is_below_minimum_spend( $settings ) ) {
			$this->set_pending_minimum_notice( $coupon_code, (float) $settings['minimum_spend'] );
			return;
		}

		if ( WC()->cart->apply_coupon( $coupon_code ) && WC()->session ) {
			WC()->session->set( 'datrooster_treasure_coupon_applied_notice', $coupon_code );
		}
	}

	/**
	 * Renders a temporary storefront notice when a coupon has just been applied.
	 */
	public function render_applied_notice(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$coupon_code    = WC()->session->get( 'datrooster_treasure_coupon_applied_notice' );
		$pending_notice = WC()->session->get( 'datrooster_treasure_coupon_pending_minimum_notice' );

		if ( ( ! is_string( $coupon_code ) || '' === $coupon_code ) && ! is_array( $pending_notice ) ) {
			return;
		}

		if ( is_array( $pending_notice ) ) {
			WC()->session->__unset( 'datrooster_treasure_coupon_pending_minimum_notice' );

			$message = $this->get_pending_minimum_message( $pending_notice );

			if ( '' !== $message ) {
				wp_enqueue_style( 'datrooster-treasure-coupons' );

				printf(
					'<div class="datrooster-treasure-toast" role="status" aria-live="polite">%s</div>',
					esc_html( $message )
				);
			}

			return;
		}

		WC()->session->__unset( 'datrooster_treasure_coupon_applied_notice' );
		$settings = SettingsPage::get_settings();
		$message  = sprintf(
			/* translators: %s: coupon code. */
			__( '%1$s Code: %2$s', 'datrooster-treasure-coupons' ),
			(string) $settings['applied_message'],
			wc_format_coupon_code( $coupon_code )
		);

		wp_enqueue_style( 'datrooster-treasure-coupons' );

		printf(
			'<div class="datrooster-treasure-toast" role="status" aria-live="polite">%s</div>',
			esc_html( $message )
		);
	}

	/**
	 * Checks whether the current cart is below the configured minimum spend.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	private function is_below_minimum_spend( array $settings ): bool {
		$minimum_spend = (float) $settings['minimum_spend'];

		if ( $minimum_spend <= 0 || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		return $this->get_cart_subtotal() < $minimum_spend;
	}

	/**
	 * Stores a temporary notice for a coupon that is unlocked but not yet usable.
	 *
	 * @param string $coupon_code Coupon code.
	 * @param float  $minimum_spend Minimum cart subtotal.
	 */
	private function set_pending_minimum_notice( string $coupon_code, float $minimum_spend ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		WC()->session->set(
			'datrooster_treasure_coupon_pending_minimum_notice',
			array(
				'coupon_code'   => wc_format_coupon_code( $coupon_code ),
				'minimum_spend' => $minimum_spend,
				'remaining'     => max( 0, $minimum_spend - $this->get_cart_subtotal() ),
			)
		);
	}

	/**
	 * Builds the pending minimum spend message.
	 *
	 * @param array<string,mixed> $notice Pending notice payload.
	 */
	private function get_pending_minimum_message( array $notice ): string {
		$coupon_code = isset( $notice['coupon_code'] ) ? wc_format_coupon_code( (string) $notice['coupon_code'] ) : '';
		$remaining   = isset( $notice['remaining'] ) ? (float) $notice['remaining'] : 0;

		if ( '' === $coupon_code ) {
			return '';
		}

		if ( $remaining > 0 ) {
			return sprintf(
				/* translators: 1: coupon code. 2: remaining cart amount. */
				__( 'Coupon %1$s is unlocked. Add %2$s more to your cart to use it.', 'datrooster-treasure-coupons' ),
				$coupon_code,
				$this->format_money( $remaining )
			);
		}

		return sprintf(
			/* translators: %s: coupon code. */
			__( 'Coupon %s is unlocked and ready to use.', 'datrooster-treasure-coupons' ),
			$coupon_code
		);
	}

	/**
	 * Returns the cart subtotal used for minimum spend messaging.
	 */
	private function get_cart_subtotal(): float {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0;
		}

		return (float) WC()->cart->get_subtotal();
	}

	/**
	 * Formats a money amount as plain text.
	 *
	 * @param float $amount Raw money amount.
	 */
	private function format_money( float $amount ): string {
		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( wc_price( $amount ) );
		}

		return number_format_i18n( $amount, 2 );
	}
}
