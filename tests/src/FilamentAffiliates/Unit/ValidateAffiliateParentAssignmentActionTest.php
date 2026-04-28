<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliates\Actions\ValidateAffiliateParentAssignment;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);

    Affiliate::query()->delete();
});

it('accepts a parent affiliate within the current owner scope', function (): void {
    $owner = User::create([
        'name' => 'Parent Guard Owner',
        'email' => 'parent-guard-owner@example.com',
        'password' => 'secret',
    ]);

    [$child, $parent] = OwnerContext::withOwner($owner, function (): array {
        $child = Affiliate::create([
            'code' => 'CHILD-' . Str::uuid(),
            'name' => 'Child Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 300,
            'currency' => 'USD',
        ]);

        $parent = Affiliate::create([
            'code' => 'PARENT-' . Str::uuid(),
            'name' => 'Parent Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 400,
            'currency' => 'USD',
        ]);

        return [$child, $parent];
    });

    OwnerContext::withOwner($owner, function () use ($child, $parent): void {
        $data = ValidateAffiliateParentAssignment::run([
            'parent_affiliate_id' => $parent->getKey(),
        ], $child);

        expect($data['parent_affiliate_id'])->toBe((string) $parent->getKey());
    });
});

it('rejects a parent affiliate from another owner scope', function (): void {
    $ownerA = User::create([
        'name' => 'Parent Guard Owner A',
        'email' => 'parent-guard-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::create([
        'name' => 'Parent Guard Owner B',
        'email' => 'parent-guard-owner-b@example.com',
        'password' => 'secret',
    ]);

    $child = OwnerContext::withOwner($ownerA, function (): Affiliate {
        return Affiliate::create([
            'code' => 'CHILD-' . Str::uuid(),
            'name' => 'Owner A Child',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 300,
            'currency' => 'USD',
        ]);
    });

    $parentB = OwnerContext::withOwner($ownerB, function (): Affiliate {
        return Affiliate::create([
            'code' => 'PARENT-' . Str::uuid(),
            'name' => 'Owner B Parent',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 400,
            'currency' => 'USD',
        ]);
    });

    OwnerContext::withOwner($ownerA, function () use ($child, $parentB): void {
        expect(fn () => ValidateAffiliateParentAssignment::run([
            'parent_affiliate_id' => $parentB->getKey(),
        ], $child))->toThrow(ValidationException::class, 'Selected parent affiliate is not accessible in the current owner scope.');
    });
});

it('rejects self-parent assignments', function (): void {
    $owner = User::create([
        'name' => 'Parent Guard Self Owner',
        'email' => 'parent-guard-self-owner@example.com',
        'password' => 'secret',
    ]);

    $affiliate = OwnerContext::withOwner($owner, function (): Affiliate {
        return Affiliate::create([
            'code' => 'SELF-' . Str::uuid(),
            'name' => 'Self Parent Attempt',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 350,
            'currency' => 'USD',
        ]);
    });

    OwnerContext::withOwner($owner, function () use ($affiliate): void {
        expect(fn () => ValidateAffiliateParentAssignment::run([
            'parent_affiliate_id' => $affiliate->getKey(),
        ], $affiliate))->toThrow(ValidationException::class, 'An affiliate cannot be its own parent.');
    });
});

it('allows global parent affiliates when include-global is enabled', function (): void {
    config()->set('affiliates.owner.include_global', true);

    $owner = User::create([
        'name' => 'Parent Guard Global Owner',
        'email' => 'parent-guard-global-owner@example.com',
        'password' => 'secret',
    ]);

    $globalParent = OwnerContext::withOwner(null, function (): Affiliate {
        return Affiliate::create([
            'code' => 'GLOBAL-' . Str::uuid(),
            'name' => 'Global Parent',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 450,
            'currency' => 'USD',
        ]);
    });

    $child = OwnerContext::withOwner($owner, function (): Affiliate {
        return Affiliate::create([
            'code' => 'CHILD-' . Str::uuid(),
            'name' => 'Scoped Child',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 320,
            'currency' => 'USD',
        ]);
    });

    OwnerContext::withOwner($owner, function () use ($child, $globalParent): void {
        $data = ValidateAffiliateParentAssignment::run([
            'parent_affiliate_id' => $globalParent->getKey(),
        ], $child);

        expect($data['parent_affiliate_id'])->toBe((string) $globalParent->getKey());
    });
});

it('still validates existing parent affiliates when owner scoping is disabled', function (): void {
    config()->set('affiliates.owner.enabled', false);

    $child = Affiliate::create([
        'code' => 'CHILD-' . Str::uuid(),
        'name' => 'Unguarded Child',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 300,
        'currency' => 'USD',
    ]);

    $parent = Affiliate::create([
        'code' => 'PARENT-' . Str::uuid(),
        'name' => 'Unguarded Parent',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 350,
        'currency' => 'USD',
    ]);

    $data = ValidateAffiliateParentAssignment::run([
        'parent_affiliate_id' => $parent->getKey(),
    ], $child);

    expect($data['parent_affiliate_id'])->toBe((string) $parent->getKey());
});