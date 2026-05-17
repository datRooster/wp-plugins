<?php
/**
 * Customer balance notification email (HTML).
 *
 * @package DatRooster\PartialPayments
 *
 * @var string              $email_heading
 * @var array<int,string>   $message_lines
 * @var string              $payment_button_text
 * @var string              $pay_url
 * @var WC_Order            $balance_order
 * @var WC_Email            $email
 * @var bool                $sent_to_admin
 * @var bool                $plain_text
 * @var string              $additional_content
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

foreach ( $message_lines as $message_line ) :
	?>
	<p><?php echo esc_html( $message_line ); ?></p>
	<?php
endforeach;

if ( '' !== $pay_url ) :
	?>
	<p>
		<a class="button" href="<?php echo esc_url( $pay_url ); ?>">
			<?php echo esc_html( $payment_button_text ); ?>
		</a>
	</p>
	<p>
		<a href="<?php echo esc_url( $pay_url ); ?>"><?php echo esc_html( $pay_url ); ?></a>
	</p>
	<?php
endif;

do_action( 'woocommerce_email_order_details', $balance_order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $balance_order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $balance_order, $sent_to_admin, $plain_text, $email );

if ( '' !== $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
