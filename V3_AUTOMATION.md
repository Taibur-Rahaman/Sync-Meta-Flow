# Sync Meta Flow V3 Controlled Automation

## Status

Phase **3.1.0-beta.1** — Controlled Automation is implemented and disabled by default.

## Pipeline

```text
Observe → Recommend → Explain → Approve → Execute → Verify → Audit
```

## Modes

| Mode | Behavior |
|------|----------|
| `observe` | Recommendations only; no execution |
| `recommend` | Recommendations + explicit approval required |
| `automate` | Low-risk allowlisted actions may run after policy checks; high/critical still need approval |

## Defaults (safe)

- `smf_v3_enabled` = `no`
- `smf_v3_automation_enabled` = `no`
- `smf_v3_automation_mode` = `observe`
- `smf_v3_automation_dry_run` = `yes`
- `smf_v3_automation_max_risk` = `medium`
- CRITICAL risk is **never** autonomous

## Recommendation types

`scale_campaign`, `reduce_spend`, `review_campaign`, `fix_capi`, `review_courier`, `review_return_rate`, `review_margin`

Each recommendation includes: stable ID, type, explanation, evidence (secret-stripped), confidence, severity, risk, affected entity, recommended action, expected effect, expiration.

Recommendations are adapted from the existing V2 Decision Engine (ROAS, contribution profit, margin, CAPI health, courier health).

## Action registry (allowlist only)

| Action | Risk | Mutation |
|--------|------|----------|
| `acknowledge_recommendation` | low | Local acknowledgement |
| `refresh_diagnostics` | low | Reads observability aggregates |
| `retry_capi_event` | medium | Invokes existing CAPI queue processor |
| `retry_courier_event` | medium | Invokes existing courier recovery retry |
| `recalculate_attribution` | low | Normalizes model for reporting refresh |

Arbitrary callbacks, URLs, or request-supplied callables are rejected.

## Policy controls

- Allowed action types (registry ∩ policy)
- Max risk ceiling
- Approval requirements
- Cooldown (default 3600s)
- Rate limit (default 20 executions / hour window)
- Dry-run (default on)

## Approval states

`pending` (implicit), `approved`, `rejected`, `expired`

Approval requires `manage_woocommerce` capability and a valid admin nonce. Duplicate approvals return `duplicate`.

## Idempotency

Every executable intent has an `idempotency_key`. Replay returns the prior result without re-executing.

## Dry-run

Validates the full path (policy → approval → registry → verify → audit) but performs no mutation.

## Verification

After execution (or dry-run), expected local state is checked. Verification failures increment health counters.

## Audit & events

Bounded option stores (max 100 entries) record recommendation, action, actor, approval, execution, verification, timestamps, and failure messages. Secrets are never stored.

Events: `recommendation_created`, `recommendation_approved`, `recommendation_rejected`, `automation_requested`, `automation_started`, `automation_succeeded`, `automation_failed`, `automation_verified`, `automation_expired`.

## Observability

`SMF_V3_Automation_Engine::health()` exposes aggregates: recommendations, approvals, rejections, expirations, executions, failures, verification_failures, dry_runs.

## Storage

No new database tables. Bounded WordPress options:

- `smf_v3_automation_approvals`
- `smf_v3_automation_idempotency`
- `smf_v3_automation_audit`
- `smf_v3_automation_health`
- `smf_v3_automation_cooldowns`

## Admin

When V3 and automation flags are enabled, an **Automation** submenu exposes recommendations, approve/reject/run controls, and health aggregates.

## Limitations

- No autonomous Meta budget changes
- No autonomous courier provider reassignment
- Campaign scale/reduce recommendations remain advisory unless mapped to an allowlisted internal action
- External provider mutations only via existing verified V2 adapters (`process_queue`, `Courier_Recovery::retry`)

## Rollback

Disable `smf_v3_automation_enabled` and/or `smf_v3_enabled`. V2 Decision Center remains authoritative and unchanged.
