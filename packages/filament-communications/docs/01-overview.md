---
title: Filament Communications Overview
---

# Filament Communications Overview

The `aiarmada/filament-communications` package provides a Filament v5 admin interface for the communications domain. It is a read-focused operational UI — no create/edit forms for sent historical facts.

## Resources

- **CommunicationResource** — list and view communications with status, category, direction, and priority filters
- **CommunicationDeliveryResource** — operational delivery list with timeline of attempts and events
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
- Sensitive destinations display masked hints by default
