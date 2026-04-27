<?php

declare(strict_types=1);

use AIArmada\Cart\Models\Condition;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

describe('Condition owner scoping', function (): void {
    beforeEach(function (): void {
        config()->set('cart.owner.enabled', true);
    });

    it('scopes owner=null to global-only records (never owner_type-only corrupt rows)', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'owner-a@example.com',
            'password' => 'secret',
        ]);

        $global = OwnerContext::withOwner(null, fn () => Condition::factory()->create([
            'owner_type' => null,
            'owner_id' => null,
        ]));

        $owned = OwnerContext::withOwner($ownerA, fn () => Condition::factory()->create([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ]));

        $corruptId = (string) Str::uuid();
        DB::table((new Condition)->getTable())->insert([
            'id' => $corruptId,
            'owner_scope' => 'global',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => null,
            'name' => 'corrupt-condition',
            'display_name' => 'Corrupt Condition',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'target_definition' => json_encode(conditionTargetDefinition('cart@cart_subtotal/aggregate')),
            'value' => '-1%',
            'order' => 0,
            'is_global' => false,
            'is_active' => true,
            'is_charge' => false,
            'is_dynamic' => false,
            'is_discount' => true,
            'is_percentage' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ids = Condition::query()->forOwner(null)->pluck('id')->all();

        expect($ids)
            ->toContain($global->id)
            ->not->toContain($owned->id)
            ->not->toContain($corruptId);
    });

    it('respects cart.owner.include_global for owner-scoped queries', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'owner-a-2@example.com',
            'password' => 'secret',
        ]);

        $global = OwnerContext::withOwner(null, fn () => Condition::factory()->create([
            'owner_type' => null,
            'owner_id' => null,
        ]));

        $owned = OwnerContext::withOwner($ownerA, fn () => Condition::factory()->create([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ]));

        config()->set('cart.owner.include_global', false);

        $ids = Condition::query()->forOwner($ownerA)->pluck('id')->all();

        expect($ids)
            ->toContain($owned->id)
            ->not->toContain($global->id);
    });

    it('excludes globals by default even when include_global is enabled', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'owner-a-3@example.com',
            'password' => 'secret',
        ]);

        $global = OwnerContext::withOwner(null, fn () => Condition::factory()->create([
            'owner_type' => null,
            'owner_id' => null,
        ]));

        $owned = OwnerContext::withOwner($ownerA, fn () => Condition::factory()->create([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ]));

        config()->set('cart.owner.include_global', true);

        $ids = Condition::query()->forOwner($ownerA)->pluck('id')->all();

        expect($ids)
            ->toContain($owned->id)
            ->not->toContain($global->id);
    });

    it('includes globals when explicitly requested', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'owner-a-4@example.com',
            'password' => 'secret',
        ]);

        $global = OwnerContext::withOwner(null, fn () => Condition::factory()->create([
            'owner_type' => null,
            'owner_id' => null,
        ]));

        $owned = OwnerContext::withOwner($ownerA, fn () => Condition::factory()->create([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ]));

        config()->set('cart.owner.include_global', true);

        $ids = Condition::query()->forOwner($ownerA, includeGlobal: true)->pluck('id')->all();

        expect($ids)
            ->toContain($owned->id)
            ->toContain($global->id);
    });

    it('allows the same condition name for different owners', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'owner-a-5@example.com',
            'password' => 'secret',
        ]);

        $ownerB = User::query()->create([
            'name' => 'Owner B',
            'email' => 'owner-b-5@example.com',
            'password' => 'secret',
        ]);

        OwnerContext::withOwner($ownerA, fn () => Condition::query()->create([
            'name' => 'shared-name',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10%',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
        ]));

        OwnerContext::withOwner($ownerB, fn () => Condition::query()->create([
            'name' => 'shared-name',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-5%',
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]));

        expect(Condition::query()->withoutOwnerScope()->where('name', 'shared-name')->count())->toBe(2);
    });

    it('auto-assigns the current owner when saving without explicit owner fields', function (): void {
        $owner = User::query()->create([
            'name' => 'Owner Context',
            'email' => 'owner-context@example.com',
            'password' => 'secret',
        ]);

        $condition = OwnerContext::withOwner($owner, fn () => Condition::query()->create([
            'name' => 'owner-assigned-condition',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10%',
        ]));

        expect($condition->owner_type)->toBe($owner->getMorphClass());
        expect((string) $condition->owner_id)->toBe((string) $owner->getKey());
    });

    it('rejects saving a condition with an explicit owner that mismatches the current owner context', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'condition-owner-a@example.com',
            'password' => 'secret',
        ]);

        $ownerB = User::query()->create([
            'name' => 'Owner B',
            'email' => 'condition-owner-b@example.com',
            'password' => 'secret',
        ]);

        expect(fn () => OwnerContext::withOwner($ownerA, fn () => Condition::query()->create([
            'name' => 'mismatched-condition',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10%',
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ])))->toThrow(AuthorizationException::class, 'Cross-owner save blocked for AIArmada\\Cart\\Models\\Condition.');
    });
});
