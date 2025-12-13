<?php

declare(strict_types=1);

namespace AIArmada\Docs\Database\Factories;

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
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
                'price' => $this->faker->randomFloat(2, 10, 500),
            ],
            [
                'name' => $this->faker->words(3, true),
                'quantity' => $this->faker->numberBetween(1, 5),
                'price' => $this->faker->randomFloat(2, 50, 1000),
            ],
        ];

        $subtotal = collect($items)->sum(fn ($item) => $item['quantity'] * $item['price']);
        $taxAmount = $subtotal * 0.06;
        $total = $subtotal + $taxAmount;

        return [
            'doc_number' => mb_strtoupper($this->faker->bothify('???-####-####')),
            'doc_type' => $this->faker->randomElement(DocType::cases())->value,
            'status' => DocStatus::DRAFT,
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => 0,
            'total' => $total,
            'currency' => 'MYR',
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
            'status' => DocStatus::DRAFT,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocStatus::PENDING,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocStatus::SENT,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocStatus::PAID,
            'paid_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocStatus::OVERDUE,
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

    public function highValue(float $amount = 10000): static
    {
        return $this->state(fn (array $attributes) => [
            'subtotal' => $amount,
            'tax_amount' => $amount * 0.06,
            'total' => $amount * 1.06,
        ]);
    }
}
