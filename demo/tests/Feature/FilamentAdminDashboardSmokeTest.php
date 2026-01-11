<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('filament admin uses the commerce command center dashboard page', function (): void {
    $user = \App\Models\User::factory()->create();

    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $this->actingAs($user);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('Commerce Command Center');
});
