<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\GiftCards\Services;

use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardTransactionType;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardType;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use AIArmada\Vouchers\GiftCards\Models\GiftCardTransaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

class GiftCardService
{
    /**
     * Issue a new gift card.
     *
     * @param  array<string, mixed>  $data
     */
    public function issue(array $data): GiftCard
    {
        $data['type'] = $data['type'] ?? GiftCardType::Standard;
        $data['status'] = $data['status'] ?? GiftCardStatus::Inactive;
        $data['currency'] = $data['currency'] ?? config('vouchers.default_currency', 'MYR');
        $data['current_balance'] = $data['initial_balance'];

        if (! isset($data['code'])) {
            $data['code'] = GiftCard::generateCode();
        }

        /** @var GiftCard $giftCard */
        $giftCard = GiftCard::create($data);

        $giftCard->transactions()->create([
            'type' => GiftCardTransactionType::Issue,
            'amount' => $data['initial_balance'],
            'balance_before' => 0,
            'balance_after' => $data['initial_balance'],
            'description' => 'Gift card issued',
        ]);

        return $giftCard;
    }

    /**
     * Purchase a gift card (for customer-initiated purchase).
     */
    public function purchase(
        int $amount,
        Model $purchaser,
        ?Model $recipient = null,
        ?GiftCardType $type = null,
        ?array $metadata = null
    ): GiftCard {
        $giftCard = $this->issue([
            'type' => $type ?? GiftCardType::Standard,
            'initial_balance' => $amount,
            'purchaser_type' => $purchaser->getMorphClass(),
            'purchaser_id' => $purchaser->getKey(),
            'recipient_type' => $recipient?->getMorphClass() ?? $purchaser->getMorphClass(),
            'recipient_id' => $recipient?->getKey() ?? $purchaser->getKey(),
            'metadata' => $metadata,
        ]);

        return $giftCard;
    }

    /**
     * Create bulk gift cards.
     *
     * @param  array<string, mixed>  $options
     * @return Collection<int, GiftCard>
     */
    public function createBulk(int $count, int $amount, array $options = []): Collection
    {
        $giftCards = new Collection();

        for ($i = 0; $i < $count; $i++) {
            $data = array_merge($options, [
                'initial_balance' => $amount,
            ]);

            $giftCards->push($this->issue($data));
        }

        return $giftCards;
    }

    /**
     * Activate a gift card.
     */
    public function activate(string $code): GiftCard
    {
        $giftCard = GiftCard::findByCodeOrFail($code);

        return $giftCard->activate();
    }

    /**
     * Activate with a delayed activation date.
     */
    public function activateWithDelay(string $code, Carbon $activateAt): GiftCard
    {
        $giftCard = GiftCard::findByCodeOrFail($code);

        if ($activateAt->isPast()) {
            return $giftCard->activate();
        }

        $giftCard->update([
            'metadata' => array_merge($giftCard->metadata ?? [], [
                'scheduled_activation_at' => $activateAt->toIso8601String(),
            ]),
        ]);

        return $giftCard;
    }

    /**
     * Check the balance of a gift card.
     */
    public function checkBalance(string $code): int
    {
        $giftCard = GiftCard::findByCodeOrFail($code);

        return $giftCard->current_balance;
    }

    /**
     * Top up a gift card.
     */
    public function topUp(string $code, int $amount, Model $actor): GiftCard
    {
        $giftCard = GiftCard::findByCodeOrFail($code);
        $giftCard->topUp($amount, null, null, $actor);

        return $giftCard->refresh();
    }

    /**
     * Transfer gift card to a new recipient.
     */
    public function transfer(string $code, Model $newRecipient, Model $actor): GiftCard
    {
        $giftCard = GiftCard::findByCodeOrFail($code);

        return $giftCard->transferTo($newRecipient, $actor);
    }

    /**
     * Merge multiple gift cards into one.
     *
     * @param  array<string>  $codes
     */
    public function merge(array $codes, Model $actor): GiftCard
    {
        if (count($codes) < 2) {
            throw new InvalidArgumentException('At least 2 gift cards required for merge');
        }

        $giftCards = collect($codes)->map(fn ($code) => GiftCard::findByCodeOrFail($code));

        // Validate all cards can be merged
        foreach ($giftCards as $giftCard) {
            if (! $giftCard->isActive() && $giftCard->status !== GiftCardStatus::Exhausted) {
                throw new RuntimeException("Gift card {$giftCard->code} is not active");
            }
        }

        // Get first card as the target
        $targetCard = $giftCards->first();
        $totalMerged = 0;

        // Transfer balances from other cards to target
        foreach ($giftCards->skip(1) as $sourceCard) {
            if ($sourceCard->current_balance > 0) {
                $amount = $sourceCard->current_balance;
                $totalMerged += $amount;

                // Debit from source
                $sourceCard->debit(
                    amount: $amount,
                    type: GiftCardTransactionType::Transfer,
                    description: "Merged to {$targetCard->code}",
                    actor: $actor,
                    metadata: ['merge_target' => $targetCard->id]
                );

                // Cancel the source card
                $sourceCard->status = GiftCardStatus::Cancelled;
                $sourceCard->save();
            }
        }

        // Credit to target
        if ($totalMerged > 0) {
            $targetCard->credit(
                amount: $totalMerged,
                type: GiftCardTransactionType::Merge,
                description: 'Merged from ' . ($giftCards->count() - 1) . ' cards',
                actor: $actor,
                metadata: ['merged_from' => $giftCards->skip(1)->pluck('id')->toArray()]
            );
        }

        return $targetCard->refresh();
    }

    /**
     * Redeem gift card for an order.
     */
    public function redeem(string $code, int $amount, Model $reference): GiftCardTransaction
    {
        $giftCard = GiftCard::findByCodeOrFail($code);

        return $giftCard->redeem($amount, $reference);
    }

    /**
     * Refund to a gift card.
     */
    public function refund(string $code, int $amount, Model $reference): GiftCardTransaction
    {
        $giftCard = GiftCard::findByCodeOrFail($code);

        return $giftCard->refund($amount, $reference);
    }

    /**
     * Find a gift card by code.
     */
    public function findByCode(string $code): ?GiftCard
    {
        return GiftCard::findByCode($code);
    }

    /**
     * Get gift cards by recipient.
     *
     * @return Collection<int, GiftCard>
     */
    public function getByRecipient(Model $recipient): Collection
    {
        return GiftCard::query()
            ->forRecipient($recipient)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get gift cards expiring within days.
     *
     * @return Collection<int, GiftCard>
     */
    public function getExpiring(int $days = 30): Collection
    {
        return GiftCard::query()
            ->active()
            ->expiringWithin($days)
            ->orderBy('expires_at')
            ->get();
    }

    /**
     * Get active gift cards with balance.
     *
     * @return Collection<int, GiftCard>
     */
    public function getActiveWithBalance(): Collection
    {
        return GiftCard::query()
            ->active()
            ->withBalance()
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Suspend a gift card.
     */
    public function suspend(string $code): GiftCard
    {
        $giftCard = GiftCard::findByCodeOrFail($code);

        return $giftCard->suspend();
    }

    /**
     * Cancel a gift card.
     */
    public function cancel(string $code): GiftCard
    {
        $giftCard = GiftCard::findByCodeOrFail($code);

        return $giftCard->cancel();
    }

    /**
     * Expire a gift card.
     */
    public function expire(string $code, ?Model $actor = null): GiftCard
    {
        $giftCard = GiftCard::findByCodeOrFail($code);

        return $giftCard->expire($actor);
    }

    /**
     * Process scheduled activations.
     */
    public function processScheduledActivations(): int
    {
        $count = 0;

        $giftCards = GiftCard::query()
            ->where('status', GiftCardStatus::Inactive)
            ->whereNotNull('metadata->scheduled_activation_at')
            ->get();

        foreach ($giftCards as $giftCard) {
            $scheduledAt = $giftCard->metadata['scheduled_activation_at'] ?? null;

            if ($scheduledAt && Carbon::parse($scheduledAt)->isPast()) {
                $giftCard->activate();
                $count++;
            }
        }

        return $count;
    }

    /**
     * Process expired gift cards.
     */
    public function processExpired(): int
    {
        $count = 0;

        $giftCards = GiftCard::query()
            ->where('status', GiftCardStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($giftCards as $giftCard) {
            $giftCard->expire();
            $count++;
        }

        return $count;
    }

    /**
     * Get statistics for gift cards.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?Model $owner = null): array
    {
        $query = GiftCard::query();

        if ($owner) {
            $query->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());
        }

        $total = (clone $query)->count();
        $active = (clone $query)->where('status', GiftCardStatus::Active)->count();
        $totalIssued = (clone $query)->sum('initial_balance');
        $totalRedeemed = (int) $totalIssued - (int) (clone $query)->sum('current_balance');
        $totalOutstanding = (clone $query)
            ->whereIn('status', [GiftCardStatus::Active, GiftCardStatus::Inactive])
            ->sum('current_balance');

        return [
            'total_cards' => $total,
            'active_cards' => $active,
            'total_issued_cents' => (int) $totalIssued,
            'total_redeemed_cents' => $totalRedeemed,
            'total_outstanding_cents' => (int) $totalOutstanding,
            'redemption_rate' => $totalIssued > 0
                ? round(($totalRedeemed / $totalIssued) * 100, 2)
                : 0,
        ];
    }

    /**
     * Validate a gift card for use with optional PIN.
     *
     * @return array{valid: bool, message: string|null, available_balance: int|null}
     */
    public function validate(string $code, ?string $pin = null): array
    {
        $giftCard = GiftCard::findByCode($code);

        if (! $giftCard) {
            return [
                'valid' => false,
                'message' => 'Gift card not found',
                'available_balance' => null,
            ];
        }

        if (! $giftCard->verifyPin($pin)) {
            return [
                'valid' => false,
                'message' => 'Invalid PIN',
                'available_balance' => null,
            ];
        }

        if (! $giftCard->canRedeem()) {
            $reason = match (true) {
                $giftCard->isExpired() => 'Gift card has expired',
                ! $giftCard->isActive() => 'Gift card is not active',
                ! $giftCard->hasBalance() => 'Gift card has no balance',
                default => 'Gift card cannot be redeemed',
            };

            return [
                'valid' => false,
                'message' => $reason,
                'available_balance' => null,
            ];
        }

        return [
            'valid' => true,
            'message' => null,
            'available_balance' => $giftCard->current_balance,
        ];
    }
}
