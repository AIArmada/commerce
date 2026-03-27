---
title: Troubleshooting
---

# Troubleshooting

## Plugin Loads But Menu Items Missing

- Check `filament-signals.features.*` flags.
- Confirm the plugin is registered on the expected panel.

## Pages Error Due To Missing Data

- Ensure `aiarmada/signals` migrations are migrated.
- Confirm tracked properties/events exist for selected filters.

## Widgets Not Visible

- Verify `features.widgets`, `features.trend_chart`, and `features.pending_alerts_widget` are enabled.
- Verify current user has access to the dashboard page where widgets are mounted.

## Empty Reports

- Verify events are being ingested.
- Run metrics aggregation command from Signals package.
- Check date range and tracked property filters.

## Devices Report Is Empty

- Ensure `filament-signals.features.devices_report` is enabled.
- Ensure `signals.features.ua_parsing.enabled` is enabled in the Signals package.
- Confirm recent sessions actually include parsed device/browser/OS data.

## IP Address Column Never Appears

- Ensure `signals.features.ip_tracking.enabled` is true.
- Re-ingest or collect fresh sessions after enabling the setting; older sessions will not be backfilled automatically.

## Monetary Cards Or Columns Are Missing

- Check `signals.features.monetary.enabled` in the Signals package.
- This plugin intentionally hides revenue-focused UI when monetary analytics are disabled upstream.
