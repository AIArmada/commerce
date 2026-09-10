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

For `organizations.Organization`, use the organization package's configured
`organizations.database.tables.members` value; do not derive or override it
with `membership.pivot.table_suffix`.

## Pivot migration

The package does not write migrations into host applications. For a `Team`
subject using the default suffix, add this migration to the host application:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('team_members')) {
            return;
        }

        Schema::create('team_members', function (Blueprint $table): void {
            $table->foreignUuid('team_id');
            $table->foreignUuid('user_id');
            $table->string('role')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->primary(['team_id', 'user_id']);
            $table->index('user_id');
        });
    }
};
```

The package does not add database foreign-key constraints or cascades. The
host model owns its pivot table and any additional lifecycle cleanup.

If the host application needs a custom pivot model, extend the package base
class and opt into it from the host relationship:

```php
namespace App\Models;

use AIArmada\Membership\Models\MembershipPivot;

final class TeamMember extends MembershipPivot
{
}
```

```php
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

public function members(): BelongsToMany
{
    return $this->belongsToMany(User::class, $this->membersTable())
        ->using(TeamMember::class)
        ->withPivot(['role', 'joined_at'])
        ->withTimestamps();
}
```

The base class does not choose a table: `membersTable()` remains the source of
truth for the host subject.

## Add a Member

```php
use AIArmada\Membership\Actions\AddMemberAction;
use AIArmada\Membership\Enums\MemberRole;

AddMemberAction::run($team, $user, MemberRole::Editor);
AddMemberAction::run($team, $founder, MemberRole::Owner);
```

`MemberRole` cases: `Owner`, `Admin`, `Editor`, `Viewer`.

When resolving an invitation from a request, use the bearer token as the
capability: hash it with `MembershipInvitation::tokenForStorage()` and query
the invitation under the current owner context. Confirm the accepting user's
email only after the token-selected invitation is loaded, then call
`AcceptInvitationAction`. A successful acceptance is single-use.

When a member is added, `MembershipRoleSyncService` ensures the mapped Spatie role exists, syncs permissions from `membership.role_permissions` (prefixing non-wildcard names with the subject basename when possible), and assigns the role to the user under the current team context when team-scoped roles are enabled.

The role-definition reconciliation is additive by default. Run
`php artisan membership:sync-roles --prune` only when permissions removed from
`membership.role_permissions` should also be removed from the mapped Spatie
roles. User role assignments are never pruned by that command.

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
