<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@commerce.demo',
            'password' => bcrypt('password'),
        ]);

        // Create additional demo users
        User::factory()->count(10)->create();
    }
}
