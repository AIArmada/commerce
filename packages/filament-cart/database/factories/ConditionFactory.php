<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Database\Factories;

use AIArmada\FilamentCart\Models\Condition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Condition>
 */
final class ConditionFactory extends Factory
{
    protected $model = Condition::class;

    public function definition(): array
    {
        return [
            'name' => 'condition_'.Str::lower(Str::random(8)),
            'display_name' => 'Condition '.Str::upper(Str::random(4)),
            'description' => 'Auto generated condition '.Str::lower(Str::random(12)),
            'type' => $this->randomFrom(['discount', 'tax', 'fee', 'shipping', 'surcharge']),
            'target' => $this->randomFrom(['subtotal', 'total', 'item']),
            'value' => $this->generateValue(),
            'order' => random_int(0, 10),
            'attributes' => [],
            'is_active' => random_int(0, 100) < 80,
            'is_global' => false,
        ];
    }

    public function discount(): static
    {
        return $this->state(fn () => [
            'type' => 'discount',
            'value' => '-'.random_int(5, 50).'%',
            'target' => $this->randomFrom(['subtotal', 'item']),
        ]);
    }

    public function tax(): static
    {
        return $this->state(fn () => [
            'type' => 'tax',
            'value' => random_int(5, 15).'%',
            'target' => 'subtotal',
        ]);
    }

    public function fee(): static
    {
        return $this->state(fn () => [
            'type' => 'fee',
            'value' => '+'.random_int(200, 5000),
            'target' => 'subtotal',
        ]);
    }

    public function shipping(): static
    {
        return $this->state(fn () => [
            'type' => 'shipping',
            'value' => '+'.random_int(500, 8000),
            'target' => 'subtotal',
            'attributes' => [
                'method' => $this->randomFrom(['standard', 'express', 'overnight']),
                'carrier' => $this->randomFrom(['UPS', 'FedEx', 'DHL', 'USPS']),
            ],
        ]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function forItems(): static
    {
        return $this->state(fn () => ['target' => 'item']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withAttributes(array $attributes): static
    {
        return $this->state(fn () => ['attributes' => $attributes]);
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function withRules(array $rules): static
    {
        return $this->state(function () use ($rules) {
            $isDynamic = ! empty($rules);

            return [
                'rules' => Condition::normalizeRulesDefinition($rules, $isDynamic),
                'is_dynamic' => $isDynamic,
            ];
        });
    }

    private function generateValue(): string
    {
        return match ($this->randomFrom(['percentage', 'fixed_positive', 'fixed_negative'])) {
            'percentage' => random_int(1, 50).'%',
            'fixed_positive' => '+'.random_int(100, 10000),
            'fixed_negative' => '-'.random_int(100, 10000),
            default => '+'.random_int(100, 10000), // fallback
        };
    }

    /**
     * @param  array<mixed>  $options
     */
    private function randomFrom(array $options): mixed
    {
        return $options[array_rand($options)];
    }
}
