---
name: commerce-filament-adapter-development
description: >-
  Develops or audits AIArmada Commerce filament packages. Activates when modifying a filament
  plugin, resource, page, widget, dashboard, action, panel registration, or any `filament-*`
  package; when the user mentions Filament admin UI for Commerce packages; or when a change needs
  to stay in the admin adapter instead of leaking domain logic into Filament.
---

# Commerce Filament Adapter Development

## When to Apply

Activate this skill when:

- the task touches a `filament-*` package,
- you are editing resources, pages, widgets, actions, or plugin registration,
- you need to decide whether logic belongs in the core package or the Filament adapter,
- you are dealing with duplicate or overlapping admin surfaces.

## Read First

1. `CONTEXT.md`
2. `docs/ai/package-manifests.json`
3. the paired core package overview
4. the target Filament package overview and configuration docs
5. the installed plugin class or resource class you plan to change

## Rules

### Keep domain logic in the core package

Core packages should own:

- persistence,
- calculations,
- gateway calls,
- lifecycle transitions,
- integration contracts,
- owner-boundary rules.

Filament packages should own:

- admin resources,
- pages,
- widgets,
- tables,
- forms,
- infolists,
- admin-only action wiring,
- panel plugin registration.

### Respect overlap boundaries

Check for intentionally split or suppressed surfaces, especially:

- `filament-promotions` versus Filament Pricing fallback promotion UI
- `filament-cashier` versus `filament-cashier-chip`
- `filament-chip` versus billing-portal or subscription UI in `filament-cashier-chip`
- `filament-shipping` versus `filament-jnt`
- `filament-affiliates` versus `filament-affiliate-network`

### Owner safety still applies

- Scope resource queries correctly.
- Revalidate submitted IDs inside actions.
- Treat widgets, dashboards, and aggregates as real read surfaces.

## Expected Output

When you use this skill, name:

- the paired core package,
- the Filament surface that should change,
- any core-package follow-up needed,
- owner-scope or authorization checks that must be preserved,
- the docs that should be updated if the public Filament surface changes.

## Common Pitfalls

- Adding business rules directly into Filament resources or widgets
- Forgetting that widgets and pages can create cross-tenant leaks too
- Duplicating admin surfaces that another plugin already owns intentionally
- Documenting fluent APIs that do not match the installed plugin class
