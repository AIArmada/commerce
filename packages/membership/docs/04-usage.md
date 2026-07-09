---
title: Membership Usage
---

## Subject Model

```php
use AIArmada\Membership\Traits\HasMembers;
use Illuminate\Database\Eloquent\Model;

final class Team extends Model
{
    use HasMembers;
}
```

## Add a Member

```php
use AIArmada\Membership\Actions\AddMemberAction;
use AIArmada\Membership\Enums\MemberRole;

AddMemberAction::run($team, $user, MemberRole::Editor);
AddMemberAction::run($team, $founder, MemberRole::Owner);
```

`MemberRole` cases: `Owner`, `Admin`, `Editor`, `Viewer`.

When a member is added, `MembershipRoleSyncService` ensures the mapped Spatie role exists, syncs permissions from `membership.role_permissions` (prefixing non-wildcard names with the subject basename when possible), and assigns the role to the user under the current team context when team-scoped roles are enabled.

## Invite a Member

```php
use AIArmada\Membership\Actions\InviteMemberAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\CommerceSupport\Support\OwnerContext;

$invitation = OwnerContext::withOwner(
    $team,
    fn () => InviteMemberAction::run(
        subject: $team,
        email: 'member@example.com',
        role: MemberRole::Viewer,
        inviter: $administrator,
    ),
);
```
