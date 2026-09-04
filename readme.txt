=== Sync Meta Flow ===
Contributors: taibur-rahaman
Tags: woocommerce, facebook, meta, attribution, analytics, ecommerce, roas, courier, delivery
Requires at least: 6.4
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 2.0.0
License: GPLv2 or later

WooCommerce revenue intelligence for Meta-driven stores. Sync Meta Flow connects advertising attribution, WooCommerce order milestones, courier delivery outcomes and realized revenue so merchants can optimize for delivered business outcomes rather than purchases alone.

== v2.0.0 — Sell-ready foundation ==
* Production-oriented diagnostics and health checks under Meta Flow > Diagnostics.
* Safe diagnostic snapshot with credentials and secrets redacted.
* Hardened Meta Conversions API transport using Authorization Bearer headers instead of query-string tokens.
* CAPI queue lock, original WooCommerce purchase timestamp and retry/error visibility.
* Meta Ads spend sync now detects and stores the actual Meta Ad Account currency instead of assuming BDT.
* Meta Ads sync uses Authorization headers and prevents overlapping sync runs.
* WordPress privacy-policy guidance for attribution, tracking and courier data.
* Safe uninstall behavior: plugin data is preserved by default unless an explicit delete option is enabled by a future/admin data-management control.
* GPL-2.0-or-later license, security policy and PHP syntax CI.
* Versioned upgrade path from earlier releases.

== Core product ==
* No-code WooCommerce tracking for PageView, ViewContent, AddToCart, InitiateCheckout and Purchase.
* First-touch and last-touch attribution snapshots with Facebook click ID, browser IDs, UTM and Meta campaign/ad-set/ad IDs.
* WooCommerce order journey: Purchase → Confirmed → Shipped → Delivered, with Cancelled and Returned outcomes.
* Purchase, delivered and net-realized revenue reporting.
* Purchase ROAS, Delivered ROAS and Net Realized ROAS.
* Customer Quality scoring using delivery, confirmation, cancellation, return and net-ROAS signals.
* Transparent SCALE / WATCH / KILL heuristic; this is not a profitability guarantee.
* Meta Ads spend import at ad level with campaign/ad-set/ad identifiers.
* Courier webhook bridge with HMAC-SHA256 authentication and idempotent event handling.
* Native Steadfast shipment creation; Pathao and RedX remain provider-specific until their official merchant API contracts are verified.

== Attribution model ==
First-touch represents the earliest attributable campaign recorded for a 30-day tracking session. Last-touch represents the most recent meaningful attributable touch before purchase. Direct visits do not erase an existing attributable touch.

If first-touch and last-touch campaigns differ, the first-touch campaign receives an assisted-conversion signal. These are Sync Meta Flow's deterministic analytics rules and do not reproduce Meta Ads Manager's proprietary attribution model.

WooCommerce also provides its own Order Attribution reporting. Sync Meta Flow is an additional first/last-touch and delivery-outcome intelligence layer, not a replacement for Meta or WooCommerce reporting.

== Financial reporting rules ==
* Ad spend is imported from Meta and stored with the Meta account currency.
* Spend and revenue must use the same currency before ROAS is interpreted.
* Net Realized Revenue = Delivered Revenue − Returned/Refunded order value within the selected purchase cohort.
* Purchase cohort is based on purchase date; later delivery, cancellation and return events update the outcome of that cohort.
* SCALE / WATCH / KILL thresholds are operational heuristics. Merchants should also account for product margin, shipping, COD, payment fees, refunds and other fulfillment costs.
* First-touch and last-touch ROAS are alternative attribution views. Never add them together.

== Meta Conversions API ==
Server-side Purchase and OrderDelivered events are queued and retried. Email and phone are SHA-256 hashed before transmission. Meta browser identifiers may be sent when available for event matching.

Access tokens are stored in WordPress options and are never displayed in diagnostics. Requests use HTTP Authorization headers rather than placing tokens in URLs.

Meta Graph API versions can change. Before a production rollout, verify the configured Graph API version against Meta's current developer documentation and the permissions granted to the token.

== Courier webhook ==
Endpoint: `/wp-json/sync-meta-flow/v1/courier/webhook`

Requests must contain a valid HMAC-SHA256 signature using the configured webhook secret:
`X-SMF-Signature: sha256=<hex digest>`

Example payload:
`{"order_id":1234,"event_id":"evt_123","status":"delivered","tracking_number":"ABC123","provider":"steadfast","cod_amount":1500,"delivery_fee":70}`

Webhook input is authenticated before the order status is changed. Provider event information and delivery state are retained for auditability.

== Installation ==
1. Install and activate WooCommerce.
2. Upload the Sync Meta Flow folder to `wp-content/plugins/` or install an installable ZIP.
3. Activate Sync Meta Flow. Existing installations automatically run the 2.0 schema/version upgrade.
4. Open Meta Flow > Setup and configure the Meta Pixel and optional CAPI access token.
5. Open Meta Flow > Diagnostics and resolve blocking checks.
6. Open Meta Flow > Meta Ads Sync, enter the Meta Ad Account ID, save and run an initial sync.
7. Confirm the detected ad-account currency before interpreting financial reports.
8. Open Meta Flow > Ad Spend & ROAS and Meta Flow > Attribution & ROAS.
9. Configure Courier & Delivery only if a courier workflow is needed.
10. For Steadfast, enter merchant credentials and create shipments from WooCommerce orders.

== Production checklist ==
* Use HTTPS.
* Use a dedicated Meta access token with only the permissions required by the store.
* Never paste Meta or courier credentials into GitHub issues, screenshots, logs or public documentation.
* Configure a real server cron for low-traffic stores instead of relying exclusively on WP-Cron.
* Review privacy/consent requirements for tracking and third-party data sharing in the store's jurisdiction.
* Test Purchase deduplication, delivery transitions, courier retries and returned orders on a staging store before production.
* Verify Meta account currency and attribution IDs before evaluating ROAS.
* Monitor Meta Flow > Diagnostics after launch.

== Support / troubleshooting ==
If events or spend are missing, start with Meta Flow > Diagnostics. Check WordPress/WooCommerce compatibility, database tables, Meta credentials, account currency, scheduled jobs and CAPI queue failures.

For courier issues, verify the webhook secret, provider selection, endpoint and HMAC signature. Native courier API behavior is provider-specific; do not assume undocumented Pathao or RedX endpoints.

== Privacy and data ==
Sync Meta Flow can store attribution identifiers, tracking events, WooCommerce order-flow events and courier event payloads. Depending on configuration, hashed customer email/phone and Meta browser identifiers may be sent to Meta for Conversions API events. Courier integrations can transmit order/delivery data to the selected courier.

The plugin adds privacy-policy guidance. Store owners remain responsible for configuring consent, privacy notices, retention and third-party disclosures appropriate to their business and jurisdiction.

== Security ==
See `SECURITY.md` for responsible vulnerability reporting. Never include secrets or customer credentials in public reports.

== Changelog ==
= 2.0.0 =
* Added production diagnostics.
* Hardened Meta CAPI authentication and queue processing.
* Hardened Meta spend sync and detected account currency.
* Added privacy-policy integration and safe uninstall handler.
* Added GPL license, security policy and PHP lint CI.
* Updated documentation for commercial deployment.

= 1.8.0 =
* Added Customer Quality Intelligence and campaign quality scoring.

= 1.7.0 =
* Added first/last-touch attribution-aware ROAS reporting and assisted conversions.
