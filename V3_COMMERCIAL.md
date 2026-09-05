# Sync Meta Flow V3 Commercial Layer

## Status

Phase **3.4.0-beta.1** foundation (shipped in **3.5.0-beta.1** line). `smf_v3_commercial_enabled` defaults to `no`.

## Plans & capabilities

| Plan | Capabilities |
|------|----------------|
| FREE | basic_tracking, advanced_reports |
| PRO | + advanced_attribution, courier_intelligence |
| BUSINESS | + automation |
| ENTERPRISE | + ai_assistant |

Use `SMF_V3_Entitlement_Checker::check($capability)` — do not scatter `if plan ==` conditionals.

## License states

`ACTIVE`, `TRIAL`, `EXPIRED`, `GRACE`, `SUSPENDED`, `UNLICENSED`

Fail safe: expired/suspended still allows `basic_tracking` so store data remains intact. **Never delete merchant data because of licensing.**

## Merchant identity

Stable internal `merchant_id` fingerprint (local hash). No unnecessary sensitive identifiers.

## Telemetry

Opt-in (`smf_v3_telemetry_opt_in`). Aggregate only (plan/state/version/flags). No PII, order payloads, or secrets. Never blocks plugin operation.

## Updates

WordPress-standard update channel only. No insecure custom updater. Compatibility snapshot exposes version, license state, entitlements, schema version.

## Security

- Never trust client-side entitlement HTML as authority
- Never expose license secrets in frontend
- Never log license tokens (storage strips token/key/secret fields)

## Onboarding

Commercial hint filter integrates with existing onboarding; does not duplicate flows.
