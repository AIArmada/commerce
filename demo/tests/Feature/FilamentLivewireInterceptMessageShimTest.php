<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('filament admin HTML includes the Livewire interceptMessage shim', function (): void {
    $user = \App\Models\User::factory()->create();

    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $this->actingAs($user);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('__filamentInterceptMessageShimApplied', escape: false);
});
