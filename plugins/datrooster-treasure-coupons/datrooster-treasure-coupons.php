<?php
/**
 * Plugin Name:       DatRooster Treasure Coupons
 * Plugin URI:        https://github.com/datRooster/wp-plugins
 * Description:       WooCommerce treasure hunt campaigns that unlock unique promotional coupons.
 * Version:           0.2.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            DatRooster
 * Author URI:        https://github.com/datRooster
 * Text Domain:       datrooster-treasure-coupons
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins:  woocommerce
 * WC requires at least: 9.0
 * WC tested up to:   10.7
 *
 * @package DatRoosterTreasureCoupons
 */

defined( 'ABSPATH' ) || exit;

define( 'DATROOSTER_TREASURE_COUPONS_FILE', __FILE__ );
define( 'DATROOSTER_TREASURE_COUPONS_PATH', plugin_dir_path( __FILE__ ) );
define( 'DATROOSTER_TREASURE_COUPONS_URL', plugin_dir_url( __FILE__ ) );
define( 'DATROOSTER_TREASURE_COUPONS_BASENAME', plugin_basename( __FILE__ ) );
define( 'DATROOSTER_TREASURE_COUPONS_VERSION', '0.2.0' );

/*
 * DatRooster Treasure Coupons is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * DatRooster Treasure Coupons is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with DatRooster Treasure Coupons. If not, see the license URI above.
 */

require_once DATROOSTER_TREASURE_COUPONS_PATH . 'src/Autoloader.php';

\DatRoosterTreasureCoupons\Autoloader::register();

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', __FILE__, true );
	}
);

register_activation_hook( __FILE__, array( \DatRoosterTreasureCoupons\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \DatRoosterTreasureCoupons\Plugin::class, 'deactivate' ) );

$datrooster_treasure_coupons = new \DatRoosterTreasureCoupons\Plugin();
$datrooster_treasure_coupons->boot();
