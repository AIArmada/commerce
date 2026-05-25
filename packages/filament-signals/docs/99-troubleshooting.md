---
title: Troubleshooting
---

# Troubleshooting

## Plugin Loads But Menu Items Missing

- Check `filament-signals.features.*` flags.
- Confirm the plugin is registered on the expected panel.

## Widgets Not Visible

- Verify `features.dashboard`, `features.widgets`, `features.trend_chart`, and `features.pending_alerts_widget` are enabled as needed.
- Verify the current user has access to the dashboard page where widgets are mounted.

## Saved report selection resets or disappears

- The selected saved report may be inactive.
- The saved report may belong to a different report type.
- The saved report may be outside the current owner scope.

Supported report pages sanitize saved-report ids automatically, so incompatible values are cleared instead of throwing an error.

## Pages Error Due To Missing Data

- Ensure `aiarmada/signals` migrations are migrated.
- Confirm tracked properties/events exist for selected filters.

## Empty Reports

- Verify events are being ingested.
- Run metrics aggregation command from Signals package.
- Check date range, tracked property, segment, and saved-report filters.

## Devices Report Is Empty

- Ensure `filament-signals.features.devices_report` is enabled.
- Ensure `signals.features.ua_parsing.enabled` is enabled in the Signals package.
- Confirm recent sessions actually include parsed device/browser/OS data.

## Pending alerts widget is empty

- This is expected when all alert logs are already marked as read.
- The widget only shows unread `SignalAlertLog` rows visible in the current owner scope.

## IP Address Column Never Appears

- Ensure `signals.features.ip_tracking.enabled` is true.
- Re-ingest or collect fresh sessions after enabling the setting; older sessions will not be backfilled automatically.

## Monetary Cards Or Columns Are Missing

- Check `signals.features.monetary.enabled` in the Signals package.
- This plugin intentionally hides revenue-focused UI when monetary analytics are disabled upstream.
