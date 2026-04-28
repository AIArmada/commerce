<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('cart.owner.enabled', false);
    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });
});

it('fails fast when owner scoping is enabled without an owner or all-owners flag', function (): void {
    config()->set('cart.owner.enabled', true);

    $this->artisan('cart:clear-abandoned --days=0')
        ->assertFailed()
        ->expectsOutputToContain('Pass --all-owners to process every owner.');
});

it('can dry-run abandoned carts for all owners when explicitly requested', function (): void {
    config()->set('cart.owner.enabled', true);

    $now = now();
    DB::table('carts')->truncate();

    DB::table('carts')->insert([
        [
            'id' => (string) Str::uuid(),
            'identifier' => 'user-1',
            'instance' => 'default',
            'owner_type' => null,
            'owner_id' => null,
            'items' => json_encode([], JSON_THROW_ON_ERROR),
            'conditions' => null,
            'metadata' => null,
            'version' => 1,
            'created_at' => $now->copy()->subDays(10),
            'updated_at' => $now->copy()->subDays(10),
        ],
    ]);

    $this->artisan('cart:clear-abandoned --days=0 --dry-run --all-owners')
        ->expectsOutputToContain('Found 1 abandoned carts to clear.')
        ->expectsOutputToContain('Would delete 1 abandoned carts.')
        ->assertSuccessful();

    expect(DB::table('carts')->count())->toBe(1);
});

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
            'id' => (string) Str::uuid(),
            'identifier' => 'user-1',
            'instance' => 'default',
            'owner_type' => null,
            'owner_id' => null,
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
            'id' => (string) Str::uuid(),
            'identifier' => 'user-1',
            'instance' => 'default',
            'owner_type' => null,
            'owner_id' => null,
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

it('skips malformed owner tuples during all-owner dry runs instead of treating them as global', function (): void {
    config()->set('cart.owner.enabled', true);

    $now = now();
    DB::table('carts')->truncate();

    DB::table('carts')->insert([
        [
            'id' => (string) Str::uuid(),
            'identifier' => 'global-cart',
            'instance' => 'default',
            'owner_type' => null,
            'owner_id' => null,
            'owner_scope' => 'global',
            'items' => json_encode([], JSON_THROW_ON_ERROR),
            'conditions' => null,
            'metadata' => null,
            'version' => 1,
            'created_at' => $now->copy()->subDays(10),
            'updated_at' => $now->copy()->subDays(10),
        ],
        [
            'id' => (string) Str::uuid(),
            'identifier' => 'corrupt-cart',
            'instance' => 'default',
            'owner_type' => 'users',
            'owner_id' => null,
            'owner_scope' => 'global',
            'items' => json_encode([], JSON_THROW_ON_ERROR),
            'conditions' => null,
            'metadata' => null,
            'version' => 1,
            'created_at' => $now->copy()->subDays(10),
            'updated_at' => $now->copy()->subDays(10),
        ],
    ]);

    $this->artisan('cart:clear-abandoned --days=0 --dry-run --all-owners')
        ->expectsOutputToContain('Skipping malformed owner tuple while clearing abandoned carts')
        ->expectsOutputToContain('Found 1 abandoned carts to clear.')
        ->expectsOutputToContain('Would delete 1 abandoned carts.')
        ->assertSuccessful();
});

it('aborts when malformed owner tuples are encountered in strict mode', function (): void {
    config()->set('cart.owner.enabled', true);

    $now = now();
    DB::table('carts')->truncate();

    DB::table('carts')->insert([
        [
            'id' => (string) Str::uuid(),
            'identifier' => 'corrupt-cart',
            'instance' => 'default',
            'owner_type' => 'users',
            'owner_id' => null,
            'owner_scope' => 'global',
            'items' => json_encode([], JSON_THROW_ON_ERROR),
            'conditions' => null,
            'metadata' => null,
            'version' => 1,
            'created_at' => $now->copy()->subDays(10),
            'updated_at' => $now->copy()->subDays(10),
        ],
    ]);

    $this->artisan('cart:clear-abandoned --days=0 --dry-run --all-owners --strict-owner-tuples')
        ->assertFailed();
});
