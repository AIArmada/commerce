<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Database\Factories;

use AIArmada\Contacting\Models\ContactMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMethod>
 */
final class ContactMethodFactory extends Factory
{
    protected $model = ContactMethod::class;

    public function definition(): array
    {
        return [
            'type' => 'email',
            'purpose' => 'general',
            'label' => 'Admin',
            'value' => 'admin@example.com',
            'normalized_value' => 'admin@example.com',
            'display_value' => 'admin@example.com',
            'is_primary' => false,
            'is_public' => true,
            'is_verified' => false,
            'metadata' => [],
        ];
    }

    public function email(): static
    {
        return $this->state(fn () => [
            'type' => 'email',
            'value' => 'admin@example.com',
            'normalized_value' => 'admin@example.com',
            'display_value' => 'admin@example.com',
        ]);
    }

    public function phone(): static
    {
        return $this->state(fn () => [
            'type' => 'phone',
            'value' => '+60123456789',
            'normalized_value' => '+60123456789',
            'display_value' => '012-345 6789',
            'country_code' => 'MY',
        ]);
    }

    public function whatsapp(): static
    {
        return $this->state(fn () => [
            'type' => 'whatsapp',
            'value' => '+60123456789',
            'normalized_value' => '+60123456789',
            'display_value' => '+60 12-345 6789',
            'country_code' => 'MY',
        ]);
    }

    public function website(): static
    {
        return $this->state(fn () => [
            'type' => 'website',
            'value' => 'https://example.com',
            'normalized_value' => 'https://example.com',
            'display_value' => 'https://example.com',
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }

    public function private(): static
    {
        return $this->state(fn () => ['is_public' => false]);
    }

    public function verified(): static
    {
        return $this->state(fn () => ['is_verified' => true, 'verified_at' => now()]);
    }
}
