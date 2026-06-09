<?php

declare(strict_types=1);

namespace AIArmada\Cart\Services;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;

/**
 * Convenience presets for common rule definitions.
 *
 * Delegates to BuiltInRulesFactory as the canonical rule source.
 *
 * @see BuiltInRulesFactory
 */

/**
 * Pre-built rule presets for common cart validation scenarios.
 *
 * These presets provide ready-to-use rule closures without needing
 * to understand the BuiltInRulesFactory or rule creation details.
 *
 * Rules are returned as arrays of callables that can be passed directly
 * to CartCondition or used with dynamic conditions.
 *
 * @example
 * ```php
 * // Create condition with minimum cart value rule
 * $cart->registerDynamicCondition(
 *     condition: ConditionPresets::percentageDiscount(10),
 *     ruleFactoryKey: null,
 *     metadata: [],
 *     rules: RulePresets::minimumCartValue(5000)
 * );
 *
 * // Combine multiple rules
 * $rules = array_merge(
 *     RulePresets::minimumCartValue(5000),
 *     RulePresets::requireWeekend()
 * );
 * ```
 */
final class RulePresets
{
    private static ?BuiltInRulesFactory $factory = null;

    private static function factory(): BuiltInRulesFactory
    {
        if (self::$factory === null) {
            self::$factory = new BuiltInRulesFactory;
        }

        return self::$factory;
    }

    public static function setFactory(?BuiltInRulesFactory $factory): void
    {
        self::$factory = $factory;
    }

    // =========================================================================
    // CART VALUE RULES
    // =========================================================================

    /**
     * Require minimum cart subtotal.
     *
     * @param  int  $minimumCents  Minimum subtotal in cents
     * @return array<callable>
     */
    public static function minimumCartValue(int $minimumCents): array
    {
        return $minimumCents !== 0
            ? self::factory()->createRules('subtotal-at-least', ['amount' => $minimumCents])
            : self::always();
    }

    /**
     * Require cart subtotal below maximum.
     *
     * @param  int  $maximumCents  Maximum subtotal in cents
     * @return array<callable>
     */
    public static function maximumCartValue(int $maximumCents): array
    {
        return self::factory()->createRules('subtotal-below', ['amount' => $maximumCents]);
    }

    /**
     * Require cart subtotal within range.
     *
     * @param  int  $minCents  Minimum subtotal in cents
     * @param  int  $maxCents  Maximum subtotal in cents
     * @return array<callable>
     */
    public static function cartValueBetween(int $minCents, int $maxCents): array
    {
        return self::factory()->createRules('subtotal-between', ['min' => $minCents, 'max' => $maxCents]);
    }

    // =========================================================================
    // QUANTITY RULES
    // =========================================================================

    /**
     * Require minimum total quantity of items.
     *
     * @param  int  $minimum  Minimum quantity
     * @return array<callable>
     */
    public static function minimumQuantity(int $minimum): array
    {
        return self::factory()->createRules('min-quantity', ['min' => $minimum]);
    }

    /**
     * Require maximum total quantity of items.
     *
     * @param  int  $maximum  Maximum quantity
     * @return array<callable>
     */
    public static function maximumQuantity(int $maximum): array
    {
        return self::factory()->createRules('max-quantity', ['max' => $maximum]);
    }

    /**
     * Require minimum number of distinct items.
     *
     * @param  int  $minimum  Minimum item count
     * @return array<callable>
     */
    public static function minimumItems(int $minimum): array
    {
        return self::factory()->createRules('min-items', ['min' => $minimum]);
    }

    /**
     * Require maximum number of distinct items.
     *
     * @param  int  $maximum  Maximum item count
     * @return array<callable>
     */
    public static function maximumItems(int $maximum): array
    {
        return self::factory()->createRules('max-items', ['max' => $maximum]);
    }

    // =========================================================================
    // PRODUCT/ITEM RULES
    // =========================================================================

    /**
     * Require specific product in cart.
     *
     * @param  string  $productId  Product ID
     * @return array<callable>
     */
    public static function requireProduct(string $productId): array
    {
        return self::factory()->createRules('has-item', ['id' => $productId]);
    }

    /**
     * Block if specific product is in cart.
     *
     * @param  string  $productId  Product ID
     * @return array<callable>
     */
    public static function excludeProduct(string $productId): array
    {
        return self::factory()->createRules('missing-item', ['id' => $productId]);
    }

    /**
     * Require any of the specified products in cart.
     *
     * @param  array<string>  $productIds  Product IDs
     * @return array<callable>
     */
    public static function requireAnyProduct(array $productIds): array
    {
        return self::factory()->createRules('item-list-includes-any', ['ids' => $productIds]);
    }

    /**
     * Require all specified products in cart.
     *
     * @param  array<string>  $productIds  Product IDs
     * @return array<callable>
     */
    public static function requireAllProducts(array $productIds): array
    {
        return self::factory()->createRules('item-list-includes-all', ['ids' => $productIds]);
    }

    /**
     * Require products with specific ID prefix.
     *
     * @param  string  $prefix  ID prefix
     * @return array<callable>
     */
    public static function requireProductPrefix(string $prefix): array
    {
        return self::factory()->createRules('item-id-prefix', ['prefix' => $prefix]);
    }

    // =========================================================================
    // TIME-BASED RULES
    // =========================================================================

    /**
     * Only valid during specific date range.
     *
     * @param  string  $startDate  Start date (parseable by Carbon)
     * @param  string  $endDate  End date (parseable by Carbon)
     * @return array<callable>
     */
    public static function dateRange(string $startDate, string $endDate): array
    {
        return self::factory()->createRules('date-window', ['start' => $startDate, 'end' => $endDate]);
    }

    /**
     * Only valid during specific time window (daily).
     *
     * @param  string  $startTime  Start time (HH:MM format)
     * @param  string  $endTime  End time (HH:MM format)
     * @return array<callable>
     */
    public static function timeWindow(string $startTime, string $endTime): array
    {
        return self::factory()->createRules('time-window', ['start' => $startTime, 'end' => $endTime]);
    }

    /**
     * Only valid on weekends (Saturday and Sunday).
     *
     * @return array<callable>
     */
    public static function requireWeekend(): array
    {
        return self::factory()->createRules('day-of-week', ['days' => ['saturday', 'sunday']]);
    }

    /**
     * Only valid on weekdays (Monday to Friday).
     *
     * @return array<callable>
     */
    public static function requireWeekday(): array
    {
        return self::factory()->createRules('day-of-week', ['days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']]);
    }

    /**
     * Only valid on specific days of week.
     *
     * @param  array<int|string>  $days
     * @return array<callable>
     */
    public static function requireDaysOfWeek(array $days): array
    {
        return self::factory()->createRules('day-of-week', ['days' => $days]);
    }

    // =========================================================================
    // CUSTOMER RULES
    // =========================================================================

    /**
     * Require customer to have specific tag.
     *
     * @param  string  $tag  Customer tag
     * @param  string  $metadataKey  Cart metadata key storing tags
     * @return array<callable>
     */
    public static function requireCustomerTag(string $tag, string $metadataKey = 'customer_tags'): array
    {
        return self::factory()->createRules('customer-tag', ['tag' => $tag, 'metadata_key' => $metadataKey]);
    }

    /**
     * Require customer to have any of specified tags.
     *
     * @param  array<string>  $tags  Customer tags
     * @param  string  $metadataKey  Cart metadata key storing tags
     * @return array<callable>
     */
    public static function requireAnyCustomerTag(array $tags, string $metadataKey = 'customer_tags'): array
    {
        $fallback = self::requireCustomerTag($tags[0], $metadataKey);

        return self::any(...array_map(
            fn (string $tag) => self::requireCustomerTag($tag, $metadataKey),
            $tags
        ));
    }

    /**
     * Require VIP customer.
     *
     * @return array<callable>
     */
    public static function requireVip(): array
    {
        return self::requireCustomerTag('vip');
    }

    /**
     * Block guest customers (require authenticated).
     *
     * @param  string  $metadataKey  Cart metadata key storing user ID
     * @return array<callable>
     */
    public static function requireAuthenticated(string $metadataKey = 'user_id'): array
    {
        return self::factory()->createRules('metadata-flag-true', ['key' => $metadataKey]);
    }

    // =========================================================================
    // METADATA RULES
    // =========================================================================

    /**
     * Require specific metadata key to exist.
     *
     * @param  string  $key  Metadata key
     * @return array<callable>
     */
    public static function requireMetadata(string $key): array
    {
        return self::factory()->createRules('has-metadata', ['key' => $key]);
    }

    /**
     * Require metadata key to have specific value.
     *
     * @param  string  $key  Metadata key
     * @param  mixed  $value  Expected value
     * @return array<callable>
     */
    public static function requireMetadataValue(string $key, mixed $value): array
    {
        return self::factory()->createRules('metadata-equals', ['key' => $key, 'value' => $value]);
    }

    /**
     * Require metadata flag to be true.
     *
     * @param  string  $key  Metadata key
     * @return array<callable>
     */
    public static function requireFlag(string $key): array
    {
        return self::factory()->createRules('metadata-flag-true', ['key' => $key]);
    }

    /**
     * Block if metadata flag is true.
     *
     * @param  string  $key  Metadata key
     * @return array<callable>
     */
    public static function blockIfFlag(string $key): array
    {
        return self::factory()->createRules('metadata-not-equals', ['key' => $key, 'value' => true]);
    }

    // =========================================================================
    // CART STATE RULES
    // =========================================================================

    /**
     * Require cart to not be empty.
     *
     * @return array<callable>
     */
    public static function requireNonEmpty(): array
    {
        return self::factory()->createRules('has-any-item');
    }

    /**
     * Block if specific condition already exists.
     *
     * @param  string  $conditionName  Condition name
     * @return array<callable>
     */
    public static function blockIfConditionExists(string $conditionName): array
    {
        return self::not(self::requireCondition($conditionName));
    }

    /**
     * Require specific condition to exist.
     *
     * @param  string  $conditionName  Condition name
     * @return array<callable>
     */
    public static function requireCondition(string $conditionName): array
    {
        return self::factory()->createRules('cart-condition-exists', ['condition' => $conditionName]);
    }

    /**
     * Block if condition type already exists.
     *
     * @param  string  $conditionType  Condition type
     * @return array<callable>
     */
    public static function blockIfConditionTypeExists(string $conditionType): array
    {
        return self::not(self::requireConditionType($conditionType));
    }

    /**
     * Require specific condition type to exist.
     *
     * @param  string  $conditionType  Condition type
     * @return array<callable>
     */
    public static function requireConditionType(string $conditionType): array
    {
        return self::factory()->createRules('cart-condition-type-exists', ['type' => $conditionType]);
    }

    // =========================================================================
    // UTILITY RULES
    // =========================================================================

    /**
     * Always pass (useful for testing or placeholder).
     *
     * @return array<callable>
     */
    public static function always(): array
    {
        return self::factory()->createRules('always-true');
    }

    /**
     * Always fail (useful for testing or blocking).
     *
     * @return array<callable>
     */
    public static function never(): array
    {
        return self::factory()->createRules('always-false');
    }

    /**
     * Combine multiple rule sets with AND logic.
     *
     * @param  array<callable>  ...$ruleSets  Multiple rule arrays
     * @return array<callable>
     */
    public static function all(array ...$ruleSets): array
    {
        return array_merge(...$ruleSets);
    }

    /**
     * Combine multiple rule sets with OR logic.
     *
     * @param  array<callable>  ...$ruleSets  Multiple rule arrays
     * @return array<callable>
     */
    public static function any(array ...$ruleSets): array
    {
        return [
            static function (Cart $cart, ?CartItem $item = null) use ($ruleSets): bool {
                foreach ($ruleSets as $ruleSet) {
                    $allPassed = true;

                    foreach ($ruleSet as $rule) {
                        if (! $rule($cart, $item)) {
                            $allPassed = false;

                            break;
                        }
                    }

                    if ($allPassed) {
                        return true;
                    }
                }

                return false;
            },
        ];
    }

    /**
     * Negate a rule set.
     *
     * @param  array<callable>  $rules  Rules to negate
     * @return array<callable>
     */
    public static function not(array $rules): array
    {
        return [
            static function (Cart $cart, ?CartItem $item = null) use ($rules): bool {
                foreach ($rules as $rule) {
                    if (! $rule($cart, $item)) {
                        return true;
                    }
                }

                return false;
            },
        ];
    }
}
