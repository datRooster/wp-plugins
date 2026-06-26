<?php
/**
 * Uninstall cleanup.
 *
 * @package DatRoosterTreasureCoupons
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'datrooster_treasure_coupons_settings' );
