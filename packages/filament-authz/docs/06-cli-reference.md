---
title: CLI Reference
---

# CLI Reference

Filament Authz provides several artisan commands to maintain your authorization system.

## authz:policies
Generate Laravel policies for your resources.

```bash
php artisan authz:policies {--panel=} {--force}
```

- `--panel`: Specify which panel to scan for resources.
- `--force`: Overwrite existing policy files.

## authz:super-admin
Create or assign the Super Admin role.

```bash
php artisan authz:super-admin {--user=}
```
This ensures a role with universal bypass exists and can optionally assign it to a user.

## authz:sync
Sync roles and permissions from config (if using the sync feature).

```bash
php artisan authz:sync
```

## authz:discover
Preview the entities discovered by the package.

```bash
php artisan authz:discover {--panel=}
```
Useful for debugging which resources/pages are being picked up by the discovery service.

## authz:seeder
Generate a production-ready seeder for your roles and permissions.

```bash
php artisan authz:seeder
```
