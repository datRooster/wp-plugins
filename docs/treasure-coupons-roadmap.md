# DatRooster Treasure Coupons Roadmap

## Vision

DatRooster Treasure Coupons turns WooCommerce stores into lightweight promotional treasure hunts. The goal is to increase product discovery, session depth, and coupon-driven conversions without relying on lottery-style mechanics or invasive tracking.

## 0.1.0 Foundation

- Single active campaign configured from `WooCommerce > Treasure Coupons`.
- Shortcode-based clue placement across pages, posts, widgets, and product descriptions.
- Logged-in user progress stored in user meta.
- Guest progress stored in a signed cookie without personal data.
- WooCommerce coupon generation after the required number of unique clues is collected.
- Percentage, fixed-cart, and free-shipping rewards.
- Coupon expiry, minimum spend, usage limit, individual use, and optional auto-apply.
- Temporary storefront notices for clue collection, reward unlock, and coupon auto-apply.
- Image-based clues with bundled transparent PNG markers for key, gem, and map.
- Bundled Italian, Spanish, and German translations.

## Next Milestones

1. Multiple campaigns with date windows and campaign status.
2. Campaign analytics: starts, clues collected, completions, coupon redemptions, and generated revenue.
3. Gutenberg block for clue placement and progress output.
4. Visual clue styles and campaign themes.
5. Customer segmentation rules for roles, products, categories, and cart conditions.
6. Optional email capture flow for guest users before coupon reveal.
7. WordPress.org assets, screenshots, and Plugin Check hardening.

## WordPress.org Notes

- Keep rewards deterministic and clearly described.
- Avoid intrusive dashboard notices or upsell prompts.
- Do not collect personal data unless explicitly required and documented.
- Prefer WooCommerce CRUD APIs for coupons and customer-facing commerce data.
