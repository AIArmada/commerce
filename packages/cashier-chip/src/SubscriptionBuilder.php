<?php

namespace AIArmada\CashierChip;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use InvalidArgumentException;

class SubscriptionBuilder
{
    use Conditionable;

    /**
     * The model that is subscribing.
     *
     * @var \AIArmada\CashierChip\Billable|\Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The type of the subscription.
     *
     * @var string
     */
    protected string $type;

    /**
     * The prices the customer is being subscribed to.
     *
     * @var array
     */
    protected array $items = [];

    /**
     * The date and time the trial will expire.
     *
     * @var \Carbon\CarbonInterface|null
     */
    protected ?CarbonInterface $trialExpires = null;

    /**
     * Indicates that the trial should end immediately.
     *
     * @var bool
     */
    protected bool $skipTrial = false;

    /**
     * The billing interval (day, week, month, year).
     *
     * @var string
     */
    protected string $billingInterval = 'month';

    /**
     * The billing interval count.
     *
     * @var int
     */
    protected int $billingIntervalCount = 1;

    /**
     * The date on which the billing cycle should be anchored.
     *
     * @var \Carbon\CarbonInterface|null
     */
    protected ?CarbonInterface $billingCycleAnchor = null;

    /**
     * The metadata to apply to the subscription.
     *
     * @var array
     */
    protected array $metadata = [];

    /**
     * Create a new subscription builder instance.
     *
     * @param  mixed  $owner
     * @param  string  $type
     * @param  string|string[]|array[]  $prices
     * @return void
     */
    public function __construct($owner, string $type, string|array $prices = [])
    {
        $this->type = $type;
        $this->owner = $owner;

        foreach ((array) $prices as $price) {
            $this->price($price);
        }
    }

    /**
     * Set a price on the subscription builder.
     *
     * @param  string|array  $price
     * @param  int|null  $quantity
     * @return $this
     */
    public function price(string|array $price, ?int $quantity = 1)
    {
        $options = is_array($price) ? $price : ['price' => $price];

        $quantity = $price['quantity'] ?? $quantity;

        if (! is_null($quantity)) {
            $options['quantity'] = $quantity;
        }

        if (isset($options['price'])) {
            $this->items[$options['price']] = $options;
        } else {
            $this->items[] = $options;
        }

        return $this;
    }

    /**
     * Specify the quantity of a subscription item.
     *
     * @param  int|null  $quantity
     * @param  string|null  $price
     * @return $this
     */
    public function quantity(?int $quantity, ?string $price = null)
    {
        if (is_null($price)) {
            if (empty($this->items)) {
                throw new InvalidArgumentException('No price specified for quantity update.');
            }

            if (count($this->items) > 1) {
                throw new InvalidArgumentException('Price is required when creating subscriptions with multiple prices.');
            }

            $price = Arr::first($this->items)['price'];
        }

        return $this->price($price, $quantity);
    }

    /**
     * Specify the number of days of the trial.
     *
     * @param  int  $trialDays
     * @return $this
     */
    public function trialDays(int $trialDays)
    {
        $this->trialExpires = Carbon::now()->addDays($trialDays);

        return $this;
    }

    /**
     * Specify the ending date of the trial.
     *
     * @param  \Carbon\CarbonInterface  $trialUntil
     * @return $this
     */
    public function trialUntil(CarbonInterface $trialUntil)
    {
        $this->trialExpires = $trialUntil;

        return $this;
    }

    /**
     * Force the trial to end immediately.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->skipTrial = true;

        return $this;
    }

    /**
     * Set the billing interval.
     *
     * @param  string  $interval  (day, week, month, year)
     * @param  int  $count
     * @return $this
     */
    public function billingInterval(string $interval, int $count = 1)
    {
        $this->billingInterval = $interval;
        $this->billingIntervalCount = $count;

        return $this;
    }

    /**
     * Set monthly billing.
     *
     * @param  int  $count
     * @return $this
     */
    public function monthly(int $count = 1)
    {
        return $this->billingInterval('month', $count);
    }

    /**
     * Set yearly billing.
     *
     * @param  int  $count
     * @return $this
     */
    public function yearly(int $count = 1)
    {
        return $this->billingInterval('year', $count);
    }

    /**
     * Set weekly billing.
     *
     * @param  int  $count
     * @return $this
     */
    public function weekly(int $count = 1)
    {
        return $this->billingInterval('week', $count);
    }

    /**
     * Set daily billing.
     *
     * @param  int  $count
     * @return $this
     */
    public function daily(int $count = 1)
    {
        return $this->billingInterval('day', $count);
    }

    /**
     * Change the billing cycle anchor on a subscription creation.
     *
     * @param  \DateTimeInterface|\Carbon\CarbonInterface  $date
     * @return $this
     */
    public function anchorBillingCycleOn(DateTimeInterface|CarbonInterface $date)
    {
        $this->billingCycleAnchor = Carbon::instance($date);

        return $this;
    }

    /**
     * The metadata to apply to a new subscription.
     *
     * @param  array  $metadata
     * @return $this
     */
    public function withMetadata(array $metadata)
    {
        $this->metadata = (array) $metadata;

        return $this;
    }

    /**
     * Add a new subscription without immediate payment.
     *
     * @param  array  $options
     * @return \AIArmada\CashierChip\Subscription
     */
    public function add(array $options = []): Subscription
    {
        return $this->create(null, $options);
    }

    /**
     * Create a new subscription.
     *
     * Since CHIP doesn't have native subscriptions, we:
     * 1. Ensure the customer exists in CHIP
     * 2. Create local subscription record
     * 3. Optionally charge immediately or wait for first billing date
     *
     * @param  string|null  $recurringToken  The CHIP recurring token for payments
     * @param  array  $options
     * @return \AIArmada\CashierChip\Subscription
     *
     * @throws \Exception
     */
    public function create(?string $recurringToken = null, array $options = []): Subscription
    {
        if (empty($this->items)) {
            throw new Exception('At least one price is required when starting subscriptions.');
        }

        // Ensure the customer exists (only if they don't have a chip_id already)
        if (method_exists($this->owner, 'createOrGetChipCustomer') && ! $this->owner->hasChipId()) {
            $this->owner->createOrGetChipCustomer();
        }

        // Calculate the next billing date
        $nextBillingAt = $this->calculateNextBillingDate();

        // Calculate trial end
        $trialEndsAt = ! $this->skipTrial ? $this->trialExpires : null;

        // If there's a trial, set next billing to after trial
        if ($trialEndsAt) {
            $nextBillingAt = $trialEndsAt->copy()->add($this->billingInterval, $this->billingIntervalCount);
        }

        // Determine initial status
        $status = $trialEndsAt ? Subscription::STATUS_TRIALING : Subscription::STATUS_ACTIVE;

        // Get the first item to set on subscription
        $firstItem = Arr::first($this->items);
        $isSinglePrice = count($this->items) === 1;

        /** @var \AIArmada\CashierChip\Subscription $subscription */
        $subscription = $this->owner->subscriptions()->create([
            'type' => $this->type,
            'chip_id' => Str::uuid()->toString(),
            'chip_status' => $status,
            'chip_price' => $isSinglePrice ? ($firstItem['price'] ?? null) : null,
            'quantity' => $isSinglePrice ? ($firstItem['quantity'] ?? 1) : null,
            'trial_ends_at' => $trialEndsAt,
            'next_billing_at' => $nextBillingAt,
            'billing_interval' => $this->billingInterval,
            'billing_interval_count' => $this->billingIntervalCount,
            'recurring_token' => $recurringToken ?? $this->owner->defaultPaymentMethod(),
            'ends_at' => null,
        ]);

        // Create subscription items
        foreach ($this->items as $item) {
            $subscription->items()->create([
                'chip_id' => Str::uuid()->toString(),
                'chip_product' => $item['product'] ?? null,
                'chip_price' => $item['price'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'unit_amount' => $item['unit_amount'] ?? null,
            ]);
        }

        return $subscription;
    }

    /**
     * Create a new subscription and charge immediately.
     *
     * @param  string|null  $recurringToken
     * @param  int  $amount  Amount in cents
     * @param  array  $options
     * @return \AIArmada\CashierChip\Subscription
     */
    public function createAndCharge(?string $recurringToken = null, int $amount = 0, array $options = []): Subscription
    {
        $subscription = $this->create($recurringToken, $options);

        // If not on trial, charge immediately
        if (! $subscription->onTrial() && $amount > 0) {
            $subscription->charge($amount);
        }

        return $subscription;
    }

    /**
     * Begin a new Checkout Session for the subscription.
     *
     * @param  array  $sessionOptions
     * @return \AIArmada\CashierChip\Checkout
     */
    public function checkout(array $sessionOptions = [])
    {
        if (empty($this->items)) {
            throw new Exception('At least one price is required when starting subscriptions.');
        }

        // Calculate the total amount from items
        $amount = $this->calculateTotalAmount();

        // Build the checkout session
        $metadata = array_merge($this->metadata, [
            'subscription_type' => $this->type,
            'billing_interval' => $this->billingInterval,
            'billing_interval_count' => $this->billingIntervalCount,
            'items' => json_encode($this->items),
        ]);

        if ($this->trialExpires) {
            $metadata['trial_ends_at'] = $this->trialExpires->toIso8601String();
        }

        return Checkout::customer($this->owner)
            ->recurring()
            ->withMetadata($metadata)
            ->create(
                $amount,
                array_merge([
                    'reference' => "Subscription: {$this->type}",
                ], $sessionOptions)
            );
    }

    /**
     * Calculate the next billing date.
     *
     * @return \Carbon\CarbonInterface
     */
    protected function calculateNextBillingDate(): CarbonInterface
    {
        if ($this->billingCycleAnchor) {
            return $this->billingCycleAnchor->copy();
        }

        return Carbon::now()->add($this->billingInterval, $this->billingIntervalCount);
    }

    /**
     * Calculate the total amount from all items.
     *
     * @return int
     */
    protected function calculateTotalAmount(): int
    {
        return collect($this->items)->sum(function ($item) {
            return ($item['unit_amount'] ?? 0) * ($item['quantity'] ?? 1);
        });
    }

    /**
     * Get the items set on the subscription builder.
     *
     * @return array
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Get the subscription type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the trial end date.
     *
     * @return \Carbon\CarbonInterface|null
     */
    public function getTrialEnd(): ?CarbonInterface
    {
        return $this->trialExpires;
    }

    /**
     * Check if the trial will be skipped.
     *
     * @return bool
     */
    public function getSkipTrial(): bool
    {
        return $this->skipTrial;
    }

    /**
     * Get the billing interval.
     *
     * @return string
     */
    public function getBillingInterval(): string
    {
        return $this->billingInterval;
    }

    /**
     * Get the billing interval count.
     *
     * @return int
     */
    public function getBillingIntervalCount(): int
    {
        return $this->billingIntervalCount;
    }

    /**
     * Get the billing cycle anchor.
     *
     * @return \Carbon\CarbonInterface|null
     */
    public function getBillingCycleAnchor(): ?CarbonInterface
    {
        return $this->billingCycleAnchor;
    }

    /**
     * Get the metadata.
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
