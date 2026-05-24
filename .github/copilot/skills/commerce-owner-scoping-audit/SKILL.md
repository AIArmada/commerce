---
name: commerce-owner-scoping-audit
description: >-
  Audits owner scoping and multi-tenancy safety in AIArmada Commerce packages. Activates when
  working on owner scope, tenant isolation, cross-tenant leaks, global rows, route-model binding,
  download routes, Filament resources, widgets, aggregates, jobs, commands, webhooks, or when the
  user mentions multitenancy, owner, tenant, cross-tenant, scoped queries, or global access.
---

# Commerce Owner Scoping Audit

## When to Apply

Activate this skill when:

- a task involves owner boundaries or multitenancy,
- you suspect a cross-tenant read or write leak,
- you are touching route binding, downloads, widgets, aggregates, jobs, commands, or webhooks,
- you are reviewing `getEloquentQuery()`, `count()`, `sum()`, `exists()`, or `DB::table(...)` paths.

## Read First

1. `CONTEXT.md`
2. `packages/commerce-support/docs/04-multi-tenancy.md`
3. `docs/ai/package-manifests.json`
4. the target package overview and configuration docs

## Audit Checklist

### Boundary and configuration

- Confirm the package has the right owner-boundary semantics.
- Confirm `owner.enabled`, `include_global`, and `auto_assign_on_create` are documented correctly.
- Treat `owner = null` as global-only, never as “all owners”.

### Reads

- Check model queries, resource queries, widgets, tables, dashboards, and export/report queries.
- Check `count()`, `sum()`, `avg()`, and `exists()` paths for explicit owner safety.
- Check `DB::table(...)` paths for explicit owner scoping.

### Writes

- Revalidate inbound IDs inside the current owner scope.
- Do not trust filtered Filament selects or table filters as security.
- Check route-model binding, download routes, and action handlers.

### Non-HTTP surfaces

- Jobs, commands, schedules, exports, reports, health checks, and webhooks must apply explicit owner context.
- Do not rely on ambient web auth outside request lifecycles.

## Expected Output

When you use this skill, report:

- the owner boundary,
- the risky read paths,
- the risky write paths,
- missing explicit owner context,
- the package-scoped tests that should cover the issue.

## Common Pitfalls

- Confusing missing owner context with explicit global context
- Assuming Eloquent global scopes protect `DB::table(...)`
- Forgetting widgets and aggregates are also read surfaces
- Trusting Filament option lists without server-side validation
- Letting route downloads or action handlers resolve cross-tenant records
