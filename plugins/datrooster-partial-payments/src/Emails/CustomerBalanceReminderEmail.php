<?php
/**
 * Customer reminder email for outstanding balance orders.
 *
 * @package DatRoosterPartialPayments
 */

namespace DatRoosterPartialPayments\Emails;

defined( 'ABSPATH' ) || exit;

final class CustomerBalanceReminderEmail extends AbstractBalanceEmail {
	/**
	 * Configures the email metadata.
	 */
	public function __construct() {
		$this->id          = 'datrooster_partial_payments_customer_balance_reminder';
		$this->title       = __( 'Balance payment reminder', 'datrooster-partial-payments' );
		$this->description = __( 'Sent to customers when a remaining balance is still unpaid and a reminder should be issued.', 'datrooster-partial-payments' );

		parent::__construct();
	}

	/**
	 * Returns the default email subject.
	 */
	public function get_default_subject(): string {
		return __( '[{site_title}] Reminder: remaining balance for order #{parent_order_number}', 'datrooster-partial-payments' );
	}

	/**
	 * Returns the default email heading.
	 */
	public function get_default_heading(): string {
		return __( 'Remaining balance reminder', 'datrooster-partial-payments' );
	}

	/**
	 * Returns the email body paragraphs.
	 *
	 * @return array<int,string>
	 */
	protected function get_message_lines(): array {
		$lines   = array(
			__( 'This is a reminder that your remaining balance is still awaiting payment.', 'datrooster-partial-payments' ),
			sprintf(
				/* translators: 1: parent order number, 2: formatted balance total. */
				__( 'The remaining balance for order #%1$s is %2$s.', 'datrooster-partial-payments' ),
				$this->get_parent_order_number(),
				$this->get_balance_total_text()
			),
		);
		$due_date = $this->get_due_date_text();

		if ( '' !== $due_date ) {
			$lines[] = sprintf(
				/* translators: %s: localized due date. */
				__( 'Please complete this payment by %s.', 'datrooster-partial-payments' ),
				$due_date
			);
		}

		$lines[] = __( 'Use the secure payment link below to settle the remaining balance.', 'datrooster-partial-payments' );

		return $lines;
	}
}
