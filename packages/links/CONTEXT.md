---
title: Links Context
package: links
status: current
surface: domain
family: growth-and-incentives
keywords:
  - link
  - short-link
  - redirect
  - click-tracking
  - cloaked-url
  - utm
---

# Links Context

## Snapshot

- Composer: `aiarmada/links`
- Role: Generic tracked-link management: cloaked redirects, click capture, expiry/limits, domain events.
- Triggers: link, short-link, redirect, click-tracking, cloaked-url, utm
- Search first: `src/Models, src/Actions, src/Events, src/Contracts, config, docs`
- Related: `filament-links`, `signals`, `affiliates`, `communications`
- Paired: `filament-links` (Filament admin adapter)

## Read next

1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-links/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails

- Owns models, actions, services, events, calculations, and persistence rules.
- Stays dependency-free of sibling domain packages. Third parties integrate by listening to `src/Events`, never by direct queries.
- Slugs are globally unique: the public redirect route resolves without owner context.
- If admin UI changes too, audit `filament-links`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast

- Use when: Cloaked/redirect links, click counting, UTM management, link expiry or limits.
- Skip when: Inbound affiliate programs with commissions — see affiliates; analytics dashboards — see signals; message delivery tracking — see communications.
- Owner/security: Owner-scoped models, global slug namespace. Link management must stay behind auth; the redirect route is public by design.
