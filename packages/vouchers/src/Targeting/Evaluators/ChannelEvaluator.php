<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting\Evaluators;

use AIArmada\Vouchers\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
use AIArmada\Vouchers\Targeting\TargetingContext;

/**
 * Evaluates channel targeting rules (web, mobile, api, pos, etc).
 */
class ChannelEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::Channel->value;
    }

    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $currentChannel = mb_strtolower($context->getChannel());
        $operator = $rule['operator'] ?? '=';

        return match ($operator) {
            '=' => $currentChannel === mb_strtolower((string) ($rule['value'] ?? '')),
            '!=' => $currentChannel !== mb_strtolower((string) ($rule['value'] ?? '')),
            'in' => $this->isIn($currentChannel, $rule['values'] ?? []),
            'not_in' => ! $this->isIn($currentChannel, $rule['values'] ?? []),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::Channel->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];
        $operator = $rule['operator'] ?? '=';

        if (in_array($operator, ['in', 'not_in'], true)) {
            if (! isset($rule['values']) || ! is_array($rule['values'])) {
                $errors[] = 'Values must be an array for in/not_in operators';
            }
        } else {
            if (! isset($rule['value'])) {
                $errors[] = 'Value is required';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string>  $values
     */
    private function isIn(string $channel, array $values): bool
    {
        $normalized = array_map('strtolower', $values);

        return in_array($channel, $normalized, true);
    }
}
