=== Sync Meta Flow ===
Contributors: taibur-rahaman
Tags: woocommerce, facebook, meta, attribution, analytics, ecommerce
Requires at least: 6.4
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 0.1.0
License: GPLv2 or later

WooCommerce order-flow tracking and Meta attribution foundation for F-commerce stores.

== MVP features ==
* Capture Facebook click and UTM attribution parameters.
* Persist first-party attribution on WooCommerce orders.
* Track WooCommerce purchase and order-status transitions.
* Admin dashboard with tracked orders, delivered/completed and cancelled counts.
* Meta Conversions API foundation for completed/delivered-style order reporting.
* Secure WordPress AJAX endpoint for browser events.

== Roadmap ==
* Proper Meta Pixel + Conversions API event deduplication.
* Campaign/ad/ad-set attribution and reporting.
* Courier integrations and delivery webhooks.
* Delivered-revenue ROAS analytics.
* AI customer quality and cancellation insights.
* Multi-store SaaS backend.

== Installation ==
1. Install and activate WooCommerce.
2. Upload the Sync Meta Flow folder to wp-content/plugins/ or install a ZIP.
3. Activate Sync Meta Flow.
4. Open Meta Flow > Settings and configure Meta credentials when CAPI is ready.

== Development note ==
This is an early foundation release. Do not treat the Meta CAPI delivery mapping as production attribution until event matching, customer data hashing, consent handling, retry queues, idempotency, and API error logging are implemented.
