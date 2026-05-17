<?php
/**
 * Uninstall routine for DatRooster Partial Payments.
 *
 * @package DatRooster\PartialPayments
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'drpp_general_settings' );
delete_option( 'drpp_label_settings' );
