# Filament Permissions — Implementation & Enhancement Checklist

This checklist consolidates migration, stability, and differentiated enhancement work for the `filament-permissions` package on Filament v4 + Laravel 12 + Spatie Permission v6 (PostgreSQL + UUID).

**Last Updated:** November 9, 2025

## Action Namespace & Tables (v4 Migration)
- [x] Replace all `Filament\Tables\Actions\*` with `Filament\Actions\*`.
- [x] Use `table()->recordActions([...])` for row actions (not `->actions([...])`).
- [x] Use `table()->headerActions([...])` for header actions.
- [x] Use `table()->toolbarActions([BulkActionGroup::make([...])])` for bulk actions.
- [x] Relation managers: use `AttachAction`, `DetachAction`, `DetachBulkAction` from `Filament\Actions`.
- [x] Clear caches after edits: `php artisan config:clear && php artisan view:clear && php artisan cache:clear`.

## Service Provider & Views
- [x] Register plugin as singleton in the container.
- [x] `mergeConfigFrom(__DIR__.'/../config/filament-permissions.php', 'filament-permissions')`.
- [x] `loadViewsFrom(__DIR__.'/../resources/views', 'filament-permissions')`.
- [x] Publish views: `__DIR__.'/../resources/views' => resource_path('views/vendor/filament-permissions')`.
- [x] Publish config: `__DIR__.'/../config/filament-permissions.php' => config_path('filament-permissions.php')`.
- [x] Optional `Gate::before` to honor a configurable Super Admin role.

## Spatie Permission DB (PostgreSQL + UUID)
- [x] Publish Spatie migrations.
- [x] Ensure pivot `model_morph_key` columns are `uuid` (not bigint) for `model_has_roles` and `model_has_permissions`.
- [x] Confirm indices and unique constraints reflect guard usage.
- [x] Run migrations successfully.

## Resource Registration & Navigation Visibility
- [x] Register resources/pages/widgets without breaking panel chaining (collect arrays before calling `Panel::resources([...])`, etc.).
- [x] `shouldRegisterNavigation()` checks: allow if user has relevant permission(s) OR the configured Super Admin role.
- [x] Seed/create Super Admin role and assign to an initial user.
- [x] Run permission sync to ensure visibility aligns with policies.

## Ability Mapping (Serializable)
- [x] Use an invokable class (not a closure) for `ability_to_permission` mapping in config.
- [x] Default mapping: `model.action` (e.g., `user.viewAny`, `role.update`).
- [x] Provide clear extension points to override mapping per-model or per-resource.

## Multi-Panel & Guard Support
- [x] Config lists supported panels and guards.
- [x] Generate permissions per configured guard.
- [x] `panel_roles` config defines allowed roles per panel id.
- [x] Middleware `AuthorizePanelRoles` enforced when feature flag `panel_role_authorization` is true.
- [x] Fallback behavior: if no roles configured for panel, allow access (guard only).
- [x] Ensure Super Admin bypasses panel-specific role restrictions (via Gate::before or role list inclusion).
- [x] Database seeder `PanelRolesSeeder` creates Admin and Member roles with panel permissions.
- [x] Comprehensive test coverage (9 Pest tests) for panel role authorization middleware.
- [ ] (Enhancement) Provide a guard/panel matrix page for bulk assignment.

## Permission Diff Engine (Enhancement)
- [ ] Implement `PermissionDiffService` to compare Declared vs Stored permissions.
- [ ] Expose a Filament widget summarizing Missing / Obsolete / Mutated / Orphaned.
- [ ] Console command `filament-permissions:diff` with `--dry` and `--apply`.
- [ ] Log applied changes and cache bust appropriately.

## Role Versioning & Audit (Enhancement)
- [ ] Add `role_versions` (or JSON history column) storing snapshots (permissions, actor, timestamps).
- [ ] Command: list versions and `:rollback <role> <version>`.
- [ ] UI timeline per role; enforce retention policy to cap history growth.

## Import / Export (Enhancement)
- [ ] Import adapters: `--format=shield|json|csv` with preview.
- [ ] Conflict resolution strategy (rename/skip) and validation.
- [ ] Export current roles/permissions to JSON for backups and CI review.

## Tenant Scope (Enhancement)
- [ ] Introduce `ScopeDriverInterface` with a default "none" driver.
- [ ] Optional drivers: `tenant_id` column, domain-based resolver, or custom provider.
- [ ] Tests to guarantee non-leakage across tenants.

## Performance & Cache
- [x] Always call `PermissionRegistrar::forgetCachedPermissions()` after mutations.
- [ ] Warm cache command for production deploys (preload roles/permissions).
- [ ] Chunk large pivot syncs; avoid N+1 during matrix operations.
- [ ] Consider tag-based cache keys (e.g., per guard/panel) for partial invalidation.

## UI / UX Enhancements
- [ ] Permission matrix with filters (guard, panel, resource type, search).
- [ ] Bulk assign/remove via `BulkActionGroup` with confirmation.
- [ ] Visual cues: heat map (assignment density), diff badges.
- [ ] Maintain a futuristic aesthetic consistent with the app's style guidelines.

## Testing (Pest)
- [x] Feature tests for resources and relation managers (actions visible, attach/detach path, policy enforcement).
- [x] Policy authorization tests via resource pages and table actions.
- [x] Multi-panel role authorization tests (9 comprehensive test cases).
- [x] Tests verify Super Admin bypass, role-specific access, no-role denial, feature flag, and empty config fallback.
- [ ] Diff engine tests (missing/obsolete detection and apply flow).
- [ ] Role versioning tests (snapshot creation, rollback).
- [ ] Import/export tests (validation, conflict handling).
- [x] Use Filament test helpers; set current panel where relevant.

## Operational Runbook
- [x] After changes: `php artisan config:clear && php artisan view:clear && php artisan cache:clear`.
- [x] Verify "Access Control" navigation group renders Roles, Permissions, Users correctly.
- [x] Ensure Super Admin role exists and has access to all.
- [x] Verify sync command completes and cache is fresh.

## Completed Features (v1.2.4+)
### Multi-Panel Role Authorization
- ✅ Config-driven panel access control via `panel_roles` mapping
- ✅ `AuthorizePanelRoles` middleware with Super Admin universal bypass
- ✅ Feature flag toggle: `panel_role_authorization`
- ✅ Graceful degradation when no roles configured for panel
- ✅ `PanelRolesSeeder` for Admin and Member role setup
- ✅ 9 passing Pest tests covering all authorization scenarios
- ✅ Admin panel: accessible by Super Admin and Admin roles
- ✅ Member panel: accessible by Super Admin and Member roles
- ✅ Proper exception handling (`AccessDeniedHttpException`)

### Files Modified/Created
- `config/filament-permissions.php` - Added panel_roles config and feature flag
- `src/Http/Middleware/AuthorizePanelRoles.php` - NEW middleware implementation
- `src/FilamentPermissionsPlugin.php` - Middleware registration
- `database/seeders/PanelRolesSeeder.php` - NEW seeder for panel roles
- `tests/Feature/PanelRoleAuthorizationTest.php` - NEW comprehensive test suite
- `docs/CHECKLIST.md` - THIS FILE (moved from /docs)

## Priority Sequence (Updated)
1) ~~Finalize v4 action namespace + relation manager updates~~ ✅ DONE
2) ~~Multi-panel role authorization~~ ✅ DONE
3) Diff engine + CLI (safe evolution foundation)
4) Role versioning + audit trail
5) Guard/panel matrix UI for bulk assignment
6) Import adapters (Shield + generic)
7) Tenant scope drivers + presets
8) Performance and cache optimizations
9) Policy DSL/templates (optional advanced ergonomics)

## Next Recommended Steps
1. Document multi-panel setup in package README with examples
2. Create `php artisan filament-permissions:sync-panels` command to auto-create panel permissions
3. Add panel role management UI in Filament admin panel
4. Implement permission diff engine for safe schema evolution
