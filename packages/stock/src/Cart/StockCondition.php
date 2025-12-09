<?php

declare(strict_types=1);

namespace AIArmada\Stock\Cart;

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Target;
use AIArmada\Cart\Contracts\CartConditionConvertible;
use AIArmada\Stock\Services\StockReservationService;
use Illuminate\Database\Eloquent\Model;

/**
 * Cart condition that validates stock availability.
 *
 * This condition doesn't modify the cart total, but blocks checkout
 * if any item has insufficient stock.
 */
final class StockCondition implements CartConditionConvertible
{
    private bool $hasIssues = false;

    /**
     * @var array<string, array{name: string, requested: int, available: int}>
     */
    private array $issues = [];

    public function __construct(
        private readonly string $cartId,
        private readonly int $order = 1
    ) {}

    /**
     * Create from cart items.
     *
     * @param  iterable<\AIArmada\Cart\Models\CartItem>  $items
     */
    public static function fromCartItems(
        string $cartId,
        iterable $items,
        StockReservationService $reservationService
    ): self {
        $condition = new self($cartId);
        $issues = [];

        foreach ($items as $item) {
            $model = $item->getAssociatedModel();

            if (! $model instanceof Model) {
                continue;
            }

            $availableStock = $reservationService->getAvailableStock($model);

            // Exclude own reservation from availability check
            $ownReservation = $reservationService->getReservation($model, $cartId);

            if ($ownReservation) {
                $availableStock += $ownReservation->quantity;
            }

            if ($availableStock < $item->quantity) {
                $issues[$item->id] = [
                    'name' => $item->name,
                    'requested' => $item->quantity,
                    'available' => $availableStock,
                ];
            }
        }

        return $condition->setIssues($issues);
    }

    /**
     * Get condition name.
     */
    public function getName(): string
    {
        return 'stock_validation';
    }

    /**
     * Get condition type.
     */
    public function getType(): string
    {
        return 'validation';
    }

    /**
     * Get condition value (no monetary effect).
     */
    public function getValue(): float | int | string
    {
        return 0;
    }

    /**
     * Get condition order (early in pipeline).
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * Get attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return [
            'cart_id' => $this->cartId,
            'has_issues' => $this->hasIssues,
            'issues' => $this->issues,
        ];
    }

    /**
     * Calculate value (no monetary effect).
     */
    public function getCalculatedValue(int | float $subtotal): float | int
    {
        return 0;
    }

    /**
     * Check if condition is valid.
     */
    public function isValid(): bool
    {
        return ! $this->hasIssues;
    }

    /**
     * Set stock issues.
     *
     * @param  array<string, array{name: string, requested: int, available: int}>  $issues
     */
    public function setIssues(array $issues): self
    {
        $this->issues = $issues;
        $this->hasIssues = count($issues) > 0;

        return $this;
    }

    /**
     * Get stock issues.
     *
     * @return array<string, array{name: string, requested: int, available: int}>
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    /**
     * Check if there are stock issues.
     */
    public function hasIssues(): bool
    {
        return $this->hasIssues;
    }

    /**
     * Convert to CartCondition.
     */
    public function toCartCondition(): CartCondition
    {
        // Use cart scope with grand_total phase since this is a validation condition
        // that should run after all calculations but has no monetary effect (value = 0)
        $target = Target::cart()
            ->phase(ConditionPhase::GRAND_TOTAL)
            ->applyAggregate()
            ->build();

        return new CartCondition(
            name: $this->getName(),
            type: $this->getType(),
            target: $target,
            value: $this->getValue(),
            attributes: $this->getAttributes(),
            order: $this->getOrder()
        );
    }
}
