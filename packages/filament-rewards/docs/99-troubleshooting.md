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

## Fraud statuses show `unknown`

The queue reads live fraud status through the bound `RewardSourceFactReader`. Bind a reader (or seed facts in development) to populate the column.
