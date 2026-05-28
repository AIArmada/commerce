<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $occurrence_id
 * @property string|null $order_id
 * @property string|null $order_item_id
 * @property string|null $purchaser_customer_id
 * @property string|null $participant_customer_id
 * @property string $code
 * @property RegistrationStatus $status
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $company
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $cancelled_at
 * @property array<string, mixed>|null $metadata
 * @property-read string $full_name
 */
class Registration extends Model
{
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'occurrence_id',
        'order_id',
        'order_item_id',
        'purchaser_customer_id',
        'participant_customer_id',
        'code',
        'status',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company',
        'checked_in_at',
        'cancelled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'checked_in_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $registration): void {
            if (is_string($registration->code) && mb_trim($registration->code) !== '') {
                return;
            }

            $registration->code = static::generateUniqueCode();
        });
    }

    public function getTable(): string
    {
        return config('events.database.tables.registrations', 'event_registrations');
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

        /** @var Builder<Registration> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobalToScope);

        return $scoped;
    }

    /**
     * @return BelongsTo<Occurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(Occurrence::class, 'occurrence_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function order(): BelongsTo
    {
        /** @var class-string<Model> $orderModel */
        $orderModel = config('events.integrations.order_model', Order::class);

        return $this->belongsTo($orderModel, 'order_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function orderItem(): BelongsTo
    {
        /** @var class-string<Model> $orderItemModel */
        $orderItemModel = config('events.integrations.order_item_model', OrderItem::class);

        return $this->belongsTo($orderItemModel, 'order_item_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function purchaserCustomer(): BelongsTo
    {
        /** @var class-string<Model> $customerModel */
        $customerModel = config('events.integrations.customer_model', Customer::class);

        return $this->belongsTo($customerModel, 'purchaser_customer_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function participantCustomer(): BelongsTo
    {
        /** @var class-string<Model> $customerModel */
        $customerModel = config('events.integrations.customer_model', Customer::class);

        return $this->belongsTo($customerModel, 'participant_customer_id');
    }

    public function getFullNameAttribute(): string
    {
        return mb_trim("{$this->first_name} {$this->last_name}");
    }

    public static function generateUniqueCode(): string
    {
        $prefix = mb_trim((string) config('events.codes.registration_prefix', 'REG'));
        $length = max(6, (int) config('events.codes.registration_length', 10));

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $random = Str::upper(Str::random($length));
            $code = $prefix !== '' ? $prefix . '-' . $random : $random;

            if (! static::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to generate a unique registration code.');
    }
}
