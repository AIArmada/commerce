<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Support;

use AIArmada\Cart\Conditions\ConditionTarget;
use Illuminate\Validation\ValidationException;
use Stringable;
use Throwable;

final class ConditionTargetFormData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function persist(array $data): array
    {
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $dsl = self::resolveDsl($data);

        try {
            $target = ConditionTarget::from($dsl);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'condition_target_dsl' => $exception->getMessage(),
            ]);
        }

        $data['target_definition'] = $target->toArray();
        unset($metadata['target_definition'], $metadata['condition_target_definition'], $metadata['condition_target_dsl']);
        $data['metadata'] = $metadata ?: null;

        unset($data['condition_target_dsl'], $data['condition_target_preset']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function resolveDsl(array $data): string
    {
        $preset = self::resolvePreset($data['condition_target_preset'] ?? null);

        if ($preset !== ConditionTargetPreset::Custom) {
            $dsl = $preset->dsl();

            if ($dsl !== null) {
                return $dsl;
            }
        }

        $dslValue = $data['condition_target_dsl'] ?? '';

        if (! is_scalar($dslValue) && ! $dslValue instanceof Stringable) {
            throw ValidationException::withMessages([
                'condition_target_dsl' => 'Custom condition target DSL must be a string.',
            ]);
        }

        $dsl = mb_trim((string) $dslValue);

        if ($dsl === '') {
            throw ValidationException::withMessages([
                'condition_target_dsl' => 'Custom condition target DSL cannot be empty.',
            ]);
        }

        return $dsl;
    }

    private static function resolvePreset(mixed $value): ConditionTargetPreset
    {
        if ($value === null || $value === '') {
            return ConditionTargetPreset::default();
        }

        if ($value instanceof ConditionTargetPreset) {
            return $value;
        }

        if (! is_string($value)) {
            throw self::invalidPreset();
        }

        return ConditionTargetPreset::tryFrom($value) ?? throw self::invalidPreset();
    }

    private static function invalidPreset(): ValidationException
    {
        return ValidationException::withMessages([
            'condition_target_preset' => 'Select a valid condition target preset.',
        ]);
    }
}
