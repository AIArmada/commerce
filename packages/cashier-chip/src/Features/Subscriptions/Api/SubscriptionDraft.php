<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Features\Subscriptions\Api;

use AIArmada\CashierChip\Data\BillingCycle;
use AIArmada\CommerceSupport\Data\RecordReference;
use DateTimeImmutable;
use InvalidArgumentException;
use SensitiveParameter;

final readonly class SubscriptionDraft
{
    /**
     * @param  array<string, array<string, mixed>>  $items
     */
    public function __construct(
        public RecordReference $billable,
        public string $type,
        public array $items,
        public BillingCycle $cycle,
        public ?DateTimeImmutable $trialEndsAt = null,
        public ?DateTimeImmutable $billingCycleAnchor = null,
        public ?string $couponId = null,
        #[SensitiveParameter]
        public ?string $recurringToken = null,
    ) {
        if ($items === []) {
            throw new InvalidArgumentException('At least one price is required when starting subscriptions.');
        }

        $prices = [];

        foreach ($items as $item) {
            if (! is_string($item['price'] ?? null) || mb_trim($item['price']) === '') {
                throw new InvalidArgumentException('A non-empty price identifier is required.');
            }

            if (isset($prices[$item['price']])) {
                throw new InvalidArgumentException('Subscription price identifiers must be unique.');
            }

            $prices[$item['price']] = true;

            if (! is_int($item['quantity'] ?? 1) || ($item['quantity'] ?? 1) < 1) {
                throw new InvalidArgumentException('Subscription quantity must be a positive integer.');
            }

            if (isset($item['unit_amount']) && (! is_int($item['unit_amount']) || $item['unit_amount'] < 0)) {
                throw new InvalidArgumentException('Subscription item unit amount must be a non-negative integer.');
            }
        }
    }

    public function hasUnknownPrices(): bool
    {
        foreach ($this->items as $item) {
            if (! isset($item['unit_amount'])) {
                return true;
            }
        }

        return false;
    }

    public function totalAmount(): int
    {
        $total = 0;

        foreach ($this->items as $item) {
            $amount = $item['unit_amount'] ?? 0;
            $quantity = $item['quantity'] ?? 1;

            if ($amount > intdiv(PHP_INT_MAX - $total, $quantity)) {
                throw new InvalidArgumentException('Subscription total exceeds the supported range.');
            }

            $total += $amount * $quantity;
        }

        return $total;
    }
}
