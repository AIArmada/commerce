---
title: Authz Context
package: authz
status: current
surface: core
family: foundation
---

# Authz Context

## Snapshot
- Composer: `aiarmada/authz`
- Role: Framework-agnostic Spatie Permission integration, UUID permission schema, scopes, wildcard permissions, and impersonation services.
- Search first: `src/Support`, `src/Services`, `src/Concerns`, `src/Console`, `config`, `database`
- Related: `commerce-support`, `filament-authz`, `membership`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../commerce-support/CONTEXT.md`
6. `../filament-authz/CONTEXT.md` for panel UI or impersonation actions
7. `docs/02-installation.md` for setup and publishing

## Guardrails
- Owns generic authorization, scope resolution, permission models, migrations, and impersonation state.
- Keep Filament resources, translations, and render middleware in `filament-authz`.
- Restore Spatie team context in `finally` blocks and keep request state safe under Octane.
