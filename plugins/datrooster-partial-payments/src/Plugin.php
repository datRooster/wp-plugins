<?php
/**
 * Main plugin bootstrap.
 *
 * @package DatRoosterPartialPayments
 */

namespace DatRoosterPartialPayments;

use DatRoosterPartialPayments\Admin\ProductDataPanel;
use DatRoosterPartialPayments\Admin\SettingsPage;
use DatRoosterPartialPayments\Cart\DepositCartManager;
use DatRoosterPartialPayments\Compatibility\WooCommerce;
use DatRoosterPartialPayments\Checkout\OrderDepositMeta;
use DatRoosterPartialPayments\Deposits\Calculator;
use DatRoosterPartialPayments\Deposits\EligibilityChecker;
use DatRoosterPartialPayments\Deposits\SettingsResolver;
use DatRoosterPartialPayments\Emails\BalanceNotifications;
use DatRoosterPartialPayments\Emails\EmailManager;
use DatRoosterPartialPayments\Frontend\ProductSelection;
use DatRoosterPartialPayments\Orders\BalanceOrderManager;
use DatRoosterPartialPayments\Orders\PartiallyPaidStatus;
use DatRoosterPartialPayments\Product\MetaRegistry;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	/**
	 * Boots the plugin.
	 */
	public function boot(): void {
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
	 * Initializes runtime services.
	 */
	public function init(): void {
		if ( ! WooCommerce::is_active() ) {
			add_action( 'admin_notices', array( WooCommerce::class, 'render_missing_notice' ) );
			return;
		}

		if ( is_admin() ) {
			add_action( 'admin_notices', array( WooCommerce::class, 'render_blocks_incompatibility_notice' ) );
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
