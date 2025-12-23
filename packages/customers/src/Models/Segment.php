<?php

declare(strict_types=1);

namespace AIArmada\Customers\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Customers\Enums\SegmentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

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
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Customer> $customers
 */
class Segment extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'customers.features.owner';

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => SegmentType::class,
        'conditions' => 'array',
        'is_active' => 'boolean',
        'is_automatic' => 'boolean',
        'priority' => 'integer',
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

    public function getTable(): string
    {
        return config('customers.database.tables.segments', 'customer_segments');
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
        return $this->belongsToMany(
            Customer::class,
            config('customers.database.tables.segment_customer', 'customer_segment_customer'),
            'segment_id',
            'customer_id'
        )->withTimestamps();
    }

    // =========================================================================
    // CONDITION ENGINE
    // =========================================================================

    /**
     * Get customers matching the segment conditions.
     */
    public function getMatchingCustomers(): Collection
    {
        if (! $this->is_automatic || empty($this->conditions)) {
            return $this->customers;
        }

        $segmentOwner = OwnerContext::fromTypeAndId($this->owner_type, $this->owner_id);

        $query = Customer::query()
            ->active()
            ->forOwner($segmentOwner, includeGlobal: false);
        $this->applyConditions($query, $this->conditions);

        return $query->get();
    }

    /**
     * Rebuild the customer list for automatic segments.
     */
    public function rebuildCustomerList(): int
    {
        if (! $this->is_automatic) {
            return $this->customers()->count();
        }

        $matchingCustomers = $this->getMatchingCustomers();
        $this->customers()->sync($matchingCustomers->pluck('id'));

        return $matchingCustomers->count();
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

    /**
     * Add a customer to this segment.
     */
    public function addCustomer(Customer $customer): void
    {
        if (! $this->isSameOwnerAsCustomer($customer)) {
            throw new InvalidArgumentException('Segment and customer must share the same owner context.');
        }

        $this->customers()->syncWithoutDetaching([$customer->id]);
    }

    /**
     * Remove a customer from this segment.
     */
    public function removeCustomer(Customer $customer): void
    {
        if (! $this->isSameOwnerAsCustomer($customer)) {
            throw new InvalidArgumentException('Segment and customer must share the same owner context.');
        }

        $this->customers()->detach($customer->id);
    }

    private function isSameOwnerAsCustomer(Customer $customer): bool
    {
        if ($this->owner_type === null && $this->owner_id === null) {
            return $customer->owner_type === null && $customer->owner_id === null;
        }

        return $customer->owner_type === $this->owner_type
            && $customer->owner_id === $this->owner_id;
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
        static::creating(function (Segment $segment): void {
            if (! (bool) config('customers.features.owner.enabled', false)) {
                return;
            }

            if (! (bool) config('customers.features.owner.auto_assign_on_create', true)) {
                return;
            }

            if ($segment->owner_type !== null || $segment->owner_id !== null) {
                return;
            }

            $owner = OwnerContext::resolve();

            if ($owner !== null) {
                $segment->assignOwner($owner);
            }
        });

        static::deleting(function (Segment $segment): void {
            $segment->customers()->detach();
        });
    }

    /**
     * Apply segment conditions to a query.
     *
     * @param  array<int, array{field?: string|null, operator?: string, value?: mixed}>  $conditions
     */
    protected function applyConditions(Builder $query, array $conditions): void
    {
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $value = $condition['value'] ?? null;

            if (! $field || $value === null) {
                continue;
            }

            switch ($field) {
                case 'lifetime_value_min':
                    $query->where('lifetime_value', '>=', (int) $value);

                    break;
                case 'lifetime_value_max':
                    $query->where('lifetime_value', '<=', (int) $value);

                    break;
                case 'total_orders_min':
                    $query->where('total_orders', '>=', (int) $value);

                    break;
                case 'total_orders_max':
                    $query->where('total_orders', '<=', (int) $value);

                    break;
                case 'last_order_days':
                    $query->where('last_order_at', '>=', now()->subDays((int) $value));

                    break;
                case 'no_order_days':
                    $query->where('last_order_at', '<=', now()->subDays((int) $value));

                    break;
                case 'accepts_marketing':
                    $query->where('accepts_marketing', (bool) $value);

                    break;
                case 'is_tax_exempt':
                    $query->where('is_tax_exempt', (bool) $value);

                    break;
                default:
                    continue 2;
            }
        }
    }
}
