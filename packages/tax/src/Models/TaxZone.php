<?php

declare(strict_types=1);

namespace AIArmada\Tax\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Persistence\DeletesAtomically;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Tax\Actions\CascadeTaxZoneDeletionAction;
use AIArmada\Tax\Contracts\TaxZoneResolverCacheInterface;
use AIArmada\Tax\Database\Factories\TaxZoneFactory;
use AIArmada\Tax\Enums\ZoneType;
use AIArmada\Tax\Services\PostcodeMatcher;
use AIArmada\Tax\Services\ZoneResolver\TaxZoneResolverInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Represents a geographic tax zone (Country, State, Postcode range).
 *
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property ZoneType $type
 * @property array|null $countries
 * @property array|null $states
 * @property array|null $postcodes
 * @property int $priority
 * @property bool $is_default
 * @property bool $is_active
 */
class TaxZone extends Model implements Auditable
{
    use DeletesAtomically;
    use HasCommerceAudit;

    /** @use HasFactory<TaxZoneFactory> */
    use HasFactory;

    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'tax.features.owner';

    use LogsCommerceActivity;

    protected $fillable = [
        'name',
        'code',
        'description',
        'type',
        'countries',
        'states',
        'postcodes',
        'priority',
        'is_default',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'country',
        'priority' => 0,
        'is_default' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'type' => ZoneType::class,
            'countries' => 'array',
            'states' => 'array',
            'postcodes' => 'array',
            'priority' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // =========================================================================
    // STATIC HELPERS
    // =========================================================================

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): TaxZoneFactory
    {
        return TaxZoneFactory::new();
    }

    public function getTable(): string
    {
        return (string) config('tax.database.tables.tax_zones', 'tax_zones');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * @return HasMany<TaxRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class, 'zone_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Prefilter by country/state and reject restricted zones when address
     * fields are missing. Postcode patterns still require matchesAddress()
     * to evaluate exact, wildcard and range matches in PHP.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForAddress(Builder $query, string $country, ?string $state = null, ?string $postcode = null): Builder
    {
        return $query
            ->active()
            ->where(function (Builder $builder) use ($country): void {
                $builder
                    ->whereNull('countries')
                    ->orWhereJsonLength('countries', 0)
                    ->orWhereJsonContains('countries', $country);
            })
            ->where(function (Builder $builder) use ($state): void {
                $builder->whereNull('states')->orWhereJsonLength('states', 0);

                if ($state !== null && mb_trim($state) !== '') {
                    $builder->orWhereJsonContains('states', $state);
                }
            })
            ->when($postcode === null || mb_trim($postcode) === '', function (Builder $builder): void {
                $builder->where(function (Builder $inner): void {
                    $inner->whereNull('postcodes')->orWhereJsonLength('postcodes', 0);
                });
            })
            ->orderBy('priority', 'desc');
    }

    // =========================================================================
    // MATCHING
    // =========================================================================

    /**
     * Check if an address matches this zone.
     */
    public function matchesAddress(string $country, ?string $state = null, ?string $postcode = null): bool
    {
        // Check country match
        if (! empty($this->countries) && ! in_array($country, $this->countries, true)) {
            return false;
        }

        // A restricted zone requires a matching state.
        if (! empty($this->states) && ($state === null || mb_trim($state) === '' || ! in_array($state, $this->states, true))) {
            return false;
        }

        // Check postcode match (if postcodes are specified)
        if (! empty($this->postcodes)) {
            if ($postcode === null || mb_trim($postcode) === '') {
                return false;
            }

            $matches = false;
            foreach ($this->postcodes as $pattern) {
                if (PostcodeMatcher::matches($postcode, $pattern)) {
                    $matches = true;

                    break;
                }
            }
            if (! $matches) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'countries', 'states', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('tax');
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::saving(function (self $zone): void {
            if (config('tax.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();

                if ($owner === null) {
                    if ($zone->owner_type !== null || $zone->owner_id !== null) {
                        throw new AuthorizationException('Cannot write owned tax zones without an owner context.');
                    }
                } else {
                    if ($zone->owner_type === null && $zone->owner_id === null) {
                        if ($zone->exists) {
                            throw new AuthorizationException('Cannot mutate global tax zones without explicit global context.');
                        }

                        if ((bool) config('tax.features.owner.auto_assign_on_create', true)) {
                            $zone->assignOwner($owner);
                        }
                    }

                    if (! $zone->belongsToOwner($owner)) {
                        throw new AuthorizationException('Cannot write tax zones outside the current owner scope.');
                    }
                }
            }

            self::assertCodeIsUnique($zone);
        });

        static::deleting(static function (TaxZone $zone): void {
            app(CascadeTaxZoneDeletionAction::class)->execute($zone);
        });

        static::saved(static function (): void {
            self::clearResolverCache();
        });

        static::deleted(static function (): void {
            self::clearResolverCache();
        });
    }

    private static function clearResolverCache(): void
    {
        if (! app()->bound(TaxZoneResolverInterface::class)) {
            return;
        }

        $resolver = app(TaxZoneResolverInterface::class);

        if ($resolver instanceof TaxZoneResolverCacheInterface) {
            $resolver->clearCache();
        }
    }

    private static function assertCodeIsUnique(self $zone): void
    {
        $code = (string) $zone->getAttribute('code');

        if ($code === '') {
            return;
        }

        $query = static::query()
            ->withoutOwnerScope()
            ->where('code', $code)
            ->when($zone->exists, fn (Builder $builder): Builder => $builder->whereKeyNot($zone->getKey()));

        if ($zone->owner_type === null && $zone->owner_id === null) {
            $query->whereNull('owner_type')->whereNull('owner_id');
        } else {
            $query->where('owner_type', $zone->owner_type)->where('owner_id', $zone->owner_id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'code' => 'The tax zone code has already been taken for this owner.',
            ]);
        }
    }
}
