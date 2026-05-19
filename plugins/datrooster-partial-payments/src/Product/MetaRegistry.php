<?php
/**
 * Registers deposit-related product meta.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments\Product;

use DatRooster\PartialPayments\Deposits\ProductSettings;

defined( 'ABSPATH' ) || exit;

final class MetaRegistry {
	/**
	 * Registers the meta hook.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_product_meta' ) );
	}

	/**
	 * Registers product meta used by the plugin.
	 */
	public function register_product_meta(): void {
		register_post_meta(
			'product',
			ProductSettings::META_BEHAVIOR,
			$this->get_meta_args( array( ProductSettings::class, 'sanitize_behavior' ) )
		);

		register_post_meta(
			'product',
			ProductSettings::META_DEPOSIT_TYPE,
			$this->get_meta_args( array( ProductSettings::class, 'sanitize_type_override' ) )
		);

		register_post_meta(
			'product',
			ProductSettings::META_DEPOSIT_AMOUNT,
			$this->get_meta_args( array( ProductSettings::class, 'sanitize_amount_override' ) )
		);

		register_post_meta(
			'product',
			ProductSettings::META_DEFAULT_SELECTION,
			$this->get_meta_args( array( ProductSettings::class, 'sanitize_default_selection_override' ) )
		);
	}

	/**
	 * Returns shared meta registration arguments.
	 *
	 * @param callable $sanitize_callback Sanitize callback.
	 * @return array<string,mixed>
	 */
	private function get_meta_args( callable $sanitize_callback ): array {
		return array(
			'single'            => true,
			'type'              => 'string',
			'show_in_rest'      => true,
			'auth_callback'     => array( self::class, 'can_edit_meta' ),
			'sanitize_callback' => $sanitize_callback,
		);
	}

	/**
	 * Limits access to users allowed to edit products.
	 *
	 * @param mixed ...$unused_args Unused callback arguments provided by WordPress.
	 */
	public static function can_edit_meta( ...$unused_args ): bool {
		unset( $unused_args );

		return current_user_can( 'edit_products' );
	}
}
