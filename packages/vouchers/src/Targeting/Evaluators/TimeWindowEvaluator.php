<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting\Evaluators;

use AIArmada\Vouchers\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
use AIArmada\Vouchers\Targeting\TargetingContext;
use Carbon\Carbon;

/**
 * Evaluates time window targeting rules.
 *
 * Checks if the current time falls within a specified time range.
 */
class TimeWindowEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::TimeWindow->value;
    }

    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $timezone = $rule['timezone'] ?? $context->getTimezone();
        $now = $context->getCurrentTime($timezone);

        $startTime = $rule['start'] ?? '00:00';
        $endTime = $rule['end'] ?? '23:59';

        $start = Carbon::createFromFormat('H:i', $startTime, $timezone);
        $end = Carbon::createFromFormat('H:i', $endTime, $timezone);

        // Carbon::createFromFormat returns Carbon|false, check for null/false
        if (! $start instanceof Carbon || ! $end instanceof Carbon) {
            return false;
        }

        // Set to today's date
        $start = $start->setDate($now->year, $now->month, $now->day);
        $end = $end->setDate($now->year, $now->month, $now->day);

        // Handle overnight windows (e.g., 22:00 - 06:00)
        if ($end->lt($start)) {
            // Check if now is after start OR before end
            return $now->gte($start) || $now->lte($end);
        }

        return $now->between($start, $end);
    }

    public function getType(): string
    {
        return TargetingRuleType::TimeWindow->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        if (! isset($rule['start']) || ! preg_match('/^\d{2}:\d{2}$/', $rule['start'])) {
            $errors[] = 'Start time must be in HH:MM format';
        }

        if (! isset($rule['end']) || ! preg_match('/^\d{2}:\d{2}$/', $rule['end'])) {
            $errors[] = 'End time must be in HH:MM format';
        }

        return $errors;
    }
}
