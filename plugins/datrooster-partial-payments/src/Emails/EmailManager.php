<?php
/**
 * Registers and dispatches WooCommerce email notifications.
 *
 * @package DatRoosterPartialPayments
 */

namespace DatRoosterPartialPayments\Emails;

defined( 'ABSPATH' ) || exit;

final class EmailManager {
	/**
	 * Registers WooCommerce email hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_email_classes', array( $this, 'register_email_classes' ) );
		add_filter( 'woocommerce_email_groups', array( $this, 'register_email_group' ) );
	}

	/**
	 * Registers the plugin-specific email group in WooCommerce settings.
	 *
	 * @param array<string,string> $groups Existing email groups.
	 * @return array<string,string>
	 */
	public function register_email_group( array $groups ): array {
		$groups['datrooster-partial-payments'] = __( 'Partial Payments', 'datrooster-partial-payments' );

		return $groups;
	}

	/**
	 * Registers custom email classes with WooCommerce.
	 *
	 * @param array<string,\WC_Email> $email_classes Existing email classes.
	 * @return array<string,\WC_Email>
	 */
	public function register_email_classes( array $email_classes ): array {
		$email_classes[ CustomerBalancePaymentEmail::class ]  = new CustomerBalancePaymentEmail();
		$email_classes[ CustomerBalanceReminderEmail::class ] = new CustomerBalanceReminderEmail();

		return $email_classes;
	}

	/**
	 * Sends the initial balance payment request email.
	 *
	 * @param int $balance_order_id Balance order ID.
	 * @param int $parent_order_id  Parent deposit order ID.
	 */
	public function send_balance_created_email( int $balance_order_id, int $parent_order_id ): bool {
		$email = $this->get_email_instance( CustomerBalancePaymentEmail::class );

		return $email instanceof CustomerBalancePaymentEmail
			? $email->trigger( $balance_order_id, $parent_order_id )
			: false;
	}

	/**
	 * Sends the balance reminder email.
	 *
	 * @param int $balance_order_id Balance order ID.
	 * @param int $parent_order_id  Parent deposit order ID.
	 */
	public function send_balance_reminder_email( int $balance_order_id, int $parent_order_id ): bool {
		$email = $this->get_email_instance( CustomerBalanceReminderEmail::class );

		return $email instanceof CustomerBalanceReminderEmail
			? $email->trigger( $balance_order_id, $parent_order_id )
			: false;
	}

	/**
	 * Resolves a WooCommerce email instance by its registration key.
	 *
	 * @param string $email_key Email array key.
	 */
	private function get_email_instance( string $email_key ): ?\WC_Email {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return null;
		}

		$emails = WC()->mailer()->get_emails();

		return isset( $emails[ $email_key ] ) && $emails[ $email_key ] instanceof \WC_Email
			? $emails[ $email_key ]
			: null;
	}
}
