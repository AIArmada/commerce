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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $option_id
 * @property string $name
 * @property int $position
 * @property string|null $swatch_color
 * @property string|null $swatch_image
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Option $option
 * @property-read Collection<int, Variant> $variants
 */
class OptionValue extends Model implements Auditable
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
        'option_id',
        'name',
        'position',
        'swatch_color',
        'swatch_image',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'swatch_color' => 'string',
            'metadata' => 'array',
        ];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
    ];

    public function getTable(): string
    {
        $tables = config('products.database.tables', []);
        $prefix = config('products.database.table_prefix', 'product_');

        return $tables['option_values'] ?? $prefix . 'option_values';
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

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the parent option.
     *
     * @return BelongsTo<Option, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'option_id');
    }

    /**
     * Get the variants using this option value.
     *
     * @return BelongsToMany<Variant, $this>
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            Variant::class,
            config('products.database.tables.variant_options', 'product_variant_options'),
            'option_value_id',
            'variant_id'
        );
    }

    // =========================================================================
    // SWATCH HELPERS
    // =========================================================================

    /**
     * Check if this option value has a color swatch.
     */
    public function hasColorSwatch(): bool
    {
        return ! empty($this->swatch_color);
    }

    /**
     * Check if this option value has an image swatch.
     */
    public function hasImageSwatch(): bool
    {
        return ! empty($this->swatch_image);
    }

    /**
     * Get the swatch style for CSS. Values that fail validation (legacy
     * rows) yield no style rather than a CSS breakout.
     */
    public function getSwatchStyle(): ?string
    {
        if ($this->hasColorSwatch() && self::isValidSwatchColor($this->swatch_color)) {
            return "background-color: {$this->swatch_color}";
        }

        if ($this->hasImageSwatch() && self::isValidSwatchImage($this->swatch_image)) {
            return "background-image: url('{$this->swatch_image}')";
        }

        return null;
    }

    public static function isValidSwatchColor(?string $color): bool
    {
        if ($color === null || $color === '') {
            return false;
        }

        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color) === 1;
    }

    public static function isValidSwatchImage(?string $image): bool
    {
        if ($image === null || $image === '') {
            return false;
        }

        return mb_strlen($image) <= 2048 && strpbrk($image, "'\"()<>\\") === false;
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::creating(function (OptionValue $optionValue): void {
            if (! (bool) config('products.features.owner.enabled', true)) {
                $option = Option::query()->whereKey($optionValue->option_id)->first();

                if ($option === null) {
                    throw new InvalidArgumentException('Invalid option_id: option not found.');
                }

                if (! Product::query()->whereKey($option->product_id)->exists()) {
                    throw new InvalidArgumentException('Invalid product_id: option product not found.');
                }

                return;
            }

            $includeGlobal = (bool) config('products.features.owner.include_global', false);

            $option = OwnerWriteGuard::findOrFailForOwner(
                Option::class,
                $optionValue->option_id,
                includeGlobal: $includeGlobal,
                message: 'Cross-tenant write blocked: option value option is not accessible in the current owner context.',
            );

            $product = OwnerWriteGuard::findOrFailForOwner(
                Product::class,
                $option->product_id,
                includeGlobal: $includeGlobal,
                message: 'Cross-tenant write blocked: option value product is not accessible in the current owner context.',
            );

            // Prefer the product lineage; legacy rows may carry an owner on the
            // option while the product is global.
            if ($product->hasOwner()) {
                ChildOwnershipGuard::apply($optionValue, $product, 'product');
            } else {
                ChildOwnershipGuard::apply($optionValue, $option, 'option');
            }
        });

        static::updating(function (OptionValue $optionValue): void {
            // Parent and tenancy are immutable after creation.
            if ($optionValue->isDirty('option_id') || $optionValue->isDirty('owner_type') || $optionValue->isDirty('owner_id')) {
                throw new InvalidArgumentException('Cross-tenant write blocked: option value option and owner are immutable after creation.');
            }

            if (! (bool) config('products.features.owner.enabled', true)) {
                return;
            }

            $currentOwner = OwnerContext::resolve();

            // No owner context = system/admin operation, allow it
            if ($currentOwner === null) {
                return;
            }

            $optionValueIsGlobal = $optionValue->owner_type === null && $optionValue->owner_id === null;

            // Block tenant from updating global option values
            if ($optionValueIsGlobal) {
                throw new InvalidArgumentException('Cross-tenant write blocked: cannot update global option value from an owner context.');
            }

            // Block cross-tenant updates
            if (! $optionValue->belongsToOwner($currentOwner)) {
                throw new InvalidArgumentException('Cross-tenant write blocked: option value does not belong to the current owner context.');
            }
        });

        static::saving(function (OptionValue $optionValue): void {
            $color = $optionValue->swatch_color;

            if ($color !== null && $color !== '' && ! self::isValidSwatchColor($color)) {
                throw new InvalidArgumentException('Invalid swatch_color: must be a hex color such as #FF0000.');
            }

            $image = $optionValue->swatch_image;

            if ($image !== null && $image !== '' && ! self::isValidSwatchImage($image)) {
                throw new InvalidArgumentException('Invalid swatch_image: must be a plain path or URL without quotes, brackets, or backslashes.');
            }
        });

        static::deleting(function (OptionValue $optionValue): void {
            $optionValue->variants()->detach();
        });
    }
}
