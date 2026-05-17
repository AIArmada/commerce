<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('filament admin uses the commerce command center dashboard page', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $user = User::query()
        ->where('email', 'admin@commerce.demo')
        ->firstOrFail();

    $this->actingAs($user);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('Commerce Command Center');
});
