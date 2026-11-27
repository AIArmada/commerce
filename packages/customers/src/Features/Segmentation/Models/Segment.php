<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeKey;
use AIArmada\Customers\Database\Factories\SegmentFactory;
use AIArmada\Customers\Enums\SegmentType;
use AIArmada\Customers\Features\Segmentation\Actions\DetachDeletedSegmentMemberships;
use AIArmada\Customers\Features\Segmentation\Queries\AssertSegmentSlugAvailable;
use AIArmada\Customers\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property SegmentType $type
 * @property array<int, array{field: string, operator?: string, value: mixed}>|null $conditions
 * @property bool $is_automatic
 * @property int $priority
 * @property bool $is_active
 * @property CarbonImmutable|null $deactivated_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Customer> $customers
 */
class Segment extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasOwnerScopeKey;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'customers.features.owner';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'conditions',
        'is_automatic',
        'priority',
        'is_active',
        'deactivated_at',
        'metadata',
    ];

    public function getAuditInclude(): array
    {
        return [
            'owner_type',
            'owner_id',
            'name',
            'slug',
            'description',
            'type',
            'conditions',
            'is_automatic',
            'priority',
            'is_active',
            'metadata',
        ];
    }

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => SegmentType::class,
        'conditions' => 'array',
        'is_active' => 'boolean',
        'is_automatic' => 'boolean',
        'priority' => 'integer',
        'deactivated_at' => 'immutable_datetime',
        'metadata' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_automatic' => true,
        'priority' => 0,
    ];

    protected static function newFactory(): SegmentFactory
    {
        return SegmentFactory::new();
    }

    public function getTable(): string
    {
        $tables = config('customers.database.tables', []);
        $prefix = config('customers.database.table_prefix', 'customer_');

        return $tables['segments'] ?? $prefix . 'segments';
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the customers in this segment.
     *
     * @return BelongsToMany<Customer, $this>
     */
    public function customers(): BelongsToMany
    {
        $tables = config('customers.database.tables', []);
        $prefix = config('customers.database.table_prefix', 'customer_');

        return $this->belongsToMany(
            Customer::class,
            $tables['segment_customer'] ?? $prefix . 'segment_customer',
            'segment_id',
            'customer_id'
        )->withTimestamps();
    }

    // =========================================================================
    // CONDITION ENGINE
    // =========================================================================

    /**
     * Build the query for customers matching the segment conditions.
     *
     * @return Builder<Customer>
     */
    public function matchingCustomersQuery(): Builder
    {
        $segmentOwner = OwnerContext::fromTypeAndId($this->owner_type, $this->owner_id);

        $query = Customer::query()
            ->active()
            ->forOwner($segmentOwner, includeGlobal: false);

        if (empty($this->conditions)) {
            $query->whereRaw('1 = 0');

            return $query;
        }

        $this->applyConditions($query, $this->conditions);

        return $query;
    }

    /**
     * Get customers matching the segment conditions.
     */
    public function getMatchingCustomers(): Collection
    {
        if (! $this->is_automatic) {
            return $this->customers;
        }

        return $this->matchingCustomersQuery()->get();
    }

    /**
     * Count customers matching the segment conditions without hydrating models.
     */
    public function countMatchingCustomers(): int
    {
        if (! $this->is_automatic) {
            return $this->customers()->count();
        }

        return $this->matchingCustomersQuery()->count();
    }

    /**
     * Lazily iterate matching customer IDs in keyset chunks.
     *
     * @return LazyCollection<int, string>
     */
    public function matchingCustomerIds(int $chunkSize = 1000): LazyCollection
    {
        return $this->matchingCustomersQuery()
            ->select('id')
            ->lazyById($chunkSize)
            ->map(fn (Customer $customer): string => (string) $customer->getKey());
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isAutomatic(): bool
    {
        return $this->is_automatic;
    }

    public function isManual(): bool
    {
        return ! $this->is_automatic;
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAutomatic(Builder $query): Builder
    {
        return $query->where('is_automatic', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeManual(Builder $query): Builder
    {
        return $query->where('is_automatic', false);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOfType(Builder $query, SegmentType $type): Builder
    {
        return $query->where('type', $type);
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::saving(function (Segment $segment): void {
            app(AssertSegmentSlugAvailable::class)->execute($segment);

            if ($segment->isDirty('is_active')) {
                if ($segment->is_active === false) {
                    $segment->deactivated_at ??= CarbonImmutable::now();
                } else {
                    $segment->deactivated_at = null;
                }
            }
        });

        static::deleting(function (Segment $segment): void {
            app(DetachDeletedSegmentMemberships::class)->forSegment($segment);
        });
    }

    protected function getActivityLogName(): string
    {
        return 'customers';
    }

    /**
     * Apply segment conditions to a query.
     *
     * Conditions can use 'value_numeric' / 'value_boolean' / 'value_status' keys
     * (Filament form) or a single public API 'value' key. We normalize before
     * matching. Missing fields, missing values, and unknown fields match
     * nothing, mirroring SegmentationService::evaluateCondition().
     *
     * @param  Builder<Customer>  $query
     * @param  array<int, array{field?: string|null, operator?: string, value?: mixed, value_numeric?: mixed, value_boolean?: mixed, value_status?: mixed}>  $conditions
     */
    protected function applyConditions(Builder $query, array $conditions): void
    {
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $value = $condition['value_numeric']
                ?? $condition['value_boolean']
                ?? $condition['value_status']
                ?? $condition['value']
                ?? null;

            if (! is_string($field) || $field === '' || $value === null) {
                $query->whereRaw('1 = 0');

                continue;
            }

            match ($field) {
                'accepts_marketing' => $query->where('accepts_marketing', (bool) $value),
                'status' => $query->where('status', (string) $value),
                'created_days_ago' => $query->where('created_at', '<=', CarbonImmutable::now()->subDays((int) $value)),
                default => $query->whereRaw('1 = 0'),
            };
        }
    }
}
