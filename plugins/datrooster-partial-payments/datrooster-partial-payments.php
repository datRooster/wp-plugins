<?php
/**
 * Plugin Name:       DatRooster Partial Payments
 * Plugin URI:        https://github.com/datRooster/wp-plugins
 * Description:       WooCommerce deposits, split payments, and linked balance collection orders.
 * Version:           0.6.4
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            DatRooster
 * Author URI:        https://github.com/datRooster
 * Text Domain:       datrooster-partial-payments
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins:  woocommerce
 * WC requires at least: 9.0
 * WC tested up to:   10.7
 *
 * @package DatRooster\PartialPayments
 */

defined( 'ABSPATH' ) || exit;

define( 'DATROOSTER_PP_FILE', __FILE__ );
define( 'DATROOSTER_PP_PATH', plugin_dir_path( __FILE__ ) );
define( 'DATROOSTER_PP_URL', plugin_dir_url( __FILE__ ) );
define( 'DATROOSTER_PP_BASENAME', plugin_basename( __FILE__ ) );

/*
 * DatRooster Partial Payments is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * DatRooster Partial Payments is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with DatRooster Partial Payments. If not, see the license URI above.
 */
define( 'DATROOSTER_PP_VERSION', '0.6.4' );

require_once DATROOSTER_PP_PATH . 'src/Autoloader.php';

\DatRooster\PartialPayments\Autoloader::register();

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		// The current milestone relies on classic cart and checkout integrations.
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', __FILE__, false );
	}
);

register_activation_hook( __FILE__, array( \DatRooster\PartialPayments\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \DatRooster\PartialPayments\Plugin::class, 'deactivate' ) );

$datrooster_partial_payments = new \DatRooster\PartialPayments\Plugin();
$datrooster_partial_payments->boot();
