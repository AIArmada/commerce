---
title: Membership Context
package: membership
status: current
surface: core
family: foundation
---

# Membership Context

## Snapshot
- Composer: `aiarmada/membership`
- Role: Polymorphic membership applications, invitations, member pivots, and Spatie role synchronization.
- Search first: `src/Actions`, `src/Models`, `src/Services`, `src/Traits`, `config`, `database`
- Related: `authz`, `commerce-support`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../authz/CONTEXT.md`
6. `../commerce-support/CONTEXT.md`
7. `docs/02-installation.md` for setup and pivot generation

## Guardrails
- Owns membership lifecycle records and subject-member pivot orchestration.
- Subject models own authorization and must expose the `HasMembers` relation surface.
- Keep pivot and Spatie role mutations atomic, restore team context, and verify invitation email identity.
