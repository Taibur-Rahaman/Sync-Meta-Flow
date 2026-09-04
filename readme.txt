=== Sync Meta Flow ===
Contributors: taibur-rahaman
Tags: woocommerce, facebook, meta, attribution, analytics, ecommerce, roas, courier, delivery
Requires at least: 6.4
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.3.0
License: GPLv2 or later

WooCommerce order-flow tracking and Meta attribution for F-commerce stores, with purchase-to-delivery revenue intelligence and courier webhook automation.

== v1.3 features ==
* Capture Facebook click and UTM attribution parameters.
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
* Courier & Delivery webhook bridge for normalized shipment updates.
* HMAC-SHA256 webhook authentication.
* Map courier statuses to Confirmed, Shipped, Delivered, Returned and Cancelled WooCommerce states.
* Store courier provider, tracking number and last courier status on the order.
* Provider presets for generic, Pathao, Steadfast and RedX adapters.

== Important ==
* Meta Ads Sync requires a Meta Ad Account ID and an access token with permission to read Ads Insights.
* Verify the Meta account currency before using financial reports.
* Courier integrations use a normalized webhook contract. Provider-specific API credentials and field transformations should live in provider adapters rather than in the core webhook endpoint.
* Never expose the courier webhook secret publicly. Rotate it if it is disclosed.
* ROAS requires spend and order attribution to use the same Campaign / Ad Set / Ad IDs and currency.
* Orders cannot be reliably matched from campaign names alone, so name-only attribution is not force-matched.
* SCALE / WATCH / KILL thresholds are heuristics, not profitability guarantees. Use your actual product margin, shipping, COD and return costs.
* Net Realized Revenue is Delivered Revenue minus Returned/Refunded order value in the selected purchase cohort.
* WP-Cron depends on WordPress traffic. For low-traffic stores, use a real server cron to trigger wp-cron.php reliably.

== Courier webhook ==
The endpoint is available at `/wp-json/sync-meta-flow/v1/courier/webhook`.
Send JSON such as:
{"order_id":1234,"status":"delivered","tracking_number":"ABC123","provider":"pathao"}

Sign the exact raw JSON body with HMAC-SHA256 using the configured webhook secret and send:
`X-SMF-Signature: sha256=<hex digest>`

Supported normalized statuses include confirmed, shipped, in_transit, delivered, completed, cancelled, failed, returned and refunded.

== Installation ==
1. Install and activate WooCommerce.
2. Upload the Sync Meta Flow folder to wp-content/plugins/ or install a ZIP.
3. Activate Sync Meta Flow.
4. Open Meta Flow > Setup and configure your Pixel and CAPI access token.
5. Open Meta Flow > Meta Ads Sync and enter your Meta Ad Account ID.
6. Save the sync settings and click Sync Meta Spend Now.
7. Open Meta Flow > Ad Spend & ROAS to review attributed performance.
8. Open Meta Flow > Courier & Delivery, choose the adapter type and configure a webhook secret.
9. Configure your courier adapter to POST signed normalized shipment events.

== Roadmap ==
* Native Pathao, Steadfast and RedX API adapters.
* First-touch / last-touch attribution controls.
* AI customer quality and cancellation insights.
* Multi-store SaaS backend.
