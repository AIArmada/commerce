<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\LogAddressResolutionGapAction;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\Addressable;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\City;
use AIArmada\Addressing\Models\ResolutionGap;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\AddressingTableResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('does not link an ambiguous city name when no state is given', function (): void {
    $country = AddressCountry::query()->create(['iso2' => 'ZZ', 'name' => 'Zedland']);
    $north = State::query()->create(['country_id' => $country->id, 'name' => 'North', 'code' => 'N']);
    $south = State::query()->create(['country_id' => $country->id, 'name' => 'South', 'code' => 'S']);

    City::query()->create(['country_id' => $country->id, 'state_id' => $north->id, 'name' => 'Springfield']);
    $southCity = City::query()->create(['country_id' => $country->id, 'state_id' => $south->id, 'name' => 'Springfield']);

    $ambiguous = Address::query()->create([
        'line1' => '1 Main St',
        'city' => 'Springfield',
        'country_code' => 'ZZ',
    ])->fresh();

    expect($ambiguous->city_id)->toBeNull()
        ->and($ambiguous->city)->toBe('Springfield');

    $scoped = Address::query()->create([
        'line1' => '2 Main St',
        'city' => 'Springfield',
        'state' => 'South',
        'country_code' => 'ZZ',
    ])->fresh();

    expect($scoped->city_id)->toBe($southCity->id);
});

it('restricts deleting a referenced country and detaches links otherwise', function (): void {
    $referenced = AddressCountry::query()->create(['iso2' => 'ZZ', 'name' => 'Zedland']);
    State::query()->create(['country_id' => $referenced->id, 'name' => 'North', 'code' => 'N']);

    expect(fn (): ?bool => $referenced->delete())->toThrow(DomainException::class);

    $free = AddressCountry::query()->create(['iso2' => 'ZY', 'name' => 'Freeland']);

    expect($free->delete())->toBeTrue()
        ->and(AddressCountry::query()->whereKey($free->id)->exists())->toBeFalse();
});

it('rejects duplicate addressable pivots and second primaries at the database', function (): void {
    $host = Address::query()->create(['line1' => 'Host', 'country_code' => 'MY']);
    $address = Address::query()->create(['line1' => 'One', 'country_code' => 'MY']);
    $other = Address::query()->create(['line1' => 'Two', 'country_code' => 'MY']);

    $attributes = [
        'address_id' => $address->id,
        'addressable_type' => $host->getMorphClass(),
        'addressable_id' => $host->getKey(),
        'type' => 'shipping',
        'is_primary' => true,
    ];

    Addressable::query()->create($attributes);

    expect(fn (): mixed => Addressable::query()->create($attributes))
        ->toThrow(QueryException::class);

    expect(fn (): mixed => Addressable::query()->create([
        ...$attributes,
        'address_id' => $other->id,
    ]))->toThrow(QueryException::class);
});

it('dedupes global resolution gaps through the dedupe hash', function (): void {
    $action = app(LogAddressResolutionGapAction::class);

    OwnerContext::withOwner(null, function () use ($action): void {
        $action->execute('search', 'MY', 'area', 'Nowhere Town');
        $action->execute('search', 'MY', 'area', 'Nowhere Town');
    });

    $gaps = OwnerContext::withOwner(
        null,
        fn (): mixed => ResolutionGap::query()->where('normalized', 'nowhere town')->get(),
    );

    expect($gaps)->toHaveCount(1)
        ->and($gaps->first()->hits)->toBe(2)
        ->and($gaps->first()->dedupe_hash)->not->toBe('');
});

it('creates functional lower indexes for name lookups', function (): void {
    $driver = DB::connection()->getDriverName();

    if ($driver !== 'sqlite') {
        $this->markTestSkipped('Functional index assertion is SQLite-specific.');
    }

    $sql = DB::selectOne(
        "SELECT sql FROM sqlite_master WHERE type = 'index' AND name = 'countries_name_lower_index'",
    );

    expect($sql?->sql)->toContain('LOWER');
});

it('no longer persists dropped address columns', function (): void {
    $table = AddressingTableResolver::resolve('addresses');

    expect(Schema::hasColumn($table, 'validation_status'))->toBeFalse()
        ->and(Schema::hasColumn($table, 'validated_at'))->toBeFalse()
        ->and(Schema::hasColumn($table, 'building_name'))->toBeFalse()
        ->and(Schema::hasColumn($table, 'raw_address'))->toBeFalse()
        ->and(Schema::hasColumn($table, 'geohash'))->toBeFalse();
});

it('creates addresses and reference rows through factories', function (): void {
    expect(Address::factory()->create())->toBeInstanceOf(Address::class)
        ->and(AddressCountry::factory()->create())->toBeInstanceOf(AddressCountry::class)
        ->and(State::factory()->create())->toBeInstanceOf(State::class)
        ->and(City::factory()->create())->toBeInstanceOf(City::class);
});
