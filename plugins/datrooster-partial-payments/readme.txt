=== DatRooster Partial Payments ===
Contributors: datrooster
Requires at least: 6.7
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: woocommerce

Flexible WooCommerce deposits and split payments foundation with a modern architecture ready for future balance collection, payment plans, and advanced rules.

== Description ==

DatRooster Partial Payments is the foundation of a WooCommerce deposits plugin built for custom client projects.

Current milestone includes:

* monorepo-ready structure;
* WordPress Settings API integration;
* product-level deposit overrides for simple and variable products;
* automatic reuse of existing WooCommerce payment gateways for deposit orders;
* optional gateway restrictions only when deposit mode is active;
* configurable handling for proportional product tax, shipping collection, and coupon eligibility on deposit items;
* estimated remaining balance summary for products, tax, and shipping in cart, checkout, and order metadata;
* linked balance orders generated from paid deposit orders;
* a `Partially paid` order status and a My Account balance payment action for classic WooCommerce flows;
* WooCommerce dependency checks;
* HPOS compatibility declaration and explicit Cart & Checkout Blocks incompatibility until the dedicated integration is built;
* customizable labels and global deposit defaults.

This release focuses on classic WooCommerce product, cart, checkout, and My Account flows. Cart & Checkout Blocks support, customer emails, and reminder automation are planned for future milestones.

== Installation ==

1. Copy the plugin folder into `/wp-content/plugins/`.
2. Activate WooCommerce.
3. Activate DatRooster Partial Payments.
4. Open `WooCommerce > Partial Payments`.

== Changelog ==

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
