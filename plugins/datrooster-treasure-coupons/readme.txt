=== DatRooster Treasure Coupons ===
Contributors: datroooster
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.3.11
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: woocommerce

WooCommerce treasure hunt campaigns that let visitors collect clues across the site and unlock unique promotional coupons.

== Description ==

DatRooster Treasure Coupons adds lightweight gamification to WooCommerce stores by turning pages, posts, and products into a coupon treasure hunt.

Current milestone includes:

* a global treasure hunt configuration page under WooCommerce;
* a universal Customizer placement layer for theme templates, fallback pages, WooCommerce cart, checkout, and product screens;
* drag-and-save visual placement inside the WordPress Customizer preview for floating treasure markers;
* no-code Treasure Clue and Treasure Progress blocks for the WordPress block editor;
* shortcode-based clues that can be placed in pages, posts, widgets, product content, and builder text modules;
* progress tracking for logged-in customers and guest visitors;
* signed guest cookies that avoid storing personal data for anonymous participants;
* automatic generation of unique WooCommerce coupons when the configured number of clues is collected;
* support for percentage discounts, fixed cart discounts, and free shipping rewards;
* optional minimum spend, coupon expiry, one-use coupons, and individual-use coupon settings;
* optional coupon auto-apply when the visitor reaches the WooCommerce cart;
* temporary storefront notices when a clue is collected, the reward is unlocked, or the coupon is auto-applied;
* optional button, hidden text-link, and image-based clue displays;
* optional advanced CSS selector placement with floating fallback when a theme target cannot be found;
* optional image-based clue buttons for custom transparent PNG treasure markers;
* three bundled default clue images: key, gem, and map;
* bundled translation files for Italian, Spanish, and German, with English kept as the source locale;
* HPOS compatibility declaration and no custom payment gateway dependency.

This release focuses on a single active campaign. Multiple campaigns, richer analytics, and advanced visual clue placement tools are planned for future milestones.

== Installation ==

1. Copy the plugin folder into `/wp-content/plugins/`.
2. Activate WooCommerce.
3. Activate DatRooster Treasure Coupons.
4. Open `WooCommerce > Treasure Coupons` and configure the campaign.
5. Add clues with the Treasure Clue block, or with `[datrooster_treasure_clue hunt="main-hunt" clue="first-clue"]`.
6. Add progress output with the Treasure Progress block, or with `[datrooster_treasure_progress hunt="main-hunt"]`.
7. To place a clue without editing page content, open `Appearance > Customize > Treasure Coupons placement`.
8. To hide a clue behind normal page text, use `[datrooster_treasure_clue hunt="main-hunt" clue="secret-word" display="text" label="special word"]`.
9. To use a bundled image clue, add `[datrooster_treasure_clue hunt="main-hunt" clue="secret-key" display="image" image="default:key" image_alt="Hidden key clue"]`.
10. Available bundled images are `default:key`, `default:gem`, and `default:map`.
11. To use a custom transparent PNG, add `[datrooster_treasure_clue hunt="main-hunt" clue="hidden-image" display="image" image="https://example.com/clue.png" image_alt="Hidden clue"]`.

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

= 0.3.11 =

* Fixed guest progress persistence by preserving the signed progress cookie before validation.
* Added no-cache headers to treasure action redirects so guest clue states are not hidden by cached mobile pages.

= 0.3.10 =

* Added a head-loaded coupon popup fallback so completion URLs still show the reward on mobile, cached, and theme-variant pages.
* Added no-cache headers for treasure completion URLs to avoid stale mobile pages hiding the coupon popup.
* Improved guest completion visibility by rebuilding the coupon popup from the signed completion URL when needed.

= 0.3.9 =

* Added reward details to the manual coupon popup, including percentage, fixed-cart, free-shipping, and minimum-spend information.
* Improved mobile popup rendering with dynamic viewport sizing and scroll-safe layout.
* Kept coupons unlocked even when the current cart is below the configured minimum spend, with a storefront notice showing how much more is needed.

= 0.3.8 =

* Fixed Customizer image marker selection for bundled marker values such as default key, gem, and map.
* Added a manual coupon popup after treasure completion when automatic cart coupon application is disabled.
* Added a copy-code interaction for manually unlocked coupon rewards.

= 0.3.7 =

* Added DOM-anchored Customizer placement so dragged clues can keep their exact position relative to the page section where they were dropped.
* Recalculate anchored placements on frontend load, resize, and orientation changes for more precise desktop, tablet, and mobile rendering.
* Kept responsive coordinate fallbacks for older saved placements and templates where the saved anchor is unavailable.

= 0.3.6 =

* Added device-specific visual placement coordinates for desktop, tablet, and mobile Customizer previews.
* Improved desktop drag precision by compensating for the Customizer sidebar width before saving frontend coordinates.

= 0.3.5 =

* Improved visual placement precision by saving horizontal drag coordinates as responsive percentages while preserving vertical document anchoring.

= 0.3.4 =

* Changed Customizer drag placement to save page-anchored pixel coordinates so clues stay fixed in the document instead of following viewport scroll.

= 0.3.3 =

* Improved Customizer drag saving with direct parent-panel updates and a fallback save after drag movement.

= 0.3.2 =

* Added authenticated AJAX saving for Customizer drag positioning so marker coordinates persist even when the Customizer publish button does not become active.

= 0.3.1 =

* Improved Customizer drag positioning so preview coordinates update the saved controls reliably.

= 0.3.0 =

* Added WordPress block editor blocks for Treasure Clue and Treasure Progress placement.
* Added universal Customizer placement for pages, WooCommerce screens, floating overlays, and advanced CSS selector targets.
* Added draggable Customizer preview positioning for floating treasure markers.
* Added optional hidden text-link clue display for embedding clues inside regular page copy.

= 0.2.0 =

* Added temporary storefront notices for unlocked and auto-applied coupons.
* Added optional image-based clue buttons for transparent PNG markers.
* Added bundled transparent PNG clue images for key, gem, and map markers.
* Added bundled Italian, Spanish, and German translation files.
* Added local bundled translation loading for non-WordPress.org development installs.

= 0.1.0 =

* Initial scaffold with WooCommerce coupon rewards, shortcode clues, progress tracking, and admin settings.
