---
title: Filament rewards overview
---

# Filament rewards overview

`aiarmada/filament-rewards` is the Filament v5 admin adapter for `aiarmada/rewards`. It ships ten resources — programs, awards, appeals, funding, payees, payouts, benefit items, benefit grants, recoveries, and the fraud queue — with tables, forms, infolists, and lifecycle actions that call the guarded core actions.

The adapter owns no domain logic: every mutation runs through a core action, and every table query scopes through the program tenant boundary (see [configuration](03-configuration.md)).

Related: [installation](02-installation.md), [configuration](03-configuration.md), [usage](04-usage.md), [troubleshooting](99-troubleshooting.md).
