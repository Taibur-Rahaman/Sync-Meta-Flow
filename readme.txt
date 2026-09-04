=== Sync Meta Flow ===
Contributors: taibur-rahaman
Tags: woocommerce, facebook, meta, attribution, analytics, ecommerce, roas
Requires at least: 6.4
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.1.0
License: GPLv2 or later

WooCommerce order-flow tracking and Meta attribution for F-commerce stores, with purchase-to-delivery revenue intelligence.

== v1.1 features ==
* Capture Facebook click and UTM attribution parameters.
* Persist Campaign ID, Ad Set ID and Ad ID attribution on WooCommerce orders.
* Track WooCommerce purchase and order-status transitions.
* Track delivery, cancellation and return outcomes.
* Ad Spend & ROAS admin page with 7/30/90 day reporting.
* Match spend to orders by Ad ID, then Ad Set ID, then Campaign ID.
* Calculate Purchase ROAS, Delivered ROAS and Net Realized ROAS.
* Calculate cost per delivered order, cancellation rate and return rate.
* Retain historical status milestones so later delivery/return events update the original purchase cohort.
* Provide SCALE / WATCH / KILL decision heuristics based on Net Realized ROAS.
* Meta Conversions API queue and event deduplication foundation.

== Important ==
* Ad spend is manually entered in v1.1. Meta Ads Insights API synchronization is planned for a future release.
* ROAS requires spend and order attribution to use the same Campaign / Ad Set / Ad IDs and currency.
* Orders cannot be reliably matched from campaign names alone, so name-only attribution is not force-matched.
* SCALE / WATCH / KILL thresholds are heuristics, not profitability guarantees. Use your actual product margin, shipping, COD and return costs.
* Net Realized Revenue is Delivered Revenue minus Returned/Refunded order value in the selected purchase cohort.

== Installation ==
1. Install and activate WooCommerce.
2. Upload the Sync Meta Flow folder to wp-content/plugins/ or install a ZIP.
3. Activate Sync Meta Flow.
4. Open Meta Flow > Setup and configure Meta credentials when CAPI is ready.
5. Open Meta Flow > Ad Spend & ROAS and record daily Meta spend using the IDs from Ads Manager.

== Roadmap ==
* Meta Ads Insights API automatic spend sync.
* Courier integrations and delivery webhooks.
* First-touch / last-touch attribution controls.
* AI customer quality and cancellation insights.
* Multi-store SaaS backend.
