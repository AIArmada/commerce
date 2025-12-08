# CLI Reference

Complete reference for all artisan commands provided by Filament Permissions.

## authz:sync

Synchronize roles and permissions from configuration.

```bash
php artisan authz:sync [--flush-cache]
```

### Options

| Option | Description |
|--------|-------------|
| `--flush-cache` | Clear Spatie permission cache after sync |

### Description

Reads `config('filament-authz.sync')` and creates:
- All listed permissions for each configured guard
- All listed roles with their assigned permissions

**Example configuration:**

```php
'sync' => [
    'permissions' => [
        'user.viewAny',
        'user.create',
        'order.viewAny',
    ],
    'roles' => [
        'Admin' => ['user.viewAny', 'user.create'],
        'Staff' => ['order.viewAny'],
    ],
],
```

**Usage:**

```bash
# Sync without cache flush
php artisan authz:sync

# Sync and flush cache (recommended for production)
php artisan authz:sync --flush-cache
```

### Output

```
Permissions & roles synced.
```

---

## authz:doctor

Diagnose permission and role configuration issues.

```bash
php artisan authz:doctor
```

### Checks Performed

1. **Invalid Guards** — Roles or permissions with guard_name not in configured guards
2. **Unused Permissions** — Permissions not attached to any role
3. **Empty Roles** — Roles without any permissions

### Output Examples

**Clean state:**
```
No issues detected.
```

**Issues found:**
```
Roles with invalid guard: Legacy Admin
Permissions with invalid guard: legacy.permission
Unused authz: feature.deprecated, old.permission
Roles without authz: Empty Role
Total issues: 4
```

### Exit Codes

| Code | Meaning |
|------|---------|
| 0 | No issues found |
| 1 | Issues detected |

---

## authz:export

Export roles and permissions to JSON file.

```bash
php artisan authz:export [path]
```

### Arguments

| Argument | Default | Description |
|----------|---------|-------------|
| `path` | `storage/permissions.json` | Output file path |

### Output Format

```json
{
    "permissions": [
        {
            "name": "user.viewAny",
            "guard_name": "web"
        },
        {
            "name": "user.create",
            "guard_name": "web"
        }
    ],
    "roles": [
        {
            "name": "Admin",
            "guard_name": "web",
            "permissions": ["user.viewAny", "user.create"]
        }
    ]
}
```

### Usage

```bash
# Default path
php artisan authz:export

# Custom path
php artisan authz:export storage/backups/permissions-2024.json
```

### Output

```
Exported to: storage/permissions.json
```

---

## authz:import

Import roles and permissions from JSON file.

```bash
php artisan authz:import [path] [--flush-cache]
```

### Arguments

| Argument | Default | Description |
|----------|---------|-------------|
| `path` | `storage/permissions.json` | Input file path |

### Options

| Option | Description |
|--------|-------------|
| `--flush-cache` | Clear permission cache after import |

### Expected Format

Same as export format. Permissions are created first, then roles with their assignments.

### Usage

```bash
# Import from default path
php artisan authz:import

# Import from custom path with cache flush
php artisan authz:import storage/migration/old-permissions.json --flush-cache
```

### Behavior

- Creates permissions if they don't exist (by name + guard)
- Creates roles if they don't exist
- Syncs role permissions (replaces existing assignments)

### Output

```
Import completed.
```

### Error Handling

```
File not found: storage/missing.json
Invalid JSON payload.
```

---

## authz:generate-policies

Generate policy classes with permission-based authorization.

```bash
php artisan authz:generate-policies {name}
```

### Arguments

| Argument | Description |
|----------|-------------|
| `name` | Policy class name (e.g., `Post`, `OrderPolicy`) |

### Generated File

Creates `app/Policies/{Name}Policy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('post.viewAny');
    }

    public function view(User $user, Post $post): bool
    {
        return $user->can('post.view');
    }

    public function create(User $user): bool
    {
        return $user->can('post.create');
    }

    public function update(User $user, Post $post): bool
    {
        return $user->can('post.update');
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->can('post.delete');
    }

    public function restore(User $user, Post $post): bool
    {
        return $user->can('post.restore');
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $user->can('post.forceDelete');
    }
}
```

### Usage

```bash
php artisan authz:generate-policies Post
php artisan authz:generate-policies Order
php artisan authz:generate-policies UserSubscription
```

### Notes

- Follows Laravel's standard policy location
- Model name is derived from policy name
- Permission strings use lowercase model name

---

## Recommended Workflows

### Initial Setup

```bash
# 1. Configure sync in config file
# 2. Run sync
php artisan authz:sync --flush-cache

# 3. Generate policies for models
php artisan authz:generate-policies User
php artisan authz:generate-policies Order

# 4. Verify setup
php artisan authz:doctor
```

### Production Deployment

```bash
# Export current state (backup)
php artisan authz:export storage/backups/pre-deploy.json

# Deploy new permissions
php artisan authz:sync --flush-cache
```

### Migration Between Environments

```bash
# On source
php artisan authz:export storage/permissions.json

# Copy file to target
scp storage/permissions.json target:/var/www/storage/

# On target
php artisan authz:import storage/permissions.json --flush-cache
```

### Debugging Access Issues

```bash
# Check for configuration problems
php artisan authz:doctor

# Export for review
php artisan authz:export
cat storage/permissions.json | jq
```
