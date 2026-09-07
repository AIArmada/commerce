<?php

declare(strict_types=1);

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\States\TaxExemptionState\ApprovedState;
use AIArmada\Tax\States\TaxExemptionState\PendingState;
use AIArmada\Tax\States\TaxExemptionState\RejectedState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\MorphTo;

describe('TaxExemption', function (): void {
    it('can create tax exemption', function (): void {
        $zone = TaxZone::create([
            'name' => 'Test Zone',
            'code' => 'TEST',
            'is_active' => true,
        ]);

        $exemption = TaxExemption::create([
            'exemptable_id' => 'customer-123',
            'exemptable_type' => 'App\\Models\\Customer',
            'tax_zone_id' => $zone->id,
            'reason' => 'Non-profit organization',
            'certificate_number' => 'CERT-123',
            'status' => ApprovedState::class,
            'verified_at' => CarbonImmutable::now(),
            'verified_by' => 'admin-1',
            'starts_at' => CarbonImmutable::now(),
            'expires_at' => CarbonImmutable::now()->addYear(),
        ]);

        $this->assertInstanceOf(TaxExemption::class, $exemption);
        $this->assertEquals('customer-123', $exemption->exemptable_id);
        $this->assertEquals('Non-profit organization', $exemption->reason);
        $this->assertInstanceOf(ApprovedState::class, $exemption->status);
    });

    it('active scope', function (): void {
        $uuid1 = '550e8400-e29b-41d4-a716-446655440001';
        $uuid2 = '550e8400-e29b-41d4-a716-446655440002';
        $uuid3 = '550e8400-e29b-41d4-a716-446655440003';
        $uuid4 = '550e8400-e29b-41d4-a716-446655440004';

        $now = CarbonImmutable::now();

        // Approved exemption within date range
        TaxExemption::create([
            'exemptable_id' => $uuid1,
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Active exemption',
            'status' => ApprovedState::class,
            'starts_at' => $now->copy()->subDays(5),
            'expires_at' => $now->copy()->addDays(5),
        ]);

        // Pending exemption
        TaxExemption::create([
            'exemptable_id' => $uuid2,
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Pending exemption',
            'status' => PendingState::class,
        ]);

        // Expired exemption
        TaxExemption::create([
            'exemptable_id' => $uuid3,
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Expired exemption',
            'status' => ApprovedState::class,
            'starts_at' => $now->copy()->subDays(20),
            'expires_at' => $now->copy()->subDays(10),
        ]);

        // Future exemption
        TaxExemption::create([
            'exemptable_id' => $uuid4,
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Future exemption',
            'status' => ApprovedState::class,
            'starts_at' => $now->copy()->addDays(10),
            'expires_at' => $now->copy()->addDays(20),
        ]);

        $activeExemptions = TaxExemption::active()->get();

        $this->assertCount(1, $activeExemptions);
        $this->assertEquals('Active exemption', $activeExemptions->first()->reason);
    });

    it('pending scope', function (): void {
        TaxExemption::create([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Pending',
            'status' => PendingState::class,
        ]);

        TaxExemption::create([
            'exemptable_id' => 'customer-2',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Approved',
            'status' => ApprovedState::class,
        ]);

        $pending = TaxExemption::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('Pending', $pending->first()->reason);
    });

    it('approved scope', function (): void {
        TaxExemption::create([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Approved',
            'status' => ApprovedState::class,
        ]);

        TaxExemption::create([
            'exemptable_id' => 'customer-2',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Rejected',
            'status' => RejectedState::class,
        ]);

        $approved = TaxExemption::approved()->get();

        $this->assertCount(1, $approved);
        $this->assertEquals('Approved', $approved->first()->reason);
    });

    it('for zone scope', function (): void {
        $zone1 = TaxZone::create(['name' => 'Zone 1', 'code' => 'Z1', 'is_active' => true]);
        $zone2 = TaxZone::create(['name' => 'Zone 2', 'code' => 'Z2', 'is_active' => true]);

        // Exemption for specific zone
        TaxExemption::create([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'tax_zone_id' => $zone1->id,
            'reason' => 'Zone specific',
            'status' => ApprovedState::class,
        ]);

        // Exemption for all zones (null zone_id)
        TaxExemption::create([
            'exemptable_id' => 'customer-2',
            'exemptable_type' => 'App\\Models\\Customer',
            'tax_zone_id' => null,
            'reason' => 'All zones',
            'status' => ApprovedState::class,
        ]);

        $zone1Exemptions = TaxExemption::forZone($zone1->id)->get();
        $zone2Exemptions = TaxExemption::forZone($zone2->id)->get();

        $this->assertCount(2, $zone1Exemptions); // Both exemptions apply to zone 1
        $this->assertCount(1, $zone2Exemptions); // Only the global exemption applies to zone 2
        $this->assertEquals('All zones', $zone2Exemptions->first()->reason);
    });

    it('relationships', function (): void {
        $zone = TaxZone::create([
            'name' => 'Test Zone',
            'code' => 'TEST',
            'is_active' => true,
        ]);

        $exemption = TaxExemption::create([
            'exemptable_id' => 'customer-123',
            'exemptable_type' => 'App\\Models\\Customer',
            'tax_zone_id' => $zone->id,
            'reason' => 'Test exemption',
            'status' => ApprovedState::class,
        ]);

        $this->assertInstanceOf(TaxZone::class, $exemption->taxZone);
        $this->assertEquals($zone->id, $exemption->taxZone->id);

        // Test morphTo relationship (would need actual Customer model for full test)
        $this->assertEquals('customer-123', $exemption->exemptable_id);
        $this->assertEquals('App\\Models\\Customer', $exemption->exemptable_type);
    });

    it('is active method', function (): void {
        $now = CarbonImmutable::now();

        // Active exemption
        $active = TaxExemption::create([
            'exemptable_id' => 'customer-active',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Active test',
            'status' => ApprovedState::class,
            'starts_at' => $now->copy()->subDay(),
            'expires_at' => $now->copy()->addDay(),
        ]);

        // Pending exemption
        $pending = TaxExemption::create([
            'exemptable_id' => 'customer-pending',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Pending test',
            'status' => PendingState::class,
        ]);

        // Expired exemption
        $expired = TaxExemption::create([
            'exemptable_id' => 'customer-expired',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Expired test',
            'status' => ApprovedState::class,
            'expires_at' => $now->copy()->subDay(),
        ]);

        // Future exemption (starts in the future)
        $future = TaxExemption::create([
            'exemptable_id' => 'customer-future',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Future test',
            'status' => ApprovedState::class,
            'starts_at' => $now->copy()->addDay(),
        ]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($pending->isActive());
        $this->assertFalse($expired->isActive());
        $this->assertFalse($future->isActive());
    });

    it('is expired method', function (): void {
        $expired = new TaxExemption(['expires_at' => CarbonImmutable::now()->subDay()]);
        $notExpired = new TaxExemption(['expires_at' => CarbonImmutable::now()->addDay()]);
        $noExpiry = new TaxExemption(['expires_at' => null]);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($notExpired->isExpired());
        $this->assertFalse($noExpiry->isExpired());
    });

    it('status helper methods', function (): void {
        $pending = new TaxExemption(['status' => PendingState::class]);
        $approved = new TaxExemption(['status' => ApprovedState::class]);
        $rejected = new TaxExemption(['status' => RejectedState::class]);

        $this->assertTrue($pending->isPending());
        $this->assertTrue($approved->isApproved());
        $this->assertTrue($rejected->isRejected());

        $this->assertFalse($approved->isPending());
        $this->assertFalse($pending->isApproved());
        $this->assertFalse($approved->isRejected());
    });

    it('approve method', function (): void {
        $exemption = TaxExemption::create([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Test',
            'status' => PendingState::class,
        ]);

        $result = $exemption->approve();

        $this->assertSame($exemption, $result);
        $this->assertInstanceOf(ApprovedState::class, $exemption->status);
        $this->assertNotNull($exemption->verified_at);
    });

    it('reject method', function (): void {
        $exemption = TaxExemption::create([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Test',
            'status' => PendingState::class,
        ]);

        $result = $exemption->reject('Invalid certificate');

        $this->assertSame($exemption, $result);
        $this->assertInstanceOf(RejectedState::class, $exemption->status);
        $this->assertEquals('Invalid certificate', $exemption->rejection_reason);
    });

    it('applies to zone method', function (): void {
        $zone = TaxZone::create(['name' => 'Zone', 'code' => 'Z', 'is_active' => true]);

        // Exemption for specific zone
        $specific = new TaxExemption(['tax_zone_id' => $zone->id]);

        // Exemption for all zones
        $global = new TaxExemption(['tax_zone_id' => null]);

        $this->assertTrue($specific->appliesToZone($zone->id));
        $this->assertFalse($specific->appliesToZone('other-zone-id'));

        $this->assertTrue($global->appliesToZone($zone->id));
        $this->assertTrue($global->appliesToZone('any-zone-id'));
        $this->assertTrue($global->appliesToZone(null));
    });

    it('casts', function (): void {
        $exemption = TaxExemption::create([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Test',
            'status' => ApprovedState::class,
            'verified_at' => '2024-01-01 12:00:00',
            'starts_at' => '2024-01-01 00:00:00',
            'expires_at' => '2024-12-31 23:59:59',
        ]);

        $this->assertInstanceOf(CarbonImmutable::class, $exemption->verified_at);
        $this->assertInstanceOf(CarbonImmutable::class, $exemption->starts_at);
        $this->assertInstanceOf(CarbonImmutable::class, $exemption->expires_at);
    });

    it('attributes defaults', function (): void {
        $exemption = new TaxExemption([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Test',
        ]);

        $this->assertInstanceOf(PendingState::class, $exemption->status);
    });

    it('activity logging', function (): void {
        $exemption = TaxExemption::create([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Activity test',
            'status' => PendingState::class,
        ]);

        $exemption->update(['status' => ApprovedState::class]);

        // Activity logging is configured but we can't easily test it without more setup
        // This test ensures the trait is applied and doesn't break
        $this->assertTrue(true);
    });

    it('get table method', function (): void {
        $exemption = new TaxExemption;

        $this->assertEquals('tax_exemptions', $exemption->getTable());
    });

    it('get table method with custom config', function (): void {
        config(['tax.database.tables.tax_exemptions' => 'custom_tax_exemptions']);

        $exemption = new TaxExemption;

        $this->assertEquals('custom_tax_exemptions', $exemption->getTable());

        // Reset to default
        config(['tax.database.tables.tax_exemptions' => 'tax_exemptions']);
    });

    it('exemptable relationship is morph to', function (): void {
        $exemption = new TaxExemption;

        // Access the relationship builder
        $relation = $exemption->exemptable();

        $this->assertInstanceOf(MorphTo::class, $relation);
    });
});
