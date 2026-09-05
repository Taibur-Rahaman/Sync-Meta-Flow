# Sync Meta Flow V3 Foundation

## Current status

The 2.x line remains the compatibility-preserving production line. V3 beta work is additive and must not require merchants to delete or transform existing order, attribution, courier, spend, or CAPI data.

The repository currently has a procedural WordPress plugin structure. Existing classes remain the compatibility adapters until a replacement service has equivalent tests and observably identical behavior.

## V3 beta implementation

The initial V3 foundation lives under `includes/v3/`:

- **Contracts:** PHP 7.4-compatible interfaces for repositories, events, dispatching, providers, configuration, clock, recommendations, automation, integrations, and observability.
- **Domain:** normalized `OrderContext`, `AttributionContext`, and versioned event envelope objects without WordPress, database, WooCommerce, REST, or provider coupling.
- **Events:** typed order, courier, and Meta event envelopes that remove sensitive payload keys.
- **Infrastructure:** explicit container, WordPress configuration/clock adapters, synchronous dispatcher, and legacy attribution/courier/observability adapters.
- **Services:** `SMF_V3_Result`, composition bootstrap, and a disabled-by-default feature flag.

V3 bootstrap constructs only contracts and adapters. It performs no network calls, database writes, admin registration, or V3 business behavior. Foundation failures are caught so V2 can continue.

## Dependency direction

```text
Admin/API -> Application Services -> Domain
					 ^        ^
				 Contracts  Policies
					 ^
			  Infrastructure / Adapters
```

Future V3 services must receive interfaces/value objects rather than call WordPress options, `$wpdb`, remote HTTP, or WooCommerce objects directly. Existing V2 static modules remain outside this graph and are not rewritten in beta.

## Boundaries

- **Core:** plugin loading, compatibility checks, settings, security, database access, and migration coordination.
- **Orders:** WooCommerce order lifecycle, order metadata, status transitions, and order events.
- **Attribution:** browser/session attribution and reporting models.
- **Meta:** CAPI queue, Meta Ads spend, retry classification, and external request adapters.
- **Courier:** provider credentials, shipment adapters, signed webhook intake, event timeline, retries, and state guards.
- **Profitability:** configurable contribution-profit assumptions and cohort reporting.
- **Intelligence:** advisory executive and decision reporting derived from deterministic stored data.
- **Diagnostics:** compatibility, queue, cron, database, attribution, profitability, and courier readiness reporting.
- **API:** REST and admin-post entry points with explicit authentication, capability, nonce, and signature boundaries.

## Migration principles

1. Add new columns, options, indexes, or tables before changing existing behavior.
2. Record a schema version in `smf_schema_version`.
3. Make every migration idempotent and safe to rerun.
4. Never delete or rewrite merchant data automatically.
5. Record a failed migration and expose it through Diagnostics rather than silently continuing.
6. Keep old class methods available as adapters until all callers migrate.
7. Test activation, upgrade, rollback-safe failure, uninstall preservation, and explicit deletion separately.

The current installer continues to use WordPress `dbDelta()` for the established tables. Future migrations should be explicit methods keyed by schema version rather than coupling destructive changes to plugin version changes.

The beta adds no V3 tables and keeps schema `1.1`. `smf_v3_enabled` defaults to `no`; enabling it currently does not switch merchant-facing behavior. Existing V2 settings, tables, orders, attribution, courier, Meta, onboarding, and observability data remain authoritative.

Phase 3.1 Controlled Automation lives under `includes/v3/Automation/` and is documented in `V3_AUTOMATION.md`. Automation remains disabled by default (`smf_v3_automation_enabled=no`, mode=`observe`, dry-run=`yes`).

Subsequent beta phases (also disabled by default):

- **3.2** Advanced Attribution — `V3_ATTRIBUTION.md` / `smf_v3_advanced_attribution`
- **3.3** Courier Intelligence — `V3_COURIER_INTELLIGENCE.md` / `smf_v3_courier_intelligence`
- **3.4** Commercial entitlements — `V3_COMMERCIAL.md` / `smf_v3_commercial_enabled`
- **3.5** AI Assistant — `V3_AI.md` / `smf_v3_ai_enabled`

Current plugin version: **3.5.0-beta.1**. V2 remains production-authoritative until features are explicitly enabled.

## Security principles

- Validate capabilities and nonces for admin mutations.
- Validate and sanitize external input at the boundary.
- Escape output at the point of rendering.
- Require signed courier webhooks and use timing-safe signature comparison.
- Do not include access tokens, API keys, webhook secrets, or passwords in diagnostic output.
- Treat Decision Center output as advisory. No automatic budget, order, financial, or courier action belongs in V3 without a separately controlled automation phase.

## Testing strategy

External Meta and courier calls must be mocked. Behavioral coverage should exercise order lifecycle transitions, duplicate and out-of-order events, webhook replay and signature failure, queue retry classification, stale processing recovery, profitability assumptions, diagnostics, compatibility checks, and uninstall modes.

V3 tests additionally cover interface loading, value normalization, event identity/version and sensitive-key removal, synchronous dispatch, explicit container resolution, disabled flag behavior, fail-safe bootstrap, and architectural coupling boundaries.

The current checkout has no test directory or CI workflow. These are release prerequisites for a 3.0 beta and must be added before claiming behavioral or cross-version verification.

## Extension points

Provider integrations should implement a narrow adapter contract for credential validation, shipment creation, status normalization, and response/error classification. Reporting modules should consume normalized stored events rather than call external providers during dashboard rendering. New capabilities should be introduced behind existing settings and hooks so existing installations continue to work.

## Migration, rollback, and privacy

Migration is staged: V2 production modules remain active, V3 contracts and adapters are introduced, V3 services consume adapters incrementally, and native implementations may replace adapters only after equivalent behavior and staging validation. Disabling the V3 flag or a failed V3 bootstrap leaves V2 active. No V2 module is removed in beta and no unfinished endpoint is exposed.

V3 domain values contain only normalized minimum-needed fields. They must not receive raw provider payloads, credentials, authorization headers, passwords, unnecessary addresses, or unbounded customer identifiers. Existing WordPress privacy export/erase remains the owner of V2 data; future V3 persistence must reuse that policy.
