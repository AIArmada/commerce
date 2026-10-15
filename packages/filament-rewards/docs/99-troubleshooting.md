---
title: Filament rewards troubleshooting
---

# Filament rewards troubleshooting

## Resources show no rows for a scoped owner

Table queries constrain through the program boundary. Programs outside the current owner scope (and their ledgers) are hidden by design. Check the resolved owner and `rewards.owner.include_global` for platform programs.

## The payee queue shows recipients from every program

Payees are recipient-keyed, not program-keyed: a pending destination has no program link yet. The queue is staff-gated instead of program-scoped. Payout requests (which do link payees to programs) are program-scoped.

## The program create form offers no owners

Register the host's owner types under `filament-rewards.owners.types`. Platform programs always work without configuration.

## Actions fail with owner-scope errors

Record actions re-validate through the core write guards. A record that vanished from scope between table render and action run (owner switch, program transfer) refuses instead of writing cross-tenant.

## A decision returns 403 for a user who can see the button

`visible()` and the handler check different things on purpose. The button renders when the Gate ability is granted; the handler re-checks the staff role (`filament-rewards.staff_roles`) **and** the same ability, then aborts `403`. If the user is staff but the ability is missing, define `approve` / `review` / `reverse` / `dismissFraud` / `decide` / `reward-fraud-steward` (or a `Gate::before`) — see [configuration](03-configuration.md). A non-staff user always gets `403` on payees, program creation, and every decision.

## Program creation fails on `owner_record_id`

The handler resolves the alias through `filament-rewards.owners.types` and loads the record with `whereKey(...)`. An alias that is not registered, or a record that no longer exists, is a validation error on `owner_record_id` and writes nothing — even when the posted payload claims a different owner. Register the owner type and re-pick the record.

## Fraud statuses show `unknown`

The queue reads live fraud status through the bound `RewardSourceFactReader`. Bind a reader (or seed facts in development) to populate the column.
