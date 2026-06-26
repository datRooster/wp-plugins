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
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
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
				'image'     => '',
				'image_alt' => __( 'Collect clue', 'datrooster-treasure-coupons' ),
			),
			is_array( $atts ) ? $atts : array(),
			'datrooster_treasure_clue'
		);

		$hunt_slug = sanitize_title( (string) $atts['hunt'] );
		$clue_id   = sanitize_key( (string) $atts['clue'] );
		$label     = sanitize_text_field( (string) $atts['label'] );
		$image_url = $this->resolve_image_url( (string) $atts['image'] );
		$image_alt = sanitize_text_field( (string) $atts['image_alt'] );

		if ( '' === $hunt_slug || '' === $clue_id ) {
			return $this->render_admin_hint( __( 'Treasure clue shortcode needs both hunt and clue attributes.', 'datrooster-treasure-coupons' ) );
		}

		$this->enqueue_assets();

		$progress  = $this->progress_store->get_progress( $hunt_slug );
		$collected = in_array( $clue_id, $progress['clues'], true );
		$completed = '' !== $this->coupon_generator->maybe_generate_for_hunt( $hunt_slug );

		ob_start();
		?>
		<div class="datrooster-treasure-clue">
			<?php if ( $collected ) : ?>
				<span class="datrooster-treasure-pill datrooster-treasure-pill--collected">
					<?php echo esc_html( (string) $settings['collected_label'] ); ?>
				</span>
			<?php elseif ( $completed ) : ?>
				<span class="datrooster-treasure-pill datrooster-treasure-pill--complete">
					<?php esc_html_e( 'Reward unlocked', 'datrooster-treasure-coupons' ); ?>
				</span>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( FormHandler::ACTION ); ?>" />
					<input type="hidden" name="hunt" value="<?php echo esc_attr( $hunt_slug ); ?>" />
					<input type="hidden" name="clue" value="<?php echo esc_attr( $clue_id ); ?>" />
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $this->get_current_url() ); ?>" />
					<?php wp_nonce_field( FormHandler::ACTION ); ?>
					<button class="<?php echo esc_attr( '' !== $image_url ? 'datrooster-treasure-image-button' : 'datrooster-treasure-button' ); ?>" type="submit">
						<?php if ( '' !== $image_url ) : ?>
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" loading="lazy" decoding="async" />
							<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
						<?php else : ?>
							<?php echo esc_html( $label ); ?>
						<?php endif; ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
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
		$status = filter_input( INPUT_GET, 'datrooster_treasure_status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( empty( $status ) ) {
			return '';
		}

		$status = sanitize_key( (string) $status );

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
