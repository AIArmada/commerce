<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting\Evaluators;

use AIArmada\Vouchers\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
use AIArmada\Vouchers\Targeting\TargetingContext;
use Carbon\Carbon;

/**
 * Evaluates date range targeting rules.
 */
class DateRangeEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::DateRange->value;
    }

    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $timezone = $rule['timezone'] ?? $context->getTimezone();
        $now = $context->getCurrentTime($timezone);
        $operator = $rule['operator'] ?? 'between';

        return match ($operator) {
            'between' => $this->evaluateBetween($rule, $now, $timezone),
            'before' => $this->evaluateBefore($rule, $now, $timezone),
            'after' => $this->evaluateAfter($rule, $now, $timezone),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::DateRange->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];
        $operator = $rule['operator'] ?? 'between';

        if ($operator === 'between') {
            if (! isset($rule['start'])) {
                $errors[] = 'Start date is required for between operator';
            }
            if (! isset($rule['end'])) {
                $errors[] = 'End date is required for between operator';
            }
        } else {
            if (! isset($rule['value'])) {
                $errors[] = 'Date value is required';
            }
        }

        return $errors;
    }

    private function evaluateBetween(array $rule, Carbon $now, string $timezone): bool
    {
        $start = $this->parseDate($rule['start'] ?? '', $timezone);
        $end = $this->parseDate($rule['end'] ?? '', $timezone);

        if ($start === null || $end === null) {
            return false;
        }

        // Include the full end day
        $end = $end->endOfDay();

        return $now->between($start, $end);
    }

    private function evaluateBefore(array $rule, Carbon $now, string $timezone): bool
    {
        $date = $this->parseDate($rule['value'] ?? '', $timezone);

        if ($date === null) {
            return false;
        }

        return $now->lt($date);
    }

    private function evaluateAfter(array $rule, Carbon $now, string $timezone): bool
    {
        $date = $this->parseDate($rule['value'] ?? '', $timezone);

        if ($date === null) {
            return false;
        }

        return $now->gt($date->endOfDay());
    }

    private function parseDate(string $dateString, string $timezone): ?Carbon
    {
        if ($dateString === '') {
            return null;
        }

        try {
            return Carbon::parse($dateString, $timezone)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
