<?php

declare(strict_types=1);

namespace AIArmada\Membership\Traits;

use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Models\MembershipInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @mixin Model
 *
 * @phpstan-require-extends Model
 *
 * @template TUser of \Illuminate\Contracts\Auth\Authenticatable
 *
 * @property-read Collection<int, TUser> $members
 * @property-read Collection<int, MembershipApplication> $applications
 * @property-read Collection<int, MembershipInvitation> $invitations
 *
 * Deleting a subject cancels all pending applications and revokes all pending
 * invitations, including records from other owner scopes. Those two lifecycle
 * updates run in an application-level transaction. Terminal application and
 * invitation history is preserved. Member pivot rows, member user records, and
 * authorization roles are intentionally not changed by this generic trait;
 * host models own that cleanup when their domain requires it. The trait does
 * not refuse deletion because members exist, although host model policies and
 * observers may still veto the delete.
 */
trait HasMembers
{
    public static function bootHasMembers(): void
    {
        static::deleting(function (Model $subject): void {
            DB::transaction(function () use ($subject): void {
                $now = CarbonImmutable::now();

                $subject->applications()
                    ->withoutOwnerScope()
                    ->where('status', ApplicationStatus::Pending->value)
                    ->update([
                        'status' => ApplicationStatus::Cancelled->value,
                        'cancelled_at' => $now,
                    ]);

                $subject->invitations()
                    ->withoutOwnerScope()
                    ->where('status', InvitationStatus::Pending->value)
                    ->update([
                        'status' => InvitationStatus::Revoked->value,
                        'revoked_at' => $now,
                        'last_state_change_at' => $now,
                    ]);
            });
        });
    }

    public function members(): BelongsToMany
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model', Model::class);

        return $this->belongsToMany(
            $userModel,
            $this->membersTable(),
        )->withPivot(['role', 'joined_at'])->withTimestamps();
    }

    public function membersTable(): string
    {
        return Str::snake(class_basename($this)) . (string) config('membership.pivot.table_suffix', '_members');
    }

    public function applications(): MorphMany
    {
        return $this->morphMany(MembershipApplication::class, 'subject');
    }

    public function invitations(): MorphMany
    {
        return $this->morphMany(MembershipInvitation::class, 'subject');
    }
}
