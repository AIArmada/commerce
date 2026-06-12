---
title: Overview
---

# Filament Commerce Support

A Filament v5 admin panel package providing runtime navigation management for the AIArmada Commerce monorepo.

## What it does

- **Navigation Manager** — A Filament settings page under "Settings → Navigation" where admins can:
  - Define and reorder navigation groups (label, icon, sort, collapsible)
  - Override individual resource/page navigation settings (visibility, group assignment, sort order, parent item)
- **Runtime config merge** — Merges persisted overrides into `commerce-support`'s navigation config at runtime, so the existing `CommerceNavigation` engine applies them automatically.
- **Zero per-package changes** — No need to modify any existing `filament-*` package. Overrides work by FQCN.

## How it works

```
Admin edits settings → CommerceNavigationSettings persisted
       ↓
NavigationConfigurator merges settings into commerce-support.filament.navigation config
       ↓
CommerceNavigation (from commerce-support) reads config on every panel render
       ↓
Group/item overrides applied to navigation
```
