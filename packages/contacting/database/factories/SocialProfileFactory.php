<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Database\Factories;

use AIArmada\Contacting\Models\SocialProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialProfile>
 */
final class SocialProfileFactory extends Factory
{
    protected $model = SocialProfile::class;

    public function definition(): array
    {
        return [
            'platform' => 'facebook',
            'purpose' => 'general',
            'handle' => 'example',
            'url' => 'https://facebook.com/example',
            'is_public' => true,
            'is_primary' => false,
            'is_verified' => false,
            'metadata' => [],
        ];
    }

    public function facebook(): static
    {
        return $this->state(fn () => [
            'platform' => 'facebook',
            'handle' => 'example',
            'url' => 'https://facebook.com/example',
        ]);
    }

    public function instagram(): static
    {
        return $this->state(fn () => [
            'platform' => 'instagram',
            'handle' => 'example',
            'url' => 'https://instagram.com/example',
        ]);
    }

    public function tiktok(): static
    {
        return $this->state(fn () => [
            'platform' => 'tiktok',
            'handle' => 'example',
            'url' => 'https://tiktok.com/@example',
        ]);
    }

    public function youtube(): static
    {
        return $this->state(fn () => [
            'platform' => 'youtube',
            'handle' => 'ExampleChannel',
            'url' => 'https://youtube.com/@ExampleChannel',
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
