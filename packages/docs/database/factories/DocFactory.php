<?php

declare(strict_types=1);

namespace AIArmada\Docs\Database\Factories;

use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\Pending;
use AIArmada\Docs\States\Sent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doc>
 */
final class DocFactory extends Factory
{
    protected $model = Doc::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $items = [
            [
                'name' => $this->faker->words(3, true),
                'quantity' => $this->faker->numberBetween(1, 10),
                'unit_price_minor' => $this->faker->numberBetween(1_000, 50_000),
            ],
            [
                'name' => $this->faker->words(3, true),
                'quantity' => $this->faker->numberBetween(1, 5),
                'unit_price_minor' => $this->faker->numberBetween(5_000, 100_000),
            ],
        ];

        $subtotalMinor = (int) collect($items)->sum(fn (array $item): int => $item['quantity'] * $item['unit_price_minor']);
        $taxAmountMinor = intdiv(($subtotalMinor * 600) + 5_000, 10_000);
        $totalMinor = $subtotalMinor + $taxAmountMinor;

        return [
            'doc_number' => mb_strtoupper($this->faker->bothify('???-####-####')),
            'doc_type' => $this->faker->randomElement(DocType::cases())->value,
            'status' => Draft::class,
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal_minor' => $subtotalMinor,
            'tax_amount_minor' => $taxAmountMinor,
            'discount_amount_minor' => 0,
            'total_minor' => $totalMinor,
            'currency' => 'MYR',
            'body' => null,
            'items' => $items,
            'customer_data' => [
                'name' => $this->faker->company(),
                'email' => $this->faker->companyEmail(),
                'address' => $this->faker->address(),
                'phone' => $this->faker->phoneNumber(),
            ],
            'company_data' => [
                'name' => config('docs.company.name', 'My Company'),
                'address' => config('docs.company.address', '123 Business St'),
                'email' => config('docs.company.email', 'info@company.com'),
            ],
        ];
    }

    public function invoice(): static
    {
        return $this->state(fn (array $attributes) => [
            'doc_type' => DocType::Invoice->value,
            'doc_number' => 'INV-' . now()->format('Y') . '-' . mb_str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        ]);
    }

    public function quotation(): static
    {
        return $this->state(fn (array $attributes) => [
            'doc_type' => DocType::Quotation->value,
            'doc_number' => 'QUO-' . now()->format('Y') . '-' . mb_str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        ]);
    }

    public function creditNote(): static
    {
        return $this->state(fn (array $attributes) => [
            'doc_type' => DocType::CreditNote->value,
            'doc_number' => 'CN-' . now()->format('Y') . '-' . mb_str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Draft::class,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Pending::class,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Sent::class,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Paid::class,
            'paid_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Overdue::class,
            'due_date' => now()->subDays(7),
        ]);
    }

    public function withRecipient(string $email, ?string $name = null): static
    {
        return $this->state(fn (array $attributes) => [
            'recipient_email' => $email,
            'recipient_name' => $name ?? $this->faker->name(),
        ]);
    }

    public function highValue(int $amountMinor = 1_000_000): static
    {
        return $this->state(fn (array $attributes) => [
            'subtotal_minor' => $amountMinor,
            'tax_amount_minor' => intdiv(($amountMinor * 600) + 5_000, 10_000),
            'total_minor' => $amountMinor + intdiv(($amountMinor * 600) + 5_000, 10_000),
        ]);
    }
}
