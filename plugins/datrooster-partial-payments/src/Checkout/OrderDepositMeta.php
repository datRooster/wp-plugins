<?php
/**
 * Persists deposit metadata to WooCommerce orders.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments\Checkout;

use DatRooster\PartialPayments\Cart\DepositCartManager;

defined( 'ABSPATH' ) || exit;

final class OrderDepositMeta {
	/**
	 * Shared cart manager.
	 *
	 * @var DepositCartManager
	 */
	private DepositCartManager $cart_manager;

	/**
	 * Class constructor.
	 *
	 * @param DepositCartManager $cart_manager Shared cart manager.
	 */
	public function __construct( DepositCartManager $cart_manager ) {
		$this->cart_manager = $cart_manager;
	}

	/**
	 * Registers order persistence hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'store_line_item_meta' ), 10, 4 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'store_order_meta' ), 10, 2 );
	}

	/**
	 * Stores deposit metadata on order line items.
	 *
	 * @param \WC_Order_Item_Product $item          Order item object.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array<string,mixed>    $values        Cart item values.
	 * @param \WC_Order              $order         Order object.
	 */
	public function store_line_item_meta( \WC_Order_Item_Product $item, string $cart_item_key, array $values, \WC_Order $order ): void {
		unset( $cart_item_key, $order );

		if ( ! $this->cart_manager->is_deposit_cart_item( $values ) ) {
			return;
		}

		$deposit_data = $values[ DepositCartManager::CART_KEY ] ?? array();
		$breakdown    = $this->cart_manager->get_cart_item_breakdown( $values );

		$item->add_meta_data( '_drpp_payment_mode', DepositCartManager::MODE_DEPOSIT, true );
		$item->add_meta_data( '_drpp_deposit_type', sanitize_text_field( (string) ( $deposit_data['deposit_type'] ?? '' ) ), true );
		$item->add_meta_data( '_drpp_deposit_rule_amount', sanitize_text_field( (string) ( $deposit_data['deposit_amount'] ?? '' ) ), true );
		$item->add_meta_data( '_drpp_original_unit_price', $this->format_decimal( $breakdown['full_unit_price'] ), true );
		$item->add_meta_data( '_drpp_deposit_unit_price', $this->format_decimal( $breakdown['deposit_unit_price'] ), true );
		$item->add_meta_data( '_drpp_balance_unit_price', $this->format_decimal( $breakdown['balance_unit_price'] ), true );
		$item->add_meta_data( '_drpp_full_products_total', $this->format_decimal( $breakdown['full_line_total'] ), true );
		$item->add_meta_data( '_drpp_deposit_products_total', $this->format_decimal( $breakdown['deposit_line_total'] ), true );
		$item->add_meta_data( '_drpp_remaining_products_total', $this->format_decimal( $breakdown['balance_line_total'] ), true );
	}

	/**
	 * Stores order-level deposit metadata.
	 *
	 * @param \WC_Order           $order Order object.
	 * @param array<string,mixed> $data  Posted checkout data.
	 */
	public function store_order_meta( \WC_Order $order, array $data ): void {
		unset( $data );

		$summary = $this->cart_manager->get_cart_deposit_summary();

		if ( empty( $summary['has_deposit'] ) ) {
			return;
		}

		$order->update_meta_data( '_drpp_has_deposit', 'yes' );
		$order->update_meta_data( '_drpp_full_products_total', $this->format_decimal( (float) $summary['full_total'] ) );
		$order->update_meta_data( '_drpp_deposit_products_total', $this->format_decimal( (float) $summary['deposit_total'] ) );
		$order->update_meta_data( '_drpp_remaining_products_total', $this->format_decimal( (float) $summary['balance_total'] ) );
	}

	/**
	 * Formats decimals before storing them in order meta.
	 *
	 * @param float $amount Raw amount.
	 */
	private function format_decimal( float $amount ): string {
		if ( function_exists( 'wc_format_decimal' ) ) {
			return wc_format_decimal( $amount, wc_get_price_decimals() );
		}

		return number_format( $amount, 2, '.', '' );
	}
}
