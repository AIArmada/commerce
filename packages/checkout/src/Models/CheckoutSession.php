<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Models;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\States\Cancelled;
use AIArmada\Checkout\States\CheckoutState;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentFailed;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Customers\Models\Customer;
use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string $cart_id
 * @property string|null $customer_id
 * @property string|null $billable_type
 * @property string|null $billable_id
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
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $payment_failed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class CheckoutSession extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasStates;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'checkout.owner';

    public function initializeHasStates(): void
    {
        // CheckoutSession keeps its own pending default in $attributes and via
        // explicit writes in the service layer. Skipping the trait initializer
        // here prevents freshly hydrated models from being primed with the
        // default state before database attributes are read back.
    }

    /**
     * Host-supplied input only. Every server-computed field (owner tuple,
     * status, totals, snapshots, payment data, timestamps) is guarded so a
     * host-side `update($request->all())` cannot hop tenants, tamper status,
     * or zero the grand total. Package internals persist computed state via
     * `persistState()`.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cart_id',
        'customer_id',
        'billing_data',
        'shipping_data',
        'selected_shipping_method',
        'selected_payment_gateway',
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
     * @return MorphTo<Model, $this>
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
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

    /**
     * Persist server-computed attributes, bypassing mass-assignment guards.
     * This is the only write path the package itself uses for computed
     * state; hosts must only mass-assign `$fillable` input fields.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function persistState(array $attributes): bool
    {
        return $this->forceFill($attributes)->save();
    }

    public function setStepState(string $identifier, StepStatus $status): void
    {
        $states = $this->step_states ?? [];

        if (($states[$identifier] ?? null) === $status->value) {
            return;
        }

        $states[$identifier] = $status->value;

        $this->persistState(['step_states' => $states]);
    }

    /**
     * Mark a step started and advance the pipeline pointer in one write.
     */
    public function beginStep(string $identifier): void
    {
        $states = $this->step_states ?? [];
        $states[$identifier] = StepStatus::Processing->value;

        $this->persistState([
            'step_states' => $states,
            'current_step' => $identifier,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStepData(string $identifier): array
    {
        $stepData = data_get($this->payment_data ?? [], "checkout_step_data.{$identifier}", []);

        return is_array($stepData) ? $stepData : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function setStepData(string $identifier, array $data): void
    {
        $paymentData = $this->payment_data ?? [];
        $stepData = $paymentData['checkout_step_data'] ?? [];

        if (! is_array($stepData)) {
            $stepData = [];
        }

        $stepData[$identifier] = $data;
        $paymentData['checkout_step_data'] = $stepData;

        $this->persistState(['payment_data' => $paymentData]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCompensationLog(): array
    {
        $log = data_get($this->payment_data ?? [], 'checkout_compensation_log', []);

        if (! is_array($log)) {
            return [];
        }

        return array_values(array_filter(
            $log,
            static fn (mixed $entry): bool => is_array($entry),
        ));
    }

    public function recordCompensation(string $stepIdentifier, StepResult $result): void
    {
        $paymentData = $this->payment_data ?? [];
        $log = $this->getCompensationLog();

        $log[] = [
            'step_identifier' => $stepIdentifier,
            'status' => $result->status->value,
            'message' => $result->message,
            'data' => $result->data,
            'errors' => $result->errors,
            'recorded_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        $paymentData['checkout_compensation_log'] = $log;

        $this->persistState(['payment_data' => $paymentData]);
    }

    public function isStepCompleted(string $identifier): bool
    {
        $state = $this->getStepState($identifier);

        return $state === StepStatus::Completed || $state === StepStatus::Skipped;
    }

    public function calculateTotals(): void
    {
        $this->grand_total = max(
            0,
            $this->subtotal
                - $this->discount_total
                + $this->shipping_total
                + $this->tax_total,
        );
    }

    /**
     * Persist a checkout status transition reliably for cross-request flows.
     *
     * This is the package's single transition path. Spatie's `transitionTo()`
     * already persists via `DefaultTransition::handle()` (one `save()`), and
     * the `updating` hook stamps the terminal lifecycle timestamp in that
     * same write, so no follow-up query is needed.
     *
     * @param  class-string<CheckoutState>  $stateClass
     */
    public function transitionStatus(string $stateClass): self
    {
        $this->status->transitionTo($stateClass);

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
            'cancelled_at' => 'immutable_datetime',
            'payment_failed_at' => 'immutable_datetime',
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
            if (! $session->isDirty('status')) {
                return;
            }

            $status = $session->status;

            if ($status instanceof Completed) {
                $session->completed_at = CarbonImmutable::now();
            } elseif ($status instanceof Cancelled) {
                $session->cancelled_at = CarbonImmutable::now();
            } elseif ($status instanceof PaymentFailed) {
                $session->payment_failed_at = CarbonImmutable::now();
            }
        });
    }
}
