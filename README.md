# Sync Meta Flow

WooCommerce Meta revenue intelligence: attribution, order-flow, courier outcomes, profitability, decision center, controlled automation, and an optional AI merchant assistant.

**Current version:** `3.5.0-beta.1`  
**Status:** Beta — safe-by-default V3 flags. Not a production-stable release until staging validation is complete.

## Download (WordPress install ZIP)

Customer-ready plugin ZIP (no `tests/`, no `.git/`):

**[sync-meta-flow-3.5.0-beta.1.zip](https://github.com/Taibur-Rahaman/Sync-Meta-Flow/releases/download/v3.5.0-beta.1/sync-meta-flow-3.5.0-beta.1.zip)**

All releases: [GitHub Releases](https://github.com/Taibur-Rahaman/Sync-Meta-Flow/releases)

### Install in WordPress

1. Download `sync-meta-flow-3.5.0-beta.1.zip` from Releases  
2. **Plugins → Add New Plugin → Upload Plugin**  
3. Choose the ZIP → **Install Now** → **Activate**  
4. Ensure WooCommerce is active  

ZIP root folder is `sync-meta-flow/` (WordPress standard).

## Requirements

- WordPress 6.4+  
- PHP 7.4+  
- WooCommerce  

## What this plugin does

- Meta / browser attribution (first-touch, last-touch, first+last, assisted, plus V3 position-based & time-decay estimates)  
- Order lifecycle tracking and CAPI queue  
- Courier intelligence (webhooks, recovery, provider health)  
- Profitability / ROAS / contribution estimates  
- Decision Center (advisory)  
- V3 Controlled Automation (observe → recommend → approve → execute → verify → audit)  
- Commercial entitlement foundation  
- AI Merchant Assistant (grounded, non-autonomous)  

## V3 feature flags (default: off)

| Option | Default |
|--------|---------|
| `smf_v3_enabled` | `no` |
| `smf_v3_automation_enabled` | `no` (mode=`observe`, dry-run=`yes`) |
| `smf_v3_advanced_attribution` | `no` |
| `smf_v3_courier_intelligence` | `no` |
| `smf_v3_commercial_enabled` | `no` |
| `smf_v3_ai_enabled` | `no` |

V2 remains production-authoritative until a V3 capability is explicitly enabled.

## Documentation

- [V3_FOUNDATION.md](V3_FOUNDATION.md)  
- [V3_AUTOMATION.md](V3_AUTOMATION.md)  
- [V3_ATTRIBUTION.md](V3_ATTRIBUTION.md)  
- [V3_COURIER_INTELLIGENCE.md](V3_COURIER_INTELLIGENCE.md)  
- [V3_COMMERCIAL.md](V3_COMMERCIAL.md)  
- [V3_AI.md](V3_AI.md)  
- [TESTING.md](TESTING.md) · [OBSERVABILITY.md](OBSERVABILITY.md) · [PERFORMANCE.md](PERFORMANCE.md) · [ONBOARDING.md](ONBOARDING.md)  
- WordPress.org-style changelog: [readme.txt](readme.txt)  

## Development tests

```bash
php tests/run.php
```

Expect **213 passed, 0 failed** on this beta line. Offline/deterministic only — no live Meta, courier, or AI network calls.

## Security & privacy

- No credentials in source, tests, logs, or diagnostic HTML  
- Privacy exporter/eraser compatible with existing WooCommerce flows  
- Uninstall preserves data unless the merchant enables deletion  

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).

## Important

This **3.5.0-beta.1** build is for evaluation / staging. Do not present it as a fully staging-validated production release until that validation is completed on a real WooCommerce store.
