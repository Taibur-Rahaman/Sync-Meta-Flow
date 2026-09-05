# Sync Meta Flow V3 Courier Intelligence

## Status

Phase **3.3.0-beta.1**. Flag `smf_v3_courier_intelligence` defaults to `no`. Existing courier providers/adapters remain authoritative.

## Architecture

```text
Order → Shipment → Provider → Events → Timeline → Outcome → Risk
```

Reuses V2: retry (`SMF_Courier_Recovery`), stale recovery, timeline, state transitions, idempotency, health monitoring (`SMF_Courier_Operations`). Does not duplicate those systems.

## Capabilities

### Provider performance

success rate, delivery rate, return rate, cancellation rate, average processing/delivery time, retry rate, stale event rate, failure rate, SLA compliance

### Customer risk

`low` / `medium` / `high` via existing customer-quality/courier risk signals

### Shipment risk (heuristic estimates)

late delivery, return, cancellation, provider degradation — labeled as estimates, not predictive guarantees

### Recommendations

`PROVIDER_HEALTHY`, `PROVIDER_WATCH`, `PROVIDER_DEGRADED`, `REVIEW_PROVIDER`, `REVIEW_SHIPMENT`

### Optimization

Advisory preferred provider + SLA/return/risk warnings. **`auto_assign` is always false in beta.**

## Limitations

- No undocumented provider APIs
- No automatic provider reassignment
- Risk scores are deterministic heuristics from observed rates and age

## Privacy / performance

No new customer tables. Bounded timelines (max 200 events). Reporting windows 1–90 days.
