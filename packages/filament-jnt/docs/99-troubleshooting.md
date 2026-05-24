---
title: Troubleshooting
---

# Troubleshooting

## The J&T resources do not appear in the panel

**Likely cause:** the plugin is not registered on the panel, or the feature flags are disabled in
`config/filament-jnt.php`.

**Fix:** register `FilamentJntPlugin::make()` on the panel and confirm the relevant
`features.orders`, `features.tracking_events`, `features.webhook_logs`, or `features.widgets` keys
are enabled.

**Verify:** reload the panel and confirm the J&T navigation items and widget appear.

## Tables show no records even though J&T data exists

**Likely cause:** the core `aiarmada/jnt` package is owner-scoped and the current request is not in
the expected owner context.

**Fix:** confirm owner resolution is working in the host app and that the J&T package can resolve
the same tenant or owner context the Filament panel is using.

**Verify:** compare the same owner context in the core J&T package and in the Filament panel, then
confirm records become visible without removing owner safeguards.

## Raw webhook payloads are missing from the admin UI

**Likely cause:** `features.show_raw_payloads` is disabled.

**Fix:** enable `features.show_raw_payloads` in `config/filament-jnt.php` when you need the debug
surface.

**Verify:** open a webhook log record and confirm the raw payload section is present.

## Tables are not auto-refreshing

**Likely cause:** polling is disabled or set to a longer interval than expected.

**Fix:** set `polling_interval` to the cadence you want, such as `30s`.

**Verify:** leave the table open and confirm fresh records appear on the expected interval.