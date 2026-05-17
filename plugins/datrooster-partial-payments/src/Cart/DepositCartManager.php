<?php
/**
 * Cart and checkout integration for deposit items.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments\Cart;

use DatRooster\PartialPayments\Deposits\Calculator;
use DatRooster\PartialPayments\Deposits\SettingsResolver;

defined( 'ABSPATH' ) || exit;

final class DepositCartManager {
	public const REQUEST_KEY = 'drpp_payment_mode';
	public const CART_KEY    = 'drpp_deposit';
	public const MODE_FULL   = 'full';
	public const MODE_DEPOSIT = 'deposit';

	/**
	 * Shared settings resolver.
	 *
	 * @var SettingsResolver
	 */
	private SettingsResolver $settings_resolver;

	/**
	 * Shared calculator.
	 *
	 * @var Calculator
	 */
	private Calculator $calculator;

	/**
	 * Class constructor.
	 *
	 * @param SettingsResolver $settings_resolver Shared settings resolver.
	 * @param Calculator       $calculator        Shared calculator.
	 */
	public function __construct( SettingsResolver $settings_resolver, Calculator $calculator ) {
		$this->settings_resolver = $settings_resolver;
		$this->calculator        = $calculator;
	}

	/**
	 * Registers cart and checkout hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 6 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'capture_cart_item_data' ), 10, 4 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_deposit_prices' ) );
		add_filter( 'woocommerce_package_rates', array( $this, 'filter_package_rates' ), 20, 2 );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_available_payment_gateways' ) );
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'filter_coupon_valid_for_product' ), 10, 4 );
		add_action( 'woocommerce_cart_totals_after_order_total', array( $this, 'render_remaining_balance_row' ) );
		add_action( 'woocommerce_review_order_after_order_total', array( $this, 'render_remaining_balance_row' ) );
	}

	/**
	 * Validates deposit add to cart requests.
	 *
	 * @param bool               $passed         Validation result so far.
	 * @param int                $product_id     Product ID.
	 * @param int|float          $quantity       Requested quantity.
	 * @param int                $variation_id   Variation ID when applicable.
	 * @param array<string,mixed> $variations     Variation data.
	 * @param array<string,mixed> $cart_item_data Existing cart item data.
	 */
	public function validate_add_to_cart( bool $passed, int $product_id, $quantity, int $variation_id = 0, array $variations = array(), array $cart_item_data = array() ): bool {
		unset( $quantity, $variations, $cart_item_data );

		if ( ! $passed ) {
			return false;
		}

		$mode = $this->get_requested_mode();

		if ( self::MODE_DEPOSIT !== $mode ) {
			return true;
		}

		$product = $this->get_request_product( $product_id, $variation_id );

		if ( ! $product instanceof \WC_Product ) {
			wc_add_notice( __( 'The selected product could not be prepared for a deposit purchase.', 'datrooster-partial-payments' ), 'error' );
			return false;
		}

		if ( ! $this->settings_resolver->product_supports_deposits( $product ) ) {
			wc_add_notice( __( 'Deposits are not available for this product.', 'datrooster-partial-payments' ), 'error' );
			return false;
		}

		$settings = $this->settings_resolver->get_effective_product_settings( $product );

		if ( empty( $settings['enabled'] ) ) {
			wc_add_notice( __( 'Deposits are currently disabled for this product.', 'datrooster-partial-payments' ), 'error' );
			return false;
		}

		if ( ! empty( $settings['require_login'] ) && ! is_user_logged_in() ) {
			wc_add_notice( __( 'Please log in before purchasing this product with a deposit.', 'datrooster-partial-payments' ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Captures deposit-related data when an item is added to the cart.
	 *
	 * @param array<string,mixed> $cart_item_data Existing cart item data.
	 * @param int                 $product_id     Product ID.
	 * @param int                 $variation_id   Variation ID when applicable.
	 * @param int|float           $quantity       Requested quantity.
	 * @return array<string,mixed>
	 */
	public function capture_cart_item_data( array $cart_item_data, int $product_id, int $variation_id = 0, $quantity = 1 ): array {
		unset( $quantity );

		$mode = $this->get_requested_mode();

		if ( self::MODE_DEPOSIT !== $mode ) {
			return $cart_item_data;
		}

		$product = $this->get_request_product( $product_id, $variation_id );

		if ( ! $product instanceof \WC_Product ) {
			return $cart_item_data;
		}

		$settings = $this->settings_resolver->get_effective_product_settings( $product );

		if ( empty( $settings['enabled'] ) ) {
			return $cart_item_data;
		}

		$original_price = (float) $product->get_price( 'edit' );
		$breakdown      = $this->calculator->get_breakdown( $original_price, 1, $settings );

		$cart_item_data[ self::CART_KEY ] = array(
			'payment_mode'       => self::MODE_DEPOSIT,
			'deposit_type'       => (string) $settings['deposit_type'],
			'deposit_amount'     => (string) $settings['deposit_amount'],
			'original_unit_price' => $this->format_decimal( $breakdown['full_unit_price'] ),
			'deposit_unit_price' => $this->format_decimal( $breakdown['deposit_unit_price'] ),
			'balance_unit_price' => $this->format_decimal( $breakdown['balance_unit_price'] ),
			'disabled_gateways'  => ! empty( $settings['disabled_gateways'] ) && is_array( $settings['disabled_gateways'] ) ? array_values( $settings['disabled_gateways'] ) : array(),
		);

		return $cart_item_data;
	}

	/**
	 * Adds user-facing metadata to cart lines.
	 *
	 * @param array<int,array<string,string>> $item_data Existing item data.
	 * @param array<string,mixed>             $cart_item Cart item values.
	 * @return array<int,array<string,string>>
	 */
	public function get_item_data( array $item_data, array $cart_item ): array {
		if ( ! $this->is_deposit_cart_item( $cart_item ) ) {
			return $item_data;
		}

		$labels    = $this->settings_resolver->get_label_settings();
		$breakdown = $this->get_cart_item_breakdown( $cart_item );

		$item_data[] = array(
			'key'   => __( 'Payment option', 'datrooster-partial-payments' ),
			'value' => $labels['pay_deposit_text'],
		);

		$item_data[] = array(
			'key'   => __( 'Due today', 'datrooster-partial-payments' ),
			'value' => $this->format_price( $breakdown['deposit_line_total'] ),
		);

		$item_data[] = array(
			'key'   => __( 'Remaining product balance', 'datrooster-partial-payments' ),
			'value' => $this->format_price( $breakdown['balance_line_total'] ),
		);

		return $item_data;
	}

	/**
	 * Rewrites eligible cart item prices to the chosen deposit amount.
	 *
	 * @param \WC_Cart $cart WooCommerce cart instance.
	 */
	public function apply_deposit_prices( \WC_Cart $cart ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! $this->is_deposit_cart_item( $cart_item ) ) {
				continue;
			}

			if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof \WC_Product ) {
				continue;
			}

			$breakdown = $this->get_cart_item_breakdown( $cart_item );

			$cart_item['data']->set_price( $breakdown['deposit_unit_price'] );

			$cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['deposit_unit_price'] = $this->format_decimal( $breakdown['deposit_unit_price'] );
			$cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['balance_unit_price'] = $this->format_decimal( $breakdown['balance_unit_price'] );
			$cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['full_line_total']    = $this->format_decimal( $breakdown['full_line_total'] );
			$cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['deposit_line_total'] = $this->format_decimal( $breakdown['deposit_line_total'] );
			$cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['balance_line_total'] = $this->format_decimal( $breakdown['balance_line_total'] );
		}
	}

	/**
	 * Filters available gateways when deposit items are in the cart.
	 *
	 * @param array<string,\WC_Payment_Gateway> $gateways Available gateways.
	 * @return array<string,\WC_Payment_Gateway>
	 */
	public function filter_available_payment_gateways( array $gateways ): array {
		if ( empty( $gateways ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $gateways;
		}

		$disabled_gateways = array();

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! $this->is_deposit_cart_item( $cart_item ) ) {
				continue;
			}

			if ( empty( $cart_item[ self::CART_KEY ]['disabled_gateways'] ) || ! is_array( $cart_item[ self::CART_KEY ]['disabled_gateways'] ) ) {
				continue;
			}

			$disabled_gateways = array_merge( $disabled_gateways, array_map( 'sanitize_text_field', $cart_item[ self::CART_KEY ]['disabled_gateways'] ) );
		}

		foreach ( array_unique( $disabled_gateways ) as $gateway_id ) {
			unset( $gateways[ $gateway_id ] );
		}

		return $gateways;
	}

	/**
	 * Adjusts shipping rates when shipping should be split proportionally.
	 *
	 * @param array<string,\WC_Shipping_Rate> $rates   Calculated shipping rates.
	 * @param array<string,mixed>             $package Shipping package.
	 * @return array<string,\WC_Shipping_Rate>
	 */
	public function filter_package_rates( array $rates, array $package ): array {
		unset( $package );

		$settings = $this->settings_resolver->get_global_settings();
		$overview = $this->get_cart_product_charge_overview();

		if ( empty( $overview['has_deposit'] ) || 'proportional' !== $settings['shipping_handling'] ) {
			return $rates;
		}

		$ratio = (float) $overview['product_due_ratio'];

		if ( $ratio <= 0 || $ratio >= 1 ) {
			return $rates;
		}

		foreach ( $rates as $rate_id => $rate ) {
			if ( ! $rate instanceof \WC_Shipping_Rate ) {
				continue;
			}

			$original_cost = (float) $rate->get_cost();
			$taxes         = $rate->get_taxes();
			$new_taxes     = array();

			if ( is_array( $taxes ) ) {
				foreach ( $taxes as $tax_id => $tax_amount ) {
					$new_taxes[ $tax_id ] = (float) $this->format_decimal( (float) $tax_amount * $ratio );
				}
			}

			$rate->set_cost( $this->format_decimal( $original_cost * $ratio ) );
			$rate->set_taxes( $new_taxes );

			$rates[ $rate_id ] = $rate;
		}

		return $rates;
	}

	/**
	 * Excludes deposit items from coupons when configured.
	 *
	 * @param bool                $valid   Current validity value.
	 * @param \WC_Product         $product Product object.
	 * @param \WC_Coupon          $coupon  Coupon object.
	 * @param array<string,mixed> $values  Cart item values.
	 */
	public function filter_coupon_valid_for_product( bool $valid, \WC_Product $product, \WC_Coupon $coupon, array $values = array() ): bool {
		unset( $product, $coupon );

		if ( ! $valid ) {
			return false;
		}

		$settings = $this->settings_resolver->get_global_settings();

		if ( 'exclude_deposit_items' !== $settings['coupon_handling'] ) {
			return $valid;
		}

		return $this->is_deposit_cart_item( $values ) ? false : $valid;
	}

	/**
	 * Renders the remaining balance rows in cart and checkout totals.
	 */
	public function render_remaining_balance_row(): void {
		$summary = $this->get_cart_deposit_summary();

		if ( ! $summary['has_deposit'] || 0 >= $summary['estimated_remaining_total'] ) {
			return;
		}
		?>
		<tr class="drpp-remaining-balance">
			<th><?php esc_html_e( 'Remaining product balance', 'datrooster-partial-payments' ); ?></th>
			<td data-title="<?php esc_attr_e( 'Remaining product balance', 'datrooster-partial-payments' ); ?>">
				<?php echo wp_kses_post( $this->format_price( $summary['remaining_product_total'] ) ); ?>
			</td>
		</tr>
		<?php if ( $summary['remaining_product_tax'] > 0 ) : ?>
			<tr class="drpp-remaining-balance">
				<th><?php esc_html_e( 'Remaining product tax', 'datrooster-partial-payments' ); ?></th>
				<td data-title="<?php esc_attr_e( 'Remaining product tax', 'datrooster-partial-payments' ); ?>">
					<?php echo wp_kses_post( $this->format_price( $summary['remaining_product_tax'] ) ); ?>
				</td>
			</tr>
		<?php endif; ?>
		<?php if ( $summary['remaining_shipping_total'] > 0 ) : ?>
			<tr class="drpp-remaining-balance">
				<th><?php esc_html_e( 'Remaining shipping balance', 'datrooster-partial-payments' ); ?></th>
				<td data-title="<?php esc_attr_e( 'Remaining shipping balance', 'datrooster-partial-payments' ); ?>">
					<?php echo wp_kses_post( $this->format_price( $summary['remaining_shipping_total'] ) ); ?>
				</td>
			</tr>
		<?php endif; ?>
		<?php if ( $summary['remaining_shipping_tax'] > 0 ) : ?>
			<tr class="drpp-remaining-balance">
				<th><?php esc_html_e( 'Remaining shipping tax', 'datrooster-partial-payments' ); ?></th>
				<td data-title="<?php esc_attr_e( 'Remaining shipping tax', 'datrooster-partial-payments' ); ?>">
					<?php echo wp_kses_post( $this->format_price( $summary['remaining_shipping_tax'] ) ); ?>
				</td>
			</tr>
		<?php endif; ?>
		<tr class="drpp-remaining-balance drpp-remaining-balance-total">
			<th><?php esc_html_e( 'Estimated remaining balance', 'datrooster-partial-payments' ); ?></th>
			<td data-title="<?php esc_attr_e( 'Estimated remaining balance', 'datrooster-partial-payments' ); ?>">
				<?php echo wp_kses_post( $this->format_price( $summary['estimated_remaining_total'] ) ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Returns whether the cart contains deposit items.
	 */
	public function cart_has_deposit_items(): bool {
		return $this->get_cart_deposit_summary()['has_deposit'];
	}

	/**
	 * Returns current deposit totals for the whole cart.
	 *
	 * @return array<string,mixed>
	 */
	public function get_cart_deposit_summary(): array {
		$product_overview = $this->get_cart_product_charge_overview();
		$settings         = $this->settings_resolver->get_global_settings();

		$summary = array(
			'has_deposit'              => $product_overview['has_deposit'],
			'full_total'               => 0.0,
			'deposit_total'            => 0.0,
			'balance_total'            => 0.0,
			'full_product_total'       => (float) $product_overview['full_product_total'],
			'deposit_product_total'    => (float) $product_overview['scheduled_product_due_total'],
			'remaining_product_total'  => (float) $product_overview['remaining_product_total'],
			'remaining_product_tax'    => 0.0,
			'remaining_shipping_total' => 0.0,
			'remaining_shipping_tax'   => 0.0,
			'estimated_remaining_total' => 0.0,
			'product_due_ratio'        => (float) $product_overview['product_due_ratio'],
			'tax_handling'             => (string) $settings['tax_handling'],
			'shipping_handling'        => (string) $settings['shipping_handling'],
			'coupon_handling'          => (string) $settings['coupon_handling'],
		);

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $summary;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! $this->is_deposit_cart_item( $cart_item ) ) {
				continue;
			}

			$breakdown = $this->get_cart_item_breakdown( $cart_item );

			$summary['has_deposit']   = true;
			$summary['full_total']    += $breakdown['full_line_total'];
			$summary['deposit_total'] += $breakdown['deposit_line_total'];
			$summary['balance_total'] += $breakdown['balance_line_total'];
			$summary['remaining_product_tax'] += $this->get_cart_item_remaining_tax( $cart_item, $breakdown );
		}

		$summary['full_total']    = (float) $this->format_decimal( $summary['full_total'] );
		$summary['deposit_total'] = (float) $this->format_decimal( $summary['deposit_total'] );
		$summary['balance_total'] = (float) $this->format_decimal( $summary['balance_total'] );
		$summary['remaining_product_total'] = (float) $this->format_decimal( $summary['remaining_product_total'] );
		$summary['remaining_product_tax']   = (float) $this->format_decimal( $summary['remaining_product_tax'] );

		if ( $summary['has_deposit'] && 'proportional' === $summary['shipping_handling'] ) {
			$ratio = (float) $summary['product_due_ratio'];

			if ( $ratio > 0 && $ratio < 1 ) {
				$current_shipping_total = (float) WC()->cart->get_shipping_total();
				$current_shipping_tax   = (float) WC()->cart->get_shipping_tax();
				$multiplier             = ( 1 - $ratio ) / $ratio;

				$summary['remaining_shipping_total'] = (float) $this->format_decimal( $current_shipping_total * $multiplier );
				$summary['remaining_shipping_tax']   = (float) $this->format_decimal( $current_shipping_tax * $multiplier );
			}
		}

		$summary['estimated_remaining_total'] = (float) $this->format_decimal(
			$summary['remaining_product_total']
			+ $summary['remaining_product_tax']
			+ $summary['remaining_shipping_total']
			+ $summary['remaining_shipping_tax']
		);

		return $summary;
	}

	/**
	 * Returns the remaining tax estimate for a deposit cart item.
	 *
	 * @param array<string,mixed> $cart_item  Cart item values.
	 * @param array<string,float> $breakdown  Item product breakdown.
	 */
	public function get_cart_item_remaining_tax( array $cart_item, array $breakdown ): float {
		$deposit_total = (float) $breakdown['deposit_line_total'];
		$balance_total = (float) $breakdown['balance_line_total'];

		if ( $deposit_total <= 0 || $balance_total <= 0 ) {
			return 0.0;
		}

		$deposit_tax_base = isset( $cart_item['line_subtotal_tax'] ) ? (float) $cart_item['line_subtotal_tax'] : 0.0;

		if ( $deposit_tax_base <= 0 ) {
			return 0.0;
		}

		return (float) $this->format_decimal( $deposit_tax_base * ( $balance_total / $deposit_total ) );
	}

	/**
	 * Returns the product charge overview used across shipping and balance calculations.
	 *
	 * @return array<string,mixed>
	 */
	private function get_cart_product_charge_overview(): array {
		$overview = array(
			'has_deposit'                 => false,
			'full_product_total'          => 0.0,
			'scheduled_product_due_total' => 0.0,
			'remaining_product_total'     => 0.0,
			'product_due_ratio'           => 1.0,
		);

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $overview;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$quantity = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;

			if ( $this->is_deposit_cart_item( $cart_item ) ) {
				$breakdown = $this->get_cart_item_breakdown( $cart_item );

				$overview['has_deposit']                 = true;
				$overview['full_product_total']         += $breakdown['full_line_total'];
				$overview['scheduled_product_due_total'] += $breakdown['deposit_line_total'];
				$overview['remaining_product_total']    += $breakdown['balance_line_total'];
				continue;
			}

			if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof \WC_Product ) {
				continue;
			}

			$line_total = (float) $cart_item['data']->get_price( 'edit' ) * $quantity;

			$overview['full_product_total']         += $line_total;
			$overview['scheduled_product_due_total'] += $line_total;
		}

		if ( $overview['full_product_total'] > 0 ) {
			$overview['product_due_ratio'] = round( $overview['scheduled_product_due_total'] / $overview['full_product_total'], 6 );
		}

		$overview['full_product_total']          = (float) $this->format_decimal( $overview['full_product_total'] );
		$overview['scheduled_product_due_total'] = (float) $this->format_decimal( $overview['scheduled_product_due_total'] );
		$overview['remaining_product_total']     = (float) $this->format_decimal( $overview['remaining_product_total'] );

		return $overview;
	}

	/**
	 * Returns the normalized deposit breakdown for a specific cart item.
	 *
	 * @param array<string,mixed> $cart_item Cart item values.
	 * @return array<string,float>
	 */
	public function get_cart_item_breakdown( array $cart_item ): array {
		$quantity       = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
		$deposit_data   = $cart_item[ self::CART_KEY ] ?? array();
		$original_price = isset( $deposit_data['original_unit_price'] ) ? (float) $deposit_data['original_unit_price'] : 0.0;
		$settings       = array(
			'deposit_type'   => $deposit_data['deposit_type'] ?? 'percentage',
			'deposit_amount' => $deposit_data['deposit_amount'] ?? '0',
		);

		return $this->calculator->get_breakdown( $original_price, $quantity, $settings );
	}

	/**
	 * Returns whether a cart item is in deposit mode.
	 *
	 * @param array<string,mixed> $cart_item Cart item values.
	 */
	public function is_deposit_cart_item( array $cart_item ): bool {
		return ! empty( $cart_item[ self::CART_KEY ]['payment_mode'] ) && self::MODE_DEPOSIT === $cart_item[ self::CART_KEY ]['payment_mode'];
	}

	/**
	 * Reads the selected payment mode from the current request.
	 */
	private function get_requested_mode(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates add to cart requests in its own handlers.
		if ( ! isset( $_POST[ self::REQUEST_KEY ] ) ) {
			return self::MODE_FULL;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates add to cart requests in its own handlers.
		$mode = sanitize_key( wp_unslash( (string) $_POST[ self::REQUEST_KEY ] ) );

		return in_array( $mode, array( self::MODE_FULL, self::MODE_DEPOSIT ), true ) ? $mode : self::MODE_FULL;
	}

	/**
	 * Returns the product object being added to cart.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 */
	private function get_request_product( int $product_id, int $variation_id ): ?\WC_Product {
		$target_id = $variation_id > 0 ? $variation_id : $product_id;
		$product   = wc_get_product( $target_id );

		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * Formats a numeric amount for storage.
	 *
	 * @param float $amount Raw amount.
	 */
	private function format_decimal( float $amount ): string {
		if ( function_exists( 'wc_format_decimal' ) ) {
			return wc_format_decimal( $amount, wc_get_price_decimals() );
		}

		return number_format( $amount, 2, '.', '' );
	}

	/**
	 * Formats a numeric amount as a storefront price.
	 *
	 * @param float $amount Raw amount.
	 */
	private function format_price( float $amount ): string {
		if ( function_exists( 'wc_price' ) ) {
			return wc_price( $amount );
		}

		return number_format_i18n( $amount, 2 );
	}
}
