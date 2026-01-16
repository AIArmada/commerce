<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Fixtures\Factories;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'User ' . Str::random(8),
            'email' => Str::random(8) . '@example.com',
            'password' => bcrypt('password'),
        ];
    }
}
