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

		$coupon_code = WC()->session->get( 'datrooster_treasure_coupon_applied_notice' );

		if ( ! is_string( $coupon_code ) || '' === $coupon_code ) {
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
}
