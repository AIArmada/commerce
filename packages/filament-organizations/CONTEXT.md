---
title: Filament Organizations Context
package: filament-organizations
status: current
surface: admin
family: foundation
---

# Filament Organizations Context

This package is the Filament v5 adapter for `aiarmada/organizations`. It owns
the generic organization resource, member and invitation relation managers,
and lifecycle/ownership actions. All queries and mutations delegate to the
core package authorization and actions.

Application-specific profile fields, branding, moderation, translations, and
permissions belong in the consuming application.
