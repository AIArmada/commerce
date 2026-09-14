<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Support;

use AIArmada\Cart\Conditions\ConditionTarget;
use Throwable;

/**
 * Crash-safe reads of stored condition-target definitions.
 *
 * Legacy or hand-edited rows can hold definitions the current DSL parser
 * rejects; display and edit hydration must fall back to the default preset
 * instead of breaking the whole page.
 */
final class ConditionTargetDisplay
{
    public static function dsl(mixed $definition): string
    {
        if (is_array($definition)) {
            try {
                return ConditionTarget::from($definition)->toDsl();
            } catch (Throwable) {
                // Fall through to the default preset below.
            }
        }

        return ConditionTargetPreset::default()->dsl() ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function definition(mixed $definition): array
    {
        if (is_array($definition)) {
            try {
                ConditionTarget::from($definition);

                return $definition;
            } catch (Throwable) {
                // Fall through to the default preset below.
            }
        }

        $default = ConditionTargetPreset::default()->target()?->toArray();

        if (is_array($default)) {
            return $default;
        }

        $dsl = ConditionTargetPreset::default()->dsl();

        if (is_string($dsl)) {
            try {
                return ConditionTarget::from($dsl)->toArray();
            } catch (Throwable) {
                // Fall through to the empty definition below.
            }
        }

        return [];
    }

    public static function presetLabel(mixed $definition): string
    {
        $preset = ConditionTargetPreset::detect(self::dsl($definition));

        return ($preset ?? ConditionTargetPreset::Custom)->label();
    }
}
