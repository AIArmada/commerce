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
```

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
