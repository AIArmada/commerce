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

`ORGANIZATIONS_TABLE_PREFIX` supplies the default prefix for both organization
tables. `ORGANIZATIONS_TABLE` and `ORGANIZATIONS_MEMBERS_TABLE` override the
individual names when needed.

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

The default is also available as `ORGANIZATIONS_REQUIRE_CONTEXT`. The resolver
binding is lazy and fails at resolve time with a diagnostic message if context
is required while `NullCurrentOrganizationResolver` is still configured.

## Institution topology

An `Organization` is the tenant/owner aggregate. It may also be used as the
persons package's institution model, but only when the host explicitly sets
`persons.models.institution` to `Organization::class`. Persons treats
`institution_id` as an opaque, nullable UUID from the organizations package's
perspective. When persons is configured with `persons.models.institution`,
persons rejects non-null values unless they resolve to a persisted institution;
without that configuration, non-null institution references fail closed.

## Authorization

Bind `OrganizationAuthorization`,
`OrganizationVisibilityTransitionAuthorizer`, and
`OrganizationLifecycleHook` to application implementations when the default
membership-role policy is not sufficient.
