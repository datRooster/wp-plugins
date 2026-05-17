<?php
/**
 * Customer email sent when a linked balance order is created.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments\Emails;

defined( 'ABSPATH' ) || exit;

final class CustomerBalancePaymentEmail extends AbstractBalanceEmail {
	/**
	 * Configures the email metadata.
	 */
	public function __construct() {
		$this->id          = 'drpp_customer_balance_payment';
		$this->title       = __( 'Balance payment request', 'datrooster-partial-payments' );
		$this->description = __( 'Sent to customers when a remaining balance order is created after the initial deposit is paid.', 'datrooster-partial-payments' );

		parent::__construct();
	}

	/**
	 * Returns the default email subject.
	 */
	public function get_default_subject(): string {
		return __( '[{site_title}] Pay the remaining balance for order #{parent_order_number}', 'datrooster-partial-payments' );
	}

	/**
	 * Returns the default email heading.
	 */
	public function get_default_heading(): string {
		return __( 'Complete your remaining payment', 'datrooster-partial-payments' );
	}

	/**
	 * Returns the email body paragraphs.
	 *
	 * @return array<int,string>
	 */
	protected function get_message_lines(): array {
		$lines   = array(
			__( 'A balance payment order has been created for your deposit purchase.', 'datrooster-partial-payments' ),
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
