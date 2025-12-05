<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting\Evaluators;

use AIArmada\Vouchers\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
use AIArmada\Vouchers\Targeting\TargetingContext;

/**
 * Evaluates device type targeting rules (desktop, mobile, tablet).
 */
class DeviceEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::Device->value;
    }

    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $currentDevice = mb_strtolower($context->getDevice());
        $operator = $rule['operator'] ?? '=';

        return match ($operator) {
            '=' => $currentDevice === mb_strtolower((string) ($rule['value'] ?? '')),
            '!=' => $currentDevice !== mb_strtolower((string) ($rule['value'] ?? '')),
            'in' => $this->isIn($currentDevice, $rule['values'] ?? []),
            'not_in' => ! $this->isIn($currentDevice, $rule['values'] ?? []),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::Device->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];
        $operator = $rule['operator'] ?? '=';
        $validDevices = ['desktop', 'mobile', 'tablet'];

        if (in_array($operator, ['in', 'not_in'], true)) {
            if (! isset($rule['values']) || ! is_array($rule['values'])) {
                $errors[] = 'Values must be an array for in/not_in operators';
            } else {
                foreach ($rule['values'] as $device) {
                    if (! in_array(mb_strtolower((string) $device), $validDevices, true)) {
                        $errors[] = "Invalid device type: {$device}. Valid: desktop, mobile, tablet";
                    }
                }
            }
        } else {
            if (! isset($rule['value'])) {
                $errors[] = 'Value is required';
            } elseif (! in_array(mb_strtolower((string) $rule['value']), $validDevices, true)) {
                $errors[] = "Invalid device type: {$rule['value']}. Valid: desktop, mobile, tablet";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string>  $values
     */
    private function isIn(string $device, array $values): bool
    {
        $normalized = array_map('strtolower', $values);

        return in_array($device, $normalized, true);
    }
}
