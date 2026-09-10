---
title: Filament Communications Overview
---

# Filament Communications Overview

The `aiarmada/filament-communications` package provides a Filament v5 admin interface for the communications domain. It is a read-focused operational UI with one guarded operational action for retrying failed deliveries.

## Resources

- **CommunicationResource** — list and view communications with status, category, direction, and priority filters
- **CommunicationDeliveryResource** — operational delivery list with timeline of attempts and events plus a guarded retry action for failed deliveries
- **CommunicationThreadResource** — thread list and timeline view
- **CommunicationTemplateResource** — create/edit templates with version management
- **CommunicationPreferenceResource** — manage recipient channel/category preferences
- **CommunicationSuppressionResource** — create, inspect, and lift suppressions
- **CommunicationBatchResource** — batch progress and cancellation

## Principles

- Adapter only; no domain ownership
- All queries and action handlers are owner-scoped
- Navigation group reads from config (`filament-communications.navigation.group`)
- No static `$navigationGroup` property
- No direct provider API calls from Livewire requests
- Delivery retry delegates to the core `RetryCommunicationDeliveryAction` and revalidates ownership with `OwnerWriteGuard`
- Sensitive destinations display masked hints by default
