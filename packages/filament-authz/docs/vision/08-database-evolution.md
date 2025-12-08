# Database Evolution

> **Document:** 8 of 10  
> **Package:** `aiarmada/filament-authz`  
> **Status:** Vision

---

## Overview

Database schema enhancements to support hierarchical permissions, role inheritance, contextual access, audit logging, and ABAC policies.

---

## Schema Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    DATABASE SCHEMA EVOLUTION                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  EXISTING (Spatie)              NEW (Vision)                    │
│  ┌────────────────┐            ┌────────────────┐               │
│  │  permissions   │            │ permission_    │               │
│  ├────────────────┤            │ groups         │               │
│  │  roles         │            ├────────────────┤               │
│  ├────────────────┤            │ role_templates │               │
│  │  model_has_    │            ├────────────────┤               │
│  │  permissions   │            │ scoped_        │               │
│  ├────────────────┤            │ permissions    │               │
│  │  model_has_    │            ├────────────────┤               │
│  │  roles         │            │ access_        │               │
│  ├────────────────┤            │ policies       │               │
│  │  role_has_     │            ├────────────────┤               │
│  │  permissions   │            │ permission_    │               │
│  └────────────────┘            │ audit_logs     │               │
│                                └────────────────┘               │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## New Tables

### 1. Permission Groups

```php
Schema::create('permission_groups', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->foreignUuid('parent_id')->nullable();
    $table->json('implicit_abilities')->nullable();
    $table->integer('sort_order')->default(0);
    $table->boolean('is_system')->default(false);
    $table->timestamps();

    $table->index('parent_id');
    $table->index('slug');
});

// Pivot: permission_group_permission
Schema::create('permission_group_permission', function (Blueprint $table) {
    $table->foreignUuid('permission_group_id');
    $table->foreignUuid('permission_id');

    $table->primary(['permission_group_id', 'permission_id']);
    $table->index('permission_id');
});
```

### 2. Role Templates

```php
Schema::create('role_templates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->foreignUuid('parent_id')->nullable();
    $table->string('guard_name');
    $table->json('default_permissions')->nullable();
    $table->json('metadata')->nullable();
    $table->boolean('is_system')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index('parent_id');
    $table->index('guard_name');
    $table->index('is_active');
});
```

### 3. Extend Roles (Add Columns)

```php
Schema::table('roles', function (Blueprint $table) {
    $table->foreignUuid('parent_role_id')->nullable()->after('guard_name');
    $table->foreignUuid('template_id')->nullable()->after('parent_role_id');
    $table->text('description')->nullable()->after('template_id');
    $table->integer('level')->default(0)->after('description');
    $table->json('metadata')->nullable();
    $table->boolean('is_system')->default(false);
    $table->boolean('is_assignable')->default(true);

    $table->index('parent_role_id');
    $table->index('template_id');
    $table->index('level');
});
```

### 4. Scoped Permissions

```php
Schema::create('scoped_permissions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('permission_id');
    $table->uuidMorphs('permissionable'); // User or Role
    $table->string('scope_type'); // team, tenant, resource, temporal
    $table->uuid('scope_id')->nullable();
    $table->string('scope_model')->nullable();
    $table->json('conditions')->nullable();
    $table->timestamp('granted_at');
    $table->timestamp('expires_at')->nullable();
    $table->foreignUuid('granted_by')->nullable();
    $table->timestamps();

    $table->index('permission_id');
    $table->index(['permissionable_type', 'permissionable_id']);
    $table->index(['scope_type', 'scope_id']);
    $table->index('expires_at');
});
```

### 5. Access Policies (ABAC)

```php
Schema::create('access_policies', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('effect'); // allow, deny
    $table->string('target_action');
    $table->string('target_resource')->nullable();
    $table->json('conditions');
    $table->integer('priority')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamp('valid_from')->nullable();
    $table->timestamp('valid_until')->nullable();
    $table->timestamps();

    $table->index('is_active');
    $table->index('target_action');
    $table->index('priority');
});
```

### 6. Permission Audit Logs

```php
Schema::create('permission_audit_logs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('event_type');
    $table->string('severity');
    $table->uuidMorphs('actor');
    $table->nullableUuidMorphs('subject');
    $table->nullableUuidMorphs('target');
    $table->string('target_name')->nullable();
    $table->json('old_value')->nullable();
    $table->json('new_value')->nullable();
    $table->json('context')->nullable();
    $table->ipAddress('ip_address')->nullable();
    $table->text('user_agent')->nullable();
    $table->string('session_id')->nullable();
    $table->timestamp('occurred_at');
    $table->timestamps();

    $table->index('event_type');
    $table->index('severity');
    $table->index(['actor_type', 'actor_id']);
    $table->index(['subject_type', 'subject_id']);
    $table->index('occurred_at');
});
```

---

## Indexes & Performance

### Composite Indexes

```php
// For scoped permission lookups
$table->index(['permissionable_type', 'permissionable_id', 'scope_type']);

// For active policy evaluation
$table->index(['is_active', 'target_action', 'priority']);

// For audit log queries
$table->index(['event_type', 'occurred_at']);
$table->index(['actor_type', 'actor_id', 'occurred_at']);
```

### Covering Indexes

```php
// Fast permission group lookups with common columns
$table->rawIndex(
    'CREATE INDEX permission_groups_lookup ON permission_groups (slug, parent_id, is_system) INCLUDE (name)'
);

// Fast role hierarchy traversal
$table->rawIndex(
    'CREATE INDEX roles_hierarchy ON roles (parent_role_id, level) INCLUDE (name, guard_name)'
);
```

---

## JSON Column Strategies

### Conditions in Access Policies

```php
// Example conditions structure
{
    "conditions": [
        {
            "attribute": "roles",
            "operator": "contains",
            "value": "manager",
            "source": "subject"
        },
        {
            "attribute": "status",
            "operator": "eq",
            "value": "pending",
            "source": "resource"
        }
    ]
}
```

### Metadata Columns

```php
// Role metadata
{
    "color": "#3B82F6",
    "icon": "heroicon-o-shield-check",
    "category": "administrative",
    "created_by": "user-uuid"
}

// Template default permissions
{
    "permissions": ["orders.view", "orders.create"],
    "groups": ["order-management"]
}
```

---

## Migration Order

```
1. permission_groups (no dependencies)
2. role_templates (no dependencies)
3. alter roles (add columns)
4. permission_group_permission (depends on permission_groups)
5. scoped_permissions (depends on permissions, users, roles)
6. access_policies (no dependencies)
7. permission_audit_logs (no dependencies)
```

---

## Config Keys

```php
return [
    'database' => [
        'table_prefix' => 'perm_',
        'tables' => [
            'permission_groups' => 'perm_permission_groups',
            'role_templates' => 'perm_role_templates',
            'scoped_permissions' => 'perm_scoped_permissions',
            'access_policies' => 'perm_access_policies',
            'audit_logs' => 'perm_audit_logs',
        ],
        'json_column_type' => 'json', // or 'jsonb' for PostgreSQL
    ],
];
```

---

## Data Integrity

### Application-Level Cascades

```php
// In PermissionGroup model
protected static function booted(): void
{
    static::deleting(function (PermissionGroup $group): void {
        // Reassign children to parent
        PermissionGroup::query()
            ->where('parent_id', $group->id)
            ->update(['parent_id' => $group->parent_id]);

        // Detach permissions
        $group->permissions()->detach();
    });
}

// In Role model extension
protected static function booted(): void
{
    static::deleting(function (Role $role): void {
        // Orphan child roles (or reassign)
        Role::query()
            ->where('parent_role_id', $role->id)
            ->update(['parent_role_id' => null]);

        // Delete scoped permissions
        ScopedPermission::query()
            ->where('permissionable_type', Role::class)
            ->where('permissionable_id', $role->id)
            ->delete();
    });
}
```

---

## Query Examples

### Hierarchical Permission Check

```sql
WITH RECURSIVE role_tree AS (
    SELECT id, name, parent_role_id, 0 AS depth
    FROM roles
    WHERE id = :user_role_id

    UNION ALL

    SELECT r.id, r.name, r.parent_role_id, rt.depth + 1
    FROM roles r
    INNER JOIN role_tree rt ON r.id = rt.parent_role_id
)
SELECT DISTINCT p.name
FROM role_tree rt
JOIN role_has_permissions rhp ON rt.id = rhp.role_id
JOIN permissions p ON rhp.permission_id = p.id;
```

### Active Policies for Action

```sql
SELECT *
FROM access_policies
WHERE is_active = true
  AND (target_action = :action OR target_action = '*')
  AND (target_resource IS NULL OR target_resource = :resource OR target_resource = '*')
  AND (valid_from IS NULL OR valid_from <= NOW())
  AND (valid_until IS NULL OR valid_until >= NOW())
ORDER BY priority DESC;
```

### Audit Summary by Date

```sql
SELECT
    DATE(occurred_at) AS date,
    event_type,
    COUNT(*) AS count
FROM permission_audit_logs
WHERE occurred_at >= :start_date
  AND occurred_at <= :end_date
GROUP BY DATE(occurred_at), event_type
ORDER BY date DESC, count DESC;
```

---

## Navigation

**Previous:** [07-permission-simulation.md](07-permission-simulation.md)  
**Next:** [09-filament-enhancements.md](09-filament-enhancements.md)
