---
title: Overview
---

# Filament Authz

Filament Authz is a highly sophisticated authorization package for Filament v5, built on top of `spatie/laravel-permission`. It provides an automated, developer-friendly way to manage roles and permissions across multiple panels and multi-tenant environments.

## Features

- **Multi-Panel Support**: Configure different authorization settings for each Filament panel.
- **Tenant Isolation**: Seamless support for multi-tenant applications with scoped roles and permissions.
- **Automatic Discovery**: Automatically discovers Resources, Pages, and Widgets to generate permissions.
- **Enhanced UI**: A beautiful, user-friendly Role resource with master toggles, section icons, and collapsible groups.
- **Wildcard Permissions**: Support for flexible wildcard matching (e.g., `user.*`).
- **Policy Generation**: CLI command to scaffold Laravel Policies based on discovered permissions.
- **Super Admin Bypass**: Built-in bypass logic for a designated Super Admin role.
- **Fluent Plugin API**: A clean, closure-based API for plugin configuration.

## Core Concepts

### Discovery vs. Generation
Unlike other packages that rely on generated permission files, Filament Authz dynamically discovers your Filament entities. This means as you add new Resources or Pages, they are automatically available in the Role management UI without running commands.

### Permission Keys
Permission keys are constructed using a configurable format (default is `kebab-case` with `.` separator). 
Example: `order.view-any`, `user.create`.

### Multi-Tenancy
When `scopedToTenant()` is enabled, roles and permissions are automatically filtered by the current tenant (via `owner_id`/`owner_type`). This uses the standard `commerce-support` multitenancy primitives.
