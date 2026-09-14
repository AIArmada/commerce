<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Support;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Immutable scalar snapshot of a subscription for queued event payloads.
 *
 * Live SubscriptionContract implementations wrap gateway SDK objects or
 * Eloquent models with loaded relations that are expensive or impossible
 * to serialize. Queued listeners receive this snapshot instead and must
 * re-resolve the live subscription for gateway operations or mutations.
 */
final readonly class SnapshotSubscription implements SubscriptionContract
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(private array $snapshot) {}

    /**
     * Capture scalar state from a live subscription.
     *
     * @return array<string, mixed>
     */
    public static function capture(SubscriptionContract $subscription): array
    {
        return [
            'id' => $subscription->id(),
            'gateway' => $subscription->gateway(),
            'gateway_id' => $subscription->gatewayId(),
            'type' => $subscription->type(),
            'active' => $subscription->active(),
            'valid' => $subscription->valid(),
            'on_trial' => $subscription->onTrial(),
            'has_expired_trial' => $subscription->hasExpiredTrial(),
            'canceled' => $subscription->canceled(),
            'on_grace_period' => $subscription->onGracePeriod(),
            'ended' => $subscription->ended(),
            'recurring' => $subscription->recurring(),
            'past_due' => $subscription->pastDue(),
            'incomplete' => $subscription->incomplete(),
            'has_incomplete_payment' => $subscription->hasIncompletePayment(),
            'quantity' => $subscription->quantity(),
            'trial_ends_at' => self::toIso($subscription->trialEndsAt()),
            'ends_at' => self::toIso($subscription->endsAt()),
            'current_period_start' => self::toIso($subscription->currentPeriodStart()),
            'current_period_end' => self::toIso($subscription->currentPeriodEnd()),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function fromSnapshot(array $snapshot): self
    {
        return new self($snapshot);
    }

    public function id(): string
    {
        return (string) ($this->snapshot['id'] ?? '');
    }

    public function gateway(): string
    {
        return (string) ($this->snapshot['gateway'] ?? '');
    }

    public function gatewayId(): string
    {
        return (string) ($this->snapshot['gateway_id'] ?? '');
    }

    public function type(): string
    {
        return (string) ($this->snapshot['type'] ?? '');
    }

    public function active(): bool
    {
        return (bool) ($this->snapshot['active'] ?? false);
    }

    public function valid(): bool
    {
        return (bool) ($this->snapshot['valid'] ?? false);
    }

    public function onTrial(): bool
    {
        return (bool) ($this->snapshot['on_trial'] ?? false);
    }

    public function hasExpiredTrial(): bool
    {
        return (bool) ($this->snapshot['has_expired_trial'] ?? false);
    }

    public function canceled(): bool
    {
        return (bool) ($this->snapshot['canceled'] ?? false);
    }

    public function onGracePeriod(): bool
    {
        return (bool) ($this->snapshot['on_grace_period'] ?? false);
    }

    public function ended(): bool
    {
        return (bool) ($this->snapshot['ended'] ?? false);
    }

    public function recurring(): bool
    {
        return (bool) ($this->snapshot['recurring'] ?? false);
    }

    public function pastDue(): bool
    {
        return (bool) ($this->snapshot['past_due'] ?? false);
    }

    public function incomplete(): bool
    {
        return (bool) ($this->snapshot['incomplete'] ?? false);
    }

    public function hasIncompletePayment(): bool
    {
        return (bool) ($this->snapshot['has_incomplete_payment'] ?? false);
    }

    public function hasPrice(string $price): bool
    {
        return false;
    }

    public function trialEndsAt(): ?CarbonInterface
    {
        return self::fromIso($this->snapshot['trial_ends_at'] ?? null);
    }

    public function endsAt(): ?CarbonInterface
    {
        return self::fromIso($this->snapshot['ends_at'] ?? null);
    }

    public function currentPeriodStart(): ?CarbonInterface
    {
        return self::fromIso($this->snapshot['current_period_start'] ?? null);
    }

    public function currentPeriodEnd(): ?CarbonInterface
    {
        return self::fromIso($this->snapshot['current_period_end'] ?? null);
    }

    public function quantity(): ?int
    {
        $quantity = $this->snapshot['quantity'] ?? null;

        return is_numeric($quantity) ? (int) $quantity : null;
    }

    public function items(): Collection
    {
        return collect();
    }

    public function owner(): BillableContract
    {
        throw new LogicException('A subscription snapshot has no owner; re-resolve the live subscription first.');
    }

    public function cancel(): static
    {
        throw new LogicException('A subscription snapshot is read-only; re-resolve the live subscription first.');
    }

    public function cancelNow(): static
    {
        throw new LogicException('A subscription snapshot is read-only; re-resolve the live subscription first.');
    }

    public function cancelNowAndInvoice(): static
    {
        throw new LogicException('A subscription snapshot is read-only; re-resolve the live subscription first.');
    }

    public function resume(): static
    {
        throw new LogicException('A subscription snapshot is read-only; re-resolve the live subscription first.');
    }

    public function swap(string | array $prices, array $options = []): static
    {
        throw new LogicException('A subscription snapshot is read-only; re-resolve the live subscription first.');
    }

    public function updateQuantity(int $quantity, ?string $price = null): static
    {
        throw new LogicException('A subscription snapshot is read-only; re-resolve the live subscription first.');
    }

    public function incrementQuantity(int $count = 1, ?string $price = null): static
    {
        throw new LogicException('A subscription snapshot is read-only; re-resolve the live subscription first.');
    }

    public function decrementQuantity(int $count = 1, ?string $price = null): static
    {
        throw new LogicException('A subscription snapshot is read-only; re-resolve the live subscription first.');
    }

    public function asGatewaySubscription(): mixed
    {
        return $this->snapshot;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'gateway' => $this->gateway(),
            'gateway_id' => $this->gatewayId(),
            'type' => $this->type(),
            'quantity' => $this->quantity(),
            'active' => $this->active(),
            'valid' => $this->valid(),
            'on_trial' => $this->onTrial(),
            'canceled' => $this->canceled(),
            'ended' => $this->ended(),
        ];
    }

    public function toJson($options = 0): string
    {
        return (string) json_encode($this->toArray(), $options);
    }

    private static function toIso(?CarbonInterface $date): ?string
    {
        return $date?->toIso8601String();
    }

    private static function fromIso(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}
