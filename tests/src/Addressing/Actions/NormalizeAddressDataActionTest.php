<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\AddressNormalizer;
use AIArmada\Addressing\Features\Geography\Models\AddressCountry;
use AIArmada\Addressing\Features\Geography\Models\City;
use AIArmada\Addressing\Features\Geography\Models\State;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seedCountry('US');

    $country = AddressCountry::query()->where('iso2', 'US')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->getKey(),
        'name' => 'New York',
        'country_code' => 'US',
        'code' => 'NY',
    ]);

    $this->city = City::query()->create([
        'country_id' => $country->getKey(),
        'state_id' => $state->getKey(),
        'name' => 'New York City',
        'country_code' => 'US',
        'state_code' => 'NY',
    ]);
});

it('resolves free-text geography to canonical country state and city ids', function (): void {
    $address = app(AddressNormalizer::class)->normalize([
        'country_code' => ' us ',
        'state' => ' new york ',
        'city' => ' new york city ',
    ]);

    expect($address->countryId)->not->toBeNull()
        ->and($address->stateId)->not->toBeNull()
        ->and($address->cityId)->toBe($this->city->getKey())
        ->and($address->country)->toBe('United States')
        ->and($address->state)->toBe('New York')
        ->and($address->city)->toBe('New York City')
        ->and($address->countryCode)->toBe('US');
});

it('resolves a country name before resolving its free-text geography', function (): void {
    $address = app(AddressNormalizer::class)->normalize([
        'country' => ' United States ',
        'state' => 'New York',
        'city' => 'New York City',
    ]);

    expect($address->countryId)->not->toBeNull()
        ->and($address->cityId)->toBe($this->city->getKey())
        ->and($address->countryCode)->toBe('US');
});

it('reuses explicit reference lookups within the request', function (): void {
    $normalizer = app(AddressNormalizer::class);
    $payload = [
        'country_id' => $this->city->country_id,
        'state_id' => $this->city->state_id,
        'city_id' => $this->city->getKey(),
    ];
    $queries = [];

    DB::listen(static function (QueryExecuted $query) use (&$queries): void {
        $sql = mb_strtolower($query->sql);

        if (str_starts_with($sql, 'select') && preg_match('/from "(countries|states|cities)"/', $sql) === 1) {
            $queries[] = $sql;
        }
    });

    $normalizer->normalize($payload);
    $normalizer->normalize($payload);

    expect($queries)->toHaveCount(3);
});

it('rejects an explicit city linked to a different state', function (): void {
    $otherState = State::query()->create([
        'country_id' => $this->city->country_id,
        'name' => 'California',
        'country_code' => 'US',
        'code' => 'CA',
    ]);

    expect(fn (): mixed => app(AddressNormalizer::class)->normalize([
        'country_id' => $this->city->country_id,
        'state_id' => $otherState->getKey(),
        'city_id' => $this->city->getKey(),
    ]))->toThrow(InvalidArgumentException::class, 'does not belong to the selected state');
});
