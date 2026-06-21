---
title: Filament Communications Context
package: aiarmada/filament-communications
status: current
surface: filament
family: communications
---

# Filament Communications Context

## Snapshot

- Composer: `aiarmada/filament-communications`
- Role: Filament v5 admin interface for communications — read-focused list and view for messages, deliveries, threads, templates, preferences, suppressions, and batches.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/RelationManagers`, `config`
- Related: `communications`, `commerce-support`, `filament-commerce-support`

## Read next

1. `../communications/docs/01-overview.md`
2. `../communications/docs/03-configuration.md`
3. `docs/01-overview.md`
4. `docs/03-configuration.md`
5. `docs/04-usage.md`

## Guardrails

- Adapter only; no domain ownership.
- All queries and action handlers must be owner-scoped.
- Uses nested `navigation.group` config — never static `$navigationGroup`.
- No direct provider API calls from Livewire requests; invokes core Actions/jobs.
- Sensitive destinations display masked hints by default.
- Raw payload viewers require explicit authorization and redaction.
