# Setup Assistant

The Setup Assistant is an optional admin wizard for Sync Meta Flow. It reviews the existing store configuration and never resets credentials, attribution history, courier history, profitability settings, or plugin data.

## State model

The assistant stores only non-sensitive progress state:

- `smf_onboarding_completed`
- `smf_onboarding_dismissed`
- `smf_onboarding_step`
- `smf_onboarding_attribution_reviewed`
- `smf_onboarding_profitability_reviewed`
- `smf_onboarding_meta_skipped`
- `smf_onboarding_courier_skipped`

Credentials are read only from the existing settings screens and are never copied into onboarding state.

## Steps

1. Welcome and scope.
2. Compatibility using `SMF_Compatibility::report()`.
3. Meta tracking status, with a link to the existing Meta setup screen.
4. Attribution model selection.
5. Courier provider and signed-webhook readiness.
6. Existing profitability assumptions and review state.
7. Readiness score and finish action.

Meta and courier are optional. A skipped optional category is shown as skipped and receives its weighted score explicitly; it is not silently reported as configured.

## Readiness score

The deterministic score uses these weights:

- Core compatibility: 30%
- Meta tracking: 25%
- Attribution review: 10%
- Courier: 20%
- Profitability review: 15%

Core compatibility must be ready for the final page to describe the store as ready. The score is an orientation aid, not a guarantee that external integrations or financial reporting are correct.

## Security and migration behavior

All state mutations require `manage_woocommerce` and a WordPress nonce. State inputs are sanitized and rendered values are escaped. Existing merchants are detected from current settings or plugin event data, but are not redirected into the wizard. Activation only adds missing state options with `add_option`, so existing values remain unchanged.

The Setup Assistant does not create credentials, call external APIs, change ad budgets, route orders, or modify historical data. Real WooCommerce, HPOS, Meta, courier, consent, and browser validation remains a staging concern.
