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
use DatRooster\PartialPayments\Deposits\EligibilityChecker;
use DatRooster\PartialPayments\Deposits\SettingsResolver;
use DatRooster\PartialPayments\Emails\BalanceNotifications;
use DatRooster\PartialPayments\Emails\EmailManager;
use DatRooster\PartialPayments\Frontend\ProductSelection;
use DatRooster\PartialPayments\Orders\BalanceOrderManager;
use DatRooster\PartialPayments\Orders\PartiallyPaidStatus;
use DatRooster\PartialPayments\Product\MetaRegistry;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	/**
	 * Boots the plugin.
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
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
		wp_clear_scheduled_hook( BalanceNotifications::REMINDER_ACTION_HOOK );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( BalanceNotifications::REMINDER_ACTION_HOOK, array(), BalanceNotifications::SCHEDULER_GROUP );
		}
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
		$eligibility       = new EligibilityChecker( $settings_resolver );
		$calculator        = new Calculator();
		$status_manager    = new PartiallyPaidStatus();
		$product_selection = new ProductSelection( $settings_resolver, $eligibility );
		$cart_manager      = new DepositCartManager( $settings_resolver, $calculator, $eligibility );
		$order_meta        = new OrderDepositMeta( $cart_manager );
		$email_manager     = new EmailManager();
		$balance_manager   = new BalanceOrderManager( $settings_resolver );
		$notifications     = new BalanceNotifications( $settings_resolver, $email_manager );

		$status_manager->register();
		$product_selection->register();
		$cart_manager->register();
		$order_meta->register();
		$email_manager->register();
		$balance_manager->register();
		$notifications->register();

		if ( ! is_admin() ) {
			return;
		}

		$settings_page     = new SettingsPage();
		$product_data      = new ProductDataPanel( $settings_resolver );

		$settings_page->register();
		$product_data->register();
	}
}
