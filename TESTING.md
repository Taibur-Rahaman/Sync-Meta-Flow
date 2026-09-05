# Testing

## Local deterministic checks

Run the dependency-free behavioral runner from the plugin root:

```sh
php tests/run.php
```

The runner returns a non-zero exit code when any assertion fails. It uses an in-memory `$wpdb` stub and fixed order-event/spend fixtures. It does not bootstrap WordPress, WooCommerce, Meta, a courier provider, or a production database, and it makes no HTTP requests.

Coverage includes:

- attribution allocation and model normalization;
- order lifecycle transition rules and terminal-state protection;
- profitability costs, contribution profit, margin, ROAS, zero-spend, zero-revenue, and negative-profit cases;
- CAPI retry classification and bounded attempts;
- courier risk and state normalization;
- compatibility reporting and credential redaction;
- courier signature/idempotency/recovery contracts;
- advisory Decision Center rules and browser-event nonce/deduplication contracts.
- compatibility severity levels, minimum runtime metadata, required API checks, and HPOS reporting;
- privacy export/erasure linkage, order-key validation, credential non-exposure, uninstall cleanup, queue lock recovery, retry accounting, and transactional spend replacement.
- onboarding state, seven-step coverage, deterministic readiness scoring, optional skips, blocking compatibility, and credential-safe mutations.
- observability aggregation, normalized health levels, bounded queue/timeline reads, stale/retry/exhausted states, redaction, and Diagnostics integration.

Run PHP syntax validation across the repository:

```sh
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Staging-only validation

A real staging WooCommerce installation is still required to validate activation and upgrade hooks, HPOS behavior, CRUD persistence, scheduled cron execution, admin capability/nonce behavior, REST dispatch ordering, Meta Graph responses, and provider-specific courier shipment/webhook contracts. Those checks must use test credentials and provider sandboxes where available.

The local runner intentionally does not claim coverage of live external API behavior, real database locking, WordPress hook dispatch, browser consent behavior, or a merchant's theme and checkout flow.

Observability checks are deterministic because they validate pure severity/redaction contracts and static integration boundaries. Runtime validation is still required to confirm aggregate queries, admin rendering, WP-Cron scheduling, HPOS, and provider/API behavior in WordPress/WooCommerce.

Phase 2.20 adds deterministic checks for bounded order-flow windows, request-level memoization, additive schema indexes, schema-version recording, and observability report reuse. Query plans and large-store performance require staging profiling with representative data; the local suite does not claim a performance percentage.

The 3.0 beta foundation adds offline checks for V3 contracts, normalized domain values, event identity/version and payload redaction, synchronous dispatch, explicit container resolution, disabled feature flags, fail-safe bootstrap, and architecture coupling boundaries. These checks do not claim WordPress hook, WooCommerce HPOS, database, provider API, or production runtime validation.

Phase 3.1 automation adds deterministic checks for policy defaults, risk ceilings, approval/nonce gates, expiration, idempotency, dry-run, critical-action blocking, secret-stripped evidence/audit, action registry allowlisting, and health aggregates. No network calls are performed.

Phases 3.2–3.5 add offline checks for advanced attribution models/quality/pipeline, courier intelligence heuristics and non-auto-assign optimization, commercial entitlements/license states/telemetry, and AI explainability/redaction/non-execution guarantees.

## Compatibility and security checks

The compatibility report classifies checks as `ok`, `warning`, or `blocking`. Diagnostics treats only `blocking` failures as release-blocking while preserving warnings for optional integrations and unavailable-but-compatible HPOS status APIs. Static review covers REST signature gates, AJAX nonces/capabilities, cron registration and cleanup, prepared SQL/value normalization, WooCommerce CRUD order access, external HTTP status/error handling, secret redaction, privacy erasure, and uninstall preservation.

## Security assumptions

Courier webhook tests use fixed signatures and inspect the existing HMAC/timing-safe comparison and idempotency contracts. No credentials are stored in the repository. Decision Center remains advisory and does not perform budget, order, financial, or courier automation.