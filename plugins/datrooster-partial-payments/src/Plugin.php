<?php
/**
 * Main plugin bootstrap.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments;

use DatRooster\PartialPayments\Admin\SettingsPage;
use DatRooster\PartialPayments\Compatibility\WooCommerce;

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

		if ( ! is_admin() ) {
			return;
		}

		$settings_page = new SettingsPage();
		$settings_page->register();
	}
}
