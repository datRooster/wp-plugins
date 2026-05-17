<?php
/**
 * Main plugin bootstrap.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments;

use DatRooster\PartialPayments\Admin\ProductDataPanel;
use DatRooster\PartialPayments\Admin\SettingsPage;
use DatRooster\PartialPayments\Cart\DepositCartManager;
use DatRooster\PartialPayments\Compatibility\WooCommerce;
use DatRooster\PartialPayments\Checkout\OrderDepositMeta;
use DatRooster\PartialPayments\Deposits\Calculator;
use DatRooster\PartialPayments\Deposits\SettingsResolver;
use DatRooster\PartialPayments\Frontend\ProductSelection;
use DatRooster\PartialPayments\Product\MetaRegistry;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	/**
	 * Boots the plugin.
	 */
	public function boot(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
	}

	/**
	 * Runs activation logic.
	 */
	public static function activate(): void {
		if ( false === get_option( SettingsPage::GENERAL_OPTION, false ) ) {
			add_option( SettingsPage::GENERAL_OPTION, SettingsPage::get_default_general_settings() );
		}

		if ( false === get_option( SettingsPage::LABELS_OPTION, false ) ) {
			add_option( SettingsPage::LABELS_OPTION, SettingsPage::get_default_label_settings() );
		}
	}

	/**
	 * Runs deactivation logic.
	 */
	public static function deactivate(): void {
	}

	/**
	 * Loads translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'datrooster-partial-payments',
			false,
			dirname( DATROOSTER_PP_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initializes runtime services.
	 */
	public function init(): void {
		if ( ! WooCommerce::is_active() ) {
			add_action( 'admin_notices', array( WooCommerce::class, 'render_missing_notice' ) );
			return;
		}

		$meta_registry = new MetaRegistry();
		$meta_registry->register();

		$settings_resolver = new SettingsResolver();
		$calculator        = new Calculator();
		$product_selection = new ProductSelection( $settings_resolver );
		$cart_manager      = new DepositCartManager( $settings_resolver, $calculator );
		$order_meta        = new OrderDepositMeta( $cart_manager );

		$product_selection->register();
		$cart_manager->register();
		$order_meta->register();

		if ( ! is_admin() ) {
			return;
		}

		$settings_page     = new SettingsPage();
		$product_data      = new ProductDataPanel( $settings_resolver );

		$settings_page->register();
		$product_data->register();
	}
}
