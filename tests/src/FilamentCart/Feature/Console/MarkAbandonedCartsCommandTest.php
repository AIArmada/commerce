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

    $this->artisan('cart:mark-abandoned --dry-run')
        ->expectsOutputToContain('Skipping malformed owner tuple while marking abandoned carts')
        ->expectsOutputToContain('Total marked: 1 cart(s) as abandoned.')
        ->assertSuccessful();
});
