---
name: filament-feedback-development
description: Develop and integrate filament-feedback using its owned Dune features.
---

# FilamentFeedback development

Register `FilamentFeedbackPlugin` on the Filament panel. Resources, pages, widgets and exports live in feature-local `EntryPoints/Filament`; panel contributions are registered through feature modules. Delegate writes to feedback Actions, enforce policies and avoid exposing private responses or invitation tokens.

Run the package architecture checks from the monorepo and the affected Pest regression tests before shipping changes. Do not introduce legacy aliases or duplicate write paths.
