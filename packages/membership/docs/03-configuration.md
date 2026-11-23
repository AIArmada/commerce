---
title: Membership Configuration
---

## Main Settings

The shipped `config/membership.php` top-level keys, with their default values:

```php
'database' => [
    'json_column_type' => env('MEMBERSHIP_JSON_COLUMN_TYPE', 'jsonb'),
    'tables' => [
        'applications' => env('MEMBERSHIP_TABLE_APPLICATIONS', 'membership_applications'),
        'invitations' => env('MEMBERSHIP_TABLE_INVITATIONS', 'membership_invitations'),
    ],
],
'invitations' => [
    'token_length' => 64,
    'default_expiry_days' => 14,
    'default_channel' => 'email',
    'channels' => [],
    'verifiers' => [],
],
'pivot' => [
    'table_suffix' => env('MEMBERSHIP_PIVOT_SUFFIX', '_members'),
],
'role_mapping' => [
    'owner' => env('MEMBERSHIP_ROLE_OWNER_NAME', 'owner'),
    'admin' => env('MEMBERSHIP_ROLE_ADMIN_NAME', 'admin'),
    'editor' => env('MEMBERSHIP_ROLE_EDITOR_NAME', 'editor'),
    'viewer' => env('MEMBERSHIP_ROLE_VIEWER_NAME', 'viewer'),
],
'role_permissions' => [
    'owner' => ['*'],
    'admin' => ['update', 'manage-members'],
    'editor' => ['update'],
    'viewer' => ['view'],
],
'owner' => [
    'enabled' => true,
    'include_global' => false,
    'auto_assign_on_create' => true,
    'owner_type_column' => 'owner_type',
    'owner_id_column' => 'owner_id',
],
'features' => [
    'team_scoped_roles' => env('MEMBERSHIP_TEAM_SCOPED', true),
],
```

## Role mapping

- `role_mapping` maps each `MemberRole` case (`owner`, `admin`, `editor`, `viewer`) to a Spatie role name
- Override via env when the host app already uses different role names

## Role permissions

- `role_permissions` lists permission names granted when `MembershipRoleSyncService` ensures a role
- `owner => ['*']` expands to every permission registered for the default guard
- Non-wildcard entries are prefixed with the subject class basename in snake case (for example `Team` → `team.update`) when a subject is available
- Apps should override `role_permissions` rather than hard-coding host permission names in the package
- Role reconciliation is additive by default. Use `membership:sync-roles --prune` when unconfigured permissions should be removed from mapped membership roles.

## Invitation channels

- `invitations.channels` maps a channel name to a class implementing `AIArmada\Membership\Contracts\InvitationChannel`. The built-in `email` channel is always available; add SMS, WhatsApp, social DM, or other integrations by registering a channel class here.
- `invitations.verifiers` maps a channel name to a class implementing `AIArmada\Membership\Contracts\InvitationRecipientVerifier`. Only the `email` verifier ships with the package; every non-email channel must provide one before invitations on that channel can be accepted.
- `invitations.default_channel` is used when `InviteMemberAction` receives no explicit channel.
- Registering a channel at runtime (rather than through config) uses `InvitationChannelManager::registerChannel()` and `registerVerifier()`, from your own service provider. Register stateless instances there, or use class configuration so Laravel can resolve scoped dependencies.

## Notes

- Invitation tokens are always hashed with SHA-256 before persistence. The plaintext token is carried on the `InvitationDeliveryData` passed to the channel and on `MembershipInvitationSent`.
- Recipient identifiers are normalized by the channel (`EmailInvitationChannel` lowercases and validates the address); the model stores whatever the channel returns.
- Configure owner scoping under `membership.owner`; the legacy nested key is not read.
- When owner scoping is enabled, resolve the current owner before reading or mutating applications and invitations.
- `Organization::membersTable()` is the source of truth for organization memberships and reads `organizations.database.tables.members`. The generic `membership.pivot.table_suffix` setting applies to other `HasMembers` subjects only.
