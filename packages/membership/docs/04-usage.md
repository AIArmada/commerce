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

```php
use AIArmada\Membership\Actions\AcceptInvitationAction;

AcceptInvitationAction::run($invitation, $user, $rawTokenFromRequest);
```

Pass the raw invitation credential as the third argument whenever the host
resolves the invitation by id or route-model binding instead of by token-hash
lookup. Acceptance is rejected when the credential does not match the
invitation.

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

Pending invitations are unique per subject, normalized email, role, and
status. Repeating the same invitation request returns the existing pending
invitation without sending another `MembershipInvitationSent` event. A new
invitation can be created after the previous invitation reaches a terminal
status. Re-inviting after the expiry deadline transitions the stale row to
`expired` and creates a fresh invitation with a new event.

Past-due invitations stay `pending` until they are re-invited or swept. Run
the sweep command from a scheduler when reporting or downstream jobs need the
persisted `expired` status:

```bash
php artisan membership:expire-invitations
```

The package does not register a scheduler entry automatically. If the
application wants a periodic sweep, schedule the command in its application
scheduler:

```php
use Illuminate\Console\Scheduling\Schedule;

protected function schedule(Schedule $schedule): void
{
    $schedule->command('membership:expire-invitations')->daily();
}
```

The command processes each owner scope explicitly; it does not rely on ambient
web authentication.

## Host Authorization

The package ships no routes, policies, or gates: the host application must
authorize every invite, apply, approve, reject, cancel, revoke, and accept
call on its own endpoints. `CancelMembershipApplicationAction` accepts an
optional actor that is recorded as `cancelled_by` for audit, but the package
cannot define who may cancel. Gate these actions with host policies before
calling them.

## Rate Limiting

Invite and apply endpoints must be throttled by the host. Unique indexes only
deduplicate identical subject/email/role or subject/applicant keys, so varying
emails or justifications can otherwise create unbounded rows, and every new
invitation emits a mail-driving `MembershipInvitationSent` event.

## Deleting a Subject

Deleting a `HasMembers` subject cancels its pending applications and revokes
its pending invitations across all owner scopes in one transaction. Terminal
history is preserved. The cleanup uses mass updates, so application and
invitation model events do not fire, and the timestamps are recorded without
actor attribution (`cancelled_by`/`revoked_by` stay null). Member pivot rows,
member user records, and authorization roles are intentionally untouched; the
host model owns that cleanup when its domain requires it.

## Apply for Membership

```php
use AIArmada\Membership\Actions\ApplyForMembershipAction;
use AIArmada\CommerceSupport\Support\OwnerContext;

$application = OwnerContext::withOwner(
    $team,
    fn () => ApplyForMembershipAction::run(
        subject: $team,
        user: $applicant,
        justification: 'I want to join this team.',
    ),
);
```

Only one pending application is kept for a subject and applicant. Repeated or
concurrent submissions return that application without dispatching a duplicate
`MembershipApplicationSubmitted` event.
