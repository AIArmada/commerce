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
the invitation under the current owner context. Acceptance verifies the
accepting user against the invitation's channel through the registered
`InvitationRecipientVerifier`, then call `AcceptInvitationAction`. A
successful acceptance is single-use.

```php
use AIArmada\Membership\Actions\AcceptInvitationAction;

AcceptInvitationAction::run($invitation, $user, $rawTokenFromRequest);
```

Pass the raw invitation credential as the third argument whenever the host
resolves the invitation by id or route-model binding instead of by token-hash
lookup. Acceptance is rejected when the credential does not match the
invitation.

Acceptance is recipient-bound on every channel. The email verifier compares
the accepting user's email with the stored recipient. Hosts should enforce
email verification before calling the action for untrusted requests. A non-email
invitation is accepted only when its registered verifier confirms the
accepting user owns the recipient identity (for example, an SMS integration
that has verified the user's phone number). If a channel has no verifier, or
the verifier returns false, acceptance fails with
`Invitation recipient identity could not be verified.` The package never
accepts a caller-supplied identity string as proof and never degrades an
invitation into a bearer-only token.

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
        recipient: 'member@example.com',
        role: MemberRole::Viewer,
        inviter: $administrator,
    ),
);
```

The channel defaults to `membership.invitations.default_channel` (email).
Pass a channel explicitly for other integrations:

```php
$invitation = InviteMemberAction::run(
    subject: $team,
    recipient: '+60123456789',
    role: MemberRole::Viewer,
    inviter: $administrator,
    channel: 'sms',
);
```

The channel validates and normalizes the recipient before anything is
persisted, so an invalid recipient throws `InvalidArgumentException` and no
invitation row or event is created. An unregistered channel also throws
instead of silently falling back to email.

Pending invitations are deduplicated within the current owner scope by subject,
channel, normalized recipient, and role. Repeating the same invitation request returns the existing
pending invitation without sending another `MembershipInvitationSent` event. A
new invitation can be created after the previous invitation reaches a terminal
status. Re-inviting after the expiry deadline transitions the stale row to
`expired` and creates a fresh invitation with a new event.

After the transaction commits, every new invitation dispatches one `MembershipInvitationSent` event carrying
an `InvitationDeliveryData` with the channel, recipient, plaintext token, and
expiry. The event is the single notification seam for all channels: an email
listener sends mail, an SMS integration sends an SMS message. Channels that
must push over an external transport also receive the delivery through
`InvitationChannel::deliver()`. Choose one delivery path per channel. For
external transports, enqueue delivery using the invitation ID as the
idempotency key and let the job retry provider failures.

### Adding a channel integration

Implement `InvitationChannel` to describe the channel, normalize recipients,
and optionally push the delivery:

```php
use AIArmada\Membership\Contracts\InvitationChannel;
use AIArmada\Membership\Data\InvitationDeliveryData;
use InvalidArgumentException;

final class SmsInvitationChannel implements InvitationChannel
{
    public function channel(): string
    {
        return 'sms';
    }

    public function label(): string
    {
        return 'SMS';
    }

    public function normalize(string $identifier): string
    {
        $identifier = trim($identifier);

        if (preg_match('/^\+[1-9]\d{6,14}$/', $identifier) !== 1) {
            throw new InvalidArgumentException('A valid E.164 phone number is required.');
        }

        return $identifier;
    }

    public function deliver(InvitationDeliveryData $delivery): void
    {
        // Hand off to your SMS provider here.
    }
}
```

Implement `InvitationRecipientVerifier` to prove that an accepting user owns
the recipient identity. Verification must use a trusted source, such as a
verified phone number on the user record or a provider callback — never an
identity string supplied by the accepting request:

```php
use AIArmada\Membership\Contracts\InvitationRecipientVerifier;
use AIArmada\Membership\Data\InvitationActorData;
use AIArmada\Membership\Data\InvitationRecipientData;

final class SmsInvitationRecipientVerifier implements InvitationRecipientVerifier
{
    public function channel(): string
    {
        return 'sms';
    }

    public function verify(InvitationRecipientData $recipient, InvitationActorData $actor): bool
    {
        return $this->verifiedPhoneFor($actor->id) === $recipient->identifier;
    }
}
```

Register both classes in `membership.invitations.channels` and
`membership.invitations.verifiers`, or call
`InvitationChannelManager::registerChannel()` and `registerVerifier()` from a
service provider.

Host UIs can read `InvitationChannelManager::options()` for a
`name => label` map and `defaultChannelName()` for the default selection.

Acceptance is gated on the date, not the stored status: `isValid()` consults
`isExpired()`, so a past-due invitation is refused whether or not anything has
written `expired`. There is no sweep command.

`expireIfDue()` performs the transition on demand and returns whether it did.
`InviteMemberAction` already calls it, which is why re-inviting a past-due
subject replaces the stale invitation instead of returning it.

```php
$invitation->expireIfDue(); // true when it transitioned pending → expired
```

To bulk-normalise rows for reporting, iterate inside an explicit owner scope:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Models\MembershipInvitation;

OwnerContext::withOwner(null, function (): void {
    MembershipInvitation::query()
        ->where('status', InvitationStatus::Pending)
        ->whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->get()
        ->each(fn (MembershipInvitation $invitation) => $invitation->expireIfDue());
});
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
deduplicate identical subject/channel/recipient/role or subject/applicant keys,
so varying recipients or justifications can otherwise create unbounded rows, and
every new invitation emits a delivery event.

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

## Upgrading invitation callers

The invitation schema now stores `channel` and `recipient` where it previously
stored `email`. Fresh installs use the updated original migration. Databases
that already ran it need their invitation schema updated before deploying this
version. Update invitation callers to pass `recipient` and update event
listeners to read the event's `delivery` payload.
