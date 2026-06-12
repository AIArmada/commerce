---
title: Addressing Consumer Adoption Update Notes
---

# Update Notes

This documentation pack adds a complete adoption strategy for other packages using `aiarmada/addressing`.

## Major decisions

- Do not treat `aiarmada/addressing` as a forced table migration for every package.
- Treat `aiarmada/addressing` as a canonical address language first.
- Use physical `addresses` / `addressables` storage only for reusable, mutable addresses.
- Use snapshots or JSON casts for orders, shipments, event publications, tax contexts, and provider payloads.
- Keep config-only addresses as strings unless structured rendering is needed.
- Keep IP/resolved-location data separate from full postal addresses.

## Included package playbooks

- customers
- orders
- events / venues / institutions
- chip
- shipping
- signals
- jnt
- commerce-support
- cashier
- tax
- docs
- cashier-chip

## Agent safety

The rollout guide splits work by package and surface so multiple agents can work in parallel without modifying the same files.
