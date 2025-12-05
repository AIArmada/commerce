<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting\Evaluators;

use AIArmada\Vouchers\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
use AIArmada\Vouchers\Targeting\TargetingContext;

/**
 * Evaluates day-of-week targeting rules.
 *
 * Days are represented as integers: 0 = Sunday, 1 = Monday, ..., 6 = Saturday
 * Or as strings: 'sunday', 'monday', etc.
 */
class DayOfWeekEvaluator implements TargetingRuleEvaluator
{
    private const DAY_MAP = [
        'sunday' => 0,
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sun' => 0,
        'mon' => 1,
        'tue' => 2,
        'wed' => 3,
        'thu' => 4,
        'fri' => 5,
        'sat' => 6,
    ];

    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::DayOfWeek->value;
    }

    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $timezone = $rule['timezone'] ?? $context->getTimezone();
        $now = $context->getCurrentTime($timezone);
        $currentDay = $now->dayOfWeek; // 0 = Sunday

        $targetDays = $this->normalizeDays($rule['values'] ?? []);
        $operator = $rule['operator'] ?? 'in';

        if (empty($targetDays)) {
            return true;
        }

        return match ($operator) {
            'in' => in_array($currentDay, $targetDays, true),
            'not_in' => ! in_array($currentDay, $targetDays, true),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::DayOfWeek->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        if (! isset($rule['values']) || ! is_array($rule['values'])) {
            $errors[] = 'Values must be an array of days';

            return $errors;
        }

        foreach ($rule['values'] as $day) {
            if (is_int($day) && ($day < 0 || $day > 6)) {
                $errors[] = "Invalid day number: {$day}. Must be 0-6";
            } elseif (is_string($day) && ! isset(self::DAY_MAP[strtolower($day)])) {
                $errors[] = "Invalid day name: {$day}";
            }
        }

        return $errors;
    }

    /**
     * Convert day names to integers.
     *
     * @param  array<int|string>  $days
     * @return array<int>
     */
    private function normalizeDays(array $days): array
    {
        return array_map(function ($day): int {
            if (is_int($day)) {
                return $day;
            }

            return self::DAY_MAP[strtolower((string) $day)] ?? -1;
        }, $days);
    }
}
