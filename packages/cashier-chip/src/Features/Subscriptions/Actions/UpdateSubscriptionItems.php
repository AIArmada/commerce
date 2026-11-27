<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Features\Subscriptions\Actions;

use AIArmada\CashierChip\Exceptions\SubscriptionUpdateFailure;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionItem;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class UpdateSubscriptionItems
{
    public function updateQuantity(Subscription $subscription, int $quantity, ?string $price = null): Subscription
    {
        $subscription->guardAgainstIncomplete();

        $quantity = max(1, $quantity);

        if ($price) {
            $subscription->findItemOrFail($price)->updateQuantity($quantity);

            return $subscription->refresh();
        }

        $subscription->guardAgainstMultiplePrices();

        return $subscription->getConnection()->transaction(function () use ($subscription, $quantity): Subscription {
            $subscription->fill([
                'quantity' => $quantity,
            ])->save();

            $singleSubscriptionItem = $subscription->items()->firstOrFail();

            $singleSubscriptionItem->fill([
                'quantity' => $quantity,
            ])->save();

            return $subscription;
        });
    }

    public function swap(Subscription $subscription, string | array $prices, array $options = []): Subscription
    {
        if (empty($prices = (array) $prices)) {
            throw new InvalidArgumentException('Please provide at least one price when swapping.');
        }

        $subscription->guardAgainstIncomplete();

        return $subscription->getConnection()->transaction(function () use ($subscription, $prices, $options): Subscription {
            $isSinglePrice = count($prices) === 1;
            $firstPrice = is_string(array_values($prices)[0])
                ? array_values($prices)[0]
                : array_keys($prices)[0];

            $existingItemIds = $subscription->items()->pluck('id')->all();
            $existingByPrice = $subscription->items()->get()->keyBy('chip_price');
            $createdItems = [];

            foreach ($prices as $priceKey => $priceValue) {
                $price = is_string($priceValue) ? $priceValue : $priceKey;
                $quantity = is_array($priceValue) ? ($priceValue['quantity'] ?? 1) : 1;
                $quantity = max(1, (int) $quantity);

                $unitAmount = is_array($priceValue) ? ($priceValue['unit_amount'] ?? null) : null;
                $unitAmount ??= $options['unit_amounts'][$price] ?? $options['unit_amount'] ?? null;

                if ($unitAmount === null) {
                    $unitAmount = $existingByPrice->get($price)?->unit_amount;
                }

                if ($unitAmount !== null && (int) $unitAmount < 0) {
                    throw new InvalidArgumentException('Subscription item unit amount must be a non-negative integer.');
                }

                $createdItems[] = $this->createTrustedSubscriptionItem($subscription, [
                    'owner_type' => $subscription->owner_type,
                    'owner_id' => $subscription->owner_id,
                    'chip_id' => Str::orderedUuid()->toString(),
                    'chip_product' => $options['product'] ?? null,
                    'chip_price' => $price,
                    'quantity' => $quantity,
                    'unit_amount' => $unitAmount !== null ? (int) $unitAmount : null,
                ]);
            }

            if ($existingItemIds !== []) {
                $subscription->items()->whereIn('id', $existingItemIds)->delete();
            }

            $firstNewItem = $createdItems[0];

            $subscription->fill([
                'chip_price' => $isSinglePrice ? $firstPrice : null,
                'quantity' => $isSinglePrice ? $firstNewItem?->quantity : null,
                'ends_at' => null,
            ])->save();

            $subscription->unsetRelation('items');

            return $subscription;
        });
    }

    public function addPrice(Subscription $subscription, string $price, ?int $quantity = 1, array $options = []): Subscription
    {
        $subscription->guardAgainstIncomplete();

        return $subscription->getConnection()->transaction(function () use ($subscription, $price, $quantity, $options): Subscription {
            $subscription->loadMissing('items');

            if ($subscription->items->contains('chip_price', $price)) {
                throw SubscriptionUpdateFailure::duplicatePrice($subscription, $price);
            }

            $this->createTrustedSubscriptionItem($subscription, [
                'owner_type' => $subscription->owner_type,
                'owner_id' => $subscription->owner_id,
                'chip_id' => Str::orderedUuid()->toString(),
                'chip_product' => $options['product'] ?? null,
                'chip_price' => $price,
                'quantity' => $quantity,
                'unit_amount' => $options['unit_amount'] ?? null,
            ]);

            $subscription->unsetRelation('items');

            if ($subscription->hasSinglePrice()) {
                $subscription->fill([
                    'chip_price' => null,
                    'quantity' => null,
                ])->save();
            }

            return $subscription;
        });
    }

    public function removePrice(Subscription $subscription, string $price): Subscription
    {
        return $subscription->getConnection()->transaction(function () use ($subscription, $price): Subscription {
            if ($subscription->hasSinglePrice()) {
                throw SubscriptionUpdateFailure::cannotDeleteLastPrice($subscription);
            }

            $subscription->items()->where('chip_price', $price)->delete();

            $subscription->unsetRelation('items');

            if ($subscription->items()->count() < 2) {
                $item = $subscription->items()->first();

                if (! $item instanceof SubscriptionItem) {
                    return $subscription;
                }

                $subscription->fill([
                    'chip_price' => $item->chip_price,
                    'quantity' => $item->quantity,
                ])->save();
            }

            return $subscription;
        });
    }

    private function createTrustedSubscriptionItem(Subscription $subscription, array $attributes): SubscriptionItem
    {
        /** @var SubscriptionItem $item */
        $item = $subscription->items()->make();
        $item->forceFill($attributes);
        $item->save();

        return $item;
    }

    public function deleteAll(Subscription $subscription): void
    {
        $subscription->items()->delete();
    }

    public function updateItemQuantity(SubscriptionItem $item, int $quantity): SubscriptionItem
    {
        $item->subscription->guardAgainstIncomplete();

        $quantity = max(1, $quantity);

        return $item->getConnection()->transaction(function () use ($item, $quantity): SubscriptionItem {
            $item->fill([
                'quantity' => $quantity,
            ])->save();

            if ($item->subscription->hasSinglePrice()) {
                $item->subscription->fill([
                    'quantity' => $quantity,
                ])->save();
            }

            return $item;
        });
    }

    public function swapItem(SubscriptionItem $item, string $price, array $options = []): SubscriptionItem
    {
        $item->subscription->guardAgainstIncomplete();

        $unitAmount = $options['unit_amount'] ?? $item->unit_amount;

        if ($unitAmount !== null && (int) $unitAmount < 0) {
            throw new InvalidArgumentException('Subscription item unit amount must be a non-negative integer.');
        }

        return $item->getConnection()->transaction(function () use ($item, $price, $options): SubscriptionItem {
            $item->forceFill([
                'chip_product' => $options['product'] ?? $item->chip_product,
                'chip_price' => $price,
                'unit_amount' => isset($options['unit_amount']) ? (int) $options['unit_amount'] : $item->unit_amount,
            ])->save();

            if ($item->subscription->hasSinglePrice()) {
                $item->subscription->fill([
                    'chip_price' => $price,
                ])->save();
            }

            return $item;
        });
    }
}
