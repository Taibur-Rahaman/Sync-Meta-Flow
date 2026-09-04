=== Sync Meta Flow ===
Contributors: taibur-rahaman
Tags: woocommerce, facebook, meta, attribution, analytics, ecommerce, roas, courier, delivery
Requires at least: 6.4
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.7.0
License: GPLv2 or later

WooCommerce order-flow tracking and Meta attribution for F-commerce stores, with first/last-touch attribution, attribution-aware ROAS, purchase-to-delivery revenue intelligence and courier automation.

== v1.7 features ==
* Compare delivered revenue under first-touch and last-touch attribution models.
* Calculate attribution-aware first-touch ROAS and last-touch ROAS against the selected ad spend period.
* Show assisted conversion count and assisted delivered revenue when first-touch and last-touch campaigns differ.
* Add campaign-level spend, first-touch revenue, last-touch revenue and ROAS comparison.
* Keep advertising spend counted once; first-touch and last-touch are alternative models and must not be summed.
* Preserve the v1.6 first-touch / last-touch session and order snapshots.
* Retain all v1.5 courier, Meta CAPI, Meta Ads Sync and delivery intelligence features.

== v1.6 attribution ==
* Preserve first-touch and last-touch attribution independently for each 30-day tracking session.
* Capture Facebook click, UTM, Campaign ID, Ad Set ID and Ad ID attribution.
* Copy both first-touch and last-touch attribution to WooCommerce orders at purchase.
* Compare first-touch discovery, last-touch conversion and assisted campaign influence.
* Add a dedicated Meta Flow > Attribution & ROAS report.

== Attribution model ==
First-touch represents the earliest attributable campaign recorded for the tracking session. Last-touch represents the most recent attributable campaign before purchase. Direct visits do not erase an existing attributable touch because only non-empty campaign/source parameters update last-touch.

If first-touch and last-touch campaigns differ, the first-touch campaign receives an assisted conversion signal. The v1.7 ROAS report uses delivered revenue for both models and compares that revenue with the same spend pool. This is an internal deterministic analytics model; it does not claim to reproduce Meta Ads Manager's proprietary attribution calculations.

WooCommerce's own Order Attribution feature uses a last-click model and provides order attribution reports, so Sync Meta Flow's first/last-touch comparison is intended as an additional layer rather than a replacement for Meta or WooCommerce's native reporting. See WooCommerce's Order Attribution documentation for its own rules and limitations.

== v1.5 courier reliability ==
Courier webhook requests are authenticated before idempotency records are accepted. A provider event ID, when supplied, is retained; otherwise the exact raw request body is hashed so retries of the same payload are safely recognized as duplicates.

Each webhook event stores provider, event ID, payload hash, order ID, status, received time, processing result and response code. Duplicate deliveries return a successful `duplicate` response without applying the WooCommerce status transition again.

== Courier webhook ==
The endpoint is available at `/wp-json/sync-meta-flow/v1/courier/webhook`.
Send JSON such as:
{"order_id":1234,"event_id":"evt_123","status":"delivered","tracking_number":"ABC123","provider":"steadfast","cod_amount":1500,"delivery_fee":70}

Sign the exact raw JSON body with HMAC-SHA256 using the configured webhook secret and send:
`X-SMF-Signature: sha256=<hex digest>`

== Important ==
* Meta Ads Sync requires a Meta Ad Account ID and an access token with permission to read Ads Insights.
* Verify the Meta account currency before using financial reports.
* Never expose courier API keys, secret keys or webhook secrets publicly.
* Native courier API behavior is provider-specific; do not paste credentials into the repository.
* ROAS requires spend and order attribution to use the same Campaign / Ad Set / Ad IDs and currency.
* First/last-touch reports are Sync Meta Flow's own deterministic model and should not be presented as Meta's official attribution numbers.
* SCALE / WATCH / KILL thresholds are heuristics, not profitability guarantees. Use your actual product margin, shipping, COD and return costs.
* Net Realized Revenue is Delivered Revenue minus Returned/Refunded order value in the selected purchase cohort.
* WP-Cron depends on WordPress traffic. For low-traffic stores, use a real server cron to trigger wp-cron.php reliably.

== Installation ==
1. Install and activate WooCommerce.
2. Upload the Sync Meta Flow folder to wp-content/plugins/ or install a ZIP.
3. Activate Sync Meta Flow or allow the v1.7 upgrade to run automatically.
4. Open Meta Flow > Setup and configure your Pixel and CAPI access token.
5. Open Meta Flow > Attribution & ROAS to compare campaign models.
6. Open Meta Flow > Meta Ads Sync and enter your Meta Ad Account ID.
7. Save the sync settings and click Sync Meta Spend Now.
8. Open Meta Flow > Ad Spend & ROAS to review milestone-aware performance.
9. Open Meta Flow > Courier & Delivery and select your provider.
10. For Steadfast, enter the merchant API key and secret key, then create shipments from WooCommerce orders.

== Roadmap ==
* Verified native Pathao merchant API adapter with configurable store/area mapping.
* Verified native RedX merchant API adapter.
* AI customer quality and cancellation insights.
* Multi-store SaaS backend.
