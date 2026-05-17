<?php
/**
 * Product page deposit selection UI.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments\Frontend;

use DatRooster\PartialPayments\Cart\DepositCartManager;
use DatRooster\PartialPayments\Deposits\EligibilityChecker;
use DatRooster\PartialPayments\Deposits\SettingsResolver;

defined( 'ABSPATH' ) || exit;

final class ProductSelection {
	/**
	 * Shared settings resolver.
	 *
	 * @var SettingsResolver
	 */
	private SettingsResolver $settings_resolver;

	/**
	 * Shared eligibility checker.
	 *
	 * @var EligibilityChecker
	 */
	private EligibilityChecker $eligibility_checker;

	/**
	 * Class constructor.
	 *
	 * @param SettingsResolver  $settings_resolver  Shared settings resolver.
	 * @param EligibilityChecker $eligibility_checker Shared eligibility checker.
	 */
	public function __construct( SettingsResolver $settings_resolver, EligibilityChecker $eligibility_checker ) {
		$this->settings_resolver  = $settings_resolver;
		$this->eligibility_checker = $eligibility_checker;
	}

	/**
	 * Registers storefront hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_selection' ), 15 );
	}

	/**
	 * Renders the deposit selection radios on supported product pages.
	 */
	public function render_selection(): void {
		if ( is_admin() ) {
			return;
		}

		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		if ( ! $this->settings_resolver->product_supports_deposits( $product ) ) {
			return;
		}

		$eligibility = $this->eligibility_checker->get_product_eligibility( $product );
		$settings    = $eligibility['settings'];

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		if ( ! $eligibility['eligible'] ) {
			$this->render_threshold_notice( $product, $eligibility );
			return;
		}

		$labels          = $this->settings_resolver->get_label_settings();
		$deposit_allowed = ! $settings['require_login'] || is_user_logged_in();
		$default_mode    = $deposit_allowed ? (string) $settings['default_selection'] : DepositCartManager::MODE_FULL;
		?>
		<div class="drpp-product-selection">
			<fieldset>
				<legend><strong><?php esc_html_e( 'Payment options', 'datrooster-partial-payments' ); ?></strong></legend>
				<p><?php echo esc_html( $this->get_description_text( $product, $settings ) ); ?></p>
				<label>
					<input
						type="radio"
						name="<?php echo esc_attr( DepositCartManager::REQUEST_KEY ); ?>"
						value="<?php echo esc_attr( DepositCartManager::MODE_FULL ); ?>"
						<?php checked( DepositCartManager::MODE_FULL, $default_mode ); ?>
					/>
					<?php echo esc_html( $labels['pay_full_amount_text'] ); ?>
				</label>
				<br />
				<label>
					<input
						type="radio"
						name="<?php echo esc_attr( DepositCartManager::REQUEST_KEY ); ?>"
						value="<?php echo esc_attr( DepositCartManager::MODE_DEPOSIT ); ?>"
						<?php checked( DepositCartManager::MODE_DEPOSIT, $default_mode ); ?>
						<?php disabled( ! $deposit_allowed ); ?>
					/>
					<?php echo esc_html( $labels['pay_deposit_text'] ); ?>
				</label>
				<?php if ( ! $deposit_allowed ) : ?>
					<p class="description">
						<?php esc_html_e( 'Please log in to use deposits for this product.', 'datrooster-partial-payments' ); ?>
					</p>
				<?php endif; ?>
			</fieldset>
		</div>
		<?php
	}

	/**
	 * Renders a storefront notice when the threshold has not been met yet.
	 *
	 * @param \WC_Product         $product     Product object.
	 * @param array<string,mixed> $eligibility Eligibility context.
	 */
	private function render_threshold_notice( \WC_Product $product, array $eligibility ): void {
		$threshold = isset( $eligibility['threshold_amount'] ) ? (float) $eligibility['threshold_amount'] : 0.0;

		if ( $threshold <= 0 ) {
			return;
		}
		?>
		<div class="drpp-product-selection">
			<p class="description">
				<?php
				echo esc_html( $this->get_threshold_notice_text( $product, $threshold ) );
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Builds the description shown above the product radios.
	 *
	 * @param \WC_Product        $product  Product object.
	 * @param array<string,mixed> $settings Effective product settings.
	 */
	private function get_description_text( \WC_Product $product, array $settings ): string {
		$amount = (float) $settings['deposit_amount'];

		if ( 'fixed' === $settings['deposit_type'] ) {
			return sprintf(
				/* translators: %s: fixed deposit amount. */
				__( 'Choose whether to pay in full or leave a fixed deposit of %s per item today.', 'datrooster-partial-payments' ),
				$this->format_price( $amount )
			);
		}

		if ( 'variable' === $product->get_type() ) {
			return sprintf(
				/* translators: %s: deposit percentage. */
				__( 'Choose whether to pay in full or leave a %s%% deposit today. The deposit amount will follow the selected variation price.', 'datrooster-partial-payments' ),
				$this->format_plain_amount( $amount )
			);
		}

		return sprintf(
			/* translators: %s: deposit percentage. */
			__( 'Choose whether to pay in full or leave a %s%% deposit today.', 'datrooster-partial-payments' ),
			$this->format_plain_amount( $amount )
		);
	}

	/**
	 * Builds the threshold notice shown when deposits are not yet eligible.
	 *
	 * @param \WC_Product $product   Product object.
	 * @param float       $threshold Minimum eligible threshold.
	 */
	private function get_threshold_notice_text( \WC_Product $product, float $threshold ): string {
		$formatted_threshold = $this->format_price( $threshold );

		if ( 'variable' === $product->get_type() ) {
			return sprintf(
				/* translators: %s: formatted threshold amount. */
				__( 'Deposits will become available when the selected variation or your cart products total reaches %s.', 'datrooster-partial-payments' ),
				$formatted_threshold
			);
		}

		return sprintf(
			/* translators: %s: formatted threshold amount. */
			__( 'Deposits will become available when this product or your cart products total reaches %s.', 'datrooster-partial-payments' ),
			$formatted_threshold
		);
	}

	/**
	 * Formats a price with the active store currency.
	 *
	 * @param float $amount Amount to format.
	 */
	private function format_price( float $amount ): string {
		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( wc_price( $amount ) );
		}

		return number_format_i18n( $amount, 2 );
	}

	/**
	 * Formats a plain decimal amount.
	 *
	 * @param float $amount Raw amount.
	 */
	private function format_plain_amount( float $amount ): string {
		$text = number_format( $amount, 2, '.', '' );
		$text = rtrim( rtrim( $text, '0' ), '.' );

		return '' !== $text ? $text : '0';
	}
}
