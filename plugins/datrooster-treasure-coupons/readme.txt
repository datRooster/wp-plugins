=== DatRooster Treasure Coupons ===
Contributors: datroooster
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: woocommerce

WooCommerce treasure hunt campaigns that let visitors collect clues across the site and unlock unique promotional coupons.

== Description ==

DatRooster Treasure Coupons adds lightweight gamification to WooCommerce stores by turning pages, posts, and products into a coupon treasure hunt.

Current milestone includes:

* a global treasure hunt configuration page under WooCommerce;
* shortcode-based clues that can be placed in pages, posts, widgets, and product content;
* progress tracking for logged-in customers and guest visitors;
* signed guest cookies that avoid storing personal data for anonymous participants;
* automatic generation of unique WooCommerce coupons when the configured number of clues is collected;
* support for percentage discounts, fixed cart discounts, and free shipping rewards;
* optional minimum spend, coupon expiry, one-use coupons, and individual-use coupon settings;
* optional coupon auto-apply when the visitor reaches the WooCommerce cart;
* temporary storefront notices when a clue is collected, the reward is unlocked, or the coupon is auto-applied;
* optional image-based clue buttons for custom transparent PNG treasure markers;
* three bundled default clue images: key, gem, and map;
* bundled translation files for Italian, Spanish, and German, with English kept as the source locale;
* HPOS compatibility declaration and no custom payment gateway dependency.

This release focuses on a single active campaign. Multiple campaigns, richer analytics, Gutenberg blocks, and visual clue placement tools are planned for future milestones.

== Installation ==

1. Copy the plugin folder into `/wp-content/plugins/`.
2. Activate WooCommerce.
3. Activate DatRooster Treasure Coupons.
4. Open `WooCommerce > Treasure Coupons` and configure the campaign.
5. Add clues with `[datrooster_treasure_clue hunt="main-hunt" clue="first-clue"]`.
6. Add progress output with `[datrooster_treasure_progress hunt="main-hunt"]`.
7. To use a bundled image clue, add `[datrooster_treasure_clue hunt="main-hunt" clue="secret-key" image="default:key" image_alt="Hidden key clue"]`.
8. Available bundled images are `default:key`, `default:gem`, and `default:map`.
9. To use a custom transparent PNG, add `[datrooster_treasure_clue hunt="main-hunt" clue="hidden-image" image="https://example.com/clue.png" image_alt="Hidden clue"]`.

== Frequently Asked Questions ==

= Does this plugin create WooCommerce coupons automatically? =

Yes. Once the visitor collects the configured number of unique clues, the plugin creates a WooCommerce coupon with the configured reward settings.

= Can guests use the treasure hunt? =

Yes. Guest progress is stored in a signed cookie. You can require login from the plugin settings if you prefer customer-account-only campaigns.

= Is the reward random? =

No. The reward is deterministic and configured by the merchant. This keeps the campaign clear for customers and avoids lottery-style mechanics.

= Can I place clues inside products? =

Yes. The clue shortcode can be used anywhere WordPress shortcodes are supported, including product descriptions.

== Changelog ==

= 0.2.0 =

* Added temporary storefront notices for unlocked and auto-applied coupons.
* Added optional image-based clue buttons for transparent PNG markers.
* Added bundled transparent PNG clue images for key, gem, and map markers.
* Added bundled Italian, Spanish, and German translation files.
* Added local bundled translation loading for non-WordPress.org development installs.

= 0.1.0 =

* Initial scaffold with WooCommerce coupon rewards, shortcode clues, progress tracking, and admin settings.
