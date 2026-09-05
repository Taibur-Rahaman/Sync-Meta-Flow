# Observability

Sync Meta Flow observability is a bounded, aggregate operational view over existing plugin records. It does not create a second event or log system.

## Health model

Every module reports one normalized level:

- `ok`: no operational action is currently indicated.
- `warning`: work is pending, optional setup is incomplete, or recent activity needs review.
- `blocking`: compatibility, schema, stale processing, or exhausted retries require attention.

The report includes a module summary, aggregate metrics, a safe next action, and a sanitized latest failure reason where one exists.

## Monitored modules

- **CAPI:** queued, processing, succeeded, failed, retryable, exhausted, stale processing, oldest pending timestamp, recent failures, and last successful send.
- **Courier:** bounded recent event counts, processing/failed/retryable/exhausted/stale states, recent failures, and provider-health availability.
- **Attribution:** active sessions in the recent window and data-collection warnings.
- **Orders:** recent tracked orders and lifecycle events.
- **System:** compatibility, schema version, WooCommerce availability, PHP/WordPress versions, and background scheduling.

CAPI and courier reads use existing queue/timeline tables and a seven-day window capped at 200 rows. No new observability table or unbounded retention path is introduced.

## Incident interpretation

A failed or exhausted retry means the existing recovery or queue surface should be inspected. A retryable backlog can be normal during temporary provider/API failures, but an aging queue should prompt a cron and credentials/configuration review. Stale processing means an event exceeded the bounded processing threshold and should be inspected before replaying. Provider health remains based on existing courier event data and merchant-configured thresholds; no contractual SLA is invented.

## Safe snapshot

Diagnostics includes aggregate observability metrics suitable for support. It excludes access tokens, API keys, webhook secrets, authorization headers, raw signed payloads, customer identifiers, and order identifiers. Failure text is stripped of markup and redacts common bearer/token/secret/password forms before display.

## V3 automation health

When Controlled Automation is used, `SMF_V3_Automation_Engine::health()` exposes bounded aggregate counters (recommendations, approvals, rejections, expirations, executions, failures, verification failures, dry-runs). These counters live in capped option stores (max 100 entries) and never include secrets or raw provider payloads.

## Runtime limitations

The local deterministic suite tests aggregation contracts, severity classification, redaction, bounded reads, and Diagnostics integration without network calls. Real WordPress admin rendering, database query plans, HPOS behavior, WP-Cron execution, Meta responses, courier provider behavior, and staging traffic require a test WooCommerce installation. External CI is not verified in this checkout.
