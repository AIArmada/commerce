<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting;

use AIArmada\Vouchers\Targeting\Enums\TargetingMode;

/**
 * Value object representing a targeting configuration.
 *
 * Parses target_definition array into structured targeting rules.
 */
readonly class TargetingConfiguration
{
    /**
     * @param  TargetingMode  $mode  Evaluation mode (all, any, custom)
     * @param  array<int, array<string, mixed>>  $rules  Targeting rules
     * @param  array<string, mixed>|null  $expression  Boolean expression for custom mode
     */
    public function __construct(
        public TargetingMode $mode,
        public array $rules,
        public ?array $expression = null,
    ) {}

    /**
     * Create from target_definition array.
     *
     * @param  array<string, mixed>|null  $targetDefinition
     */
    public static function fromArray(?array $targetDefinition): ?self
    {
        if ($targetDefinition === null) {
            return null;
        }

        $targeting = $targetDefinition['targeting'] ?? $targetDefinition;

        if (! is_array($targeting) || empty($targeting)) {
            return null;
        }

        $modeValue = $targeting['mode'] ?? 'all';
        $mode = $modeValue instanceof TargetingMode
            ? $modeValue
            : TargetingMode::tryFrom($modeValue) ?? TargetingMode::All;

        /** @var array<int, array<string, mixed>> $rules */
        $rules = [];
        if (isset($targeting['rules']) && is_array($targeting['rules'])) {
            $rules = array_values($targeting['rules']);
        }

        /** @var array<string, mixed>|null $expression */
        $expression = null;
        if ($mode === TargetingMode::Custom && isset($targeting['expression']) && is_array($targeting['expression'])) {
            $expression = $targeting['expression'];
        }

        if (empty($rules) && $expression === null) {
            return null;
        }

        return new self(
            mode: $mode,
            rules: $rules,
            expression: $expression,
        );
    }

    /**
     * Check if configuration has any targeting rules.
     */
    public function hasRules(): bool
    {
        return ! empty($this->rules) || $this->expression !== null;
    }

    /**
     * Get the number of rules.
     */
    public function getRuleCount(): int
    {
        return count($this->rules);
    }

    /**
     * Convert to array format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'mode' => $this->mode->value,
            'rules' => $this->rules,
        ];

        if ($this->expression !== null) {
            $data['expression'] = $this->expression;
        }

        return ['targeting' => $data];
    }
}
