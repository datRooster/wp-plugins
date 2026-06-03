<?php
/**
 * Uninstall routine for DatRooster Partial Payments.
 *
 * @package DatRoosterPartialPayments
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'datrooster_partial_payments_general_settings' );
delete_option( 'datrooster_partial_payments_label_settings' );
