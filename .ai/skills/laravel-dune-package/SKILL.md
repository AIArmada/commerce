---
name: laravel-dune-package
description: Design or structurally convert a Laravel Composer package in the Commerce monorepo, including selecting a layout and reviewing downstream integration surfaces.
---

# Laravel Dune Composer Packages in Commerce

Read the [Commerce Dune guideline](../../guidelines/laravel-dune.blade.php), [package rules](../../rules/features.md), and the owning package's context and canonical docs.

1. Treat a focused package as one capability. Use the existing Composer namespace, provider, and `src/` layout. Assess a modular design only when the requested change involves independently owned capabilities within one package. Check whether an existing Commerce package already owns each capability.
2. For a conversion, inventory public classes and scopes, providers, aliases, facades, config keys, command signatures, publish tags, migrations, and declared optional integrations. Identify callers and route behavior before moving code.
3. Put durable mutations in supported owning-package operations and enforce owner boundaries there. Keep external integrations behind typed seams. Use package providers for registration and config or contracts for host-specific bindings.
4. Verify the changed package, paired adapters, and affected consumers using [laravel-dune-verify](../laravel-dune-verify/SKILL.md). When changing package bootstrapping, verify provider discovery, publishing, and driver swaps through existing Laravel test hosts.

The [distributed Composer package skill](../../../packages/commerce-support/resources/boost/skills/laravel-dune-package/SKILL.md) contains the generic focused and modular layouts, a read-only inventory tool, and scaffold templates for standalone packages. Use Commerce's existing models, migrations, tenancy, compatibility policy, and verification commands when working here.
