=== DatRooster Partial Payments ===
Contributors: datroooster
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.6.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: woocommerce

WooCommerce deposits and split payments with cart thresholds, linked balance orders, balance reminders, and classic checkout support.

== Description ==

DatRooster Partial Payments is the foundation of a WooCommerce deposits plugin built for custom client projects.

Current milestone includes:

* monorepo-ready structure;
* WordPress Settings API integration;
* product-level deposit overrides for simple and variable products;
* automatic reuse of existing WooCommerce payment gateways for deposit orders;
* optional gateway restrictions only when deposit mode is active;
* configurable handling for proportional product tax, shipping collection, and coupon eligibility on deposit items;
* configurable minimum cart or product threshold before deposits become available, with a cart and checkout selector once the threshold is reached;
* estimated remaining balance summary for products, tax, and shipping in cart, checkout, and order metadata;
* linked balance orders generated from paid deposit orders;
* a `Partially paid` order status and a My Account balance payment action for classic WooCommerce flows;
* transactional balance emails with configurable WooCommerce subjects, headings, and email formats;
* automatic balance due dates and reminder scheduling for classic WooCommerce order-pay flows;
* bundled translation files for Italian, Spanish, and German, with English kept as the source locale;
* WooCommerce dependency checks;
* HPOS compatibility declaration and explicit Cart & Checkout Blocks incompatibility until the dedicated integration is built, with an admin notice when block-based cart or checkout pages are detected;
* customizable labels and global deposit defaults.

This release focuses on classic WooCommerce product, cart, checkout, and My Account flows. Cart & Checkout Blocks support, more advanced deposit rules, and merchant tooling are planned for future milestones.

== Installation ==

1. Copy the plugin folder into `/wp-content/plugins/`.
2. Activate WooCommerce.
3. Activate DatRooster Partial Payments.
4. Make sure the Cart and Checkout pages use the classic shortcodes `[woocommerce_cart]` and `[woocommerce_checkout]` until Cart & Checkout Blocks support is released.
5. Open `WooCommerce > Partial Payments`.

== Frequently Asked Questions ==

= Does this plugin support Cart and Checkout Blocks? =

Not yet. The current release supports classic WooCommerce cart, checkout, and My Account flows. If your store uses WooCommerce Cart or Checkout Blocks, replace those page contents with `[woocommerce_cart]` and `[woocommerce_checkout]`.

== Changelog ==

= 0.6.6 =

* Standardized plugin prefixes across namespaces, constants, options, hooks, metadata, request keys, and UI identifiers for WordPress.org review.

= 0.6.5 =

* Updated the WordPress compatibility metadata for the current Plugin Check requirements.
* Reworked the My Account balance-order filtering to avoid slow query warnings while keeping only technical balance orders hidden.

= 0.6.4 =

* Updated the contributor username to match the WordPress.org owner account.
* Removed the manual plugin textdomain loader for WordPress.org-hosted translations.
* Corrected URL escaping in the plain-text balance email template.
* Reduced common Plugin Check warnings around request handling and readme metadata.

= 0.6.3 =

* Removed hidden packaging files that are not allowed by the WordPress.org automated submission checks.
* Hardened the release packaging script so hidden files are excluded from future plugin ZIP archives.

= 0.6.2 =

* Added a contextual admin notice when WooCommerce Cart or Checkout Blocks are detected on the configured store pages.
* Clarified the classic shortcode requirement in the installation instructions and FAQ for WordPress.org distribution.

= 0.6.1 =

* Added a cart-wide payment selector for classic WooCommerce cart and checkout flows.
* Fixed threshold behavior so the deposit option becomes available as soon as the cart products total reaches the configured minimum amount.
* Applied the selected cart payment mode automatically across eligible products, existing gateways, and order deposit metadata.

= 0.6.0 =

* Added a configurable minimum eligible amount for deposits based on product price or cart products total.
* Added storefront threshold messaging and add-to-cart validation for deposit eligibility.
* Kept the threshold logic aligned across product page UI, deposit validation, and cart capture.

= 0.5.0 =

* Added WooCommerce customer emails for balance creation and balance reminders.
* Added configurable balance due dates and reminder lead times.
* Added bundled `it_IT`, `es_ES`, and `de_DE` translation files plus a translation build script.
* Moved plugin textdomain loading to `init` for current WordPress i18n best practices.

= 0.4.0 =

* Added linked balance orders for deposit purchases.
* Added a `Partially paid` status for parent deposit orders.
* Added a My Account `Pay balance` action backed by WooCommerce `order-pay`.
* Added automatic parent order completion when the linked balance order is paid.

= 0.3.0 =

* Added configurable handling for coupon eligibility on deposit items.
* Added proportional tax tracking and optional proportional shipping handling.
* Added estimated remaining balance storage for products, tax, and shipping.
* Declared Cart & Checkout Blocks incompatibility until the dedicated integration is implemented.

= 0.2.0 =

* Added product-level deposit storefront flow.
* Added automatic gateway reuse for deposit checkouts.
* Added remaining product balance summaries and order metadata.

= 0.1.0 =

* Initial scaffold.
