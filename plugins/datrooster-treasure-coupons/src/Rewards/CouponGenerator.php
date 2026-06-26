<?php
/**
 * Generates WooCommerce reward coupons.
 *
 * @package DatRoosterTreasureCoupons
 */

namespace DatRoosterTreasureCoupons\Rewards;

use DatRoosterTreasureCoupons\Admin\SettingsPage;
use DatRoosterTreasureCoupons\Progress\ProgressStore;

defined( 'ABSPATH' ) || exit;

final class CouponGenerator {
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
	 * Generates a coupon when the hunt has been completed.
	 *
	 * @param string $hunt_slug Hunt identifier.
	 */
	public function maybe_generate_for_hunt( string $hunt_slug ): string {
		$settings  = SettingsPage::get_settings();
		$hunt_slug = sanitize_title( $hunt_slug );
		$progress  = $this->progress_store->get_progress( $hunt_slug );

		if ( '' !== $progress['coupon_code'] ) {
			return $progress['coupon_code'];
		}

		if ( count( $progress['clues'] ) < absint( $settings['required_clues'] ) ) {
			return '';
		}

		if ( ! class_exists( '\WC_Coupon' ) || ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return '';
		}

		$coupon_code = $this->generate_unique_code( (string) $settings['coupon_prefix'] );
		$coupon      = new \WC_Coupon();
		$reward_type = sanitize_key( (string) $settings['discount_type'] );

		if ( 'free_shipping' === $reward_type ) {
			$coupon->set_discount_type( 'fixed_cart' );
			$coupon->set_amount( 0 );
			$coupon->set_free_shipping( true );
		} else {
			$coupon->set_discount_type( $reward_type );
			$coupon->set_amount( (float) $settings['discount_amount'] );
		}

		$coupon->set_code( $coupon_code );
		$coupon->set_usage_limit( 1 );
		$coupon->set_individual_use( true );

		if ( (float) $settings['minimum_spend'] > 0 ) {
			$coupon->set_minimum_amount( (string) $settings['minimum_spend'] );
		}

		$expires_at = time() + ( max( 1, absint( $settings['coupon_valid_days'] ) ) * DAY_IN_SECONDS );
		$coupon->set_date_expires( $expires_at );

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();

			if ( $user instanceof \WP_User && is_email( $user->user_email ) ) {
				$coupon->set_email_restrictions( array( $user->user_email ) );
			}
		}

		$coupon->add_meta_data( '_datrooster_treasure_coupon', 'yes', true );
		$coupon->add_meta_data( '_datrooster_treasure_hunt', $hunt_slug, true );
		$coupon->save();

		$this->progress_store->set_coupon( $hunt_slug, $coupon->get_id(), $coupon_code );

		return $coupon_code;
	}

	/**
	 * Generates a unique coupon code.
	 *
	 * @param string $prefix Coupon prefix.
	 */
	private function generate_unique_code( string $prefix ): string {
		$prefix = strtoupper( preg_replace( '/[^A-Z0-9_-]/', '', $prefix ) ?? '' );
		$prefix = '' !== $prefix ? substr( $prefix, 0, 20 ) : 'TREASURE';

		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$random = strtoupper( wp_generate_password( 8, false, false ) );
			$random = preg_replace( '/[^A-Z0-9]/', '', $random ) ?? '';
			$code   = wc_format_coupon_code( $prefix . '-' . $random );

			if ( 0 === wc_get_coupon_id_by_code( $code ) ) {
				return $code;
			}
		}

		return wc_format_coupon_code( $prefix . '-' . strtoupper( wp_generate_uuid4() ) );
	}
}
