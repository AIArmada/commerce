<?php

declare(strict_types=1);

use AIArmada\Cart\Features\LiveCart\Api\AbandonedCartCleanup;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function abandonedCartRow(array $overrides = []): array
{
    $now = now();

    return array_merge([
        'id' => (string) Str::uuid(),
        'identifier' => 'abandoned-' . Str::uuid(),
        'instance' => 'default',
        'owner_type' => null,
        'owner_id' => null,
        'items' => json_encode([], JSON_THROW_ON_ERROR),
        'conditions' => null,
        'metadata' => null,
        'version' => 1,
        'created_at' => $now->copy()->subDays(10),
        'updated_at' => $now->copy()->subDays(10),
    ], $overrides);
}

beforeEach(function (): void {
    DB::table('carts')->truncate();
    config()->set('cart.owner.enabled', false);
    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?RecordReference
        {
            return null;
        }
    });
});

it('requires explicit global context to enumerate owners', function (): void {
    config()->set('cart.owner.enabled', true);

    expect(fn () => app(AbandonedCartCleanup::class)->owners('carts'))
        ->toThrow(AuthorizationException::class);
});

it('requires the cleanup owner tuple to match the ambient owner scope', function (): void {
    config()->set('cart.owner.enabled', true);

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    expect(fn () => app(AbandonedCartCleanup::class)->count(
        'carts',
        false,
        0,
        now(),
        $ownerB->getMorphClass(),
        $ownerB->getKey(),
        false,
    ))->toThrow(AuthorizationException::class);
});

it('continues keyset cleanup past rows whose conditional delete no longer matches', function (): void {
    $now = now()->subDay();

    $rows = [
        abandonedCartRow(['id' => '00000000-0000-0000-0000-000000000001', 'abandoned_at' => $now]),
        abandonedCartRow(['id' => '00000000-0000-0000-0000-000000000002', 'abandoned_at' => $now]),
        abandonedCartRow(['id' => '00000000-0000-0000-0000-000000000003', 'abandoned_at' => $now]),
    ];

    DB::table('carts')->insert($rows);

    $changed = false;

    DB::beforeExecuting(function (string $query) use (&$changed): void {
        if ($changed || ! str_contains($query, 'delete from')) {
            return;
        }

        $changed = true;
        DB::table('carts')
            ->where('id', '00000000-0000-0000-0000-000000000001')
            ->update(['abandoned_at' => null]);
    });

    $deleted = app(AbandonedCartCleanup::class)->execute(
        'carts',
        false,
        0,
        now(),
        1,
        null,
        null,
        true,
    );

    expect($deleted)->toBe(2)
        ->and(DB::table('carts')->where('id', '00000000-0000-0000-0000-000000000001')->exists())->toBeTrue()
        ->and(DB::table('carts')->count())->toBe(1);
});

it('clears owner-tagged carts without owner filtering when scoping is disabled', function (): void {
    $owner = User::factory()->create();

    DB::table('carts')->insert(abandonedCartRow([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
    ]));

    $deleted = app(AbandonedCartCleanup::class)->execute(
        'carts',
        false,
        0,
        CarbonImmutable::now(),
        10,
        null,
        null,
        false,
    );

    expect($deleted)->toBe(1)
        ->and(DB::table('carts')->count())->toBe(0);
});

it('keeps per-owner cleanup scoped to the authorized owner tuple', function (): void {
    config()->set('cart.owner.enabled', true);

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    DB::table('carts')->insert([
        abandonedCartRow([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
        ]),
        abandonedCartRow([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]),
    ]);

    $deleted = OwnerContext::withOwner($ownerA, fn (): int => app(AbandonedCartCleanup::class)->execute(
        'carts',
        false,
        0,
        CarbonImmutable::now(),
        10,
        $ownerA->getMorphClass(),
        $ownerA->getKey(),
        false,
    ));

    expect($deleted)->toBe(1)
        ->and(DB::table('carts')->where('owner_id', $ownerA->getKey())->count())->toBe(0)
        ->and(DB::table('carts')->where('owner_id', $ownerB->getKey())->count())->toBe(1);
});
