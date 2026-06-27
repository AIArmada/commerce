<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Products\Concerns\IsAttributeEntity;
use AIArmada\Products\Enums\Visibility;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property int $position
 * @property string $visibility
 * @property CarbonImmutable|null $hidden_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Attribute> $groupAttributes
 * @property-read Collection<int, AttributeSet> $attributeSets
 */
class AttributeGroup extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;
    use IsAttributeEntity;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'products.features.owner';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'name',
        'code',
        'description',
        'position',
        'visibility',
        'hidden_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'visibility' => 'string',
            'hidden_at' => 'immutable_datetime',
        ];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
        'visibility' => 'visible',
    ];

    public function getTable(): string
    {
        $tables = config('products.database.tables', []);
        $prefix = config('products.database.table_prefix', 'product_');

        return $tables['attribute_groups'] ?? $prefix . 'attribute_groups';
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
            $includeGlobalToScope = (bool) config('products.features.owner.include_global', false);
        }

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobalToScope);

        return $scoped;
    }

    /**
     * Get the attributes in this group.
     *
     * @return BelongsToMany<Attribute, $this>
     */
    public function groupAttributes(): BelongsToMany
    {
        return $this->belongsToMany(
            Attribute::class,
            config('products.database.tables.attribute_attribute_group', 'attribute_attribute_group'),
            'attribute_group_id',
            'attribute_id'
        )->withPivot('position')->orderByPivot('position')->withTimestamps();
    }

    /**
     * Get the attribute sets that include this group.
     *
     * @return BelongsToMany<AttributeSet, $this>
     */
    public function attributeSets(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeSet::class,
            config('products.database.tables.attribute_group_attribute_set', 'attribute_group_attribute_set'),
            'attribute_group_id',
            'attribute_set_id'
        )->withPivot('position')->orderByPivot('position')->withTimestamps();
    }

    /**
     * Scope to visible groups only.
     *
     * @param  Builder<AttributeGroup>  $query
     * @return Builder<AttributeGroup>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visibility', Visibility::Visible);
    }

    protected static function booted(): void
    {
        static::creating(function (AttributeGroup $group): void {
            if (! (bool) config('products.features.owner.enabled', true)) {
                return;
            }

            $hasOwnerType = $group->owner_type !== null;
            $hasOwnerId = $group->owner_id !== null;

            if ($hasOwnerType !== $hasOwnerId) {
                throw new InvalidArgumentException('Invalid owner columns: owner_type and owner_id must be both set or both null.');
            }

            $owner = OwnerContext::resolve();

            if ($owner !== null && $hasOwnerType && ! $group->belongsToOwner($owner)) {
                throw new InvalidArgumentException('Cross-tenant write blocked: attribute group owner does not match the current owner context.');
            }

            if (! (bool) config('products.features.owner.auto_assign_on_create', true)) {
                return;
            }

            if ($group->owner_type !== null || $group->owner_id !== null) {
                return;
            }

            if ($owner === null) {
                return;
            }

            $group->assignOwner($owner);
        });

        static::saving(function (AttributeGroup $group): void {
            if ($group->isDirty('visibility')) {
                $group->hidden_at = $group->visibility === Visibility::Hidden->value ? now() : null;
            }
        });

        static::deleting(function (AttributeGroup $group): void {
            $group->groupAttributes()->detach();
            $group->attributeSets()->detach();
        });
    }
}
