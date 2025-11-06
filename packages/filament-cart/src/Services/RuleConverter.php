<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class RuleConverter
{
    /**
     * Convert JSON rule definitions to callable functions
     *
     * @param  array<string, mixed>  $rules
     * @return array<callable>
     */
    public static function convertRules(array $rules): array
    {
        $callables = [];

        foreach ($rules as $ruleKey => $ruleValue) {
            $callables[] = self::createRuleCallable($ruleKey, $ruleValue);
        }

        return $callables;
    }

    /**
     * Create a callable function for a specific rule
     */
    private static function createRuleCallable(string $ruleKey, mixed $ruleValue): callable
    {
        return match ($ruleKey) {
            'min_total' => fn (Cart $cart) => $cart->getRawSubtotalWithoutConditions() >= (float) $ruleValue,
            'min_items' => fn (Cart $cart) => $cart->getItems()->count() >= (int) $ruleValue,
            'max_total' => fn (Cart $cart) => $cart->getRawSubtotalWithoutConditions() <= (float) $ruleValue,
            'max_items' => fn (Cart $cart) => $cart->getItems()->count() <= (int) $ruleValue,
            'has_category' => fn (Cart $cart) => self::cartHasCategory($cart, $ruleValue),
            'user_vip' => fn (Cart $cart) => self::userIsVip(),
            'specific_items' => fn (Cart $cart) => self::cartHasSpecificItems($cart, $ruleValue),
            'item_quantity' => fn (Cart $cart, ?CartItem $item = null) => $item && $item->quantity >= (int) $ruleValue,
            'item_price' => fn (Cart $cart, ?CartItem $item = null) => $item && $item->price >= (float) $ruleValue,
            default => throw new InvalidArgumentException("Unknown rule type: {$ruleKey}")
        };
    }

    /**
     * Check if cart contains items from a specific category
     */
    private static function cartHasCategory(Cart $cart, string $category): bool
    {
        foreach ($cart->getItems() as $item) {
            if (isset($item->attributes['category']) && $item->attributes['category'] === $category) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if current user is VIP
     */
    private static function userIsVip(): bool
    {
        $user = Auth::user();

        return $user && ($user->is_vip ?? false);
    }

    /**
     * Check if cart contains specific items (by ID or SKU)
     *
     * @param  array<string>  $itemIds
     */
    private static function cartHasSpecificItems(Cart $cart, array $itemIds): bool
    {
        $cartItemIds = $cart->getItems()->pluck('id')->toArray();
        $cartItemSkus = $cart->getItems()->pluck('attributes.sku')->filter()->toArray();

        foreach ($itemIds as $itemId) {
            if (in_array($itemId, $cartItemIds) || in_array($itemId, $cartItemSkus)) {
                return true;
            }
        }

        return false;
    }
}
