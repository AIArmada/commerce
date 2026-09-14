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

## Relation managers

- `CommunicationThreadResource` shows thread communications via `CommunicationsRelationManager`
- `CommunicationResource` shows `DeliveriesRelationManager` and the event `CommunicationTimelineRelationManager` on the view page

Both resolve through the owner-scoped parent record.

## Delivery status widget

`DeliveryStatusOverviewWidget` buckets all 19 `DeliveryStatus` cases into five
stats (Pending, Sent, Delivered, Failed, Suppressed) so the buckets reconcile
with the delivery total.

## Authorization

These resources are visible to any authenticated panel user; row-level
isolation comes from owner scoping, not roles. If least-privilege access is
required, gate panel access in the host app or add resource policies there.
