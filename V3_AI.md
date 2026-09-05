# Sync Meta Flow V3 AI Intelligence

## Status

Phase **3.5.0-beta.1**. `smf_v3_ai_enabled` defaults to `no`.

## Architecture

```text
V2/V3 Data → Normalized Intelligence → Deterministic Insights
  → AI Context Builder → AI Provider Adapter → Merchant Assistant
```

## Boundaries

- Swappable `SMF_V3_AI_Provider_Interface` (default: deterministic local provider)
- Context builder uses aggregated metrics/recommendations/courier/alerts only
- Redacts passwords, tokens, API keys, secrets, emails, phones, addresses
- **AI never executes actions** (`can_execute=false`)
- Execution path remains: AI suggestion → deterministic validation → policy → approval → controlled action
- AI may rank deterministic recommendations but **cannot override safety policy**

## Explainability

Every answer includes: Answer, Evidence, Confidence, Recommended next step.

## Limitations

- Default provider is deterministic/offline (no external AI vendor required)
- Does not invent missing metrics — states unavailability explicitly
- No autonomous campaign, credential, or destructive operations
