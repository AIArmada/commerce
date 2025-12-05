<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting\Evaluators;

use AIArmada\Vouchers\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
use AIArmada\Vouchers\Targeting\TargetingContext;

/**
 * Evaluates geographic targeting rules (country code).
 */
class GeographicEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::Geographic->value;
    }

    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $currentCountry = $context->getCountry();
        $operator = $rule['operator'] ?? 'in';

        if ($currentCountry === null) {
            // Unknown country - by default, allow (return true)
            // Can be configured to deny via rule setting
            return (bool) ($rule['allow_unknown'] ?? true);
        }

        $currentCountry = strtoupper($currentCountry);
        $targetCountries = array_map('strtoupper', $rule['values'] ?? []);

        if (empty($targetCountries)) {
            return true;
        }

        return match ($operator) {
            'in' => in_array($currentCountry, $targetCountries, true),
            'not_in' => ! in_array($currentCountry, $targetCountries, true),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::Geographic->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        if (! isset($rule['values']) || ! is_array($rule['values'])) {
            $errors[] = 'Values must be an array of country codes';

            return $errors;
        }

        foreach ($rule['values'] as $country) {
            if (! is_string($country) || strlen($country) !== 2) {
                $errors[] = "Invalid country code: {$country}. Use ISO 3166-1 alpha-2 codes";
            }
        }

        return $errors;
    }
}
