---
name: filament-events-development
description: Develop Filament event administration resources, pages, and widgets using the Events domain workflows and owner-scoped queries.
---

# Filament Events Development

Register AIArmada\FilamentEvents\FilamentEventsPlugin with the panel. Configure resource visibility and form extensions using filament-events.php. Keep UI entry points thin, call Events workflows for lifecycle, moderation, approval and check-in writes, and never treat UI visibility as authorization. Use the active panel actor for approval decisions. Preserve configured model resolution and event ownership scopes; venue and taxonomy catalogs are global by design.
