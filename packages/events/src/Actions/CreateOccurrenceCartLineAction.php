<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Models\CartItem;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventCheckoutIntentResolver;
use AIArmada\Events\Models\Occurrence;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class CreateOccurrenceCartLineAction
{
    public function __construct(
        private readonly EventCheckoutIntentResolver $checkoutIntentResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(Occurrence $occurrence, int $quantity = 1, array $metadata = [], ?Model $owner = null): ?CartItem
    {
        $owner ??= OwnerContext::fromTypeAndId(
            is_string($occurrence->getAttribute('owner_type')) ? $occurrence->getAttribute('owner_type') : null,
            is_scalar($occurrence->getAttribute('owner_id')) ? (string) $occurrence->getAttribute('owner_id') : null,
        );

        return OwnerContext::withOwner($owner, function () use ($occurrence, $quantity, $metadata): ?CartItem {
            $intent = $this->checkoutIntentResolver->resolve($occurrence, $quantity, $metadata);

            if ($intent === null) {
                return null;
            }

            $buyable = $intent->buyable;
            $buyableName = $this->resolveBuyableName($buyable);
            $buyablePrice = $this->resolveBuyablePrice($buyable);

            $item = Cart::add(
                $intent->cartItemId,
                $buyableName,
                $this->normalizeBuyablePrice($buyablePrice),
                $intent->quantity,
                array_merge(
                    $intent->attributes,
                    [
                        'checkout_metadata' => $metadata,
                    ],
                ),
                null,
                $buyable,
            );

            Cart::setMetadataBatch($intent->metadata);

            return $item;
        });
    }

    private function resolveBuyableName(Model $buyable): string
    {
        foreach (['getBuyableName', 'getDisplayName', 'getName'] as $method) {
            if (! method_exists($buyable, $method)) {
                continue;
            }

            $name = $buyable->{$method}();

            if (is_string($name) && mb_trim($name) !== '') {
                return $name;
            }
        }

        $name = $buyable->getAttribute('name');

        if (is_string($name) && mb_trim($name) !== '') {
            return $name;
        }

        throw new RuntimeException('Resolved event checkout intent must provide a buyable name.');
    }

    private function resolveBuyablePrice(Model $buyable): mixed
    {
        foreach (['getBuyablePrice', 'getEffectivePrice', 'getBasePrice'] as $method) {
            if (! method_exists($buyable, $method)) {
                continue;
            }

            return $buyable->{$method}();
        }

        if (method_exists($buyable, 'getCalculatedPrice')) {
            return $buyable->getCalculatedPrice();
        }

        $price = $buyable->getAttribute('price');

        if (is_int($price) || is_float($price) || is_string($price)) {
            return $price;
        }

        throw new RuntimeException('Resolved event checkout intent must provide a scalar buyable price.');
    }

    private function normalizeBuyablePrice(mixed $price): int | float | string
    {
        if (is_int($price) || is_float($price) || is_string($price)) {
            return $price;
        }

        if (is_object($price)) {
            if (method_exists($price, 'getAmount')) {
                $amount = $price->getAmount();

                if (is_int($amount) || is_float($amount) || is_string($amount)) {
                    return $amount;
                }
            }

            if (method_exists($price, '__toString')) {
                $amount = (string) $price;

                if ($amount !== '') {
                    return $amount;
                }
            }
        }

        throw new RuntimeException('Resolved event checkout intent must provide a scalar buyable price.');
    }
}
