<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Models;

use AIArmada\Membership\Contracts\MembershipMutationGuard;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Traits\HasMembers;
use AIArmada\Organizations\Enums\OrganizationStatus;
use AIArmada\Organizations\Enums\OrganizationVisibility;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property OrganizationStatus $status
 * @property OrganizationVisibility $visibility
 * @property string $created_by
 * @property CarbonInterface|null $published_at
 * @property CarbonInterface|null $privatized_at
 * @property CarbonInterface|null $suspended_at
 * @property CarbonInterface|null $archived_at
 * @property CarbonInterface|null $last_state_change_at
 */
class Organization extends Model implements MembershipMutationGuard
{
    use HasMembers;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $attributes = [
        'status' => OrganizationStatus::Active->value,
        'visibility' => OrganizationVisibility::Private->value,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'visibility',
        'created_by',
        'published_at',
        'privatized_at',
        'suspended_at',
        'archived_at',
        'last_state_change_at',
    ];

    public function getTable(): string
    {
        return (string) config('organizations.database.tables.organizations', 'organizations');
    }

    public function membersTable(): string
    {
        return (string) config('organizations.database.tables.members', 'organization_members');
    }

    public function ownerMember(): BelongsToMany
    {
        return $this->members()->wherePivot('role', MemberRole::Owner->spatieRoleName());
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('status'), OrganizationStatus::Active->value)
            ->where($this->qualifyColumn('visibility'), OrganizationVisibility::Public->value);
    }

    public function isPublic(): bool
    {
        return $this->visibility === OrganizationVisibility::Public
            && $this->status === OrganizationStatus::Active;
    }

    public function isPrivate(): bool
    {
        return $this->visibility === OrganizationVisibility::Private;
    }

    public function assertMemberCanBeAdded(Model $member, MemberRole $role, ?Model $existingMember): void
    {
        $existingRole = $this->memberRole($existingMember);

        if ($existingRole === MemberRole::Owner && $role !== MemberRole::Owner) {
            throw new AuthorizationException('Transfer organization ownership before changing the owner role.');
        }

        if ($role === MemberRole::Owner
            && $existingRole !== MemberRole::Owner
            && $this->ownerMember()->lockForUpdate()->exists()) {
            throw new AuthorizationException('Transfer organization ownership instead of assigning a second owner.');
        }
    }

    public function assertMemberCanBeRemoved(Model $member): void
    {
        $role = $this->memberRole($member);

        if ($role === MemberRole::Owner) {
            throw new AuthorizationException('Transfer organization ownership before removing the owner.');
        }
    }

    public function assertMemberRoleCanChange(Model $member, MemberRole $role): void
    {
        $currentRole = $this->memberRole($member);

        if ($currentRole === MemberRole::Owner && $role !== MemberRole::Owner) {
            throw new AuthorizationException('Transfer organization ownership before changing the owner role.');
        }

        if ($role === MemberRole::Owner && $currentRole !== MemberRole::Owner) {
            throw new AuthorizationException('Use the ownership transfer workflow to assign an owner.');
        }
    }

    public function transitionToVisibility(OrganizationVisibility $visibility, CarbonInterface $at): void
    {
        $this->visibility = $visibility;
        $this->last_state_change_at = $at;

        if ($visibility === OrganizationVisibility::Public) {
            $this->published_at = $at;
            $this->privatized_at = null;
        } else {
            $this->privatized_at = $at;
        }
    }

    public function transitionToStatus(OrganizationStatus $status, CarbonInterface $at): void
    {
        $this->status = $status;
        $this->last_state_change_at = $at;

        match ($status) {
            OrganizationStatus::Suspended => $this->suspended_at = $at,
            OrganizationStatus::Archived => $this->archived_at = $at,
            OrganizationStatus::Active => null,
        };
    }

    private function memberRole(?Model $member): ?MemberRole
    {
        $pivot = $member?->getRelationValue('pivot');

        if (! $pivot instanceof Model) {
            return null;
        }

        return MemberRole::fromSpatieRoleName((string) $pivot->getAttribute('role'));
    }

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'visibility' => OrganizationVisibility::class,
            'published_at' => 'immutable_datetime',
            'privatized_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'last_state_change_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Organization $organization): void {
            if ($organization->exists && $organization->isDirty('created_by')) {
                throw new InvalidArgumentException('Organization provenance cannot be changed after creation.');
            }
        });
    }
}
