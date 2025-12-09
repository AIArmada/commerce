<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\GiftCards\Traits;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Vouchers\GiftCards\Conditions\GiftCardCondition;
use AIArmada\Vouchers\GiftCards\Exceptions\InvalidGiftCardException;
use AIArmada\Vouchers\GiftCards\Exceptions\InvalidGiftCardPinException;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * InteractsWithGiftCards trait adds gift card management capabilities to the Cart.
 *
 * This trait provides convenient methods for applying, removing, and checking gift cards
 * while integrating seamlessly with the cart's condition system.
 */
trait InteractsWithGiftCards
{
    /**
     * Get the underlying cart instance.
     */
    abstract protected function getUnderlyingCart(): Cart;

    /**
     * Apply a gift card to the cart by code.
     *
     * @throws InvalidGiftCardException If the gift card is invalid or cannot be applied
     * @throws InvalidGiftCardPinException If the PIN is incorrect
     */
    public function applyGiftCard(string $code, ?string $pin = null, int $order = 100): static
    {
        $giftCard = GiftCard::findByCode($code);

        if (! $giftCard) {
            throw new InvalidGiftCardException("Gift card not found: {$code}");
        }

        if (! $giftCard->verifyPin($pin)) {
            throw new InvalidGiftCardPinException($code);
        }

        if (! $giftCard->canRedeem()) {
            $reason = match (true) {
                $giftCard->isExpired() => 'Gift card has expired',
                ! $giftCard->isActive() => 'Gift card is not active',
                ! $giftCard->hasBalance() => 'Gift card has no balance',
                default => 'Gift card cannot be redeemed',
            };

            throw new InvalidGiftCardException("{$reason}: {$code}");
        }

        // Check if gift card is already applied
        if ($this->hasGiftCard($code)) {
            return $this;
        }

        $condition = new GiftCardCondition($giftCard, $order);

        $cart = $this->getUnderlyingCart();
        $cart->registerDynamicCondition($condition->toCartCondition());

        return $this;
    }

    /**
     * Remove a gift card from the cart.
     */
    public function removeGiftCard(string $code): static
    {
        $conditionName = 'gift_card_' . mb_strtoupper($code);

        $cart = $this->getUnderlyingCart();
        $cart->removeCondition($conditionName);

        return $this;
    }

    /**
     * Remove all gift cards from the cart.
     */
    public function clearGiftCards(): static
    {
        $cart = $this->getUnderlyingCart();
        $giftCardConditions = $this->getAppliedGiftCards();

        foreach ($giftCardConditions as $condition) {
            $cart->removeCondition($condition->getName());
        }

        return $this;
    }

    /**
     * Check if a specific gift card is applied.
     */
    public function hasGiftCard(string $code): bool
    {
        $conditionName = 'gift_card_' . mb_strtoupper($code);

        return $this->getAppliedGiftCards()
            ->contains(fn (CartCondition $c) => $c->getName() === $conditionName);
    }

    /**
     * Check if any gift cards are applied.
     */
    public function hasGiftCards(): bool
    {
        return $this->getAppliedGiftCards()->isNotEmpty();
    }

    /**
     * Get all applied gift card conditions.
     *
     * @return Collection<int, CartCondition>
     */
    public function getAppliedGiftCards(): Collection
    {
        $cart = $this->getUnderlyingCart();

        return collect($cart->getConditions())
            ->filter(fn (CartCondition $condition) => $condition->getType() === 'gift_card');
    }

    /**
     * Get the total gift card amount to be deducted.
     */
    public function getGiftCardTotal(): int
    {
        $cartTotal = $this->getCartTotalBeforeGiftCards();
        $giftCardConditions = $this->getAppliedGiftCards();
        $total = 0;

        foreach ($giftCardConditions as $condition) {
            $giftCardId = $condition->getAttribute('gift_card_id');
            $giftCard = GiftCard::find($giftCardId);

            if ($giftCard) {
                $deduction = min($giftCard->current_balance, $cartTotal - $total);
                $total += $deduction;
            }
        }

        return $total;
    }

    /**
     * Get the remaining amount to pay after gift cards.
     */
    public function getRemainingBalance(): int
    {
        $cartTotal = $this->getCartTotalBeforeGiftCards();
        $giftCardTotal = $this->getGiftCardTotal();

        return max(0, $cartTotal - $giftCardTotal);
    }

    /**
     * Get cart total before gift cards are applied.
     * This includes voucher discounts but not gift card deductions.
     */
    public function getCartTotalBeforeGiftCards(): int
    {
        $cart = $this->getUnderlyingCart();

        // Get grand total without gift card conditions
        $conditions = collect($cart->getConditions())
            ->filter(fn (CartCondition $c) => $c->getType() !== 'gift_card');

        $subtotal = $cart->getSubtotalRaw();

        foreach ($conditions as $condition) {
            $subtotal = $this->applyConditionToValue($condition, $subtotal);
        }

        return max(0, (int) $subtotal);
    }

    /**
     * Get gift card deduction breakdown.
     *
     * @return array<string, array{code: string, balance: int, deduction: int}>
     */
    public function getGiftCardBreakdown(): array
    {
        $breakdown = [];
        $remainingTotal = $this->getCartTotalBeforeGiftCards();
        $giftCardConditions = $this->getAppliedGiftCards();

        foreach ($giftCardConditions as $condition) {
            $giftCardId = $condition->getAttribute('gift_card_id');
            $giftCardCode = $condition->getAttribute('gift_card_code');
            $giftCard = GiftCard::find($giftCardId);

            if ($giftCard) {
                $deduction = min($giftCard->current_balance, $remainingTotal);
                $remainingTotal -= $deduction;

                $breakdown[$giftCardCode] = [
                    'code' => $giftCardCode,
                    'balance' => $giftCard->current_balance,
                    'deduction' => $deduction,
                ];
            }
        }

        return $breakdown;
    }

    /**
     * Commit gift card deductions (call after order is confirmed).
     *
     * @param  Model  $order  The order reference
     * @return array<string, int> Map of gift card codes to deducted amounts
     */
    public function commitGiftCards(Model $order): array
    {
        $deductions = [];
        $breakdown = $this->getGiftCardBreakdown();

        foreach ($breakdown as $code => $data) {
            if ($data['deduction'] > 0) {
                $giftCard = GiftCard::findByCode($code);

                if ($giftCard) {
                    $giftCard->redeem($data['deduction'], $order);
                    $deductions[$code] = $data['deduction'];
                }
            }
        }

        return $deductions;
    }

    /**
     * Apply a condition value to a subtotal.
     */
    protected function applyConditionToValue(CartCondition $condition, float $value): float
    {
        $conditionValue = (string) $condition->getValue();

        // Handle percentage
        if (str_ends_with($conditionValue, '%')) {
            $percentage = (float) str_replace('%', '', $conditionValue);

            return $value + ($value * ($percentage / 100));
        }

        // Handle fixed value (positive or negative)
        $numericValue = (float) preg_replace('/[^0-9.-]/', '', $conditionValue);

        if (str_starts_with($conditionValue, '-')) {
            return $value - abs($numericValue);
        }

        return $value + $numericValue;
    }
}
