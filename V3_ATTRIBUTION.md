# Sync Meta Flow V3 Advanced Attribution

## Status

Phase **3.2.0-beta.1** — Advanced Attribution is implemented. Flag `smf_v3_advanced_attribution` defaults to `no`.

## Models

Existing V2 models are preserved and extended:

| Model | Behavior |
|-------|----------|
| `first_touch` | Acquisition credit |
| `last_touch` | Conversion credit (default) |
| `first_last` | 50/50 when endpoints differ |
| `assisted` | First-touch influence when endpoints differ |
| `position_based` | Configurable first/middle/last weights (estimate) |
| `time_decay` | Exponential decay by touch age (estimate) |

All advanced views are labeled **estimates**, not accounting truth.

## Pipeline

```text
Tracking → Touchpoint normalization → Identity/session association
  → Order association → Attribution model → Revenue allocation
  → Quality scoring → Reporting
```

## Normalized entities

`Touchpoint`, `Campaign`/`AdSet`/`Ad` identifiers, `Source`, `Medium`, `Session`, `Conversion`

Normalization covers UTM fields, Meta campaign/adset/ad IDs, and click IDs when already captured (`fbclid`). Duplicate touchpoints are deduplicated. Volume is bounded (max 50 touches per run).

## Quality score (deterministic)

Weighted dimensions:

- identity completeness (15%)
- timestamp validity (10%)
- source completeness (15%)
- campaign completeness (20%)
- conversion linkage (20%)
- duplicate score (10%)
- attribution coverage (10%)

## Campaign intelligence

Consumes V2 profitability through `SMF_Profitability::report` — does not replace the profitability model. Surfaces spend, revenue, conversions, ROAS, contribution, and estimate labeling.

## Feature flag

- Requires `smf_v3_enabled=yes` and `smf_v3_advanced_attribution=yes` for the Attribution Models admin panel.
- V2 Attribution Intelligence and reporting continue to work regardless.

## Limitations

- V2 storage retains first/last touch only; mid-funnel touches are used only when supplied to the V3 pipeline.
- Position-based and time-decay on first/last alone are two-point estimates.
- No invention of missing tracking data.

## Privacy

No new customer tables. Session keys follow existing UUID validation. Privacy export/erase remain owned by V2 `SMF_Privacy`.

## Performance

Bounded windows (1–90 days), bounded touch lists, no lifetime scans, no N+1 external calls.
