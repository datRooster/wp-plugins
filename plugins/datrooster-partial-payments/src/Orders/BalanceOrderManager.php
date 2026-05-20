<?php
/**
 * Creates and manages balance payment orders for deposit purchases.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments\Orders;

use DatRooster\PartialPayments\Deposits\SettingsResolver;

defined( 'ABSPATH' ) || exit;

final class BalanceOrderManager {
	public const BALANCE_ORDER_CREATED_VIA  = 'datrooster-partial-payments-balance';
	public const META_IS_BALANCE_ORDER       = '_drpp_is_balance_order';
	public const META_PARENT_DEPOSIT_ORDER   = '_drpp_parent_deposit_order_id';
	public const META_BALANCE_ORDER_ID       = '_drpp_balance_order_id';
	public const META_BALANCE_PAID           = '_drpp_balance_paid';
	public const META_BALANCE_PAID_AT        = '_drpp_balance_paid_at';
	public const META_BALANCE_CREATED_AT     = '_drpp_balance_created_at';
	public const META_BALANCE_DUE_AT               = '_drpp_balance_due_at';
	public const META_BALANCE_REMINDER_SENT_AT     = '_drpp_balance_reminder_sent_at';
	public const META_BALANCE_REMINDER_SCHEDULED_AT = '_drpp_balance_reminder_scheduled_at';

	/**
	 * Shared settings resolver.
	 *
	 * @var SettingsResolver
	 */
	private SettingsResolver $settings_resolver;

	/**
	 * Class constructor.
	 *
	 * @param SettingsResolver $settings_resolver Shared settings resolver.
	 */
	public function __construct( SettingsResolver $settings_resolver ) {
		$this->settings_resolver = $settings_resolver;
	}

	/**
	 * Registers balance management hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_payment_complete', array( $this, 'handle_paid_order' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'handle_paid_order' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'handle_paid_order' ) );
		add_filter( 'woocommerce_my_account_my_orders_query', array( $this, 'filter_account_orders_query' ) );
		add_filter( 'woocommerce_order_query', array( $this, 'filter_account_order_results' ), 10, 2 );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'filter_account_order_actions' ), 10, 2 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_balance_section' ), 20 );
	}

	/**
	 * Reacts when an order has been paid or marked as paid.
	 *
	 * @param int $order_id Order ID.
	 */
	public function handle_paid_order( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( $this->is_balance_order( $order ) ) {
			$this->maybe_mark_parent_fully_paid( $order );
			return;
		}

		if ( ! $this->is_deposit_order( $order ) ) {
			return;
		}

		$this->maybe_create_balance_order( $order );
	}

	/**
	 * Excludes technical balance orders from the My Account orders list.
	 *
	 * @param array<string,mixed> $args Existing order query args.
	 * @return array<string,mixed>
	 */
	public function filter_account_orders_query( array $args ): array {
		$args['drpp_exclude_balance_orders'] = true;

		return $args;
	}

	/**
	 * Removes technical balance orders from the My Account query results.
	 *
	 * WooCommerce resolves this filter after the order query has run, which lets
	 * us keep the account list clean without introducing a custom meta query in
	 * the storefront order lookup itself.
	 *
	 * @param array<int,\WC_Order|int>|object $results Order query results.
	 * @param array<string,mixed>             $args    Query arguments.
	 * @return array<int,\WC_Order|int>|object
	 */
	public function filter_account_order_results( array|object $results, array $args ): array|object {
		if ( empty( $args['drpp_exclude_balance_orders'] ) ) {
			return $results;
		}

		$excluded_ids = $this->get_customer_balance_order_ids( $args );

		if ( array() === $excluded_ids ) {
			return $results;
		}

		if ( is_object( $results ) && isset( $results->orders ) && is_array( $results->orders ) ) {
			$results->orders = $this->exclude_order_ids_from_collection( $results->orders, $excluded_ids );

			if ( isset( $results->total, $args['limit'] ) ) {
				$results->total = max( 0, (int) $results->total - count( $excluded_ids ) );
				$page_size      = max( 1, (int) $args['limit'] );
				$results->max_num_pages = (int) ceil( $results->total / $page_size );
			}

			return $results;
		}

		if ( is_array( $results ) ) {
			return $this->exclude_order_ids_from_collection( $results, $excluded_ids );
		}

		return $results;
	}

	/**
	 * Adds a balance payment action to eligible deposit orders in My Account.
	 *
	 * @param array<string,array<string,string>> $actions Existing actions.
	 * @param \WC_Order                          $order   Current order object.
	 * @return array<string,array<string,string>>
	 */
	public function filter_account_order_actions( array $actions, \WC_Order $order ): array {
		if ( $this->is_balance_order( $order ) || ! $this->is_deposit_order( $order ) ) {
			return $actions;
		}

		$balance_order = $this->get_balance_order( $order );

		if ( ! $balance_order instanceof \WC_Order || ! $balance_order->needs_payment() ) {
			return $actions;
		}

		$actions['drpp-pay-balance'] = array(
			'url'        => $balance_order->get_checkout_payment_url(),
			'name'       => __( 'Pay balance', 'datrooster-partial-payments' ),
			'aria-label' => sprintf(
				/* translators: %s: parent order number. */
				__( 'Pay remaining balance for order %s', 'datrooster-partial-payments' ),
				$order->get_order_number()
			),
		);

		return $actions;
	}

	/**
	 * Renders balance information on the parent order view.
	 *
	 * @param \WC_Order $order Current order object.
	 */
	public function render_balance_section( \WC_Order $order ): void {
		if ( function_exists( 'is_account_page' ) && ! is_account_page() ) {
			return;
		}

		if ( $this->is_balance_order( $order ) || ! $this->is_deposit_order( $order ) ) {
			return;
		}

		$balance_order = $this->get_balance_order( $order );

		if ( ! $balance_order instanceof \WC_Order ) {
			return;
		}

		$status_name = wc_get_order_status_name( $balance_order->get_status() );
		$due_date    = $this->get_balance_due_date_text( $balance_order );
		$total_text  = wp_strip_all_tags(
			wc_price(
				(float) $balance_order->get_total(),
				array( 'currency' => $balance_order->get_currency() )
			)
		);
		?>
		<section class="woocommerce-order-details drpp-balance-details">
			<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Remaining balance', 'datrooster-partial-payments' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: formatted total, 2: balance order status. */
					esc_html__( 'Balance order total: %1$s. Current status: %2$s.', 'datrooster-partial-payments' ),
					esc_html( $total_text ),
					esc_html( $status_name )
				);
				?>
			</p>
			<?php if ( '' !== $due_date ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: localized due date. */
						esc_html__( 'Payment due date: %s.', 'datrooster-partial-payments' ),
						esc_html( $due_date )
					);
					?>
				</p>
			<?php endif; ?>
			<?php if ( $balance_order->needs_payment() ) : ?>
				<p>
					<a class="button" href="<?php echo esc_url( $balance_order->get_checkout_payment_url() ); ?>">
						<?php esc_html_e( 'Pay balance', 'datrooster-partial-payments' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Creates the linked balance order if it does not exist yet.
	 *
	 * @param \WC_Order $order Parent deposit order.
	 */
	private function maybe_create_balance_order( \WC_Order $order ): void {
		if ( 'yes' === $order->get_meta( self::META_BALANCE_PAID, true ) ) {
			return;
		}

		if ( $this->get_balance_order( $order ) instanceof \WC_Order ) {
			$this->maybe_mark_parent_partially_paid( $order );
			return;
		}

		$expected_total = $this->get_expected_balance_total( $order );

		if ( $expected_total <= 0 ) {
			return;
		}

		$balance_order = wc_create_order(
			array(
				'customer_id' => $order->get_customer_id(),
				'parent'      => $order->get_id(),
				'status'      => 'pending',
			)
		);

		if ( is_wp_error( $balance_order ) || ! $balance_order instanceof \WC_Order ) {
			return;
		}

		$this->copy_order_context( $order, $balance_order );
		$this->copy_balance_product_items( $order, $balance_order );
		$this->copy_balance_shipping_items( $order, $balance_order );

		$balance_order->update_meta_data( self::META_IS_BALANCE_ORDER, 'yes' );
		$balance_order->update_meta_data( self::META_PARENT_DEPOSIT_ORDER, $order->get_id() );
		$balance_order->update_meta_data( self::META_BALANCE_CREATED_AT, gmdate( 'c' ) );
		$balance_order->calculate_totals( false );
		$balance_order->save();

		$order->update_meta_data( self::META_BALANCE_ORDER_ID, $balance_order->get_id() );
		$order->save();

		$order->add_order_note(
			sprintf(
				/* translators: 1: balance order number, 2: formatted total. */
				__( 'Balance order %1$s has been created for the remaining amount of %2$s.', 'datrooster-partial-payments' ),
				$balance_order->get_order_number(),
				wp_strip_all_tags( wc_price( (float) $balance_order->get_total(), array( 'currency' => $balance_order->get_currency() ) ) )
			)
		);

		$balance_order->add_order_note(
			sprintf(
				/* translators: %s: parent order number. */
				__( 'This order collects the remaining balance for deposit order %s.', 'datrooster-partial-payments' ),
				$order->get_order_number()
			)
		);

		$this->maybe_mark_parent_partially_paid( $order );

		/**
		 * Fires after a linked balance order has been created for a deposit purchase.
		 *
		 * @param int $balance_order_id Newly created balance order ID.
		 * @param int $parent_order_id  Parent deposit order ID.
		 */
		do_action( 'drpp_balance_order_created', $balance_order->get_id(), $order->get_id() );
	}

	/**
	 * Marks the parent deposit order as fully paid after the balance order is settled.
	 *
	 * @param \WC_Order $balance_order Paid balance order.
	 */
	private function maybe_mark_parent_fully_paid( \WC_Order $balance_order ): void {
		$parent_order = $this->get_parent_deposit_order( $balance_order );

		if ( ! $parent_order instanceof \WC_Order ) {
			return;
		}

		if ( 'yes' === $parent_order->get_meta( self::META_BALANCE_PAID, true ) ) {
			return;
		}

		$parent_order->update_meta_data( self::META_BALANCE_PAID, 'yes' );
		$parent_order->update_meta_data( self::META_BALANCE_PAID_AT, gmdate( 'c' ) );
		$parent_order->save();

		$fully_paid_status = $this->get_fully_paid_status();

		$parent_order->add_order_note(
			sprintf(
				/* translators: 1: balance order number, 2: formatted total. */
				__( 'The remaining balance was paid via order %1$s for %2$s.', 'datrooster-partial-payments' ),
				$balance_order->get_order_number(),
				wp_strip_all_tags( wc_price( (float) $balance_order->get_total(), array( 'currency' => $balance_order->get_currency() ) ) )
			)
		);

		if ( ! $parent_order->has_status( $fully_paid_status ) ) {
			$parent_order->update_status(
				$fully_paid_status,
				__( 'The linked balance order has been paid in full.', 'datrooster-partial-payments' ),
				true
			);
		}

		$balance_order->add_order_note(
			sprintf(
				/* translators: %s: parent order number. */
				__( 'The parent deposit order %s has been marked as fully paid.', 'datrooster-partial-payments' ),
				$parent_order->get_order_number()
			)
		);
	}

	/**
	 * Marks the parent order as partially paid once the balance order exists.
	 *
	 * @param \WC_Order $order Parent deposit order.
	 */
	private function maybe_mark_parent_partially_paid( \WC_Order $order ): void {
		if ( 'yes' === $order->get_meta( self::META_BALANCE_PAID, true ) ) {
			return;
		}

		if ( $order->has_status( PartiallyPaidStatus::STATUS_UNPREFIXED ) ) {
			return;
		}

		if ( $this->get_expected_balance_total( $order ) <= 0 ) {
			return;
		}

		$order->update_status(
			PartiallyPaidStatus::STATUS_UNPREFIXED,
			__( 'The initial deposit has been paid and the remaining balance is now awaiting payment.', 'datrooster-partial-payments' ),
			true
		);
	}

	/**
	 * Copies customer and store context from the deposit order to the balance order.
	 *
	 * @param \WC_Order $source_order  Parent deposit order.
	 * @param \WC_Order $balance_order Balance order instance.
	 */
	private function copy_order_context( \WC_Order $source_order, \WC_Order $balance_order ): void {
		$balance_order->set_created_via( self::BALANCE_ORDER_CREATED_VIA );
		$balance_order->set_parent_id( $source_order->get_id() );
		$balance_order->set_currency( $source_order->get_currency() );
		$balance_order->set_prices_include_tax( $source_order->get_prices_include_tax() );
		$balance_order->set_customer_id( $source_order->get_customer_id() );
		$balance_order->set_address( $source_order->get_address( 'billing' ), 'billing' );
		$balance_order->set_address( $source_order->get_address( 'shipping' ), 'shipping' );
	}

	/**
	 * Copies outstanding product items to the balance order.
	 *
	 * @param \WC_Order $source_order  Parent deposit order.
	 * @param \WC_Order $balance_order Balance order instance.
	 */
	private function copy_balance_product_items( \WC_Order $source_order, \WC_Order $balance_order ): void {
		foreach ( $source_order->get_items( 'line_item' ) as $item_id => $item ) {
			$remaining_total = (float) $item->get_meta( '_drpp_remaining_products_total', true );

			if ( $remaining_total <= 0 ) {
				continue;
			}

			$remaining_tax  = (float) $item->get_meta( '_drpp_remaining_products_tax', true );
			$source_total   = (float) $item->get_total();
			$source_tax     = (float) $item->get_total_tax();
			$item_taxes     = $item->get_taxes();
			$tax_ratio      = $source_tax > 0 ? ( $remaining_tax / $source_tax ) : 0.0;
			$scaled_taxes   = $this->scale_item_tax_data( is_array( $item_taxes ) ? $item_taxes : array(), $tax_ratio, $remaining_tax );
			$product_item   = new \WC_Order_Item_Product();

			$product_item->set_name( $item->get_name() );
			$product_item->set_product_id( $item->get_product_id() );
			$product_item->set_variation_id( $item->get_variation_id() );
			$product_item->set_quantity( $item->get_quantity() );
			$product_item->set_tax_class( $item->get_tax_class() );
			$product_item->set_subtotal( $this->format_decimal( $remaining_total ) );
			$product_item->set_total( $this->format_decimal( $remaining_total ) );
			$product_item->set_subtotal_tax( $this->format_decimal( $remaining_tax ) );
			$product_item->set_total_tax( $this->format_decimal( $remaining_tax ) );

			if ( ! empty( $scaled_taxes['total'] ) || ! empty( $scaled_taxes['subtotal'] ) ) {
				$product_item->set_taxes( $scaled_taxes );
			}

			$product_item->add_meta_data( '_drpp_balance_parent_line_item_id', $item_id, true );
			$balance_order->add_item( $product_item );
		}
	}

	/**
	 * Copies outstanding shipping rows to the balance order.
	 *
	 * @param \WC_Order $source_order  Parent deposit order.
	 * @param \WC_Order $balance_order Balance order instance.
	 */
	private function copy_balance_shipping_items( \WC_Order $source_order, \WC_Order $balance_order ): void {
		$remaining_shipping_total = (float) $source_order->get_meta( '_drpp_remaining_shipping_total', true );
		$remaining_shipping_tax   = (float) $source_order->get_meta( '_drpp_remaining_shipping_tax', true );
		$current_shipping_total   = (float) $source_order->get_shipping_total();
		$current_shipping_tax     = (float) $source_order->get_shipping_tax();

		if ( $remaining_shipping_total <= 0 || $current_shipping_total <= 0 ) {
			return;
		}

		$ratio                 = $remaining_shipping_total / $current_shipping_total;
		$shipping_tax_ratio    = $current_shipping_tax > 0 ? ( $remaining_shipping_tax / $current_shipping_tax ) : 0.0;

		foreach ( $source_order->get_items( 'shipping' ) as $item_id => $item ) {
			$line_total = (float) $item->get_total();

			if ( $line_total <= 0 ) {
				continue;
			}

			$line_tax       = (float) $item->get_total_tax();
			$shipping_taxes = $item->get_taxes();
			$tax_amounts    = isset( $shipping_taxes['total'] ) && is_array( $shipping_taxes['total'] ) ? $shipping_taxes['total'] : array();
			$shipping_item  = new \WC_Order_Item_Shipping();

			$shipping_item->set_method_title( $item->get_method_title() );
			$shipping_item->set_method_id( $item->get_method_id() );
			$shipping_item->set_total( $this->format_decimal( $line_total * $ratio ) );

			if ( method_exists( $shipping_item, 'set_instance_id' ) && method_exists( $item, 'get_instance_id' ) ) {
				$shipping_item->set_instance_id( (string) $item->get_instance_id() );
			}

			if ( $line_tax > 0 ) {
				$shipping_item->set_taxes(
					array(
						'total' => $this->scale_tax_amounts( $tax_amounts, $shipping_tax_ratio, $line_tax * $shipping_tax_ratio ),
					)
				);
			}

			$shipping_item->add_meta_data( '_drpp_balance_parent_shipping_item_id', $item_id, true );
			$balance_order->add_item( $shipping_item );
		}
	}

	/**
	 * Returns the linked balance order for a deposit parent order.
	 *
	 * @param \WC_Order $order Parent deposit order.
	 */
	private function get_balance_order( \WC_Order $order ): ?\WC_Order {
		$balance_order_id = absint( $order->get_meta( self::META_BALANCE_ORDER_ID, true ) );

		if ( $balance_order_id <= 0 ) {
			return null;
		}

		$balance_order = wc_get_order( $balance_order_id );

		return $balance_order instanceof \WC_Order ? $balance_order : null;
	}

	/**
	 * Returns balance order IDs belonging to the same customer scope as the query.
	 *
	 * @param array<string,mixed> $args Account orders query arguments.
	 * @return array<int,int>
	 */
	private function get_customer_balance_order_ids( array $args ): array {
		if ( empty( $args['customer'] ) && empty( $args['customer_id'] ) ) {
			return array();
		}

		$query_args = $args;
		unset( $query_args['drpp_exclude_balance_orders'], $query_args['page'], $query_args['offset'] );

		$query_args['limit']       = -1;
		$query_args['paginate']    = false;
		$query_args['return']      = 'ids';
		$query_args['created_via'] = self::BALANCE_ORDER_CREATED_VIA;

		$balance_order_ids = wc_get_orders( $query_args );

		if ( ! is_array( $balance_order_ids ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'absint', $balance_order_ids )
			)
		);
	}

	/**
	 * Removes specific order IDs from an order collection.
	 *
	 * @param array<int,\WC_Order|int> $orders       Order objects or order IDs.
	 * @param array<int,int>           $excluded_ids Order IDs to exclude.
	 * @return array<int,\WC_Order|int>
	 */
	private function exclude_order_ids_from_collection( array $orders, array $excluded_ids ): array {
		$excluded_lookup = array_fill_keys( $excluded_ids, true );

		return array_values(
			array_filter(
				$orders,
				static function ( $order ) use ( $excluded_lookup ): bool {
					$order_id = $order instanceof \WC_Order ? $order->get_id() : absint( $order );

					return $order_id > 0 && ! isset( $excluded_lookup[ $order_id ] );
				}
			)
		);
	}

	/**
	 * Returns the parent deposit order for a balance order.
	 *
	 * @param \WC_Order $balance_order Balance order object.
	 */
	private function get_parent_deposit_order( \WC_Order $balance_order ): ?\WC_Order {
		$parent_order_id = absint( $balance_order->get_meta( self::META_PARENT_DEPOSIT_ORDER, true ) );

		if ( $parent_order_id <= 0 ) {
			return null;
		}

		$parent_order = wc_get_order( $parent_order_id );

		return $parent_order instanceof \WC_Order ? $parent_order : null;
	}

	/**
	 * Returns whether the given order is the original deposit order.
	 *
	 * @param \WC_Order $order Order object.
	 */
	private function is_deposit_order( \WC_Order $order ): bool {
		return 'yes' === $order->get_meta( '_drpp_has_deposit', true ) && ! $this->is_balance_order( $order );
	}

	/**
	 * Returns whether the order is a technical balance order.
	 *
	 * @param \WC_Order $order Order object.
	 */
	private function is_balance_order( \WC_Order $order ): bool {
		return 'yes' === $order->get_meta( self::META_IS_BALANCE_ORDER, true );
	}

	/**
	 * Returns the expected outstanding total stored on the deposit order.
	 *
	 * @param \WC_Order $order Parent deposit order.
	 */
	private function get_expected_balance_total( \WC_Order $order ): float {
		return (float) $order->get_meta( '_drpp_estimated_remaining_total', true );
	}

	/**
	 * Returns the localized due date text stored on a balance order.
	 *
	 * @param \WC_Order $balance_order Balance order object.
	 */
	private function get_balance_due_date_text( \WC_Order $balance_order ): string {
		$raw_due_date = (string) $balance_order->get_meta( self::META_BALANCE_DUE_AT, true );

		if ( '' === $raw_due_date ) {
			return '';
		}

		$timestamp = strtotime( $raw_due_date );

		if ( false === $timestamp ) {
			return '';
		}

		return wp_date( get_option( 'date_format' ), $timestamp, wp_timezone() );
	}

	/**
	 * Returns the fully paid target status configured in the plugin settings.
	 */
	private function get_fully_paid_status(): string {
		$settings = $this->settings_resolver->get_global_settings();
		$status   = isset( $settings['fully_paid_status'] ) ? (string) $settings['fully_paid_status'] : 'wc-completed';

		return $this->normalize_status_key( $status );
	}

	/**
	 * Normalizes a WooCommerce status key for runtime methods like update_status.
	 *
	 * @param string $status Raw status key.
	 */
	private function normalize_status_key( string $status ): string {
		return 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
	}

	/**
	 * Scales product tax data to a target remaining tax value.
	 *
	 * @param array<string,mixed> $taxes      Original tax data.
	 * @param float               $ratio      Scaling ratio.
	 * @param float               $total_tax  Target total tax.
	 * @return array<string,array<int|string,float>>
	 */
	private function scale_item_tax_data( array $taxes, float $ratio, float $total_tax ): array {
		$taxes = array(
			'subtotal' => isset( $taxes['subtotal'] ) && is_array( $taxes['subtotal'] ) ? $taxes['subtotal'] : array(),
			'total'    => isset( $taxes['total'] ) && is_array( $taxes['total'] ) ? $taxes['total'] : array(),
		);

		return array(
			'subtotal' => $this->scale_tax_amounts( $taxes['subtotal'], $ratio, $total_tax ),
			'total'    => $this->scale_tax_amounts( $taxes['total'], $ratio, $total_tax ),
		);
	}

	/**
	 * Scales keyed tax amounts while preserving the expected rounded total.
	 *
	 * @param array<int|string,mixed> $amounts       Source tax amounts.
	 * @param float                   $ratio         Scaling ratio.
	 * @param float                   $target_total  Target total tax.
	 * @return array<int|string,float>
	 */
	private function scale_tax_amounts( array $amounts, float $ratio, float $target_total ): array {
		$scaled = array();

		if ( empty( $amounts ) || $ratio <= 0 || $target_total <= 0 ) {
			return $scaled;
		}

		foreach ( $amounts as $tax_rate_id => $tax_amount ) {
			$scaled[ $tax_rate_id ] = (float) $this->format_decimal( (float) $tax_amount * $ratio );
		}

		$scaled_keys = array_keys( $scaled );
		$last_key    = end( $scaled_keys );
		$difference  = (float) $this->format_decimal( $target_total - array_sum( $scaled ) );

		if ( false !== $last_key && abs( $difference ) > 0 ) {
			$scaled[ $last_key ] = (float) $this->format_decimal( $scaled[ $last_key ] + $difference );
		}

		return $scaled;
	}

	/**
	 * Formats amounts before writing them into WooCommerce order items.
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
