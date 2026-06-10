<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\Support\Integration\ConfiguredEventModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $event_id
 * @property string|null $person_type
 * @property string|null $person_id
 * @property string|null $display_name
 * @property string|null $role
 * @property string|null $role_key
 * @property string|null $role_label
 * @property string|null $biography
 * @property int|null $order_column
 * @property EventVisibility $visibility
 * @property array<string, mixed>|null $metadata
 */
class EventPerson extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'event_id',
        'person_type',
        'person_id',
        'display_name',
        'role',
        'role_key',
        'role_label',
        'biography',
        'order_column',
        'visibility',
        'metadata',
    ];

    protected $attributes = [
        'visibility' => 'public',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => EventVisibility::class,
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('events.database.tables.people', 'event_speakers');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?Model $owner = null, bool $includeGlobal = false): Builder
    {
        $ownerToScope = $owner;

        if (func_num_args() < 2) {
            $ownerToScope = OwnerContext::CURRENT;
        }

        $includeGlobalToScope = $includeGlobal;

        if (func_num_args() < 3) {
            $includeGlobalToScope = (bool) config('events.features.owner.include_global', false);
        }

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobalToScope);

        return $scoped;
    }

    /**
     * @return BelongsTo<Event, $this>
     *
     * @phpstan-return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(ConfiguredEventModel::classFor('events.models.event', Event::class), 'event_id');
    }

    /**
     * Generic morph link for a person assignment.
     */
    public function person(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'person_type', 'person_id');
    }

    /**
     * @param  Builder<EventPerson>  $query
     * @return Builder<EventPerson>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereIn($this->qualifyColumn('visibility'), [
                EventVisibility::Public->value,
                EventVisibility::Unlisted->value,
            ]);
        });
    }

    /**
     * @param  Builder<EventPerson>  $query
     * @param  array<int, string>|string  $roleKeys
     * @return Builder<EventPerson>
     */
    public function scopeWithRoleKey(Builder $query, array | string $roleKeys): Builder
    {
        return $query->whereIn($this->qualifyColumn('role_key'), Arr::wrap($roleKeys));
    }

    public function getRoleKeyAttribute(): ?string
    {
        $roleKey = $this->getAttributes()['role_key'] ?? null;

        if (is_string($roleKey) && mb_trim($roleKey) !== '') {
            return mb_trim($roleKey);
        }

        $roleLabel = $this->role_label;

        if (is_string($roleLabel) && mb_trim($roleLabel) !== '') {
            return Str::slug($roleLabel);
        }

        $role = $this->role;

        if (is_string($role) && mb_trim($role) !== '') {
            return Str::slug($role);
        }

        return null;
    }

    public function getRoleLabelAttribute(): ?string
    {
        $roleLabel = $this->getAttributes()['role_label'] ?? null;

        if (is_string($roleLabel) && mb_trim($roleLabel) !== '') {
            return mb_trim($roleLabel);
        }

        $role = $this->getAttributes()['role'] ?? null;

        if (is_string($role) && mb_trim($role) !== '') {
            return mb_trim($role);
        }

        return null;
    }
}
