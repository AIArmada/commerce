---
name: filament-signals-development
description: Develop and integrate filament-signals using owner-scoped Laravel Dune feature APIs.
---

# Filament Signals development

- Register `AIArmada\FilamentSignals\FilamentSignalsPlugin` in the Filament panel.
- Configuration, Reporting and Alerts own their respective Filament entry points. Keep business writes in the core Signals feature APIs.
- Use `AIArmada\Signals\Features\Reporting\Api\DashboardRead` for dashboard widgets and `AIArmada\Signals\Features\Reporting\Api\SegmentOptions` for segment choices.
- Enforce both Filament authorization and the core owner boundary; never treat user-selected filters as authorization.
- Remote interaction-rule scans must use the pinned transport with bounded responses and disabled redirects.
