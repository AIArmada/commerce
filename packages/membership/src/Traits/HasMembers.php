<?php

declare(strict_types=1);

namespace AIArmada\Membership\Traits;

use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Models\MembershipInvitation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
 */
trait HasMembers
{
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model'),
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
