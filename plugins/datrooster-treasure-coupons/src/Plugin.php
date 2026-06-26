<?php
/**
 * Main plugin bootstrap.
 *
 * @package DatRoosterTreasureCoupons
 */

namespace DatRoosterTreasureCoupons;

use DatRoosterTreasureCoupons\Admin\SettingsPage;
use DatRoosterTreasureCoupons\Compatibility\WooCommerce;
use DatRoosterTreasureCoupons\Frontend\CouponAutoApply;
use DatRoosterTreasureCoupons\Frontend\FormHandler;
use DatRoosterTreasureCoupons\Frontend\Shortcodes;
use DatRoosterTreasureCoupons\Progress\ProgressStore;
use DatRoosterTreasureCoupons\Rewards\CouponGenerator;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	/**
	 * Boots the plugin.
	 */
	public function boot(): void {
		add_action( 'init', array( self::class, 'load_textdomain' ), 0 );
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
	}

	/**
	 * Loads bundled translations for local and self-hosted installs.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain(
			'datrooster-treasure-coupons',
			false,
			dirname( DATROOSTER_TREASURE_COUPONS_BASENAME ) . '/languages'
		);
	}

	/**
	 * Runs activation logic.
	 */
	public static function activate(): void {
		self::load_textdomain();

		if ( false === get_option( SettingsPage::OPTION_NAME, false ) ) {
			add_option( SettingsPage::OPTION_NAME, SettingsPage::get_default_settings() );
		}
	}

	/**
	 * Runs deactivation logic.
	 */
	public static function deactivate(): void {
		// No recurring tasks are scheduled in this milestone.
	}

	/**
	 * Initializes runtime services.
	 */
	public function init(): void {
		if ( ! WooCommerce::is_active() ) {
			add_action( 'admin_notices', array( WooCommerce::class, 'render_missing_notice' ) );
			return;
		}

		$progress_store   = new ProgressStore();
		$coupon_generator = new CouponGenerator( $progress_store );

		$form_handler = new FormHandler( $progress_store, $coupon_generator );
		$form_handler->register();

		$shortcodes = new Shortcodes( $progress_store, $coupon_generator );
		$shortcodes->register();

		$coupon_auto_apply = new CouponAutoApply( $progress_store );
		$coupon_auto_apply->register();

		if ( ! is_admin() ) {
			return;
		}

		$settings_page = new SettingsPage();
		$settings_page->register();
	}
}
