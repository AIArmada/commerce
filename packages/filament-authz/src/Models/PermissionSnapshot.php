<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\FilamentAuthz\Support\UserModelResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string|null $created_by
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed> $state
 * @property string $hash
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|null $creator
 * @property-read Model|null $owner
 */
class PermissionSnapshot extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'filament-authz.owner';

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'state',
        'hash',
        'owner_type',
        'owner_id',
    ];

    public function getTable(): string
    {
        return config('filament-authz.database.tables.permission_snapshots', 'authz_permission_snapshots');
    }

    /**
     * Get the user who created this snapshot.
     */
    public function creator(): BelongsTo
    {
        $userModel = UserModelResolver::resolve();

        return $this->belongsTo($userModel, 'created_by');
    }

    protected static function booted(): void
    {
        static::saving(function (self $snapshot): void {
            if (! config('filament-authz.owner.enabled', false)) {
                return;
            }

            $owner = OwnerContext::resolve();

            if ($owner === null) {
                return;
            }

            if ($snapshot->owner_id === null) {
                $snapshot->assignOwner($owner);

                return;
            }

            if (! $snapshot->belongsToOwner($owner)) {
                throw new AuthorizationException('Cannot write snapshots outside the current owner scope.');
            }
        });
    }

    /**
     * Get the roles from the snapshot.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRoles(): array
    {
        return $this->state['roles'] ?? [];
    }

    /**
     * Get the permissions from the snapshot.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPermissions(): array
    {
        return $this->state['permissions'] ?? [];
    }

    /**
     * Get the assignments from the snapshot.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAssignments(): array
    {
        return $this->state['assignments'] ?? [];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
