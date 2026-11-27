---
name: filament-communications-development
description: Develop and integrate Filament Communications with Laravel Dune ownership, security and canonical APIs.
---

# Filament Communications development

- Register `AIArmada\FilamentCommunications\FilamentCommunicationsPlugin` on the panel.
- Resources and widgets are UI adapters. Use owner-scoped queries and core Communications actions for delivery retry, cancellation, suppression and template publication; do not write underlying tables directly.
- Keep redacted payloads and destination secrets protected. Check Filament authorization and owner scope independently; visibility is not authorization.
