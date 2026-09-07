---
title: Organizations Configuration
---

## Configuration

`config/organizations.php` controls the organization and membership table
names, the current-organization resolver, and whether middleware requires a
resolved context by default.

The organization membership table is configured only at
`organizations.database.tables.members`:

```php
'database' => [
    'tables' => [
        'organizations' => 'organizations',
        'members' => 'organization_members',
    ],
],
```

`membership.pivot.table_suffix` is used by generic `HasMembers` subjects but
is intentionally ignored by `Organization::membersTable()`. This keeps the
organization migration and runtime relation on one physical table. The
membership table retains its surrogate UUID primary key so relation managers
can address membership rows individually.

```php
'middleware' => [
    'require_context' => true,
],
```

When the route has no parameter, `require_context` is the default. Route
parameters can explicitly require or release context with
`current.organization:true` or `current.organization:false`; the latter is
only for intentionally global/public handlers and must keep their global
owner context explicit.

## Authorization

Bind `OrganizationAuthorization`,
`OrganizationVisibilityTransitionAuthorizer`, and
`OrganizationLifecycleHook` to application implementations when the default
membership-role policy is not sufficient.
