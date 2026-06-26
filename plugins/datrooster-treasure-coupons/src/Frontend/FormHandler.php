<?php
/**
 * Handles clue collection form submissions.
 *
 * @package DatRoosterTreasureCoupons
 */

namespace DatRoosterTreasureCoupons\Frontend;

use DatRoosterTreasureCoupons\Admin\SettingsPage;
use DatRoosterTreasureCoupons\Progress\ProgressStore;
use DatRoosterTreasureCoupons\Rewards\CouponGenerator;

defined( 'ABSPATH' ) || exit;

final class FormHandler {
	public const ACTION = 'datrooster_treasure_collect_clue';

	/**
	 * Progress store.
	 *
	 * @var ProgressStore
	 */
	private ProgressStore $progress_store;

	/**
	 * Coupon generator.
	 *
	 * @var CouponGenerator
	 */
	private CouponGenerator $coupon_generator;

	/**
	 * Constructor.
	 *
	 * @param ProgressStore   $progress_store Progress store.
	 * @param CouponGenerator $coupon_generator Coupon generator.
	 */
	public function __construct( ProgressStore $progress_store, CouponGenerator $coupon_generator ) {
		$this->progress_store   = $progress_store;
		$this->coupon_generator = $coupon_generator;
	}

	/**
	 * Registers form handlers.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Handles clue collection.
	 */
	public function handle(): void {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::ACTION ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$settings = SettingsPage::get_settings();
		$redirect = $this->get_redirect_url();

		if ( empty( $settings['enabled'] ) ) {
			$this->redirect_with_status( $redirect, 'disabled' );
		}

		if ( ! empty( $settings['require_login'] ) && ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( $redirect ) );
			exit;
		}

		$hunt_slug = $this->get_posted_value( 'hunt' );
		$clue_id   = $this->get_posted_value( 'clue' );

		if ( '' === $hunt_slug || '' === $clue_id ) {
			$this->redirect_with_status( $redirect, 'invalid' );
		}

		$progress = $this->progress_store->collect_clue( $hunt_slug, $clue_id );
		$status   = 'collected';

		if ( count( $progress['clues'] ) >= absint( $settings['required_clues'] ) ) {
			$coupon_code = $this->coupon_generator->maybe_generate_for_hunt( $hunt_slug );
			$status      = '' !== $coupon_code ? 'completed' : 'collected';
		}

		$this->redirect_with_status( $redirect, $status );
	}

	/**
	 * Reads a submitted scalar value.
	 *
	 * @param string $key POST key.
	 */
	private function get_posted_value( string $key ): string {
		$value = filter_input( INPUT_POST, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * Returns a safe redirect URL.
	 */
	private function get_redirect_url(): string {
		$redirect = $this->get_posted_value( 'redirect_to' );

		if ( '' === $redirect ) {
			$redirect = wp_get_referer();
		}

		if ( ! is_string( $redirect ) || '' === $redirect ) {
			$redirect = home_url( '/' );
		}

		return remove_query_arg(
			array(
				'datrooster_treasure_status',
			),
			esc_url_raw( $redirect )
		);
	}

	/**
	 * Redirects back to the source URL with a status code.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $status Status code.
	 */
	private function redirect_with_status( string $redirect, string $status ): void {
		wp_safe_redirect(
			add_query_arg(
				'datrooster_treasure_status',
				sanitize_key( $status ),
				$redirect
			)
		);
		exit;
	}
}
