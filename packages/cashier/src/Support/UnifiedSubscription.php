<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

final readonly class UnifiedSubscription
{
    public function __construct(
        public string $id,
        public string $gateway,
        public string $userId,
        public string $type,
        public string $planId,
        public int $amount,
        public string $currency,
        public int $quantity,
        public SubscriptionStatus $status,
        public ?CarbonImmutable $trialEndsAt,
        public ?CarbonImmutable $endsAt,
        public ?CarbonImmutable $nextBillingDate,
        public CarbonImmutable $createdAt,
        public Model $original,
    ) {}

    public static function fromStripe(Model $subscription): self
    {
        $attributes = $subscription->getAttributes();
        $trialEndsAt = $attributes['trial_ends_at'] ?? null;
        $endsAt = $attributes['ends_at'] ?? null;
        $createdAt = $attributes['created_at'] ?? now();

        return new self(
            id: (string) $subscription->getKey(),
            gateway: 'stripe',
            userId: (string) ($attributes['user_id'] ?? ''),
            type: (string) ($attributes['type'] ?? 'default'),
            planId: (string) ($attributes['stripe_price'] ?? ($attributes['name'] ?? ($attributes['type'] ?? 'default'))),
            amount: self::getStripeAmount($subscription),
            currency: mb_strtoupper((string) ($attributes['currency'] ?? config('cashier.gateways.stripe.currency', config('cashier.currency', 'MYR')))),
            quantity: (int) ($attributes['quantity'] ?? 1),
            status: self::normalizeStripeStatus($subscription),
            trialEndsAt: $trialEndsAt ? CarbonImmutable::parse($trialEndsAt) : null,
            endsAt: $endsAt ? CarbonImmutable::parse($endsAt) : null,
            nextBillingDate: self::calculateStripeNextBilling($subscription),
            createdAt: CarbonImmutable::parse($createdAt),
            original: $subscription,
        );
    }

    public static function fromChip(Model $subscription): self
    {
        $attributes = $subscription->getAttributes();
        $trialEndsAt = $attributes['trial_ends_at'] ?? null;
        $endsAt = $attributes['ends_at'] ?? null;
        $nextBillingAt = $attributes['next_billing_at'] ?? null;
        $createdAt = $attributes['created_at'] ?? now();

        return new self(
            id: (string) $subscription->getKey(),
            gateway: 'chip',
            userId: (string) ($attributes['billable_id'] ?? $attributes['user_id'] ?? ''),
            type: (string) ($attributes['type'] ?? 'default'),
            planId: (string) ($attributes['plan_id'] ?? ($attributes['name'] ?? ($attributes['type'] ?? 'default'))),
            amount: self::getChipAmount($subscription),
            currency: 'MYR',
            quantity: (int) ($attributes['quantity'] ?? 1),
            status: self::normalizeChipStatus($subscription),
            trialEndsAt: $trialEndsAt ? CarbonImmutable::parse($trialEndsAt) : null,
            endsAt: $endsAt ? CarbonImmutable::parse($endsAt) : null,
            nextBillingDate: $nextBillingAt ? CarbonImmutable::parse($nextBillingAt) : null,
            createdAt: CarbonImmutable::parse($createdAt),
            original: $subscription,
        );
    }

    public function formattedAmount(): string
    {
        return CurrencyFormatter::format($this->amount, $this->currency);
    }

    public function billingCycle(): string
    {
        $planLower = mb_strtolower($this->planId);

        if (str_contains($planLower, 'annual') || str_contains($planLower, 'yearly')) {
            return __('filament-cashier::subscriptions.cycle.yearly');
        }

        if (str_contains($planLower, 'quarter')) {
            return __('filament-cashier::subscriptions.cycle.quarterly');
        }

        return __('filament-cashier::subscriptions.cycle.monthly');
    }

    public function needsAttention(): bool
    {
        return in_array($this->status, [
            SubscriptionStatus::PastDue,
            SubscriptionStatus::Incomplete,
        ]);
    }

    /**
     * @return array{label: string, icon: string, color: string, dashboard_url: string}
     */
    public function gatewayConfig(): array
    {
        return app(GatewayDetector::class)->getGatewayConfig($this->gateway);
    }

    public function externalDashboardUrl(): string
    {
        $baseUrl = $this->gatewayConfig()['dashboard_url'];

        return match ($this->gateway) {
            'stripe' => "{$baseUrl}/subscriptions/{$this->getExternalId()}",
            'chip' => "{$baseUrl}/subscriptions/{$this->getExternalId()}",
            default => $baseUrl,
        };
    }

    public function getExternalId(): string
    {
        $attributes = $this->original->getAttributes();

        return match ($this->gateway) {
            'stripe' => (string) ($attributes['stripe_id'] ?? $this->id),
            'chip' => (string) ($attributes['chip_id'] ?? ($attributes['chip_subscription_id'] ?? $this->id)),
            default => $this->id,
        };
    }

    private static function getStripeAmount(Model $subscription): int
    {
        if ($subscription->relationLoaded('items')) {
            $items = $subscription->getRelation('items');

            if (is_iterable($items)) {
                foreach ($items as $item) {
                    if (is_object($item) && isset($item->stripe_price)) {
                        return (int) (($item->quantity ?? 0) * ($item->unit_amount ?? 0));
                    }

                    break;
                }
            }

            return 0;
        }

        if (! method_exists($subscription, 'items')) {
            return 0;
        }

        $items = $subscription->items();

        if ($items instanceof Relation) {
            $item = $items->select(['quantity', 'unit_amount', 'stripe_price'])->first();

            if (is_object($item) && isset($item->stripe_price)) {
                return (int) (($item->quantity ?? 0) * ($item->unit_amount ?? 0));
            }

            return 0;
        }

        if (is_object($items) && method_exists($items, 'exists') && ! $items->exists()) {
            return 0;
        }

        if (is_object($items) && method_exists($items, 'first')) {
            $item = $items->first();

            if (is_object($item) && isset($item->stripe_price)) {
                return (int) (($item->quantity ?? 0) * ($item->unit_amount ?? 0));
            }
        }

        return 0;
    }

    private static function getChipAmount(Model $subscription): int
    {
        $attributes = $subscription->getAttributes();

        if (isset($attributes['amount'])) {
            return (int) $attributes['amount'];
        }

        if ($subscription->relationLoaded('items')) {
            $items = $subscription->getRelation('items');

            if (is_iterable($items)) {
                $total = 0;

                foreach ($items as $item) {
                    if (! is_object($item)) {
                        continue;
                    }

                    $total += (int) (($item->quantity ?? 0) * ($item->unit_amount ?? 0));
                }

                return $total;
            }

            return 0;
        }

        if (! method_exists($subscription, 'items')) {
            return 0;
        }

        $items = $subscription->items();

        if ($items instanceof Relation) {
            return (int) $items->sum(DB::raw('quantity * unit_amount'));
        }

        if (is_object($items) && method_exists($items, 'sum')) {
            return (int) $items->sum(DB::raw('quantity * unit_amount'));
        }

        if (is_object($items) && method_exists($items, 'exists') && ! $items->exists()) {
            return 0;
        }

        return 0;
    }

    private static function normalizeStripeStatus(Model $subscription): SubscriptionStatus
    {
        if (method_exists($subscription, 'onGracePeriod') && $subscription->onGracePeriod()) {
            return SubscriptionStatus::OnGracePeriod;
        }

        if (method_exists($subscription, 'onTrial') && $subscription->onTrial()) {
            return SubscriptionStatus::OnTrial;
        }

        if (method_exists($subscription, 'canceled') && $subscription->canceled()) {
            return SubscriptionStatus::Canceled;
        }

        if (method_exists($subscription, 'pastDue') && $subscription->pastDue()) {
            return SubscriptionStatus::PastDue;
        }

        if (method_exists($subscription, 'active') && $subscription->active()) {
            return SubscriptionStatus::Active;
        }

        $stripeStatus = $subscription->getAttributes()['stripe_status'] ?? 'active';

        return match ($stripeStatus) {
            'active' => SubscriptionStatus::Active,
            'trialing' => SubscriptionStatus::OnTrial,
            'past_due' => SubscriptionStatus::PastDue,
            'canceled' => SubscriptionStatus::Canceled,
            'incomplete' => SubscriptionStatus::Incomplete,
            'incomplete_expired' => SubscriptionStatus::Expired,
            'paused' => SubscriptionStatus::Paused,
            default => SubscriptionStatus::Active,
        };
    }

    private static function normalizeChipStatus(Model $subscription): SubscriptionStatus
    {
        if (method_exists($subscription, 'onGracePeriod') && $subscription->onGracePeriod()) {
            return SubscriptionStatus::OnGracePeriod;
        }

        if (method_exists($subscription, 'onTrial') && $subscription->onTrial()) {
            return SubscriptionStatus::OnTrial;
        }

        if (method_exists($subscription, 'canceled') && $subscription->canceled()) {
            return SubscriptionStatus::Canceled;
        }

        if (method_exists($subscription, 'active') && $subscription->active()) {
            return SubscriptionStatus::Active;
        }

        $status = $subscription->getAttributes()['status'] ?? 'active';

        return match ($status) {
            'active' => SubscriptionStatus::Active,
            'trialing', 'trial' => SubscriptionStatus::OnTrial,
            'past_due' => SubscriptionStatus::PastDue,
            'canceled', 'cancelled' => SubscriptionStatus::Canceled,
            'paused' => SubscriptionStatus::Paused,
            'expired' => SubscriptionStatus::Expired,
            default => SubscriptionStatus::Active,
        };
    }

    private static function calculateStripeNextBilling(Model $subscription): ?CarbonImmutable
    {
        $attributes = $subscription->getAttributes();

        if (($attributes['ends_at'] ?? null) !== null) {
            return null;
        }

        $trialEndsAt = $attributes['trial_ends_at'] ?? null;

        if ($trialEndsAt !== null && CarbonImmutable::parse($trialEndsAt)->isFuture()) {
            return CarbonImmutable::parse($trialEndsAt);
        }

        $currentPeriodEnd = $attributes['current_period_end'] ?? null;

        if ($currentPeriodEnd !== null) {
            return is_numeric($currentPeriodEnd)
                ? CarbonImmutable::createFromTimestamp((int) $currentPeriodEnd)
                : CarbonImmutable::parse((string) $currentPeriodEnd);
        }

        return null;
    }
}
