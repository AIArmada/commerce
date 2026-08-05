---
title: Filament Organizations Usage
---

## Resource surfaces

The adapter provides organization list, create, view, and edit pages, plus
members and invitations relation managers. Ownership transfer and lifecycle
actions call the core package actions.

## Security boundary

Filament is not the tenancy security boundary. The resource query is scoped to
organizations where the authenticated user is a member, and custom action
handlers delegate to the core authorization contract and owner-safe actions.
Applications must keep the same rule in any extensions they add.
