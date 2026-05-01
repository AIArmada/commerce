<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Models;

use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\States\CheckoutState;
use AIArmada\Checkout\States\Completed;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Customers\Models\Customer;
use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string $cart_id
 * @property string|null $customer_id
 * @property string|null $order_id
 * @property string|null $payment_id
 * @property CheckoutState $status
 * @property string|null $current_step
 * @property array<string, mixed> $cart_snapshot
 * @property array<string, string> $step_states
 * @property array<string, mixed> $shipping_data
 * @property array<string, mixed> $billing_data
 * @property array<string, mixed> $pricing_data
 * @property array<string, mixed> $discount_data
 * @property array<string, mixed> $tax_data
 * @property array<string, mixed> $payment_data
 * @property string|null $payment_redirect_url
 * @property int $payment_attempts
 * @property string|null $selected_shipping_method
 * @property string|null $selected_payment_gateway
 * @property int $subtotal
 * @property int $discount_total
 * @property int $shipping_total
 * @property int $tax_total
 * @property int $grand_total
 * @property string $currency
 * @property string|null $error_message
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class CheckoutSession extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasStates;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'checkout.owner';

    public function initializeHasStates(): void
    {
        // CheckoutSession keeps its own pending default in $attributes and via
        // explicit writes in the service layer. Skipping the trait initializer
        // here prevents freshly hydrated models from being primed with the
        // default state before database attributes are read back.
    }

    protected $fillable = [
        'cart_id',
        'customer_id',
        'order_id',
        'payment_id',
        'status',
        'current_step',
        'cart_snapshot',
        'step_states',
        'shipping_data',
        'billing_data',
        'pricing_data',
        'discount_data',
        'tax_data',
        'payment_data',
        'payment_redirect_url',
        'payment_attempts',
        'selected_shipping_method',
        'selected_payment_gateway',
        'subtotal',
        'discount_total',
        'shipping_total',
        'tax_total',
        'grand_total',
        'currency',
        'error_message',
        'expires_at',
        'completed_at',
        'owner_type',
        'owner_id',
    ];

    protected $attributes = [
        'status' => 'pending',
        'payment_attempts' => 0,
        'subtotal' => 0,
        'discount_total' => 0,
        'shipping_total' => 0,
        'tax_total' => 0,
        'grand_total' => 0,
    ];

    public function getTable(): string
    {
        $tables = config('checkout.database.tables', []);
        $prefix = config('checkout.database.table_prefix', '');

        return $tables['checkout_sessions'] ?? $prefix . 'checkout_sessions';
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        /** @var class-string<Customer> $customerModel */
        $customerModel = config('checkout.models.customer', Customer::class);

        return $this->belongsTo($customerModel, 'customer_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function order(): BelongsTo
    {
        /** @var class-string<Model> $orderModel */
        $orderModel = config('checkout.models.order', Order::class);

        return $this->belongsTo($orderModel, 'order_id');
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    public function getStepState(string $identifier): ?StepStatus
    {
        $states = $this->step_states ?? [];
        $state = $states[$identifier] ?? null;

        return $state !== null ? StepStatus::from($state) : null;
    }

    public function setStepState(string $identifier, StepStatus $status): void
    {
        $states = $this->step_states ?? [];
        $states[$identifier] = $status->value;

        $this->update(['step_states' => $states]);
    }

    public function isStepCompleted(string $identifier): bool
    {
        $state = $this->getStepState($identifier);

        return $state === StepStatus::Completed || $state === StepStatus::Skipped;
    }

    public function calculateTotals(): void
    {
        $this->grand_total = $this->subtotal
            - $this->discount_total
            + $this->shipping_total
            + $this->tax_total;
    }

    /**
     * Persist a checkout status transition reliably for cross-request flows.
     *
     * @param  class-string<CheckoutState>  $stateClass
     */
    public function transitionStatus(string $stateClass): self
    {
        $this->status->transitionTo($stateClass);

        $updates = [
            'status' => $stateClass::getMorphClass(),
        ];

        if (is_a($stateClass, Completed::class, true)) {
            $updates['completed_at'] = CarbonImmutable::now();
        }

        $updates['updated_at'] = CarbonImmutable::now();

        // Direct DB::table() update scoped to this model's own PK to bypass Spatie's HasStates
        // Eloquent listener (which would cause an infinite loop). The PK constraint guarantees
        // this touches exactly one row for the already-resolved model instance.
        $this->getConnection()
            ->table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->update($updates);

        $this->forceFill(['status' => $stateClass]);

        if (array_key_exists('completed_at', $updates)) {
            $this->completed_at = $updates['completed_at'];
        }

        $this->updated_at = $updates['updated_at'];

        unset($this->classCastCache['status'], $this->attributeCastCache['status']);

        $this->syncOriginal();

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => CheckoutState::class,
            'cart_snapshot' => 'array',
            'step_states' => 'array',
            'shipping_data' => 'array',
            'billing_data' => 'array',
            'pricing_data' => 'array',
            'discount_data' => 'array',
            'tax_data' => 'array',
            'payment_data' => 'array',
            'payment_attempts' => 'integer',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'shipping_total' => 'integer',
            'tax_total' => 'integer',
            'grand_total' => 'integer',
            'expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CheckoutSession $session): void {
            if (
                (bool) config('checkout.owner.enabled', false)
                && (bool) config('checkout.owner.auto_assign_on_create', true)
                && ! $session->hasOwner()
            ) {
                $owner = OwnerContext::resolve();

                if ($owner !== null) {
                    $session->assignOwner($owner);
                }
            }

            $session->currency ??= config('checkout.defaults.currency', 'MYR');
        });

        static::updating(function (CheckoutSession $session): void {
            if ($session->isDirty('status') && $session->status instanceof Completed) {
                $session->completed_at = CarbonImmutable::now();
            }
        });
    }
}
