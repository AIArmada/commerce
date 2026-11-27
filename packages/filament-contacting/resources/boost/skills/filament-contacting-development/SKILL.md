---
name: filament-contacting-development
description: Develop and integrate filament-contacting using its owned Dune features.
---

# FilamentContacting development

Register `FilamentContactingPlugin` on the Filament panel. Resources and imports live in feature-local `EntryPoints/Filament`; panel contributions are registered through feature modules. Use contacting Actions for mutations, enforce owner authorization and keep primary selection atomic.

Run the package architecture checks from the monorepo and the affected Pest regression tests before shipping changes. Do not introduce legacy aliases or duplicate write paths.
