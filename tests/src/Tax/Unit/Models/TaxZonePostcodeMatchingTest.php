<?php

declare(strict_types=1);

namespace AIArmada\Tax\Tests\Unit\Models;

use AIArmada\Commerce\Tests\Tax\TaxTestCase;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaxZonePostcodeMatchingTest extends TaxTestCase
{
    use RefreshDatabase;

    public function test_matches_postcode_exact(): void
    {
        $zone = TaxZone::create([
            'name' => 'Exact Postcode',
            'code' => 'EXACT',
            'countries' => ['MY'],
            'postcodes' => ['43000'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('MY', null, '43000'));
        $this->assertFalse($zone->matchesAddress('MY', null, '43001'));
    }

    public function test_matches_postcode_range_numeric(): void
    {
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
    }

    public function test_matches_postcode_range_with_non_numeric_postcodes(): void
    {
        $zone = TaxZone::create([
            'name' => 'UK Range',
            'code' => 'UK-RANGE',
            'countries' => ['GB'],
            'postcodes' => ['SW1A1-SW1A9'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('GB', null, 'SW1A5'));
        $this->assertFalse($zone->matchesAddress('GB', null, 'SW2A5'));
    }

    public function test_matches_postcode_range_with_zero_start_and_end(): void
    {
        $zone = TaxZone::create([
            'name' => 'Zero Range',
            'code' => 'ZERO-RANGE',
            'countries' => ['TEST'],
            'postcodes' => ['ABC-DEF'],
            'is_active' => true,
        ]);

        $this->assertFalse($zone->matchesAddress('TEST', null, 'ABC'));
        $this->assertFalse($zone->matchesAddress('TEST', null, 'XYZ'));
    }

    public function test_matches_postcode_wildcard_simple(): void
    {
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
    }

    public function test_matches_postcode_wildcard_middle(): void
    {
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
    }

    public function test_matches_postcode_wildcard_multiple(): void
    {
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
    }

    public function test_matches_postcode_wildcard_empty_match(): void
    {
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
    }

    public function test_matches_postcode_no_match_returns_false(): void
    {
        $zone = TaxZone::create([
            'name' => 'No Match',
            'code' => 'NO-MATCH',
            'countries' => ['MY'],
            'postcodes' => ['12345'],
            'is_active' => true,
        ]);

        $this->assertFalse($zone->matchesAddress('MY', null, '54321'));
    }

    public function test_matches_postcode_multiple_patterns(): void
    {
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
    }

    public function test_matches_address_postcode_null(): void
    {
        $zone = TaxZone::create([
            'name' => 'Postcode Null Test',
            'code' => 'NULL-POST',
            'countries' => ['MY'],
            'postcodes' => ['43000'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('MY', null, null));
    }

    public function test_matches_address_empty_postcodes_with_postcode(): void
    {
        $zone = TaxZone::create([
            'name' => 'Empty Postcodes',
            'code' => 'EMPTY-POST',
            'countries' => ['MY'],
            'postcodes' => [],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('MY', null, '43000'));
        $this->assertTrue($zone->matchesAddress('MY', null, 'ANY'));
    }

    public function test_matches_address_null_postcodes_with_postcode(): void
    {
        $zone = TaxZone::create([
            'name' => 'Null Postcodes',
            'code' => 'NULL-POSTS',
            'countries' => ['MY'],
            'postcodes' => null,
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('MY', null, '43000'));
    }

    public function test_matches_postcode_with_special_regex_characters(): void
    {
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
    }
}
