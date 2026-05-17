<?php
/**
 * Deposit-related product setting helpers.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments\Deposits;

defined( 'ABSPATH' ) || exit;

final class ProductSettings {
	public const META_BEHAVIOR          = '_drpp_deposit_behavior';
	public const META_DEPOSIT_TYPE      = '_drpp_deposit_type';
	public const META_DEPOSIT_AMOUNT    = '_drpp_deposit_amount';
	public const META_DEFAULT_SELECTION = '_drpp_default_selection';

	public const BEHAVIOR_INHERIT = 'inherit';
	public const BEHAVIOR_ENABLE  = 'enable';
	public const BEHAVIOR_DISABLE = 'disable';

	/**
	 * Returns the list of supported meta keys.
	 *
	 * @return array<int,string>
	 */
	public static function get_meta_keys(): array {
		return array(
			self::META_BEHAVIOR,
			self::META_DEPOSIT_TYPE,
			self::META_DEPOSIT_AMOUNT,
			self::META_DEFAULT_SELECTION,
		);
	}

	/**
	 * Sanitizes the deposit behavior override.
	 *
	 * @param mixed $value Meta value.
	 */
	public static function sanitize_behavior( $value ): string {
		$value = sanitize_key( (string) $value );

		if ( in_array( $value, array( self::BEHAVIOR_INHERIT, self::BEHAVIOR_ENABLE, self::BEHAVIOR_DISABLE ), true ) ) {
			return $value;
		}

		return self::BEHAVIOR_INHERIT;
	}

	/**
	 * Sanitizes the deposit type override.
	 *
	 * @param mixed $value Meta value.
	 */
	public static function sanitize_type_override( $value ): string {
		$value = sanitize_key( (string) $value );

		if ( in_array( $value, array( 'inherit', 'percentage', 'fixed' ), true ) ) {
			return $value;
		}

		return 'inherit';
	}

	/**
	 * Sanitizes the effective deposit type.
	 *
	 * @param mixed  $value    Raw setting value.
	 * @param string $fallback Fallback value.
	 */
	public static function sanitize_effective_type( $value, string $fallback = 'percentage' ): string {
		$value = sanitize_key( (string) $value );

		if ( in_array( $value, array( 'percentage', 'fixed' ), true ) ) {
			return $value;
		}

		return $fallback;
	}

	/**
	 * Sanitizes the default selection override.
	 *
	 * @param mixed $value Meta value.
	 */
	public static function sanitize_default_selection_override( $value ): string {
		$value = sanitize_key( (string) $value );

		if ( in_array( $value, array( 'inherit', 'deposit', 'full' ), true ) ) {
			return $value;
		}

		return 'inherit';
	}

	/**
	 * Sanitizes the effective default selection.
	 *
	 * @param mixed  $value    Raw setting value.
	 * @param string $fallback Fallback value.
	 */
	public static function sanitize_effective_default_selection( $value, string $fallback = 'deposit' ): string {
		$value = sanitize_key( (string) $value );

		if ( in_array( $value, array( 'deposit', 'full' ), true ) ) {
			return $value;
		}

		return $fallback;
	}

	/**
	 * Sanitizes a deposit amount override.
	 *
	 * @param mixed $value Meta value.
	 */
	public static function sanitize_amount_override( $value ): string {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		return self::normalize_amount( $value );
	}

	/**
	 * Sanitizes an effective deposit amount.
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback Fallback value.
	 */
	public static function sanitize_effective_amount( $value, string $fallback = '50' ): string {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return self::normalize_amount( $fallback );
		}

		return self::normalize_amount( $value );
	}

	/**
	 * Returns whether the provided override values contain any product-level customization.
	 *
	 * @param array<string,string> $overrides Raw overrides.
	 */
	public static function has_overrides( array $overrides ): bool {
		return self::BEHAVIOR_INHERIT !== ( $overrides['behavior'] ?? self::BEHAVIOR_INHERIT )
			|| 'inherit' !== ( $overrides['deposit_type'] ?? 'inherit' )
			|| '' !== ( $overrides['deposit_amount'] ?? '' )
			|| 'inherit' !== ( $overrides['default_selection'] ?? 'inherit' );
	}

	/**
	 * Normalizes numeric values to a safe, non-negative string.
	 *
	 * @param string $value Raw numeric value.
	 */
	private static function normalize_amount( string $value ): string {
		$amount = max( 0, (float) $value );
		$text   = number_format( $amount, 2, '.', '' );
		$text   = rtrim( rtrim( $text, '0' ), '.' );

		return '' !== $text ? $text : '0';
	}
}
