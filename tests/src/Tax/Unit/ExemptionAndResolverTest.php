<?php

declare(strict_types=1);

use AIArmada\Tax\Actions\Exemption\RequestTaxExemption;
use AIArmada\Tax\Contracts\TaxZoneResolverInterface;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\States\TaxExemptionState\ApprovedState;
use AIArmada\Tax\States\TaxExemptionState\PendingState;
use Symfony\Component\Console\Exception\CommandNotFoundException;

describe('Exemption requests', function (): void {
    it('forces new requests into pending and drops privileged fields', function (): void {
        $zone = TaxZone::query()->create([
            'name' => 'Request Zone',
            'code' => 'REQUEST-ZONE',
            'is_active' => true,
        ]);

        $exemption = app(RequestTaxExemption::class)->execute([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'tax_zone_id' => $zone->id,
            'reason' => 'Non-profit organization',
            'certificate_number' => 'CERT-1',
            'status' => ApprovedState::class,
            'verified_at' => now()->toDateTimeString(),
            'rejection_reason' => 'forged',
            'owner_type' => 'App\\Models\\Tenant',
            'owner_id' => 'tenant-1',
        ]);

        expect($exemption->status)->toBeInstanceOf(PendingState::class)
            ->and($exemption->verified_at)->toBeNull()
            ->and($exemption->rejection_reason)->toBeNull()
            ->and($exemption->owner_type)->toBeNull()
            ->and($exemption->owner_id)->toBeNull()
            ->and($exemption->reason)->toBe('Non-profit organization')
            ->and($exemption->certificate_number)->toBe('CERT-1');
    });
});

describe('Zone resolver cache', function (): void {
    it('clears the default zone cache along with child resolvers', function (): void {
        $resolver = app(TaxZoneResolverInterface::class);
        $context = [];

        $zone = TaxZone::query()->create([
            'name' => 'Default Zone',
            'code' => 'DEFAULT-ZONE',
            'is_default' => true,
            'is_active' => true,
        ]);

        expect($resolver->resolve(null, $context)?->is($zone))->toBeTrue();

        $zone->delete();

        expect($resolver->resolve(null, $context))->toBeNull();
    });
});

describe('Postcode matching', function (): void {
    it('rejects alphanumeric input against numeric ranges', function (): void {
        $zone = TaxZone::query()->create([
            'name' => 'Numeric Range',
            'code' => 'NUM-RANGE-REJECT',
            'countries' => ['MY'],
            'postcodes' => ['10-20'],
            'is_active' => true,
        ]);

        expect($zone->matchesAddress('MY', null, 'AB12'))->toBeFalse()
            ->and($zone->matchesAddress('MY', null, '15'))->toBeTrue();
    });

    it('resolves zones with wildcard and range postcode patterns', function (): void {
        TaxZone::query()->create([
            'name' => 'Wildcard Postcode Zone',
            'code' => 'WILD-POST-RESOLVE',
            'countries' => ['MY'],
            'postcodes' => ['50*'],
            'is_active' => true,
        ]);

        TaxZone::query()->create([
            'name' => 'Range Postcode Zone',
            'code' => 'RANGE-POST-RESOLVE',
            'countries' => ['GB'],
            'postcodes' => ['40000-40999'],
            'is_active' => true,
        ]);

        $resolver = app(TaxZoneResolverInterface::class);

        $wildcard = $resolver->resolve(null, [
            'shipping_address' => ['country' => 'MY', 'postcode' => '50123'],
        ]);

        $range = $resolver->resolve(null, [
            'shipping_address' => ['country' => 'GB', 'postcode' => '40500'],
        ]);

        $miss = $resolver->resolve(null, [
            'shipping_address' => ['country' => 'GB', 'postcode' => '99999'],
        ]);

        expect($wildcard?->code)->toBe('WILD-POST-RESOLVE')
            ->and($range?->code)->toBe('RANGE-POST-RESOLVE')
            ->and($miss)->toBeNull();
    });
});

describe('Owner mass assignment', function (): void {
    it('ignores owner fields passed to mass assignment', function (): void {
        $zone = TaxZone::query()->create([
            'name' => 'Spoofed Owner Zone',
            'code' => 'SPOOF-OWNER',
            'owner_type' => 'App\\Models\\Tenant',
            'owner_id' => 'tenant-9',
            'is_active' => true,
        ]);

        expect($zone->owner_type)->toBeNull()
            ->and($zone->owner_id)->toBeNull();

        $exemption = TaxExemption::query()->create([
            'exemptable_id' => 'customer-9',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Ownership probe',
            'status' => PendingState::class,
            'owner_type' => 'App\\Models\\Tenant',
            'owner_id' => 'tenant-9',
        ]);

        expect($exemption->owner_type)->toBeNull()
            ->and($exemption->owner_id)->toBeNull();
    });
});

describe('Removed console commands', function (): void {
    it('no longer registers rate recalculation', function (): void {
        $this->expectException(CommandNotFoundException::class);

        $this->artisan('tax:recalculate-rates');
    });

    it('no longer registers zone sync', function (): void {
        $this->expectException(CommandNotFoundException::class);

        $this->artisan('tax:sync-zones');
    });
});
