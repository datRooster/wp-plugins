<?php
/**
 * Customer balance notification email (plain text).
 *
 * @package DatRooster\PartialPayments
 *
 * @var string             $email_heading
 * @var array<int,string>  $message_lines
 * @var string             $payment_button_text
 * @var string             $pay_url
 * @var WC_Order           $balance_order
 * @var WC_Email           $email
 * @var bool               $sent_to_admin
 * @var bool               $plain_text
 * @var string             $additional_content
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . wp_strip_all_tags( $email_heading ) . " =\n\n";

foreach ( $message_lines as $message_line ) {
	echo wp_strip_all_tags( $message_line ) . "\n\n";
}

if ( '' !== $pay_url ) {
	echo wp_strip_all_tags( $payment_button_text ) . ': ' . esc_url_raw( $pay_url ) . "\n\n";
}

do_action( 'woocommerce_email_order_details', $balance_order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $balance_order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $balance_order, $sent_to_admin, $plain_text, $email );

if ( '' !== $additional_content ) {
	echo "\n" . wp_strip_all_tags( $additional_content ) . "\n";
}
