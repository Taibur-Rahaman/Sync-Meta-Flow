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
* Hardened courier webhook idempotency and retry behavior: processed events remain idempotent while failed events can be retried.
* Hardened CAPI queue processing with atomic queue locking, per-row claiming, stale processing recovery, bounded retries and diagnostics for processing jobs.
* Added attribution model persistence and Attribution Intelligence with Last Touch, First Touch, First + Last 50/50 and Assisted First-Touch Influence views.
* Added attribution-aware campaign decisions, ad-level attribution detail, browser funnel analytics and CSV/JSON exports.
* Added database indexes for purchase/event and funnel reporting workloads.
* Expanded attribution behavioral tests in CI across the supported PHP matrix.

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

== Meta Conversions API ==
Server-side Purchase and OrderDelivered events are queued and retried. Email and phone are SHA-256 hashed before transmission. Meta browser identifiers may be sent when available for event matching.

Queue workers use an atomic global lock plus per-row claiming so concurrent cron invocations cannot normally process the same queued row simultaneously. Rows stuck in `processing` beyond the claim TTL are returned to the retryable queue. Failed requests use bounded backoff and are permanently marked failed after the configured maximum attempts.

Access tokens are stored in WordPress options and are never displayed in diagnostics. Requests use HTTP Authorization headers rather than placing tokens in URLs.

Meta Graph API versions can change. Before production rollout, verify the configured Graph API version and required permissions against Meta's current developer documentation.

== Courier webhook ==
Endpoint: `/wp-json/sync-meta-flow/v1/courier/webhook`

Requests must contain a valid HMAC-SHA256 signature using the configured webhook secret:
`X-SMF-Signature: sha256=<hex digest>`

When a provider event ID is supplied, idempotency is based on provider + provider event ID; otherwise a provider + raw-body hash is used as fallback. A successfully processed event returns a duplicate success on replay. A failed event remains retryable and can be processed again with the same canonical identity.

Webhook input is authenticated before the order status is changed. Payloads are rate-limited and capped by request size before business processing.

== Privacy and data ==
Sync Meta Flow can store attribution identifiers, tracking events, WooCommerce order-flow events and courier event payloads. Depending on configuration, hashed customer email/phone and Meta browser identifiers may be sent to Meta for Conversions API events. Courier integrations can transmit order/delivery data to the selected courier.

The plugin integrates with WordPress privacy-policy guidance, personal-data export and personal-data erasure for plugin-owned order-related records. Tracking sessions that cannot be linked to an identified customer are not guessed or indiscriminately deleted by the privacy eraser.

Store owners remain responsible for configuring consent, privacy notices, retention and third-party disclosures appropriate to their business and jurisdiction.

== Installation ==
1. Install and activate WooCommerce.
2. Upload the Sync Meta Flow folder to `wp-content/plugins/` or install an installable ZIP.
3. Activate Sync Meta Flow. Existing installations automatically run the versioned schema upgrade.
4. Open Meta Flow > Setup and configure the Meta Pixel and optional CAPI access token.
5. Open Meta Flow > Diagnostics and resolve blocking checks.
6. Open Meta Flow > Meta Ads Sync, enter the Meta Ad Account ID, save and run an initial sync.
7. Confirm the detected ad-account currency before interpreting financial reports.
8. Review Meta Flow > Ad Spend & ROAS and Meta Flow > Attribution Intelligence.
9. Configure Courier & Delivery only if a courier workflow is needed.
10. For Steadfast, enter merchant credentials and create shipments from WooCommerce orders.

== Production checklist ==
* Use HTTPS.
* Use a dedicated Meta access token with only the permissions required by the store.
* Never paste Meta or courier credentials into GitHub issues, screenshots, logs or public documentation.
* Configure a real server cron for low-traffic stores instead of relying exclusively on WP-Cron.
* If `DISABLE_WP_CRON` is enabled, verify that a real server cron invokes `wp-cron.php` frequently enough for the store's workload.
* Review privacy/consent requirements for tracking and third-party data sharing in the store's jurisdiction.
* Test Purchase deduplication, delivery transitions, courier retries and returned orders on a staging store before production.
* Verify Meta account currency and attribution IDs before evaluating ROAS.
* Monitor Meta Flow > Diagnostics after launch; overdue or disabled cron jobs are surfaced there.
* Confirm WooCommerce HPOS and current WooCommerce compatibility on the target store.

== Support / troubleshooting ==
Start with Meta Flow > Diagnostics when events or spend are missing. Check WordPress/WooCommerce compatibility, database tables, Meta credentials, account currency, scheduled jobs and CAPI queue failures.

For courier issues, verify the webhook secret, provider selection, endpoint and HMAC signature. Native courier API behavior is provider-specific; do not assume undocumented Pathao or RedX endpoints.

For privacy requests, use WordPress Tools > Export Personal Data or Erase Personal Data. For full plugin removal, enable the explicit deletion option before uninstalling only if the merchant intentionally wants plugin-owned analytics data removed.

== Security ==
See `SECURITY.md` for responsible vulnerability reporting. Never include secrets or customer credentials in public reports.

== Release status ==
2.0.1 remains a production-oriented release candidate. Phase 2.1 reliability/security hardening and Phase 2.2 attribution intelligence are code-complete at the plugin level, but every merchant deployment should still complete live staging tests for its specific Meta account, WooCommerce version, theme, consent configuration and courier provider before relying on financial decisions.

== Changelog ==
= 2.0.1 =
* Added HPOS-compatible unified order journey.
* Added privacy exporter/eraser integration and explicit uninstall data-deletion control.
* Fixed diagnostics health checks and improved safe diagnostic snapshots.
* Improved courier webhook idempotency and retry handling.
* Hardened CAPI queue locking, row claiming, stale recovery and bounded retries.
* Added persisted attribution model selection and Attribution Intelligence reporting.
* Added attribution-aware campaign decisions, ad-level detail, funnel analytics and CSV/JSON exports.
* Added reporting indexes and expanded behavioral tests.

= 2.0.0 =
* Added production diagnostics.
* Hardened Meta CAPI authentication and queue processing.
* Hardened Meta spend sync and detected account currency.
* Added privacy-policy integration and safe uninstall handler.
* Added GPL license, security policy and PHP lint CI.

= 1.8.0 =
* Added Customer Quality Intelligence and campaign quality scoring.

= 1.7.0 =
* Added first/last-touch attribution-aware ROAS reporting and assisted conversions.
