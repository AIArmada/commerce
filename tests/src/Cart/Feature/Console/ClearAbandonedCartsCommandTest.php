<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('reports success when no abandoned carts are found', function (): void {
    DB::table('carts')->truncate();

    $this->artisan('cart:clear-abandoned --days=0')
        ->assertSuccessful();
});

it('simulates deletion in dry-run mode without removing data', function (): void {
    $now = now();
    DB::table('carts')->truncate();

    DB::table('carts')->insert([
        [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'identifier' => 'user-1',
            'instance' => 'default',
            'items' => json_encode([], JSON_THROW_ON_ERROR),
            'conditions' => null,
            'metadata' => null,
            'version' => 1,
            'created_at' => $now->copy()->subDays(10),
            'updated_at' => $now->copy()->subDays(10),
        ],
    ]);

    $countBefore = DB::table('carts')->count();

    $this->artisan('cart:clear-abandoned --days=0 --dry-run')
        ->expectsOutputToContain('DRY RUN MODE - No data will be deleted')
        ->assertSuccessful();

    $countAfter = DB::table('carts')->count();
    expect($countAfter)->toBe($countBefore);
});

it('deletes abandoned carts after confirmation', function (): void {
    $now = now();
    DB::table('carts')->truncate();

    DB::table('carts')->insert([
        [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'identifier' => 'user-1',
            'instance' => 'default',
            'items' => json_encode([], JSON_THROW_ON_ERROR),
            'conditions' => null,
            'metadata' => null,
            'version' => 1,
            'created_at' => $now->copy()->subDays(10),
            'updated_at' => $now->copy()->subDays(10),
        ],
    ]);

    $this->artisan('cart:clear-abandoned --days=0')
        ->expectsConfirmation('Are you sure you want to delete these carts?', 'yes')
        ->assertSuccessful();

    expect(DB::table('carts')->count())->toBe(0);
});
