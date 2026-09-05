=== Sync Meta Flow ===
Contributors: taibur-rahaman
Tags: woocommerce, facebook, meta, attribution, analytics, ecommerce, roas, courier, delivery
Requires at least: 6.4
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 3.5.0-beta.1
License: GPLv2 or later

WooCommerce revenue intelligence for Meta-driven stores. Sync Meta Flow connects advertising attribution, WooCommerce order milestones, courier delivery outcomes and realized revenue so merchants can optimize for delivered business outcomes rather than purchases alone.

== Description ==

Sync Meta Flow helps Meta-driven WooCommerce stores measure delivered business outcomes — not purchases alone — with attribution, order-flow, courier intelligence, profitability estimates, and an advisory Decision Center.

V3 beta adds controlled automation, advanced attribution models, courier intelligence, commercial entitlements, and an AI merchant assistant. All V3 capabilities are disabled by default.

This 3.5.0-beta.1 line is a beta release. Use it for evaluation or staging; do not treat it as fully staging-validated production-stable software until that validation is complete.

Official installable ZIP: https://github.com/Taibur-Rahaman/Sync-Meta-Flow/releases/tag/v3.5.0-beta.1

== 3.5.0-beta.1 — AI Merchant Assistant ==
* Added AI intelligence layer with swappable provider, safe context builder, explainable answers, and no autonomous execution.
* AI may rank deterministic recommendations but cannot override automation policy.
* Published customer-ready ZIP on GitHub Releases for WordPress upload install.

== 3.4.0-beta.1 — Commercial SaaS Foundation ==
* Added merchant identity, plan/capability entitlements, license states (incl. trial/grace), opt-in aggregate telemetry, and WordPress-standard update compatibility snapshot.
* Entitlement checker centralizes capability gates; expired licenses never delete store data.

== 3.3.0-beta.1 — Advanced Courier Intelligence ==
* Added V3 courier intelligence: normalized shipment/timeline, provider performance, customer/shipment risk heuristics, advisory optimization.
* Reuses V2 courier recovery/timeline/health; no automatic provider reassignment; flag defaults off.

== 3.2.0-beta.1 — Advanced Attribution ==
* Extended attribution models with position-based and time-decay estimates while preserving first/last/assisted.
* Added V3 attribution pipeline: touchpoint normalization, quality scoring, model comparison, and campaign intelligence via profitability adapters.
* Advanced attribution UI remains behind `smf_v3_advanced_attribution` (default off).

== 3.1.0-beta.1 — Controlled Automation ==
* Added V3 controlled automation pipeline: observe → recommend → explain → approve → execute → verify → audit.
* Defaults remain safe: V3 off, automation off, mode=observe, dry-run enabled; CRITICAL actions never autonomous.
* Allowlisted internal actions only (diagnostics refresh, CAPI/courier retry adapters, attribution refresh, acknowledge).
* Bounded audit/idempotency/approval stores with secret redaction and automation health aggregates.

== v2.1.0 — Production release hardening ==
* Promoted the plugin version to 2.1.0 after completing profitability, executive dashboard and merchant decision intelligence phases.
* Added Decision Center recommendations for campaign scale/stop/review, margin risk, courier health/economics and CAPI reliability.
* Added estimated contribution-profit and contribution-margin intelligence with configurable COGS, payment fees and courier delivery/return costs.
* Added Executive Dashboard combining revenue, profitability, campaign, courier and system-health signals.
* Hardened uninstall cleanup so the actual courier recovery retry cron is removed when explicit data deletion is enabled.
* Preserved conservative uninstall behavior: plugin data remains unless the merchant explicitly enables deletion.

== v2.1.1 — Stability and compatibility ==
* Fixed a PHP parse error in Courier Operations that prevented the plugin from loading.
* Added reusable compatibility and readiness checks for PHP, WordPress, WooCommerce, HPOS, database tables, cron and schema state.
* Integrated compatibility checks into Diagnostics without exposing credentials.
* Added an explicit schema version option and preserved additive, data-safe upgrade behavior.

== v2.1.2 — Integration QA and financial correctness ==
* Corrected percentage-based payment fee calculation in contribution profitability reporting.
* Added deterministic dependency-free behavioral and contract tests with no live API calls.
* Documented local testing, staging-only validation, and known test limitations.

== v2.1.3 — Compatibility and security hardening ==
* Hardened privacy export/erasure for linked tracking sessions and events.
* Recovered stale CAPI queue locks and bounded courier transport retries.
* Corrected campaign-level payment fee percentages and courier failed-status consistency.
* Validated thank-you page order keys and stopped rendering saved courier credentials.
* Made Meta spend replacement transactional and expanded compatibility severity reporting.

== v2.1.4 — Merchant Setup Assistant ==
* Added an optional seven-step Setup Assistant with deterministic readiness scoring.
* Added explicit compatibility, Meta, attribution, courier, profitability, and finish states.
* Preserved existing merchant settings and made optional integrations skippable.
* Fixed admin notice lifecycle handling for the Setup Assistant.

== v2.2.0 — Performance and scale hardening ==
* Added bounded 90-day order-flow metrics with request-level memoization.
* Added additive reporting indexes for order events, spend currency/date, and courier timelines.
* Memoized observability aggregation within an admin request.
* Recorded schema version 1.1 for the additive index upgrade.

== v3.0.0-beta.1 — V3 architecture foundation ==
* Added additive V3 contracts, normalized domain values, typed event envelopes, adapters, and a lightweight container.
* Kept V3 behavior disabled by default; existing V2 modules, settings, tables, and merchant data remain authoritative.
* Added fail-safe bootstrap and documented migration, rollback, privacy, and dependency boundaries.

== Decision Center ==
Decision Center turns observed financial and operational signals into advisory merchant actions:
* SCALE — positive contribution profit, margin at least 20% and ROAS at least 2×.
* STOP/REVIEW — campaign spend with negative estimated contribution profit.
* WATCH — low contribution margin on campaigns with spend.
* Courier health/economics warnings from observed provider performance and financial impact.
* CAPI failure and backlog alerts.
* Overall negative contribution-profit warning.

Recommendations are advisory only. Sync Meta Flow never automatically changes ad budgets, orders or courier routing.

== Executive Dashboard ==
The Executive Dashboard provides a 7/30/90-day management view of:
* Purchase and delivered revenue.
* Estimated contribution profit and margin.
* Ad spend and ROAS.
* Delivery, cancellation and return quality.
* Campaign and courier leaderboards.
* Courier health and CAPI system health.
* Attention alerts and quick links into operational screens.

== Profitability and financial reporting ==
Contribution profitability is an estimated operating metric, not accounting-grade net profit. The calculation can include:
* Meta ad spend matched at campaign level.
* Delivered revenue and returned/cancelled outcomes from the purchase cohort.
* Configurable COGS percentage.
* Configurable payment-fee percentage.
* Configurable courier delivery cost per delivered order.
* Configurable courier return cost per returned order.

Supported attribution views are Last Touch, First Touch, First + Last 50/50 and Assisted. Attribution models are alternative analytical views and must not be added together. Campaign spend must use the same currency as revenue before ROAS or contribution economics are interpreted.

== Courier Operations ==
Courier Operations is an operational intelligence layer, not a fraud engine. It provides:
* Dispatch queue for recent confirmed/shipped orders without courier tracking.
* Customer history risk score from previous WooCommerce orders matched by billing email or, when email is unavailable, phone.
* Delivery, return and cancellation history used as dispatch signals.
* Configured-provider recommendations without inventing undocumented courier APIs.
* Provider webhook event/processed/failed counts.
* Provider health, processing SLA and delivery SLA observations.
* Courier financial/operational impact reporting.
* Configurable customer-risk history window and merchant-configured SLA thresholds.

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
* Contribution profit is estimated from merchant-configured assumptions and observed order/courier outcomes; it is not accounting-grade net profit.
* First-touch and last-touch ROAS are alternative attribution views. Never add them together.

== Privacy and data ==
Sync Meta Flow can store attribution identifiers, tracking events, WooCommerce order-flow events and courier event payloads. Depending on configuration, hashed customer email/phone and Meta browser identifiers may be sent to Meta for Conversions API events. Courier integrations can transmit order/delivery data to the selected courier.

The plugin integrates with WordPress privacy-policy guidance, personal-data export and personal-data erasure for plugin-owned order-related records. Tracking sessions that cannot be linked to an identified customer are not guessed or indiscriminately deleted by the privacy eraser.

Uninstall preserves data by default. When the merchant explicitly enables deletion, plugin-owned tables, options and scheduled jobs are removed, including the courier recovery retry job.

== Installation ==
1. Download `sync-meta-flow-3.5.0-beta.1.zip` from GitHub Releases:
   https://github.com/Taibur-Rahaman/Sync-Meta-Flow/releases/tag/v3.5.0-beta.1
2. In WordPress go to Plugins → Add New Plugin → Upload Plugin, select the ZIP, Install Now, then Activate.
3. Confirm WooCommerce is installed and active. Existing installations automatically run the versioned schema/option upgrade.
4. Open Meta Flow > Setup and configure the Meta Pixel and optional CAPI access token.
5. Open Meta Flow > Diagnostics and resolve blocking checks.
6. Open Meta Flow > Meta Ads Sync, enter the Meta Ad Account ID, save and run an initial sync.
7. Confirm the detected ad-account currency before interpreting financial reports.
8. Open Meta Flow > Profitability to configure COGS, payment fees and courier cost assumptions.
9. Review Meta Flow > Executive Dashboard and Meta Flow > Decision Center for merchant-level actions.
10. Configure Courier & Delivery and Courier Operations if a courier workflow is needed.
11. Leave V3 beta flags off unless you intentionally enable automation, advanced attribution, courier intelligence, commercial, or AI features.
12. Do not upload a full repository ZIP that includes `tests/` or `.git/`. Use the official Releases asset only.

== Setup Assistant ==
The optional Setup Assistant reviews compatibility, Meta tracking, attribution, courier readiness and profitability assumptions without changing existing merchant settings. Meta and courier setup can be skipped explicitly. The readiness score is a deterministic orientation aid; it is not a guarantee of external API, consent, delivery, or accounting outcomes. Existing stores are not redirected into the assistant.

== System Health and Observability ==
Diagnostics includes a bounded operational view of CAPI queues, courier webhook processing, attribution activity, order lifecycle activity, compatibility, schema and cron. It reports OK, warning, or blocking states with safe next actions. Support snapshots use aggregate metrics and redact credentials, secrets, authorization headers, raw payloads and unnecessary customer/order data.

== Production checklist ==
* Use HTTPS.
* Use a dedicated Meta access token with only the permissions required by the store.
* Never paste Meta or courier credentials into GitHub issues, screenshots, logs or public documentation.
* Configure a real server cron for low-traffic stores instead of relying exclusively on WP-Cron.
* Review privacy/consent requirements for tracking and third-party data sharing in the store's jurisdiction.
* Test Purchase deduplication, delivery transitions, courier retries, risk scoring, returned orders and Decision Center thresholds on a staging store before production.
* Verify Meta account currency and attribution IDs before evaluating ROAS.
* Configure realistic COGS, payment-fee and courier-cost assumptions before using contribution-profit recommendations.
* Monitor Meta Flow > Diagnostics and Meta Flow > Courier Recovery after launch.
* Confirm WooCommerce HPOS and current WooCommerce compatibility on the target store.

== Release status ==
3.5.0-beta.1 completes the V3 beta roadmap through AI Merchant Assistant. V2 remains the default operational path; V3 features stay disabled until explicitly enabled. Live staging validation is still required for each merchant's Meta account, WooCommerce version, theme, consent configuration and courier provider. Contribution-profit and AI answers remain advisory. Do not treat this beta as a fully staging-validated production release.

Installable customer ZIP: https://github.com/Taibur-Rahaman/Sync-Meta-Flow/releases/tag/v3.5.0-beta.1

== Changelog ==
= 3.5.0-beta.1 =
* Added AI merchant assistant with swappable provider, safe context builder, explainability, and no autonomous execution.
* Completed commercial entitlement foundation and GitHub Releases packaging for WordPress upload install.
= 3.4.0-beta.1 =
* Added merchant identity, plan/capability entitlements, license states, and opt-in aggregate telemetry foundation.
= 3.3.0-beta.1 =
* Added V3 courier intelligence with advisory provider recommendations (no auto-reassignment).
= 3.2.0-beta.1 =
* Extended attribution with position-based and time-decay estimates plus quality scoring pipeline.
= 3.1.0-beta.1 =
* Added controlled automation pipeline with policy, approval, idempotency, dry-run, verification and audit.
= 3.0.0-beta.1 =
* Added the additive V3 contract, domain, event, adapter, container, and feature-flag foundation.
* Preserved V2 behavior and documented migration and rollback boundaries.

= 2.2.0 =
* Added bounded order-flow metrics and request-level calculation reuse.
* Added additive reporting indexes and schema version 1.1.
* Memoized observability calculations within admin requests.

= 2.1.4 =
* Added the optional Setup Assistant and deterministic readiness score.
* Preserved existing settings and added explicit optional integration skips.
* Fixed Setup Assistant admin notice lifecycle handling.

= 2.1.2 =
* Corrected percentage-based payment fee calculation in contribution profitability.
* Added deterministic Phase 2.16 behavioral and contract checks.

= 2.1.3 =
* Hardened privacy, compatibility, queue recovery, courier retry, credential, order-key, and Meta spend replacement behavior.
* Added Phase 2.17 deterministic security and compatibility regression checks.

= 2.1.1 =
* Fixed a PHP parse error in Courier Operations.
* Added reusable compatibility and schema readiness checks.

= 2.1.0 =
* Promoted release version after completion of merchant decision intelligence.
* Added Decision Center advisory recommendations.
* Added Executive Dashboard management view.
* Added estimated contribution profitability and configurable fulfillment economics.
* Hardened courier recovery uninstall cleanup.
* Updated production onboarding and verification guidance.

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
