=== Sync Meta Flow ===
Contributors: taibur-rahaman
Tags: woocommerce, facebook, meta, attribution, analytics, ecommerce, roas, courier, delivery
Requires at least: 6.4
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 2.0.1
License: GPLv2 or later

WooCommerce revenue intelligence for Meta-driven stores. Sync Meta Flow connects advertising attribution, WooCommerce order milestones, courier delivery outcomes and realized revenue so merchants can optimize for delivered business outcomes rather than purchases alone.

== v2.0.1 — Sell-ready hardening ==
* Added unified order journey, privacy exporter/eraser, explicit uninstall deletion and safer diagnostics.
* Hardened courier webhook idempotency and retry behavior.
* Hardened CAPI queue processing with locking, claiming, stale recovery and bounded retries.
* Added attribution model persistence and Attribution Intelligence with Last Touch, First Touch, First + Last 50/50 and Assisted First-Touch Influence views.
* Added attribution-aware campaign decisions, ad-level detail, browser funnel analytics and CSV/JSON exports.
* Added Courier Operations with dispatch queue, customer-risk scoring, provider recommendations and webhook performance reporting.
* Added installer upgrade initialization for core options and courier-operation defaults.

== Courier Operations ==
Courier Operations is an operational intelligence layer, not a fraud engine. It provides:
* Dispatch queue for recent confirmed/shipped orders without courier tracking.
* Customer history risk score from previous WooCommerce orders matched by billing email or, when email is unavailable, phone.
* Delivery, return and cancellation history used as dispatch signals.
* Configured-provider recommendations without inventing undocumented courier APIs.
* Provider webhook event/processed/failed counts.
* Configurable customer-risk history window.

Risk scores are advisory. They must not be treated as definitive fraud, identity or credit decisions. Native shipment creation remains provider-specific. Steadfast is supported by the existing native adapter; Pathao and RedX are not called until their official merchant API contracts are implemented and verified.

== Core product ==
* No-code WooCommerce tracking for PageView, ViewContent, AddToCart, InitiateCheckout and Purchase.
* First-touch and last-touch attribution snapshots with Facebook click ID, browser IDs, UTM and Meta campaign/ad-set/ad IDs.
* WooCommerce order journey: Purchase → Confirmed → Shipped → Delivered, with Cancelled and Returned outcomes.
* Purchase, delivered and net-realized revenue reporting.
* Purchase ROAS, Delivered ROAS and Net Realized ROAS.
* Customer Quality scoring using delivery, confirmation, cancellation, return and net-ROAS signals.
* Transparent SCALE / WATCH / KILL heuristic; this is not a profitability guarantee.
* Meta Ads spend import at ad level with campaign/ad-set/ad identifiers and account currency.
* Courier webhook bridge with HMAC-SHA256 authentication and canonical idempotency.
* Native Steadfast shipment creation; Pathao and RedX remain provider-specific until their official merchant API contracts are verified.
* Production diagnostics and safe diagnostic snapshot.

== Attribution Intelligence ==
The Attribution Intelligence screen provides a persisted merchant-selected reporting model:
* Last Touch — conversion credit goes to the latest attributable touch.
* First Touch — acquisition credit goes to the earliest attributable touch.
* First + Last — 50/50 revenue allocation when the endpoints differ; otherwise 100% to the shared touch.
* Assisted — a separate first-touch influence signal for journeys where first and last touch differ.

Campaign decisions use the selected model's delivered revenue credit divided by campaign spend. Spend is sourced once at campaign level to prevent campaign/ad-set/ad namespace double counting. Attribution models are alternative analytical views and must not be added together.

Only first and last touches are retained. This is deterministic first/last-touch intelligence, not full all-touch multi-touch attribution and does not reproduce Meta Ads Manager's proprietary attribution model.

== Funnel analytics ==
Browser funnel reporting covers PageView, ViewContent, AddToCart, InitiateCheckout and Purchase using event counts and unique sessions. WooCommerce operational reporting separately reconstructs Purchase, Confirmed, Shipped, Delivered, Cancelled and Returned outcomes from the purchase cohort and later order events.

== Financial reporting rules ==
* Ad spend is imported from Meta and stored with the Meta account currency.
* Spend and revenue must use the same currency before ROAS is interpreted.
* Net Realized Revenue = Delivered Revenue − Returned/Refunded order value within the selected purchase cohort.
* Purchase cohort is based on purchase date; later delivery, cancellation and return events update the outcome of that cohort.
* SCALE / WATCH / KILL thresholds are operational heuristics. Merchants should also account for product margin, shipping, COD, payment fees, refunds and other fulfillment costs.
* First-touch and last-touch ROAS are alternative attribution views. Never add them together.

== Privacy and data ==
Sync Meta Flow can store attribution identifiers, tracking events, WooCommerce order-flow events and courier event payloads. Depending on configuration, hashed customer email/phone and Meta browser identifiers may be sent to Meta for Conversions API events. Courier integrations can transmit order/delivery data to the selected courier.

The plugin integrates with WordPress privacy-policy guidance, personal-data export and personal-data erasure for plugin-owned order-related records. Tracking sessions that cannot be linked to an identified customer are not guessed or indiscriminately deleted by the privacy eraser.

Uninstall preserves data by default. When the merchant explicitly enables deletion, plugin-owned tables, options and scheduled jobs are removed.

== Installation ==
1. Install and activate WooCommerce.
2. Upload the Sync Meta Flow folder to `wp-content/plugins/` or install an installable ZIP.
3. Activate Sync Meta Flow. Existing installations automatically run the versioned schema/option upgrade.
4. Open Meta Flow > Setup and configure the Meta Pixel and optional CAPI access token.
5. Open Meta Flow > Diagnostics and resolve blocking checks.
6. Open Meta Flow > Meta Ads Sync, enter the Meta Ad Account ID, save and run an initial sync.
7. Confirm the detected ad-account currency before interpreting financial reports.
8. Review Meta Flow > Ad Spend & ROAS and Meta Flow > Attribution Intelligence.
9. Configure Courier & Delivery and Courier Operations if a courier workflow is needed.
10. For Steadfast, enter merchant credentials and create shipments from WooCommerce orders.

== Production checklist ==
* Use HTTPS.
* Use a dedicated Meta access token with only the permissions required by the store.
* Never paste Meta or courier credentials into GitHub issues, screenshots, logs or public documentation.
* Configure a real server cron for low-traffic stores instead of relying exclusively on WP-Cron.
* Review privacy/consent requirements for tracking and third-party data sharing in the store's jurisdiction.
* Test Purchase deduplication, delivery transitions, courier retries, risk scoring and returned orders on a staging store before production.
* Verify Meta account currency and attribution IDs before evaluating ROAS.
* Monitor Meta Flow > Diagnostics after launch.
* Confirm WooCommerce HPOS and current WooCommerce compatibility on the target store.

== Release status ==
2.0.1 remains a production-oriented release candidate. Core reliability, attribution intelligence and courier operations are implemented at plugin level, but live staging tests remain required for each merchant's Meta account, WooCommerce version, theme, consent configuration and courier provider.

== Changelog ==
= 2.0.1 =
* Added HPOS-compatible unified order journey.
* Added privacy exporter/eraser integration and explicit uninstall data-deletion control.
* Fixed diagnostics health checks and improved safe diagnostic snapshots.
* Improved courier webhook idempotency and retry handling.
* Hardened CAPI queue locking, row claiming, stale recovery and bounded retries.
* Added persisted attribution model selection and Attribution Intelligence reporting.
* Added attribution-aware campaign decisions, ad-level detail, funnel analytics and CSV/JSON exports.
* Added Courier Operations and customer-risk dispatch intelligence.
* Added reporting indexes and expanded behavioral tests.
