---
title: Troubleshooting
---

# Troubleshooting

## Cache Issues
If permissions are not reflecting, clear the Spatie and Authz caches:

```bash
php artisan permission:cache-reset
# or
php artisan authz:discover --clear-cache
```

## Entity Not Showing Up
If a Resource or Page isn't showing up in the Role management UI:
1. Ensure it is registered in the current Filament panel.
2. Check if it's in the `exclude` list in your config or plugin settings.
3. If using `scopedToTenant()`, ensure the owner context is set.

## Super Admin Not Bypassing
1. Check the role name matches `config('filament-authz.super_admin_role')`.
2. Ensure the user has that role assigned in the database.

## Method Conflicts
If you have custom `canAccess` logic in your Pages or Widgets, ensure you call the trait's method or integrate the logic correctly. The `HasPageAuthz` trait handles the standard permission checks automatically.
