<?php
/**
 * Shared base for customer balance collection emails.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments\Emails;

use DatRooster\PartialPayments\Orders\BalanceOrderManager;

defined( 'ABSPATH' ) || exit;

abstract class AbstractBalanceEmail extends \WC_Email {
	/**
	 * Current balance order.
	 *
	 * @var \WC_Order|null
	 */
	protected ?\WC_Order $balance_order = null;

	/**
	 * Current parent deposit order.
	 *
	 * @var \WC_Order|null
	 */
	protected ?\WC_Order $parent_order = null;

	/**
	 * Shared constructor setup for custom customer emails.
	 */
	public function __construct() {
		$this->customer_email = true;
		$this->email_group    = 'datrooster-partial-payments';
		$this->template_base  = trailingslashit( DATROOSTER_PP_PATH ) . 'templates/';
		$this->template_html  = 'emails/customer-balance-notification.php';
		$this->template_plain = 'emails/plain/customer-balance-notification.php';

		parent::__construct();

		add_action( 'woocommerce_update_options_email_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Returns the default "additional content" text shown in WooCommerce emails.
	 */
	public function get_default_additional_content(): string {
		return __( 'You can review your deposit order and payment status from your account at any time.', 'datrooster-partial-payments' );
	}

	/**
	 * Builds the admin form fields shown in WooCommerce > Settings > Emails.
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Enable/Disable', 'datrooster-partial-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'datrooster-partial-payments' ),
				'default' => 'yes',
			),
			'subject'            => array(
				'title'       => __( 'Subject', 'datrooster-partial-payments' ),
				'type'        => 'text',
				'description' => sprintf(
					/* translators: %s: list of available email placeholders. */
					__( 'Available placeholders: %s', 'datrooster-partial-payments' ),
					'{site_title}, {parent_order_number}, {balance_order_number}, {balance_total}, {balance_due_date}'
				),
				'desc_tip'    => true,
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			),
			'heading'            => array(
				'title'       => __( 'Email heading', 'datrooster-partial-payments' ),
				'type'        => 'text',
				'description' => __( 'Main heading shown at the top of the email notification.', 'datrooster-partial-payments' ),
				'desc_tip'    => true,
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			),
			'additional_content' => array(
				'title'       => __( 'Additional content', 'datrooster-partial-payments' ),
				'description' => __( 'Optional text shown below the main email content.', 'datrooster-partial-payments' ),
				'css'         => 'width:400px; height: 75px;',
				'placeholder' => __( 'N/A', 'datrooster-partial-payments' ),
				'type'        => 'textarea',
				'default'     => $this->get_default_additional_content(),
				'desc_tip'    => true,
			),
			'email_type'         => array(
				'title'       => __( 'Email type', 'datrooster-partial-payments' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'datrooster-partial-payments' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Triggers the email for a specific balance order.
	 *
	 * @param int $balance_order_id Balance order ID.
	 * @param int $parent_order_id  Parent deposit order ID.
	 */
	public function trigger( int $balance_order_id, int $parent_order_id = 0 ): bool {
		$this->setup_locale();

		$this->balance_order = wc_get_order( $balance_order_id );
		$this->parent_order  = $parent_order_id > 0 ? wc_get_order( $parent_order_id ) : null;

		if ( ! $this->balance_order instanceof \WC_Order ) {
			$this->restore_locale();
			return false;
		}

		if ( ! $this->parent_order instanceof \WC_Order ) {
			$this->parent_order = $this->resolve_parent_order( $this->balance_order );
		}

		$this->recipient    = $this->get_customer_email_address();
		$this->placeholders = $this->get_placeholders();

		if ( ! $this->is_enabled() || '' === $this->get_recipient() ) {
			$this->restore_locale();
			return false;
		}

		$sent = $this->send(
			$this->get_recipient(),
			$this->get_subject(),
			$this->get_content(),
			$this->get_headers(),
			$this->get_attachments()
		);

		$this->restore_locale();

		return $sent;
	}

	/**
	 * Returns the rendered HTML content.
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			$this->get_template_args( false ),
			'',
			$this->template_base
		);
	}

	/**
	 * Returns the rendered plain text content.
	 */
	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			$this->get_template_args( true ),
			'',
			$this->template_base
		);
	}

	/**
	 * Returns the message paragraphs shown inside the email body.
	 *
	 * @return array<int,string>
	 */
	abstract protected function get_message_lines(): array;

	/**
	 * Builds the template context shared by HTML and plain emails.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_template_args( bool $plain_text ): array {
		return array(
			'email_heading'       => $this->get_heading(),
			'message_lines'       => $this->get_message_lines(),
			'payment_button_text' => __( 'Pay balance', 'datrooster-partial-payments' ),
			'pay_url'             => $this->get_payment_url(),
			'balance_order'       => $this->balance_order,
			'parent_order'        => $this->parent_order,
			'additional_content'  => $this->get_additional_content(),
			'sent_to_admin'       => false,
			'plain_text'          => $plain_text,
			'email'               => $this,
		);
	}

	/**
	 * Returns the customer email address for the current order context.
	 */
	protected function get_customer_email_address(): string {
		$order = $this->parent_order instanceof \WC_Order ? $this->parent_order : $this->balance_order;

		return $order instanceof \WC_Order ? sanitize_email( (string) $order->get_billing_email() ) : '';
	}

	/**
	 * Returns the current balance order payment URL.
	 */
	protected function get_payment_url(): string {
		return $this->balance_order instanceof \WC_Order ? $this->balance_order->get_checkout_payment_url() : '';
	}

	/**
	 * Returns the localized due date text for the current balance order.
	 */
	protected function get_due_date_text(): string {
		if ( ! $this->balance_order instanceof \WC_Order ) {
			return '';
		}

		$raw_due_date = (string) $this->balance_order->get_meta( BalanceOrderManager::META_BALANCE_DUE_AT, true );

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
	 * Returns the formatted balance total for email copy and placeholders.
	 */
	protected function get_balance_total_text(): string {
		if ( ! $this->balance_order instanceof \WC_Order ) {
			return '';
		}

		return wp_strip_all_tags(
			wc_price(
				(float) $this->balance_order->get_total(),
				array(
					'currency' => $this->balance_order->get_currency(),
				)
			)
		);
	}

	/**
	 * Returns the current parent order number.
	 */
	protected function get_parent_order_number(): string {
		return $this->parent_order instanceof \WC_Order ? $this->parent_order->get_order_number() : '';
	}

	/**
	 * Returns the current balance order number.
	 */
	protected function get_balance_order_number(): string {
		return $this->balance_order instanceof \WC_Order ? $this->balance_order->get_order_number() : '';
	}

	/**
	 * Resolves placeholder values for the current email context.
	 *
	 * @return array<string,string>
	 */
	protected function get_placeholders(): array {
		return array(
			'{site_title}'           => $this->get_blogname(),
			'{parent_order_number}'  => $this->get_parent_order_number(),
			'{balance_order_number}' => $this->get_balance_order_number(),
			'{balance_total}'        => $this->get_balance_total_text(),
			'{balance_due_date}'     => $this->get_due_date_text(),
		);
	}

	/**
	 * Resolves the parent deposit order from a balance order meta reference.
	 *
	 * @param \WC_Order $balance_order Balance order object.
	 */
	private function resolve_parent_order( \WC_Order $balance_order ): ?\WC_Order {
		$parent_order_id = absint( $balance_order->get_meta( BalanceOrderManager::META_PARENT_DEPOSIT_ORDER, true ) );

		if ( $parent_order_id <= 0 ) {
			return null;
		}

		$parent_order = wc_get_order( $parent_order_id );

		return $parent_order instanceof \WC_Order ? $parent_order : null;
	}
}
