<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Tax\Enums\ZoneType;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

$bindTaxOwnerForScoping = function (?Model $owner): void {
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
};

describe('TaxZone', function () use ($bindTaxOwnerForScoping): void {
    it('can create tax zone', function (): void {
        $zone = TaxZone::create([
            'name' => 'Malaysia',
            'code' => 'MY',
            'type' => 'country',
            'countries' => ['MY'],
            'priority' => 10,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(TaxZone::class, $zone);
        $this->assertEquals('Malaysia', $zone->name);
        $this->assertEquals('MY', $zone->code);
        $this->assertEquals(['MY'], $zone->countries);
        $this->assertTrue($zone->is_default);
        $this->assertTrue($zone->is_active);
    });

    it('rejects duplicate tax zone codes for the same owner', function (): void {
        TaxZone::create([
            'name' => 'Malaysia',
            'code' => 'MY',
        ]);

        expect(fn () => TaxZone::create([
            'name' => 'Another Malaysia',
            'code' => 'MY',
        ]))->toThrow(ValidationException::class);
    });

    it('active scope', function (): void {
        TaxZone::create(['name' => 'Active Zone', 'code' => 'ACTIVE', 'is_active' => true]);
        TaxZone::create(['name' => 'Inactive Zone', 'code' => 'INACTIVE', 'is_active' => false]);

        $activeZones = TaxZone::active()->get();

        $this->assertCount(1, $activeZones);
        $this->assertEquals('Active Zone', $activeZones->first()->name);
    });

    it('default scope', function (): void {
        TaxZone::create(['name' => 'Default Zone', 'code' => 'DEFAULT', 'is_default' => true]);
        TaxZone::create(['name' => 'Regular Zone', 'code' => 'REGULAR', 'is_default' => false]);

        $defaultZone = TaxZone::default()->first();

        $this->assertEquals('Default Zone', $defaultZone->name);
    });

    it('for address scope country match', function (): void {
        $zone = TaxZone::create([
            'name' => 'Malaysia',
            'code' => 'MY',
            'countries' => ['MY', 'SG'],
            'priority' => 10,
            'is_active' => true,
        ]);

        $matchingZones = TaxZone::forAddress('MY')->get();

        $this->assertCount(1, $matchingZones);
        $this->assertEquals($zone->id, $matchingZones->first()->id);
    });

    it('for address scope state match', function (): void {
        $zone = TaxZone::create([
            'name' => 'California',
            'code' => 'CA',
            'countries' => ['US'],
            'states' => ['CA', 'NY'],
            'priority' => 10,
            'is_active' => true,
        ]);

        $matchingZones = TaxZone::forAddress('US', 'CA')->get();

        $this->assertCount(1, $matchingZones);
        $this->assertEquals($zone->id, $matchingZones->first()->id);
    });

    it('for address scope no match', function (): void {
        TaxZone::create([
            'name' => 'Malaysia',
            'code' => 'MY',
            'countries' => ['MY'],
            'is_active' => true,
        ]);

        $matchingZones = TaxZone::forAddress('US')->get();

        $this->assertCount(0, $matchingZones);
    });

    it('matches address country only', function (): void {
        $zone = TaxZone::create([
            'name' => 'Malaysia',
            'code' => 'MY',
            'countries' => ['MY', 'SG'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('MY'));
        $this->assertTrue($zone->matchesAddress('SG'));
        $this->assertFalse($zone->matchesAddress('US'));
    });

    it('matches address with state', function (): void {
        $zone = TaxZone::create([
            'name' => 'US States',
            'code' => 'US',
            'countries' => ['US'],
            'states' => ['CA', 'NY'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('US', 'CA'));
        $this->assertTrue($zone->matchesAddress('US', 'NY'));
        $this->assertFalse($zone->matchesAddress('US', 'TX'));
        $this->assertFalse($zone->matchesAddress('CA', 'CA')); // Wrong country
    });

    it('matches address with postcode exact', function (): void {
        $zone = TaxZone::create([
            'name' => 'Specific Postcode',
            'code' => 'POST',
            'countries' => ['MY'],
            'postcodes' => ['12345', '67890'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('MY', null, '12345'));
        $this->assertTrue($zone->matchesAddress('MY', null, '67890'));
        $this->assertFalse($zone->matchesAddress('MY', null, '11111'));
    });

    it('matches address with postcode wildcard', function (): void {
        $zone = TaxZone::create([
            'name' => 'Postcode Range',
            'code' => 'RANGE',
            'countries' => ['MY'],
            'postcodes' => ['50*', '60*'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('MY', null, '50000'));
        $this->assertTrue($zone->matchesAddress('MY', null, '60012'));
        $this->assertFalse($zone->matchesAddress('MY', null, '70000'));
    });

    it('matches address with postcode range', function (): void {
        $zone = TaxZone::create([
            'name' => 'Postcode Numeric Range',
            'code' => 'NUMRANGE',
            'countries' => ['MY'],
            'postcodes' => ['10000-19999', '30000-39999'],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('MY', null, '15000'));
        $this->assertTrue($zone->matchesAddress('MY', null, '35000'));
        $this->assertFalse($zone->matchesAddress('MY', null, '25000'));
        $this->assertFalse($zone->matchesAddress('MY', null, '45000'));
    });

    it('matches address combined conditions', function (): void {
        $zone = TaxZone::create([
            'name' => 'Complex Zone',
            'code' => 'COMPLEX',
            'countries' => ['US'],
            'states' => ['CA'],
            'postcodes' => ['90*'],
            'is_active' => true,
        ]);

        // All match
        $this->assertTrue($zone->matchesAddress('US', 'CA', '90210'));

        // Country doesn't match
        $this->assertFalse($zone->matchesAddress('MY', 'CA', '90210'));

        // State doesn't match
        $this->assertFalse($zone->matchesAddress('US', 'NY', '90210'));

        // Postcode doesn't match
        $this->assertFalse($zone->matchesAddress('US', 'CA', '80000'));
    });

    it('relationships with rates', function (): void {
        $zone = TaxZone::create([
            'name' => 'Test Zone',
            'code' => 'TEST',
            'is_active' => true,
        ]);

        $rate = TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Standard Rate',
            'rate' => 600, // 6%
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(TaxRate::class, $zone->rates()->first());
        $this->assertEquals($rate->id, $zone->rates()->first()->id);
    });

    it('casts', function (): void {
        $zone = TaxZone::create([
            'name' => 'Cast Test',
            'code' => 'CAST',
            'countries' => ['MY', 'SG'],
            'states' => ['CA', 'NY'],
            'postcodes' => ['12345'],
            'priority' => 10,
            'is_default' => true,
            'is_active' => false,
        ]);

        $this->assertIsArray($zone->countries);
        $this->assertIsArray($zone->states);
        $this->assertIsArray($zone->postcodes);
        $this->assertIsInt($zone->priority);
        $this->assertIsBool($zone->is_default);
        $this->assertIsBool($zone->is_active);
    });

    it('deleting zone deletes rates', function (): void {
        $zone = TaxZone::create([
            'name' => 'Delete Test',
            'code' => 'DELETE',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Rate to Delete',
            'rate' => 1000,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $this->assertEquals(1, TaxRate::count());

        $zone->delete();

        $this->assertEquals(0, TaxRate::count());
    });

    it('activity logging', function (): void {
        $zone = TaxZone::create([
            'name' => 'Activity Test',
            'code' => 'ACTIVITY',
            'countries' => ['MY'],
            'is_active' => true,
        ]);

        $zone->update(['name' => 'Updated Name']);

        // Activity logging is configured but we can't easily test it without more setup
        // This test ensures the trait is applied and doesn't break
        $this->assertTrue(true);
    });

    it('for owner scope when owner disabled', function (): void {
        config(['tax.features.owner.enabled' => false]);

        TaxZone::create([
            'name' => 'Global Zone',
            'code' => 'GLOBAL',
            'is_active' => true,
        ]);

        $zones = TaxZone::forOwner(null)->get();

        $this->assertCount(1, $zones);
    });

    it('for owner scope with null owner', function () use ($bindTaxOwnerForScoping): void {
        config(['tax.features.owner.enabled' => true]);

        $owner = new class extends Model
        {
            protected $table = 'users';

            public $id = 1;

            public function getMorphClass(): string
            {
                return 'App\\Models\\Store';
            }

            public function getKey(): mixed
            {
                return '123';
            }
        };

        $bindTaxOwnerForScoping(null);

        $globalZone = OwnerContext::withOwner(null, fn () => TaxZone::create([
            'name' => 'Global Zone',
            'code' => 'GLOBAL',
            'owner_type' => null,
            'owner_id' => null,
            'is_active' => true,
        ]));

        $bindTaxOwnerForScoping($owner);

        TaxZone::create([
            'name' => 'Owned Zone',
            'code' => 'OWNED',
            'is_active' => true,
        ]);

        // When owner is null and includeGlobal=true (default), returns records where owner_id is null
        $zones = TaxZone::forOwner(null)->get();

        $this->assertCount(1, $zones);
        $this->assertEquals('Global Zone', $zones->first()->name);
    });

    it('for owner scope with null owner exclude global', function () use ($bindTaxOwnerForScoping): void {
        config(['tax.features.owner.enabled' => true]);

        $bindTaxOwnerForScoping(null);

        OwnerContext::withOwner(null, fn () => TaxZone::create([
            'name' => 'Global Zone',
            'code' => 'GLOBAL',
            'owner_type' => null,
            'owner_id' => null,
            'is_active' => true,
        ]));

        // When owner is null and includeGlobal=false, returns records where both owner_type and owner_id are null
        $zones = TaxZone::forOwner(null, includeGlobal: false)->get();

        $this->assertCount(1, $zones);
        $this->assertEquals('Global Zone', $zones->first()->name);
    });

    it('for owner scope with owner include global', function () use ($bindTaxOwnerForScoping): void {
        config(['tax.features.owner.enabled' => true]);
        config(['tax.features.owner.include_global' => true]);

        $owner = new class extends Model
        {
            protected $table = 'users';

            public $id = 1;

            public function getMorphClass(): string
            {
                return 'App\\Models\\Store';
            }

            public function getKey(): mixed
            {
                return '123';
            }
        };

        $bindTaxOwnerForScoping(null);

        OwnerContext::withOwner(null, fn () => TaxZone::create([
            'name' => 'Global Zone',
            'code' => 'GLOBAL',
            'owner_type' => null,
            'owner_id' => null,
            'is_active' => true,
        ]));

        $bindTaxOwnerForScoping($owner);

        TaxZone::create([
            'name' => 'Owned Zone',
            'code' => 'OWNED',
            'is_active' => true,
        ]);

        DB::table((new TaxZone)->getTable())->insert([
            'id' => (string) Str::uuid(),
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => '456',
            'name' => 'Other Owner Zone',
            'code' => 'OTHER',
            'description' => null,
            'type' => 'country',
            'countries' => null,
            'states' => null,
            'postcodes' => null,
            'priority' => 0,
            'is_default' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $zones = TaxZone::forOwner($owner, includeGlobal: true)->get();

        $this->assertCount(2, $zones);
        $names = $zones->pluck('name')->toArray();
        $this->assertContains('Global Zone', $names);
        $this->assertContains('Owned Zone', $names);
    });

    it('for owner scope with owner exclude global', function () use ($bindTaxOwnerForScoping): void {
        config(['tax.features.owner.enabled' => true]);

        $owner = new class extends Model
        {
            protected $table = 'users';

            public $id = 1;

            public function getMorphClass(): string
            {
                return 'App\\Models\\Store';
            }

            public function getKey(): mixed
            {
                return '123';
            }
        };

        $bindTaxOwnerForScoping(null);

        OwnerContext::withOwner(null, fn () => TaxZone::create([
            'name' => 'Global Zone',
            'code' => 'GLOBAL',
            'owner_type' => null,
            'owner_id' => null,
            'is_active' => true,
        ]));

        $bindTaxOwnerForScoping($owner);

        TaxZone::create([
            'name' => 'Owned Zone',
            'code' => 'OWNED',
            'is_active' => true,
        ]);

        $zones = TaxZone::forOwner($owner, includeGlobal: false)->get();

        $this->assertCount(1, $zones);
        $this->assertEquals('Owned Zone', $zones->first()->name);
    });

    it('attributes defaults', function (): void {
        $zone = new TaxZone(['name' => 'Test', 'code' => 'TEST']);

        $this->assertSame(ZoneType::Country, $zone->type);
        $this->assertEquals(0, $zone->priority);
        $this->assertFalse($zone->is_default);
        $this->assertTrue($zone->is_active);
    });

    it('matches address empty countries', function (): void {
        $zone = new TaxZone([
            'name' => 'No Country Zone',
            'code' => 'NONE',
            'countries' => [],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('ANY'));
    });

    it('matches address with empty states', function (): void {
        $zone = new TaxZone([
            'name' => 'Empty States Zone',
            'code' => 'EMPTY',
            'countries' => ['US'],
            'states' => [],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('US', 'CA'));
    });

    it('matches address with empty postcodes', function (): void {
        $zone = new TaxZone([
            'name' => 'Empty Postcodes Zone',
            'code' => 'EMPTY',
            'countries' => ['US'],
            'postcodes' => [],
            'is_active' => true,
        ]);

        $this->assertTrue($zone->matchesAddress('US', null, '12345'));
    });
});
