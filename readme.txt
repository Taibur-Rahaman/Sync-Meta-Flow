=== Sync Meta Flow ===
Contributors: taibur-rahaman
Tags: woocommerce, facebook, meta, attribution, analytics, ecommerce, roas, courier, delivery
Requires at least: 6.4
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.4.0
License: GPLv2 or later

WooCommerce order-flow tracking and Meta attribution for F-commerce stores, with purchase-to-delivery revenue intelligence and courier automation.

== v1.4 features ==
* Capture Facebook click and UTM attribution.
* Persist Campaign ID, Ad Set ID and Ad ID attribution on WooCommerce orders.
* Track WooCommerce purchase and order-status transitions.
* Track delivery, cancellation and return outcomes.
* Ad Spend & ROAS reporting with 7/30/90 day periods.
* Match spend to orders by Ad ID, then Ad Set ID, then Campaign ID.
* Calculate Purchase ROAS, Delivered ROAS and Net Realized ROAS.
* Calculate cost per delivered order, cancellation rate and return rate.
* Retain historical status milestones so later delivery/return events update the original purchase cohort.
* Provide SCALE / WATCH / KILL decision heuristics based on Net Realized ROAS.
* Meta Conversions API queue and event deduplication foundation.
* Meta Ads Sync admin page for automatic spend import from the Meta Ads Insights API.
* Automatic spend refresh every 6 hours through WP-Cron.
* Courier webhook bridge with HMAC-SHA256 authentication.
* Native Steadfast shipment creation from the WooCommerce order screen.
* Save Steadfast consignment ID, tracking code, COD amount and courier metadata.
* Map courier updates to Confirmed, Shipped, Delivered, Returned, Cancelled and other WooCommerce states.
* Pathao and RedX provider presets for normalized webhook flows.

== v1.4 courier setup ==
Open Meta Flow > Courier & Delivery.

Steadfast native API uses the merchant API key and secret key. Shipment creation is available from an individual WooCommerce order after selecting Steadfast. The implementation uses the documented Steadfast API base and create-order contract.

Pathao currently exposes Developer API credentials and webhook configuration through its merchant platform. Sync Meta Flow keeps Pathao in the normalized webhook/provider layer until the merchant-specific API contract is configured.

RedX is supported as a provider preset and normalized webhook source. Native RedX shipment creation is intentionally not guessed without a verified merchant API contract.

== Courier webhook ==
The endpoint is available at `/wp-json/sync-meta-flow/v1/courier/webhook`.
Send JSON such as:
{"order_id":1234,"status":"delivered","tracking_number":"ABC123","provider":"steadfast","cod_amount":1500,"delivery_fee":70}

Sign the exact raw JSON body with HMAC-SHA256 using the configured webhook secret and send:
`X-SMF-Signature: sha256=<hex digest>`

The webhook can identify an order by `order_id` or a previously stored `_smf_courier_invoice`. Tracking code, COD amount and delivery fee can be persisted when supplied.

== Important ==
* Meta Ads Sync requires a Meta Ad Account ID and an access token with permission to read Ads Insights.
* Verify the Meta account currency before using financial reports.
* Never expose courier API keys, secret keys or webhook secrets publicly.
* Native courier API behavior is provider-specific; do not paste credentials into the repository.
* ROAS requires spend and order attribution to use the same Campaign / Ad Set / Ad IDs and currency.
* Orders cannot be reliably matched from campaign names alone, so name-only attribution is not force-matched.
* SCALE / WATCH / KILL thresholds are heuristics, not profitability guarantees. Use your actual product margin, shipping, COD and return costs.
* Net Realized Revenue is Delivered Revenue minus Returned/Refunded order value in the selected purchase cohort.
* WP-Cron depends on WordPress traffic. For low-traffic stores, use a real server cron to trigger wp-cron.php reliably.

== Installation ==
1. Install and activate WooCommerce.
2. Upload the Sync Meta Flow folder to wp-content/plugins/ or install a ZIP.
3. Activate Sync Meta Flow.
4. Open Meta Flow > Setup and configure your Pixel and CAPI access token.
5. Open Meta Flow > Meta Ads Sync and enter your Meta Ad Account ID.
6. Save the sync settings and click Sync Meta Spend Now.
7. Open Meta Flow > Ad Spend & ROAS to review attributed performance.
8. Open Meta Flow > Courier & Delivery and select your provider.
9. For Steadfast, enter the merchant API key and secret key, then create shipments from WooCommerce orders.
10. Configure your courier webhook to POST signed normalized shipment events.

== Roadmap ==
* Verified native Pathao merchant API adapter with configurable store/area mapping.
* Verified native RedX merchant API adapter.
* Courier tracking timeline and idempotent webhook event storage.
* First-touch / last-touch attribution controls.
* AI customer quality and cancellation insights.
* Multi-store SaaS backend.
