<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('cart.owner.enabled', true);
    config()->set('filament-cart.owner.enabled', true);
    config()->set('filament-cart.monitoring.abandonment_detection_minutes', 30);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });
});

it('exits successfully when abandonment tracking feature is disabled', function (): void {
    config()->set('filament-cart.features.abandonment_tracking', false);

    $this->artisan('cart:mark-abandoned --dry-run')
        ->expectsOutputToContain('Abandonment tracking is disabled via filament-cart.features.abandonment_tracking.')
        ->assertSuccessful();
});

it('skips malformed owner tuples during all-owner abandoned-cart marking', function (): void {
    $table = config('filament-cart.database.tables.snapshots', 'cart_snapshots');
    $now = now();

    DB::table($table)->truncate();

    DB::table($table)->insert([
        [
            'id' => (string) Str::uuid(),
            'identifier' => 'global-cart',
            'instance' => 'default',
            'owner_scope' => 'global',
            'owner_type' => null,
            'owner_id' => null,
            'items_count' => 1,
            'quantity' => 1,
            'subtotal' => 1000,
            'total' => 1000,
            'savings' => 0,
            'currency' => 'USD',
            'items' => json_encode([['id' => 'sku-1']], JSON_THROW_ON_ERROR),
            'conditions' => null,
            'metadata' => null,
            'last_activity_at' => $now->copy()->subHours(2),
            'checkout_started_at' => $now->copy()->subHours(2),
            'checkout_abandoned_at' => null,
            'created_at' => $now->copy()->subDays(2),
            'updated_at' => $now->copy()->subHours(2),
        ],
        [
            'id' => (string) Str::uuid(),
            'identifier' => 'corrupt-cart',
            'instance' => 'default',
            'owner_scope' => 'global',
            'owner_type' => 'users',
            'owner_id' => null,
            'items_count' => 1,
            'quantity' => 1,
            'subtotal' => 1000,
            'total' => 1000,
            'savings' => 0,
            'currency' => 'USD',
            'items' => json_encode([['id' => 'sku-2']], JSON_THROW_ON_ERROR),
            'conditions' => null,
            'metadata' => null,
            'last_activity_at' => $now->copy()->subHours(2),
            'checkout_started_at' => $now->copy()->subHours(2),
            'checkout_abandoned_at' => null,
            'created_at' => $now->copy()->subDays(2),
            'updated_at' => $now->copy()->subHours(2),
        ],
    ]);

    $this->artisan('cart:mark-abandoned --all-owners --dry-run')
        ->expectsOutputToContain('Skipping malformed owner tuple while marking abandoned carts')
        ->expectsOutputToContain('Total marked: 1 cart(s) as abandoned.')
        ->assertSuccessful();
});

it('aborts in strict mode when malformed owner tuples are encountered', function (): void {
    $table = config('filament-cart.database.tables.snapshots', 'cart_snapshots');
    $now = now();

    DB::table($table)->truncate();

    DB::table($table)->insert([
        [
            'id' => (string) Str::uuid(),
            'identifier' => 'corrupt-cart',
            'instance' => 'default',
            'owner_scope' => 'global',
            'owner_type' => 'users',
            'owner_id' => null,
            'items_count' => 1,
            'quantity' => 1,
            'subtotal' => 1000,
            'total' => 1000,
            'savings' => 0,
            'currency' => 'USD',
            'items' => json_encode([['id' => 'sku-2']], JSON_THROW_ON_ERROR),
            'conditions' => null,
            'metadata' => null,
            'last_activity_at' => $now->copy()->subHours(2),
            'checkout_started_at' => $now->copy()->subHours(2),
            'checkout_abandoned_at' => null,
            'created_at' => $now->copy()->subDays(2),
            'updated_at' => $now->copy()->subHours(2),
        ],
    ]);

    $this->artisan('cart:mark-abandoned --all-owners --dry-run --strict-owner-tuples')
        ->assertFailed();
});

it('fails closed without owner context unless all-owners is explicit', function (): void {
    $this->artisan('cart:mark-abandoned --dry-run')
        ->expectsOutputToContain('Owner scoping is enabled but no owner context was resolved. Pass --all-owners to process every owner.')
        ->assertFailed();
});

it('requires explicit confirmation flag for non-dry all-owner mutation', function (): void {
    $this->artisan('cart:mark-abandoned --all-owners')
        ->expectsOutputToContain('Multi-owner mutation requires --confirm-all-owners')
        ->assertFailed();
});

it('aborts when affected carts exceed max-affected threshold', function (): void {
    $table = config('filament-cart.database.tables.snapshots', 'cart_snapshots');
    $now = now();

    DB::table($table)->truncate();

    DB::table($table)->insert([
        [
            'id' => (string) Str::uuid(),
            'identifier' => 'tenant-1-cart-a',
            'instance' => 'default',
            'owner_scope' => 'global',
            'owner_type' => null,
            'owner_id' => null,
            'items_count' => 1,
            'quantity' => 1,
            'subtotal' => 1000,
            'total' => 1000,
            'savings' => 0,
            'currency' => 'USD',
            'items' => json_encode([['id' => 'sku-1']], JSON_THROW_ON_ERROR),
            'conditions' => null,
            'metadata' => null,
            'last_activity_at' => $now->copy()->subHours(3),
            'checkout_started_at' => $now->copy()->subHours(3),
            'checkout_abandoned_at' => null,
            'created_at' => $now->copy()->subDays(2),
            'updated_at' => $now->copy()->subHours(3),
        ],
        [
            'id' => (string) Str::uuid(),
            'identifier' => 'tenant-2-cart-a',
            'instance' => 'default',
            'owner_scope' => 'global',
            'owner_type' => null,
            'owner_id' => null,
            'items_count' => 1,
            'quantity' => 1,
            'subtotal' => 1000,
            'total' => 1000,
            'savings' => 0,
            'currency' => 'USD',
            'items' => json_encode([['id' => 'sku-2']], JSON_THROW_ON_ERROR),
            'conditions' => null,
            'metadata' => null,
            'last_activity_at' => $now->copy()->subHours(3),
            'checkout_started_at' => $now->copy()->subHours(3),
            'checkout_abandoned_at' => null,
            'created_at' => $now->copy()->subDays(2),
            'updated_at' => $now->copy()->subHours(3),
        ],
    ]);

    $this->artisan('cart:mark-abandoned --all-owners --confirm-all-owners --max-affected=1')
        ->expectsOutputToContain('exceeds --max-affected=1')
        ->assertFailed();
});
