# Performance and Scale

## Phase 2.20 scope

The performance work is intentionally incremental. Existing V2 tables and behavior remain in place; the upgrade adds indexes through `dbDelta()` and records schema version `1.1`.

## Bounded reporting

Order-flow metrics now use a maximum 90-day window and request-level memoization. Observability uses its existing seven-day window and 200-row cap. Queue workers remain batch-bounded. Existing courier and spend screens retain their bounded result limits.

The primary additive indexes target existing predicates:

- order events by `(order_id, created_at, id)`;
- campaign spend by `(spend_date, currency)`;
- courier events by `(order_id, received_at, id)`;
- courier events by `(received_at, provider)`.

No historical data is deleted or rewritten.

## V3 beta performance conventions

V3 attribution touch lists are capped (50). Courier intelligence timelines are capped (200). Automation audit/idempotency/approval stores are capped (100). Reporting windows remain 1–90 days. No lifetime table scans or N+1 external API loops are introduced in V3 services.

## Caching and invalidation

The new caches are request-local static caches, so they cannot serve stale data across requests and require no invalidation worker. They prevent duplicate calculations when Diagnostics or an admin surface requests the same report more than once during one request.

## Runtime validation still required

The deterministic suite verifies query bounds, cache contracts, schema/index declarations, and existing behavior without a live database. Real query plans, index usage, large-store memory/time behavior, HPOS, WooCommerce CRUD performance, and admin-page rendering require staging profiling with representative data. No performance percentage claim is made.
