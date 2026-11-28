---
name: filament-engagement-development
description: Develop and integrate filament-engagement using its owned Dune features.
---

# FilamentEngagement development

Register `FilamentEngagementPlugin` on the Filament panel. Resources and widgets live in feature-local `EntryPoints/Filament`; panel contributions are registered through feature modules. Delegate changes to the owning engagement Actions instead of writing models directly.

Run the package architecture checks from the monorepo and the affected Pest regression tests before shipping changes. Do not introduce legacy aliases or duplicate write paths.
