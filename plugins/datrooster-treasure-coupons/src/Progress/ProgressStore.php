<?php
/**
 * Stores treasure hunt progress for logged-in users and guests.
 *
 * @package DatRoosterTreasureCoupons
 */

namespace DatRoosterTreasureCoupons\Progress;

defined( 'ABSPATH' ) || exit;

final class ProgressStore {
	private const USER_META_KEY = 'datrooster_treasure_coupons_progress';
	private const COOKIE_NAME   = 'datrooster_treasure_coupons_progress';
	private const COOKIE_DAYS   = 30;

	/**
	 * Resets progress for the current browser/user.
	 */
	public function reset_current_participant(): void {
		if ( is_user_logged_in() ) {
			delete_user_meta( get_current_user_id(), self::USER_META_KEY );
		}

		$this->expire_guest_cookie();
	}

	/**
	 * Returns progress for a hunt.
	 *
	 * @param string $hunt_slug Hunt identifier.
	 * @return array{clues:array<int,string>,coupon_code:string,coupon_id:int,completed_at:int}
	 */
	public function get_progress( string $hunt_slug ): array {
		$hunt_slug = sanitize_title( $hunt_slug );
		$all       = $this->get_all_progress();
		$progress  = isset( $all[ $hunt_slug ] ) && is_array( $all[ $hunt_slug ] ) ? $all[ $hunt_slug ] : array();
		$clues     = isset( $progress['clues'] ) && is_array( $progress['clues'] ) ? $progress['clues'] : array();

		$clues = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $clues )
				)
			)
		);

		return array(
			'clues'        => $clues,
			'coupon_code'  => isset( $progress['coupon_code'] ) ? sanitize_text_field( (string) $progress['coupon_code'] ) : '',
			'coupon_id'    => isset( $progress['coupon_id'] ) ? absint( $progress['coupon_id'] ) : 0,
			'completed_at' => isset( $progress['completed_at'] ) ? absint( $progress['completed_at'] ) : 0,
		);
	}

	/**
	 * Adds a clue to the progress set.
	 *
	 * @param string $hunt_slug Hunt identifier.
	 * @param string $clue_id Clue identifier.
	 * @return array{clues:array<int,string>,coupon_code:string,coupon_id:int,completed_at:int}
	 */
	public function collect_clue( string $hunt_slug, string $clue_id ): array {
		$hunt_slug = sanitize_title( $hunt_slug );
		$clue_id   = sanitize_key( $clue_id );
		$progress  = $this->get_progress( $hunt_slug );

		if ( '' !== $clue_id && ! in_array( $clue_id, $progress['clues'], true ) ) {
			$progress['clues'][] = $clue_id;
		}

		$this->save_progress( $hunt_slug, $progress );

		return $progress;
	}

	/**
	 * Stores the generated coupon reference.
	 *
	 * @param string $hunt_slug Hunt identifier.
	 * @param int    $coupon_id WooCommerce coupon ID.
	 * @param string $coupon_code WooCommerce coupon code.
	 */
	public function set_coupon( string $hunt_slug, int $coupon_id, string $coupon_code ): void {
		$hunt_slug = sanitize_title( $hunt_slug );
		$progress  = $this->get_progress( $hunt_slug );

		$progress['coupon_id']    = absint( $coupon_id );
		$progress['coupon_code']  = wc_format_coupon_code( $coupon_code );
		$progress['completed_at'] = time();

		$this->save_progress( $hunt_slug, $progress );
	}

	/**
	 * Returns all stored progress.
	 *
	 * @return array<string,mixed>
	 */
	private function get_all_progress(): array {
		if ( is_user_logged_in() ) {
			$progress = get_user_meta( get_current_user_id(), self::USER_META_KEY, true );

			return is_array( $progress ) ? $progress : array();
		}

		return $this->get_guest_progress();
	}

	/**
	 * Persists progress for a hunt.
	 *
	 * @param string                                                                                        $hunt_slug Hunt identifier.
	 * @param array{clues:array<int,string>,coupon_code:string,coupon_id:int,completed_at:int} $progress Hunt progress.
	 */
	private function save_progress( string $hunt_slug, array $progress ): void {
		$all                = $this->get_all_progress();
		$all[ $hunt_slug ]  = $progress;
		$normalized_payload = $this->normalize_payload( $all );

		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), self::USER_META_KEY, $normalized_payload );
			return;
		}

		$this->save_guest_progress( $normalized_payload );
	}

	/**
	 * Returns progress from the signed guest cookie.
	 *
	 * @return array<string,mixed>
	 */
	private function get_guest_progress(): array {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return array();
		}

		$raw_cookie = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		$decoded    = rawurldecode( $raw_cookie );

		$envelope = json_decode( $decoded, true );

		if ( ! is_array( $envelope ) || empty( $envelope['payload'] ) || empty( $envelope['hash'] ) ) {
			return array();
		}

		$payload = (string) $envelope['payload'];
		$hash    = (string) $envelope['hash'];

		if ( ! hash_equals( $this->hash_payload( $payload ), $hash ) ) {
			return array();
		}

		$progress = json_decode( $payload, true );

		return is_array( $progress ) ? $this->normalize_payload( $progress ) : array();
	}

	/**
	 * Saves progress into a signed guest cookie.
	 *
	 * @param array<string,mixed> $progress Progress payload.
	 */
	private function save_guest_progress( array $progress ): void {
		$payload = wp_json_encode( $progress );

		if ( false === $payload ) {
			return;
		}

		$envelope = wp_json_encode(
			array(
				'payload' => $payload,
				'hash'    => $this->hash_payload( $payload ),
			)
		);

		if ( false === $envelope ) {
			return;
		}

		$value = rawurlencode( $envelope );

		setcookie(
			self::COOKIE_NAME,
			$value,
			array(
				'expires'  => time() + ( self::COOKIE_DAYS * DAY_IN_SECONDS ),
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		$_COOKIE[ self::COOKIE_NAME ] = $value;
	}

	/**
	 * Expires the guest progress cookie.
	 */
	private function expire_guest_cookie(): void {
		setcookie(
			self::COOKIE_NAME,
			'',
			array(
				'expires'  => time() - DAY_IN_SECONDS,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		unset( $_COOKIE[ self::COOKIE_NAME ] );
	}

	/**
	 * Signs a payload.
	 *
	 * @param string $payload JSON payload.
	 */
	private function hash_payload( string $payload ): string {
		return hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}

	/**
	 * Normalizes a full progress payload.
	 *
	 * @param array<string,mixed> $payload Raw payload.
	 * @return array<string,mixed>
	 */
	private function normalize_payload( array $payload ): array {
		$normalized = array();

		foreach ( $payload as $hunt_slug => $progress ) {
			$hunt_slug = sanitize_title( (string) $hunt_slug );

			if ( '' === $hunt_slug || ! is_array( $progress ) ) {
				continue;
			}

			$clues = isset( $progress['clues'] ) && is_array( $progress['clues'] ) ? $progress['clues'] : array();

			$normalized[ $hunt_slug ] = array(
				'clues'        => array_values( array_unique( array_filter( array_map( 'sanitize_key', $clues ) ) ) ),
				'coupon_code'  => isset( $progress['coupon_code'] ) ? sanitize_text_field( (string) $progress['coupon_code'] ) : '',
				'coupon_id'    => isset( $progress['coupon_id'] ) ? absint( $progress['coupon_id'] ) : 0,
				'completed_at' => isset( $progress['completed_at'] ) ? absint( $progress['completed_at'] ) : 0,
			);
		}

		return $normalized;
	}
}
