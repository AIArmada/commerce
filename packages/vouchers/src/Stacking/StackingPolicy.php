<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Stacking;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Stacking\Contracts\StackingPolicyInterface;
use AIArmada\Vouchers\Stacking\Enums\StackingMode;
use AIArmada\Vouchers\Stacking\Enums\StackingRuleType;
use Illuminate\Support\Collection;

/**
 * Default stacking policy implementation.
 *
 * This is the canonical entry point for stacking logic. Clients create a policy
 * via static factories or the constructor, then use:
 *
 *     $policy->canAdd($newVoucher, $existingVouchers, $cart)  // StackingDecision
 *     $policy->resolveConflict($vouchers, $cart)              // Collection
 *     $policy->getApplicationOrder($vouchers, $cart)          // Collection
 *
 * StackingPolicy evaluates configured rules in priority order.
 */
class StackingPolicy implements StackingPolicyInterface
{
    /**
     * @param  StackingMode  $mode  The stacking mode to use
     * @param  array<int, array<string, mixed>>  $rules  Rule configurations
     * @param  bool  $autoOptimize  Whether to auto-optimize voucher combinations
     * @param  bool  $autoReplace  Whether to auto-replace on conflicts
     */
    public function __construct(
        private StackingMode $mode = StackingMode::Sequential,
        private array $rules = [],
        private bool $autoOptimize = false,
        private bool $autoReplace = true,
    ) {}

    /**
     * Create a policy from configuration array.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        $modeValue = $config['mode'] ?? 'sequential';
        $mode = StackingMode::tryFrom($modeValue) ?? StackingMode::Sequential;

        return new self(
            mode: $mode,
            rules: $config['rules'] ?? [],
            autoOptimize: (bool) ($config['auto_optimize'] ?? false),
            autoReplace: (bool) ($config['auto_replace'] ?? true),
        );
    }

    public function canAdd(
        VoucherCondition $newVoucher,
        Collection $existingVouchers,
        Cart $cart
    ): StackingDecision {
        if ($this->mode === StackingMode::None && $existingVouchers->isNotEmpty()) {
            return StackingDecision::deny(
                reason: 'Only one voucher is allowed per cart',
                conflictsWith: $existingVouchers->first()
            );
        }

        $maxVouchers = $this->getMaxVouchers();

        if ($maxVouchers === 0) {
            return StackingDecision::deny(
                reason: 'Vouchers are disabled',
            );
        }

        if ($maxVouchers > 0 && $existingVouchers->count() >= $maxVouchers) {
            return StackingDecision::deny(
                reason: "Maximum of {$maxVouchers} vouchers reached",
                conflictsWith: $existingVouchers->first()
            );
        }

        $rules = [];

        foreach ($this->rules as $config) {
            $type = $config['type'] ?? null;
            $ruleType = is_string($type) ? StackingRuleType::tryFrom($type) : null;

            if ($ruleType === null) {
                return StackingDecision::deny('Unknown voucher stacking rule.');
            }

            $class = $ruleType->getRuleClass();
            $rules[] = ['rule' => new $class, 'config' => $config];
        }

        usort($rules, static fn (array $left, array $right): int => $left['rule']->getPriority() <=> $right['rule']->getPriority());

        foreach ($rules as $entry) {
            $decision = $entry['rule']->evaluate($newVoucher, $existingVouchers, $cart, $entry['config']);

            if ($decision->isDenied()) {
                return $decision;
            }
        }

        return StackingDecision::allow();
    }

    public function resolveConflict(
        Collection $vouchers,
        Cart $cart
    ): Collection {
        $accepted = $vouchers->take(0);

        // Select a valid subset using the same rules used at application time.
        // Auto-optimization prefers higher estimated savings; it is not an
        // exhaustive combinatorial search.
        if ($this->autoOptimize) {
            $subtotal = $cart->getRawSubtotalWithoutConditions();
            $vouchers = $vouchers->sortByDesc(
                static fn (VoucherCondition $voucher): int => abs($voucher->getCalculatedValue($subtotal))
            );
        }

        foreach ($vouchers as $voucher) {
            if ($this->canAdd($voucher, $accepted, $cart)->isAllowed()) {
                $accepted->push($voucher);
            }
        }

        return $accepted->values();
    }

    public function getApplicationOrder(
        Collection $vouchers,
        Cart $cart
    ): Collection {
        if ($vouchers->count() <= 1) {
            return $vouchers;
        }

        return $vouchers->sortBy(function (VoucherCondition $voucher): int {
            $priority = $voucher->getVoucher()->metadata['stacking_priority'] ?? 100;

            return is_int($priority) ? $priority : 100;
        })->values();
    }

    public function getMode(): StackingMode
    {
        return $this->mode;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function isAutoOptimizeEnabled(): bool
    {
        return $this->autoOptimize;
    }

    public function isAutoReplaceEnabled(): bool
    {
        return $this->autoReplace;
    }

    /**
     * Add a rule to the policy.
     *
     * @param  array<string, mixed>  $ruleConfig
     */
    public function addRule(array $ruleConfig): self
    {
        $this->rules[] = $ruleConfig;

        return $this;
    }

    /**
     * Set the stacking mode.
     */
    public function withMode(StackingMode $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * Enable or disable auto-optimization.
     */
    public function withAutoOptimize(bool $enabled): self
    {
        $this->autoOptimize = $enabled;

        return $this;
    }

    /**
     * Enable or disable auto-replacement.
     */
    public function withAutoReplace(bool $enabled): self
    {
        $this->autoReplace = $enabled;

        return $this;
    }

    private function getMaxVouchers(): int
    {
        foreach ($this->rules as $rule) {
            if (($rule['type'] ?? '') === StackingRuleType::MaxVouchers->value) {
                return (int) ($rule['value'] ?? 3);
            }
        }

        return 3;
    }
}
