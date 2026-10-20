<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Products\Concerns\IsCatalogEntity;
use AIArmada\Products\Support\ChildOwnershipGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $attribute_id
 * @property string $attributable_type
 * @property string $attributable_id
 * @property string|null $value
 * @property string|null $locale
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Attribute $attribute
 * @property-read Model $attributable
 * @property-read mixed $typed_value
 */
class AttributeValue extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;
    use IsCatalogEntity;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'products.features.owner';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'attribute_id',
        'attributable_type',
        'attributable_id',
        'value',
        'locale',
    ];

    public function getTable(): string
    {
        $tables = config('products.database.tables', []);
        $prefix = config('products.database.table_prefix', 'product_');

        return $tables['attribute_values'] ?? $prefix . 'attribute_values';
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
     * Get the attribute definition.
     *
     * @return BelongsTo<Attribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    /**
     * Get the owning model (Product or Variant).
     *
     * @return MorphTo<Model, $this>
     */
    public function attributable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the value cast to the appropriate PHP type.
     */
    public function getTypedValueAttribute(): mixed
    {
        if (! $this->relationLoaded('attribute')) {
            $this->load('attribute');
        }

        return $this->attribute?->castValue($this->value);
    }

    /**
     * Set the value, serializing it appropriately.
     */
    public function setTypedValue(mixed $value): self
    {
        if (! $this->relationLoaded('attribute')) {
            $this->load('attribute');
        }

        $this->value = $this->attribute?->serializeValue($value);

        return $this;
    }

    /**
     * Scope to a specific locale.
     *
     * @param  Builder<AttributeValue>  $query
     * @return Builder<AttributeValue>
     */
    public function scopeForLocale(Builder $query, ?string $locale = null): Builder
    {
        return $query->where('locale', $locale);
    }

    /**
     * Scope to a specific attribute by code.
     *
     * @param  Builder<AttributeValue>  $query
     * @return Builder<AttributeValue>
     */
    public function scopeForAttribute(Builder $query, string $code): Builder
    {
        return $query->whereHas('attribute', fn ($q) => $q->where('code', $code));
    }

    protected static function booted(): void
    {
        static::creating(function (AttributeValue $attributeValue): void {
            $resolvedType = Relation::getMorphedModel($attributeValue->attributable_type) ?? $attributeValue->attributable_type;

            if (! in_array($resolvedType, [Product::class, Variant::class], true)) {
                throw new InvalidArgumentException('Invalid attributable: attribute values may only attach to products or variants.');
            }

            if (! (bool) config('products.features.owner.enabled', true)) {
                if (! Attribute::query()->whereKey($attributeValue->attribute_id)->exists()) {
                    throw new InvalidArgumentException('Invalid attribute_id: attribute not found.');
                }

                /** @var class-string<Model> $resolvedType */
                if (! $resolvedType::query()->whereKey($attributeValue->attributable_id)->exists()) {
                    throw new InvalidArgumentException('Invalid attributable: unable to resolve attributable model for attribute value.');
                }

                return;
            }

            $hasOwnerType = $attributeValue->owner_type !== null;
            $hasOwnerId = $attributeValue->owner_id !== null;

            if ($hasOwnerType !== $hasOwnerId) {
                throw new InvalidArgumentException('Invalid owner columns: owner_type and owner_id must be both set or both null.');
            }

            $currentOwner = OwnerContext::resolve();

            $includeGlobal = (bool) config('products.features.owner.include_global', false);

            /** @var class-string<Model> $resolvedType */
            $attributable = OwnerWriteGuard::findOrFailForOwner(
                $resolvedType,
                $attributeValue->attributable_id,
                owner: $currentOwner,
                includeGlobal: $includeGlobal,
                message: 'Invalid attributable: unable to resolve attributable model for attribute value.',
            );

            /** @var Model|null $attributableOwner */
            $attributableOwner = $attributable->getAttribute('owner');

            // The definition must resolve for the owner that will hold the
            // value, so the owner-scoped attribute relation never resolves null.
            OwnerWriteGuard::findOrFailForOwner(
                Attribute::class,
                $attributeValue->attribute_id,
                owner: $attributableOwner ?? $currentOwner,
                includeGlobal: $includeGlobal,
                message: 'Cross-tenant write blocked: attribute definition is not accessible for the owning context.',
            );

            ChildOwnershipGuard::apply($attributeValue, $attributable, 'attributable');
        });

        static::updating(function (AttributeValue $attributeValue): void {
            // Definition, target, and tenancy are immutable after creation.
            if (
                $attributeValue->isDirty('attribute_id')
                || $attributeValue->isDirty('attributable_type')
                || $attributeValue->isDirty('attributable_id')
                || $attributeValue->isDirty('owner_type')
                || $attributeValue->isDirty('owner_id')
            ) {
                throw new InvalidArgumentException('Cross-tenant write blocked: attribute value definition, target, and owner are immutable after creation.');
            }
        });
    }
}
