<?php

declare(strict_types=1);

use AIArmada\Tax\Models\TaxZone;

describe('TaxZonePostcodeMatching', function (): void {
    it('matches postcode exact', function (): void {
        $zone = TaxZone::create([
            'name' => 'Exact Postcode',
            'code' => 'EXACT',
            'countries' => ['MY'],
            'postcodes' => ['43000'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('MY', null, '43000'));
        $this->assertFalse($zone->matchesAddress('MY', null, '43001'));
    });

    it('matches postcode range numeric', function (): void {
        $zone = TaxZone::create([
            'name' => 'Range Postcode',
            'code' => 'RANGE',
            'countries' => ['MY'],
            'postcodes' => ['40000-49999'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('MY', null, '40000'));
        $this->assertTrue($zone->matchesAddress('MY', null, '45000'));
        $this->assertTrue($zone->matchesAddress('MY', null, '49999'));
        $this->assertFalse($zone->matchesAddress('MY', null, '39999'));
        $this->assertFalse($zone->matchesAddress('MY', null, '50000'));
    });

    it('matches postcode range with non numeric postcodes', function (): void {
        $zone = TaxZone::create([
            'name' => 'UK Range',
            'code' => 'UK-RANGE',
            'countries' => ['GB'],
            'postcodes' => ['SW1A1-SW1A9'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('GB', null, 'SW1A5'));
        $this->assertFalse($zone->matchesAddress('GB', null, 'SW2A5'));
    });

    it('matches postcode range with zero start and end', function (): void {
        $zone = TaxZone::create([
            'name' => 'Zero Range',
            'code' => 'ZERO-RANGE',
            'countries' => ['TEST'],
            'postcodes' => ['ABC-DEF'],
            'is_active' => true,
        ]);

        $this->assertFalse($zone->matchesAddress('TEST', null, 'ABC'));
        $this->assertFalse($zone->matchesAddress('TEST', null, 'XYZ'));
    });

    it('matches postcode wildcard simple', function (): void {
        $zone = TaxZone::create([
            'name' => 'Wildcard Simple',
            'code' => 'WILD-SIMPLE',
            'countries' => ['MY'],
            'postcodes' => ['43*'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('MY', null, '43000'));
        $this->assertTrue($zone->matchesAddress('MY', null, '43999'));
        $this->assertTrue($zone->matchesAddress('MY', null, '43'));
        $this->assertFalse($zone->matchesAddress('MY', null, '44000'));
        $this->assertFalse($zone->matchesAddress('MY', null, '42999'));
    });

    it('matches postcode wildcard middle', function (): void {
        $zone = TaxZone::create([
            'name' => 'Wildcard Middle',
            'code' => 'WILD-MID',
            'countries' => ['GB'],
            'postcodes' => ['SW*AA'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('GB', null, 'SW1AA'));
        $this->assertTrue($zone->matchesAddress('GB', null, 'SW12AA'));
        $this->assertTrue($zone->matchesAddress('GB', null, 'SWAA'));
        $this->assertFalse($zone->matchesAddress('GB', null, 'SW1AB'));
    });

    it('matches postcode wildcard multiple', function (): void {
        $zone = TaxZone::create([
            'name' => 'Wildcard Multiple',
            'code' => 'WILD-MULTI',
            'countries' => ['TEST'],
            'postcodes' => ['A*B*C'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('TEST', null, 'ABC'));
        $this->assertTrue($zone->matchesAddress('TEST', null, 'A1B2C'));
        $this->assertTrue($zone->matchesAddress('TEST', null, 'AXYZBYC'));
        $this->assertFalse($zone->matchesAddress('TEST', null, 'ABCD'));
    });

    it('matches postcode wildcard empty match', function (): void {
        $zone = TaxZone::create([
            'name' => 'Wildcard Empty',
            'code' => 'WILD-EMPTY',
            'countries' => ['MY'],
            'postcodes' => ['*'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('MY', null, ''));
        $this->assertTrue($zone->matchesAddress('MY', null, '43000'));
        $this->assertTrue($zone->matchesAddress('MY', null, 'ANY'));
    });

    it('matches postcode no match returns false', function (): void {
        $zone = TaxZone::create([
            'name' => 'No Match',
            'code' => 'NO-MATCH',
            'countries' => ['MY'],
            'postcodes' => ['12345'],
            'is_active' => true,
        ]);

        $this->assertFalse($zone->matchesAddress('MY', null, '54321'));
    });

    it('matches postcode multiple patterns', function (): void {
        $zone = TaxZone::create([
            'name' => 'Multiple Patterns',
            'code' => 'MULTI-PAT',
            'countries' => ['MY'],
            'postcodes' => ['40000-40999', '50*', '60000'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('MY', null, '40500'));
        $this->assertTrue($zone->matchesAddress('MY', null, '50123'));
        $this->assertTrue($zone->matchesAddress('MY', null, '60000'));
        $this->assertFalse($zone->matchesAddress('MY', null, '45000'));
        $this->assertFalse($zone->matchesAddress('MY', null, '60001'));
    });

    it('matches address postcode null', function (): void {
        $zone = TaxZone::create([
            'name' => 'Postcode Null Test',
            'code' => 'NULL-POST',
            'countries' => ['MY'],
            'postcodes' => ['43000'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('MY', null, null));
    });

    it('matches address empty postcodes with postcode', function (): void {
        $zone = TaxZone::create([
            'name' => 'Empty Postcodes',
            'code' => 'EMPTY-POST',
            'countries' => ['MY'],
            'postcodes' => [],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('MY', null, '43000'));
        $this->assertTrue($zone->matchesAddress('MY', null, 'ANY'));
    });

    it('matches address null postcodes with postcode', function (): void {
        $zone = TaxZone::create([
            'name' => 'Null Postcodes',
            'code' => 'NULL-POSTS',
            'countries' => ['MY'],
            'postcodes' => null,
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('MY', null, '43000'));
    });

    it('matches postcode with special regex characters', function (): void {
        $zone = TaxZone::create([
            'name' => 'Special Chars',
            'code' => 'SPECIAL',
            'countries' => ['TEST'],
            'postcodes' => ['A.B*'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('TEST', null, 'A.B'));
        $this->assertTrue($zone->matchesAddress('TEST', null, 'A.BXYZ'));
        $this->assertFalse($zone->matchesAddress('TEST', null, 'AXB'));
    });
});
