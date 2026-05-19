<?php
/**
 * Sends and schedules customer-facing balance notifications.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments\Emails;

use DatRooster\PartialPayments\Deposits\SettingsResolver;
use DatRooster\PartialPayments\Orders\BalanceOrderManager;

defined( 'ABSPATH' ) || exit;

final class BalanceNotifications {
	public const REMINDER_ACTION_HOOK = 'drpp_send_balance_payment_reminder';
	public const SCHEDULER_GROUP      = 'datrooster-partial-payments';

	/**
	 * Shared settings resolver.
	 *
	 * @var SettingsResolver
	 */
	private SettingsResolver $settings_resolver;

	/**
	 * Shared email dispatcher.
	 *
	 * @var EmailManager
	 */
	private EmailManager $email_manager;

	/**
	 * Sets up shared notification services.
	 *
	 * @param SettingsResolver $settings_resolver Shared settings resolver.
	 * @param EmailManager     $email_manager     Shared email dispatcher.
	 */
	public function __construct( SettingsResolver $settings_resolver, EmailManager $email_manager ) {
		$this->settings_resolver = $settings_resolver;
		$this->email_manager     = $email_manager;
	}

	/**
	 * Registers notification hooks.
	 */
	public function register(): void {
		add_action( 'drpp_balance_order_created', array( $this, 'handle_balance_order_created' ), 10, 2 );
		add_action( self::REMINDER_ACTION_HOOK, array( $this, 'handle_scheduled_reminder' ), 10, 1 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_clear_scheduled_reminder' ), 10, 4 );
	}

	/**
	 * Sends the initial balance payment email and schedules the reminder.
	 *
	 * @param int $balance_order_id Balance order ID.
	 * @param int $parent_order_id  Parent deposit order ID.
	 */
	public function handle_balance_order_created( int $balance_order_id, int $parent_order_id ): void {
		$balance_order = wc_get_order( $balance_order_id );

		if ( ! $balance_order instanceof \WC_Order || ! $this->is_balance_order( $balance_order ) ) {
			return;
		}

		$this->assign_balance_due_date( $balance_order );
		$this->schedule_reminder( $balance_order );

		if ( $this->email_manager->send_balance_created_email( $balance_order_id, $parent_order_id ) ) {
			$balance_order->add_order_note(
				__( 'The customer received the initial remaining balance payment email.', 'datrooster-partial-payments' )
			);
		}
	}

	/**
	 * Sends the scheduled reminder if the balance is still unpaid.
	 *
	 * @param int $balance_order_id Balance order ID.
	 */
	public function handle_scheduled_reminder( int $balance_order_id ): void {
		$balance_order = wc_get_order( $balance_order_id );

		if ( ! $balance_order instanceof \WC_Order || ! $this->is_balance_order( $balance_order ) ) {
			return;
		}

		if ( ! $balance_order->needs_payment() ) {
			$this->clear_scheduled_reminder( $balance_order_id );
			return;
		}

		$parent_order_id = absint( $balance_order->get_meta( BalanceOrderManager::META_PARENT_DEPOSIT_ORDER, true ) );
		$sent            = $this->email_manager->send_balance_reminder_email( $balance_order_id, $parent_order_id );

		if ( ! $sent ) {
			return;
		}

		$balance_order->update_meta_data( BalanceOrderManager::META_BALANCE_REMINDER_SENT_AT, gmdate( 'c' ) );
		$balance_order->save();
		$balance_order->add_order_note(
			__( 'A remaining balance reminder email was sent to the customer.', 'datrooster-partial-payments' )
		);
	}

	/**
	 * Clears pending reminders once a balance order no longer needs payment.
	 *
	 * @param int       $order_id Order ID.
	 * @param string    $from     Previous status.
	 * @param string    $to       New status.
	 * @param \WC_Order $order    Order object.
	 */
	public function maybe_clear_scheduled_reminder( int $order_id, string $from, string $to, \WC_Order $order ): void {
		unset( $from, $to );

		if ( ! $this->is_balance_order( $order ) || $order->needs_payment() ) {
			return;
		}

		$this->clear_scheduled_reminder( $order_id );
	}

	/**
	 * Stores the balance due date metadata derived from global settings.
	 *
	 * @param \WC_Order $balance_order Balance order object.
	 */
	private function assign_balance_due_date( \WC_Order $balance_order ): void {
		$settings      = $this->settings_resolver->get_global_settings();
		$due_days      = max( 1, (int) $settings['balance_due_days'] );
		$created_at    = $balance_order->get_date_created();
		$base_time     = $created_at ? $created_at->getTimestamp() : time();
		$due_timestamp = $base_time + ( $due_days * DAY_IN_SECONDS );

		$balance_order->update_meta_data( BalanceOrderManager::META_BALANCE_DUE_AT, gmdate( 'c', $due_timestamp ) );
		$balance_order->save();
	}

	/**
	 * Schedules a single reminder action if the store configuration allows it.
	 *
	 * @param \WC_Order $balance_order Balance order object.
	 */
	private function schedule_reminder( \WC_Order $balance_order ): void {
		$settings = $this->settings_resolver->get_global_settings();

		if ( empty( $settings['balance_reminder_enabled'] ) ) {
			return;
		}

		$due_timestamp = $this->get_due_timestamp( $balance_order );

		if ( $due_timestamp <= 0 ) {
			return;
		}

		$reminder_days = max( 0, (int) $settings['balance_reminder_days_before_due'] );
		$run_at        = max( time() + MINUTE_IN_SECONDS, $due_timestamp - ( $reminder_days * DAY_IN_SECONDS ) );
		$args          = array( $balance_order->get_id() );

		$this->clear_scheduled_reminder( $balance_order->get_id() );

		if ( function_exists( 'as_schedule_single_action' ) && did_action( 'action_scheduler_init' ) ) {
			as_schedule_single_action( $run_at, self::REMINDER_ACTION_HOOK, $args, self::SCHEDULER_GROUP, true );
		} else {
			wp_schedule_single_event( $run_at, self::REMINDER_ACTION_HOOK, $args );
		}

		$balance_order->update_meta_data( BalanceOrderManager::META_BALANCE_REMINDER_SCHEDULED_AT, gmdate( 'c', $run_at ) );
		$balance_order->save();
	}

	/**
	 * Clears pending reminder actions for a given balance order.
	 *
	 * @param int $balance_order_id Balance order ID.
	 */
	private function clear_scheduled_reminder( int $balance_order_id ): void {
		$args = array( $balance_order_id );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::REMINDER_ACTION_HOOK, $args, self::SCHEDULER_GROUP );
		}

		$next_timestamp = wp_next_scheduled( self::REMINDER_ACTION_HOOK, $args );

		while ( false !== $next_timestamp ) {
			wp_unschedule_event( $next_timestamp, self::REMINDER_ACTION_HOOK, $args );
			$next_timestamp = wp_next_scheduled( self::REMINDER_ACTION_HOOK, $args );
		}
	}

	/**
	 * Returns the stored due timestamp for a balance order.
	 *
	 * @param \WC_Order $balance_order Balance order object.
	 */
	private function get_due_timestamp( \WC_Order $balance_order ): int {
		$raw_due_date = (string) $balance_order->get_meta( BalanceOrderManager::META_BALANCE_DUE_AT, true );
		$timestamp    = '' !== $raw_due_date ? strtotime( $raw_due_date ) : false;

		return false !== $timestamp ? (int) $timestamp : 0;
	}

	/**
	 * Returns whether the order is one of this plugin's technical balance orders.
	 *
	 * @param \WC_Order $order Order object.
	 */
	private function is_balance_order( \WC_Order $order ): bool {
		return 'yes' === $order->get_meta( BalanceOrderManager::META_IS_BALANCE_ORDER, true );
	}
}
