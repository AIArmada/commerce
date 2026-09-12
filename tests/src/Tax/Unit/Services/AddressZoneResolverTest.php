<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Tax\Contracts\TaxZoneResolverInterface;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Support\Facades\DB;

describe('AddressZoneResolver', function (): void {
    it('caches missing addresses for the request', function (): void {
        $resolver = app(TaxZoneResolverInterface::class);
        $context = [
            'shipping_address' => [
                'country' => 'MY',
                'state' => 'Selangor',
                'postcode' => '43000',
            ],
        ];

        DB::enableQueryLog();
        DB::flushQueryLog();

        expect($resolver->resolve(null, $context))->toBeNull();
        expect(DB::getQueryLog())->not->toBeEmpty();

        DB::flushQueryLog();

        expect($resolver->resolve(null, $context))->toBeNull();
        expect(DB::getQueryLog())->toBeEmpty();

        DB::disableQueryLog();
    });

    it('invalidates the cache when a tax zone changes', function (): void {
        $resolver = app(TaxZoneResolverInterface::class);
        $context = [
            'shipping_address' => [
                'country' => 'MY',
                'state' => 'Selangor',
                'postcode' => '43000',
            ],
        ];

        DB::enableQueryLog();
        DB::flushQueryLog();

        expect($resolver->resolve(null, $context))->toBeNull();

        $zone = TaxZone::create([
            'name' => 'Selangor',
            'code' => 'MY-SELANGOR-CACHE',
            'countries' => ['MY'],
            'states' => ['Selangor'],
            'is_active' => true,
        ]);

        DB::flushQueryLog();

        expect($resolver->resolve(null, $context)?->is($zone))->toBeTrue();
        expect(DB::getQueryLog())->not->toBeEmpty();

        DB::flushQueryLog();

        expect($resolver->resolve(null, $context)?->is($zone))->toBeTrue();
        expect(DB::getQueryLog())->toBeEmpty();

        $zone->update(['is_active' => false]);
        DB::flushQueryLog();

        expect($resolver->resolve(null, $context))->toBeNull();
        expect(DB::getQueryLog())->not->toBeEmpty();

        $zone->update(['is_active' => true]);
        DB::flushQueryLog();

        expect($resolver->resolve(null, $context)?->is($zone))->toBeTrue();
        expect(DB::getQueryLog())->not->toBeEmpty();

        $zone->delete();
        DB::flushQueryLog();

        expect($resolver->resolve(null, $context))->toBeNull();
        expect(DB::getQueryLog())->not->toBeEmpty();

        DB::disableQueryLog();
    });

    it('keeps cached resolutions isolated by owner', function (): void {
        config()->set('tax.features.owner.enabled', true);
        config()->set('tax.features.owner.include_global', false);

        $ownerA = User::query()->create([
            'name' => 'Tax Cache Owner A',
            'email' => 'tax-cache-owner-a@example.com',
            'password' => 'secret',
        ]);

        $ownerB = User::query()->create([
            'name' => 'Tax Cache Owner B',
            'email' => 'tax-cache-owner-b@example.com',
            'password' => 'secret',
        ]);

        $zoneA = OwnerContext::withOwner($ownerA, fn (): TaxZone => TaxZone::create([
            'name' => 'Owner A Malaysia',
            'code' => 'MY-CACHE-A',
            'countries' => ['MY'],
            'is_active' => true,
        ]));

        $zoneB = OwnerContext::withOwner($ownerB, fn (): TaxZone => TaxZone::create([
            'name' => 'Owner B Malaysia',
            'code' => 'MY-CACHE-B',
            'countries' => ['MY'],
            'is_active' => true,
        ]));

        $resolver = app(TaxZoneResolverInterface::class);
        $address = ['country' => 'MY'];

        $resolvedA = $resolver->resolve(null, [
            'owner' => $ownerA,
            'shipping_address' => $address,
        ]);
        $resolvedB = $resolver->resolve(null, [
            'owner' => $ownerB,
            'shipping_address' => $address,
        ]);

        expect($resolvedA?->is($zoneA))->toBeTrue()
            ->and($resolvedB?->is($zoneB))->toBeTrue();
    });
});
