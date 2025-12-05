<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting\Contracts;

use AIArmada\Vouchers\Targeting\TargetingContext;

/**
 * Contract for targeting rule evaluators.
 */
interface TargetingRuleEvaluator
{
    /**
     * Check if this evaluator supports the given rule type.
     */
    public function supports(string $type): bool;

    /**
     * Evaluate the rule against the targeting context.
     *
     * @param  array<string, mixed>  $rule  The rule configuration
     * @param  TargetingContext  $context  The targeting context
     */
    public function evaluate(array $rule, TargetingContext $context): bool;

    /**
     * Get the rule type this evaluator handles.
     */
    public function getType(): string;

    /**
     * Validate the rule configuration.
     *
     * @param  array<string, mixed>  $rule
     * @return array<string> List of validation errors (empty if valid)
     */
    public function validate(array $rule): array;
}
