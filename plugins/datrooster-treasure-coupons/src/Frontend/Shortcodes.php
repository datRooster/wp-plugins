<?php
/**
 * Frontend shortcodes.
 *
 * @package DatRoosterTreasureCoupons
 */

namespace DatRoosterTreasureCoupons\Frontend;

use DatRoosterTreasureCoupons\Admin\SettingsPage;
use DatRoosterTreasureCoupons\Progress\ProgressStore;
use DatRoosterTreasureCoupons\Rewards\CouponGenerator;

defined( 'ABSPATH' ) || exit;

final class Shortcodes {
	/**
	 * Progress store.
	 *
	 * @var ProgressStore
	 */
	private ProgressStore $progress_store;

	/**
	 * Coupon generator.
	 *
	 * @var CouponGenerator
	 */
	private CouponGenerator $coupon_generator;

	/**
	 * Constructor.
	 *
	 * @param ProgressStore   $progress_store Progress store.
	 * @param CouponGenerator $coupon_generator Coupon generator.
	 */
	public function __construct( ProgressStore $progress_store, CouponGenerator $coupon_generator ) {
		$this->progress_store   = $progress_store;
		$this->coupon_generator = $coupon_generator;
	}

	/**
	 * Registers shortcodes and assets.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_send_treasure_status_nocache_headers' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_global_frontend_script' ), 11 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_coupon_popup_assets' ), 20 );
		add_action( 'wp_footer', array( $this, 'render_coupon_popup' ) );
		add_shortcode( 'datrooster_treasure_clue', array( $this, 'render_clue_shortcode' ) );
		add_shortcode( 'datrooster_treasure_progress', array( $this, 'render_progress_shortcode' ) );
	}

	/**
	 * Registers frontend assets.
	 */
	public function register_assets(): void {
		wp_register_style(
			'datrooster-treasure-coupons',
			DATROOSTER_TREASURE_COUPONS_URL . 'assets/css/frontend.css',
			array(),
			DATROOSTER_TREASURE_COUPONS_VERSION
		);

		wp_register_script(
			'datrooster-treasure-coupons-frontend',
			DATROOSTER_TREASURE_COUPONS_URL . 'assets/js/frontend.js',
			array(),
			DATROOSTER_TREASURE_COUPONS_VERSION,
			false
		);
	}

	/**
	 * Prevents mobile/page caches from serving stale pages after treasure actions.
	 */
	public function maybe_send_treasure_status_nocache_headers(): void {
		if ( '' === $this->get_query_status() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}

	/**
	 * Enqueues the tiny frontend helper so mobile cached pages can still read completion URLs.
	 */
	public function maybe_enqueue_global_frontend_script(): void {
		$settings = SettingsPage::get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		wp_enqueue_script( 'datrooster-treasure-coupons-frontend' );
		$this->add_frontend_defaults_script();
	}

	/**
	 * Enqueues popup assets when a manually unlocked coupon is present in the redirect URL.
	 */
	public function maybe_enqueue_coupon_popup_assets(): void {
		$coupon_code = $this->get_query_coupon_code();

		if ( '' === $coupon_code ) {
			return;
		}

		wp_enqueue_style( 'datrooster-treasure-coupons' );
		wp_enqueue_script( 'datrooster-treasure-coupons-frontend' );
		wp_add_inline_script(
			'datrooster-treasure-coupons-frontend',
			'window.datroosterTreasureCouponPopup = ' . wp_json_encode( $this->get_coupon_popup_payload( $coupon_code, SettingsPage::get_settings() ) ) . ';',
			'before'
		);
	}

	/**
	 * Renders a coupon popup after manual reward unlock.
	 */
	public function render_coupon_popup(): void {
		$coupon_code = $this->get_query_coupon_code();

		if ( '' === $coupon_code ) {
			return;
		}

		$settings  = SettingsPage::get_settings();
		$close_url = remove_query_arg(
			array(
				'datrooster_treasure_status',
				'datrooster_treasure_coupon',
			)
		);
		?>
		<div class="datrooster-treasure-coupon-popup" role="dialog" aria-modal="false" aria-labelledby="datrooster-treasure-coupon-popup-title">
			<div class="datrooster-treasure-coupon-popup__card">
				<a class="datrooster-treasure-coupon-popup__close" href="<?php echo esc_url( $close_url ); ?>" aria-label="<?php esc_attr_e( 'Close coupon popup', 'datrooster-treasure-coupons' ); ?>">
					<span aria-hidden="true">&times;</span>
				</a>
				<p class="datrooster-treasure-coupon-popup__eyebrow">
					<?php esc_html_e( 'Treasure reward unlocked', 'datrooster-treasure-coupons' ); ?>
				</p>
				<h2 id="datrooster-treasure-coupon-popup-title">
					<?php esc_html_e( 'Your coupon code is ready', 'datrooster-treasure-coupons' ); ?>
				</h2>
				<p>
					<?php echo esc_html( (string) $settings['completion_message'] ); ?>
				</p>
				<p class="datrooster-treasure-coupon-popup__reward">
					<?php echo esc_html( $this->get_reward_summary( $settings ) ); ?>
				</p>
				<div class="datrooster-treasure-coupon-popup__code-row">
					<code><?php echo esc_html( $coupon_code ); ?></code>
					<button
						type="button"
						class="datrooster-treasure-coupon-popup__copy"
						data-datrooster-copy-code="<?php echo esc_attr( $coupon_code ); ?>"
						data-datrooster-copied-label="<?php esc_attr_e( 'Copied', 'datrooster-treasure-coupons' ); ?>"
					>
						<?php esc_html_e( 'Copy code', 'datrooster-treasure-coupons' ); ?>
					</button>
				</div>
				<p class="datrooster-treasure-coupon-popup__hint">
					<?php esc_html_e( 'Save it and use it at checkout when you are ready.', 'datrooster-treasure-coupons' ); ?>
				</p>
				<?php if ( '' !== $this->get_minimum_spend_hint( $settings ) ) : ?>
					<p class="datrooster-treasure-coupon-popup__minimum">
						<?php echo esc_html( $this->get_minimum_spend_hint( $settings ) ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Adds translated default popup text for the JavaScript fallback.
	 */
	private function add_frontend_defaults_script(): void {
		static $added = false;

		if ( $added ) {
			return;
		}

		$added = true;

		wp_add_inline_script(
			'datrooster-treasure-coupons-frontend',
			'window.datroosterTreasureCouponPopupDefaults = ' . wp_json_encode(
				array(
					'closeLabel'  => __( 'Close coupon popup', 'datrooster-treasure-coupons' ),
					'copiedLabel' => __( 'Copied', 'datrooster-treasure-coupons' ),
					'copyLabel'   => __( 'Copy code', 'datrooster-treasure-coupons' ),
					'eyebrow'     => __( 'Treasure reward unlocked', 'datrooster-treasure-coupons' ),
					'hint'        => __( 'Save it and use it at checkout when you are ready.', 'datrooster-treasure-coupons' ),
					'message'     => __( 'You unlocked your reward.', 'datrooster-treasure-coupons' ),
					'title'       => __( 'Your coupon code is ready', 'datrooster-treasure-coupons' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Returns popup data for the PHP render and JavaScript fallback.
	 *
	 * @param string              $coupon_code Coupon code.
	 * @param array<string,mixed> $settings Plugin settings.
	 *
	 * @return array<string,string>
	 */
	private function get_coupon_popup_payload( string $coupon_code, array $settings ): array {
		$close_url = remove_query_arg(
			array(
				'datrooster_treasure_status',
				'datrooster_treasure_coupon',
			)
		);

		return array(
			'closeLabel'     => __( 'Close coupon popup', 'datrooster-treasure-coupons' ),
			'closeUrl'       => esc_url_raw( $close_url ),
			'copiedLabel'    => __( 'Copied', 'datrooster-treasure-coupons' ),
			'copyLabel'      => __( 'Copy code', 'datrooster-treasure-coupons' ),
			'couponCode'     => wc_format_coupon_code( $coupon_code ),
			'eyebrow'        => __( 'Treasure reward unlocked', 'datrooster-treasure-coupons' ),
			'hint'           => __( 'Save it and use it at checkout when you are ready.', 'datrooster-treasure-coupons' ),
			'message'        => (string) $settings['completion_message'],
			'minimumHint'    => $this->get_minimum_spend_hint( $settings ),
			'rewardSummary'  => $this->get_reward_summary( $settings ),
			'title'          => __( 'Your coupon code is ready', 'datrooster-treasure-coupons' ),
		);
	}

	/**
	 * Returns a readable reward summary for the popup.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	private function get_reward_summary( array $settings ): string {
		$discount_type = sanitize_key( (string) $settings['discount_type'] );
		$amount        = (float) $settings['discount_amount'];

		if ( 'free_shipping' === $discount_type ) {
			return __( 'You won free shipping.', 'datrooster-treasure-coupons' );
		}

		if ( 'fixed_cart' === $discount_type ) {
			return sprintf(
				/* translators: %s: formatted discount amount. */
				__( 'You won %s off your cart.', 'datrooster-treasure-coupons' ),
				$this->format_money( $amount )
			);
		}

		return sprintf(
			/* translators: %s: discount percentage. */
			__( 'You won %s off.', 'datrooster-treasure-coupons' ),
			$this->format_percentage( $amount )
		);
	}

	/**
	 * Returns the minimum spend hint for the popup.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	private function get_minimum_spend_hint( array $settings ): string {
		$minimum_spend = (float) $settings['minimum_spend'];

		if ( $minimum_spend <= 0 ) {
			return '';
		}

		return sprintf(
			/* translators: %s: formatted minimum spend. */
			__( 'Minimum spend required to use it: %s.', 'datrooster-treasure-coupons' ),
			$this->format_money( $minimum_spend )
		);
	}

	/**
	 * Formats a percentage for customer-facing reward copy.
	 *
	 * @param float $amount Raw percentage amount.
	 */
	private function format_percentage( float $amount ): string {
		$decimals = floor( $amount ) === $amount ? 0 : 2;

		return number_format_i18n( $amount, $decimals ) . '%';
	}

	/**
	 * Formats a money amount as plain text.
	 *
	 * @param float $amount Raw money amount.
	 */
	private function format_money( float $amount ): string {
		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( wc_price( $amount ) );
		}

		return number_format_i18n( $amount, 2 );
	}

	/**
	 * Renders a clue collection button.
	 *
	 * @param array<string,mixed>|string $atts Shortcode attributes.
	 */
	public function render_clue_shortcode( array|string $atts ): string {
		$settings = SettingsPage::get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return $this->render_admin_hint( __( 'Treasure hunt is currently disabled.', 'datrooster-treasure-coupons' ) );
		}

		if ( ! empty( $settings['require_login'] ) && ! is_user_logged_in() ) {
			return $this->render_login_required_message();
		}

		$atts = shortcode_atts(
			array(
				'hunt'      => (string) $settings['hunt_slug'],
				'clue'      => '',
				'label'     => (string) $settings['clue_button_label'],
				'display'   => 'button',
				'image'     => '',
				'image_alt' => __( 'Collect clue', 'datrooster-treasure-coupons' ),
			),
			is_array( $atts ) ? $atts : array(),
			'datrooster_treasure_clue'
		);

		$hunt_slug = sanitize_title( (string) $atts['hunt'] );
		$clue_id   = sanitize_key( (string) $atts['clue'] );
		$label     = sanitize_text_field( (string) $atts['label'] );
		$display   = $this->sanitize_display( (string) $atts['display'] );
		$image_url = $this->resolve_image_url( (string) $atts['image'] );
		$image_alt = sanitize_text_field( (string) $atts['image_alt'] );

		if ( '' === $hunt_slug || '' === $clue_id ) {
			return $this->render_admin_hint( __( 'Treasure clue shortcode needs both hunt and clue attributes.', 'datrooster-treasure-coupons' ) );
		}

		$this->enqueue_assets();

		$progress  = $this->progress_store->get_progress( $hunt_slug );
		$collected = in_array( $clue_id, $progress['clues'], true );
		$completed = '' !== $this->coupon_generator->maybe_generate_for_hunt( $hunt_slug );
		$display   = 'image' === $display && '' === $image_url ? 'button' : $display;

		ob_start();
		?>
		<span class="datrooster-treasure-clue datrooster-treasure-clue--<?php echo esc_attr( $display ); ?>">
			<?php if ( $collected ) : ?>
				<span class="datrooster-treasure-pill datrooster-treasure-pill--collected">
					<?php echo esc_html( (string) $settings['collected_label'] ); ?>
				</span>
			<?php elseif ( $completed ) : ?>
				<span class="datrooster-treasure-pill datrooster-treasure-pill--complete">
					<?php esc_html_e( 'Reward unlocked', 'datrooster-treasure-coupons' ); ?>
				</span>
			<?php else : ?>
				<form class="datrooster-treasure-clue__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( FormHandler::ACTION ); ?>" />
					<input type="hidden" name="hunt" value="<?php echo esc_attr( $hunt_slug ); ?>" />
					<input type="hidden" name="clue" value="<?php echo esc_attr( $clue_id ); ?>" />
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $this->get_current_url() ); ?>" />
					<?php wp_nonce_field( FormHandler::ACTION ); ?>
					<button class="<?php echo esc_attr( $this->get_clue_button_class( $display ) ); ?>" type="submit">
						<?php if ( 'image' === $display && '' !== $image_url ) : ?>
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" loading="lazy" decoding="async" />
							<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
						<?php else : ?>
							<?php echo esc_html( $label ); ?>
						<?php endif; ?>
					</button>
				</form>
			<?php endif; ?>
		</span>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Resolves a custom or bundled clue image URL.
	 *
	 * @param string $image Image shortcode value.
	 */
	private function resolve_image_url( string $image ): string {
		$image = trim( $image );

		if ( '' === $image ) {
			return '';
		}

		$default_images = array(
			'default:key' => 'default-key.png',
			'default:gem' => 'default-gem.png',
			'default:map' => 'default-map.png',
		);

		if ( isset( $default_images[ $image ] ) ) {
			return DATROOSTER_TREASURE_COUPONS_URL . 'assets/images/' . $default_images[ $image ];
		}

		return esc_url_raw( $image );
	}

	/**
	 * Sanitizes the clue display mode.
	 *
	 * @param string $display Requested display mode.
	 */
	private function sanitize_display( string $display ): string {
		$display = sanitize_key( $display );
		$allowed = array( 'button', 'text', 'image' );

		return in_array( $display, $allowed, true ) ? $display : 'button';
	}

	/**
	 * Returns the CSS class for a clue trigger.
	 *
	 * @param string $display Display mode.
	 */
	private function get_clue_button_class( string $display ): string {
		if ( 'image' === $display ) {
			return 'datrooster-treasure-image-button';
		}

		if ( 'text' === $display ) {
			return 'datrooster-treasure-text-button';
		}

		return 'datrooster-treasure-button';
	}

	/**
	 * Renders treasure hunt progress.
	 *
	 * @param array<string,mixed>|string $atts Shortcode attributes.
	 */
	public function render_progress_shortcode( array|string $atts ): string {
		$settings = SettingsPage::get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return $this->render_admin_hint( __( 'Treasure hunt is currently disabled.', 'datrooster-treasure-coupons' ) );
		}

		if ( ! empty( $settings['require_login'] ) && ! is_user_logged_in() ) {
			return $this->render_login_required_message();
		}

		$atts = shortcode_atts(
			array(
				'hunt' => (string) $settings['hunt_slug'],
			),
			is_array( $atts ) ? $atts : array(),
			'datrooster_treasure_progress'
		);

		$hunt_slug = sanitize_title( (string) $atts['hunt'] );

		if ( '' === $hunt_slug ) {
			return $this->render_admin_hint( __( 'Treasure progress shortcode needs a hunt attribute.', 'datrooster-treasure-coupons' ) );
		}

		$this->enqueue_assets();

		$progress       = $this->progress_store->get_progress( $hunt_slug );
		$coupon_code    = $this->coupon_generator->maybe_generate_for_hunt( $hunt_slug );
		$required_clues = max( 1, absint( $settings['required_clues'] ) );
		$clue_count     = min( count( $progress['clues'] ), $required_clues );
		$percentage     = min( 100, (int) floor( ( $clue_count / $required_clues ) * 100 ) );

		ob_start();
		?>
		<div class="datrooster-treasure-progress">
			<?php echo wp_kses_post( $this->render_status_notice() ); ?>

			<div class="datrooster-treasure-progress__header">
				<strong><?php esc_html_e( 'Treasure hunt progress', 'datrooster-treasure-coupons' ); ?></strong>
				<span>
					<?php
					printf(
						/* translators: 1: collected clues. 2: required clues. */
						esc_html__( '%1$d of %2$d clues', 'datrooster-treasure-coupons' ),
						absint( $clue_count ),
						absint( $required_clues )
					);
					?>
				</span>
			</div>

			<div class="datrooster-treasure-progress__track" aria-hidden="true">
				<span style="width: <?php echo esc_attr( (string) $percentage ); ?>%;"></span>
			</div>

			<?php if ( '' !== $coupon_code ) : ?>
				<div class="datrooster-treasure-reward">
					<p><?php echo esc_html( (string) $settings['completion_message'] ); ?></p>
					<code><?php echo esc_html( $coupon_code ); ?></code>
				</div>
			<?php else : ?>
				<p class="datrooster-treasure-progress__hint">
					<?php esc_html_e( 'Keep exploring the site to unlock your reward coupon.', 'datrooster-treasure-coupons' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Enqueues frontend assets.
	 */
	private function enqueue_assets(): void {
		wp_enqueue_style( 'datrooster-treasure-coupons' );
	}

	/**
	 * Renders admin-only setup hints.
	 *
	 * @param string $message Hint message.
	 */
	private function render_admin_hint( string $message ): string {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return '';
		}

		return '<div class="datrooster-treasure-notice datrooster-treasure-notice--admin">' . esc_html( $message ) . '</div>';
	}

	/**
	 * Renders a login-required message.
	 */
	private function render_login_required_message(): string {
		$login_url = wp_login_url( $this->get_current_url() );

		return sprintf(
			'<div class="datrooster-treasure-notice"><a href="%1$s">%2$s</a></div>',
			esc_url( $login_url ),
			esc_html__( 'Log in to join this treasure hunt.', 'datrooster-treasure-coupons' )
		);
	}

	/**
	 * Renders a status notice after clue collection.
	 */
	private function render_status_notice(): string {
		$status = $this->get_query_status();

		if ( '' === $status ) {
			return '';
		}

		$messages = array(
			'collected' => __( 'Clue collected. Nice find.', 'datrooster-treasure-coupons' ),
			'completed' => __( 'Treasure hunt completed. Your coupon is ready.', 'datrooster-treasure-coupons' ),
			'invalid'   => __( 'We could not verify that clue. Please try again.', 'datrooster-treasure-coupons' ),
			'disabled'  => __( 'This treasure hunt is currently disabled.', 'datrooster-treasure-coupons' ),
		);

		if ( empty( $messages[ $status ] ) ) {
			return '';
		}

		return '<div class="datrooster-treasure-notice datrooster-treasure-notice--transient">' . esc_html( $messages[ $status ] ) . '</div>';
	}

	/**
	 * Returns a normalized status from treasure action redirect URLs.
	 */
	private function get_query_status(): string {
		$status = filter_input( INPUT_GET, 'datrooster_treasure_status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( ! is_string( $status ) || '' === $status ) {
			return '';
		}

		return sanitize_key( $status );
	}

	/**
	 * Returns a coupon code from the completion redirect URL.
	 */
	private function get_query_coupon_code(): string {
		$coupon = filter_input( INPUT_GET, 'datrooster_treasure_coupon', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( 'completed' !== $this->get_query_status() || ! is_string( $coupon ) || '' === $coupon ) {
			return '';
		}

		$coupon = sanitize_text_field( $coupon );

		return function_exists( 'wc_format_coupon_code' ) ? wc_format_coupon_code( $coupon ) : $coupon;
	}

	/**
	 * Returns the current page URL without trusting arbitrary user input.
	 */
	private function get_current_url(): string {
		$post_id = get_queried_object_id();

		if ( $post_id > 0 ) {
			$permalink = get_permalink( $post_id );

			if ( is_string( $permalink ) && '' !== $permalink ) {
				return $permalink;
			}
		}

		return home_url( '/' );
	}
}
