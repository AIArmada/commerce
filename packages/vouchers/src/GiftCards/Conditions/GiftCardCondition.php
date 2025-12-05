<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\GiftCards\Conditions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Target;
use AIArmada\Cart\Contracts\CartConditionConvertible;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use Illuminate\Contracts\Support\Arrayable;
use JsonException;

/**
 * GiftCardCondition bridges the gift card system with cart's condition system.
 *
 * Unlike vouchers, gift cards use balance deduction rather than percentage/fixed discounts.
 * The condition calculates how much of the gift card balance will be applied based on the cart total.
 *
 * @implements \Illuminate\Contracts\Support\Arrayable<string, mixed>
 */
class GiftCardCondition implements Arrayable, CartConditionConvertible
{
    public const RULE_FACTORY_KEY = 'gift_card';

    private string $name;

    private string $type;

    private ConditionTarget $target;

    private string $value;

    /** @var array<string, mixed> */
    private array $attributes;

    private int $order;

    /** @var ?array<callable> */
    private ?array $rules;

    private GiftCard $giftCard;

    private ?CartCondition $cartCondition = null;

    /**
     * Create a new gift card condition.
     */
    public function __construct(
        GiftCard $giftCard,
        int $order = 100,
        bool $dynamic = true
    ) {
        $this->giftCard = $giftCard;

        $this->name = "gift_card_{$giftCard->code}";
        $this->type = 'gift_card';
        $this->target = $this->determineTarget();
        $this->value = "-{$giftCard->current_balance}";
        $this->attributes = [
            'gift_card_id' => $giftCard->id,
            'gift_card_code' => $giftCard->code,
            'available_balance' => $giftCard->current_balance,
            'currency' => $giftCard->currency,
            'pending_deduction' => 0,
        ];
        $this->order = $order;
        $this->rules = $dynamic ? [[$this, 'validateGiftCard']] : null;
    }

    /**
     * Create from a CartCondition.
     */
    public static function fromCartCondition(CartCondition $condition): ?self
    {
        if ($condition->getType() !== 'gift_card') {
            return null;
        }

        $attributes = $condition->getAttributes();
        $giftCardId = $attributes['gift_card_id'] ?? null;

        if (! $giftCardId) {
            return null;
        }

        $giftCard = GiftCard::find($giftCardId);

        if (! $giftCard) {
            return null;
        }

        $instance = new self(
            giftCard: $giftCard,
            order: $condition->getOrder(),
            dynamic: $condition->isDynamic()
        );

        $instance->cartCondition = $condition;

        return $instance;
    }

    /**
     * Convert to a CartCondition.
     */
    public function toCartCondition(): CartCondition
    {
        if ($this->cartCondition instanceof CartCondition) {
            return $this->cartCondition;
        }

        $this->cartCondition = new CartCondition(
            name: $this->name,
            type: $this->type,
            target: $this->target,
            value: $this->value,
            attributes: $this->attributes,
            order: $this->order,
            rules: $this->rules // @phpstan-ignore argument.type
        );

        return $this->cartCondition;
    }

    /**
     * Validate that the gift card can still be applied to the cart.
     */
    public function validateGiftCard(Cart $cart, ?CartItem $item = null): bool
    {
        // Refresh gift card from database
        $this->giftCard->refresh();

        return $this->giftCard->canRedeem();
    }

    /**
     * Get the gift card.
     */
    public function getGiftCard(): GiftCard
    {
        return $this->giftCard;
    }

    /**
     * Get the gift card code.
     */
    public function getGiftCardCode(): string
    {
        return $this->giftCard->code;
    }

    /**
     * Get the gift card ID.
     */
    public function getGiftCardId(): string
    {
        return $this->giftCard->id;
    }

    /**
     * Get the available balance.
     */
    public function getAvailableBalance(): int
    {
        return $this->giftCard->current_balance;
    }

    /**
     * Calculate the deduction amount based on cart total.
     */
    public function calculateDeduction(int $cartTotal): int
    {
        $availableBalance = $this->giftCard->current_balance;

        return min($availableBalance, $cartTotal);
    }

    /**
     * Set the pending deduction amount.
     */
    public function setPendingDeduction(int $amount): void
    {
        $this->attributes['pending_deduction'] = $amount;
        $this->value = "-{$amount}";
    }

    /**
     * Get the pending deduction amount.
     */
    public function getPendingDeduction(): int
    {
        return (int) ($this->attributes['pending_deduction'] ?? 0);
    }

    public function getRuleFactoryKey(): string
    {
        return self::RULE_FACTORY_KEY;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRuleFactoryContext(): array
    {
        return [
            'gift_card_code' => $this->giftCard->code,
            'gift_card_id' => $this->giftCard->id,
        ];
    }

    /**
     * Apply the condition to a value.
     */
    public function apply(float $value): float
    {
        $deduction = $this->calculateDeduction((int) $value);
        $this->setPendingDeduction($deduction);

        return max(0, $value - $deduction);
    }

    /**
     * Get calculated value for display.
     */
    public function getCalculatedValue(float $baseValue): float
    {
        return -$this->calculateDeduction((int) $baseValue);
    }

    /**
     * Check if condition is a discount.
     */
    public function isDiscount(): bool
    {
        return true;
    }

    /**
     * Check if condition is a charge/fee.
     */
    public function isCharge(): bool
    {
        return false;
    }

    /**
     * Check if condition is percentage-based.
     */
    public function isPercentage(): bool
    {
        return false;
    }

    /**
     * Check if this is a dynamic condition.
     */
    public function isDynamic(): bool
    {
        return $this->rules !== null && ! empty($this->rules);
    }

    /**
     * Get the validation rules for this condition.
     *
     * @return ?array<callable(): mixed>
     */
    public function getRules(): ?array
    {
        return $this->rules;
    }

    /**
     * Get the condition name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the condition type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the condition target definition.
     */
    public function getTargetDefinition(): ConditionTarget
    {
        return $this->target;
    }

    /**
     * Get the condition value.
     */
    public function getValue(): string|float
    {
        return $this->value;
    }

    /**
     * Get the condition attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get a specific attribute.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Get the condition order.
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * Convert to array with gift card-specific data.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'target_definition' => $this->target->toArray(),
            'value' => $this->value,
            'attributes' => $this->attributes,
            'order' => $this->order,
            'is_discount' => true,
            'is_charge' => false,
            'is_percentage' => false,
            'is_dynamic' => $this->isDynamic(),
            'gift_card' => [
                'id' => $this->giftCard->id,
                'code' => $this->giftCard->code,
                'available_balance' => $this->giftCard->current_balance,
                'currency' => $this->giftCard->currency,
                'status' => $this->giftCard->status->value,
            ],
        ];
    }

    /**
     * Convert to JSON.
     */
    public function toJson(int $options = 0): string
    {
        $json = json_encode($this->jsonSerialize(), $options);

        if ($json === false) {
            throw new JsonException('Failed to encode condition to JSON');
        }

        return $json;
    }

    /**
     * Serialize for JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Determine the condition target definition.
     * Gift cards apply at grand total phase (after discounts).
     */
    private function determineTarget(): ConditionTarget
    {
        return Target::cart()
            ->phase(ConditionPhase::GRAND_TOTAL)
            ->applyAggregate()
            ->withMeta([
                'source' => 'gift_card',
                'gift_card_id' => $this->giftCard->id,
                'gift_card_code' => $this->giftCard->code,
            ])
            ->build();
    }
}
