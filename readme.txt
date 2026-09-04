=== Sync Meta Flow ===
Contributors: taibur-rahaman
Tags: woocommerce, facebook, meta, attribution, analytics, ecommerce, roas
Requires at least: 6.4
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.2.0
License: GPLv2 or later

WooCommerce order-flow tracking and Meta attribution for F-commerce stores, with purchase-to-delivery revenue intelligence.

== v1.2 features ==
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
* Manual Sync Meta Spend Now action with nonce and capability checks.
* Imported API spend is stored separately from manual spend entries.

== Important ==
* Meta Ads Sync requires a Meta Ad Account ID and an access token with permission to read Ads Insights.
* The current importer requests ad-level daily spend and stores it as the ad account currency configured by the plugin's spend pipeline. Verify the currency shown in Ad Spend & ROAS before using financial reports.
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

== Roadmap ==
* Courier integrations and delivery webhooks.
* First-touch / last-touch attribution controls.
* AI customer quality and cancellation insights.
* Multi-store SaaS backend.
