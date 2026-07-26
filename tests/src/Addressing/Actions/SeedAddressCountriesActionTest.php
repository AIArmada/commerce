<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedAddressCountryReferencesAction;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\CommerceSupport\Actions\SeedCurrenciesAction;
use AIArmada\CommerceSupport\Actions\SeedTimezonesAction;
use AIArmada\CommerceSupport\Models\Currency;
use AIArmada\CommerceSupport\Models\Timezone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->action = app(SeedAddressCountriesAction::class);
});

it('seeds bundled countries', function (): void {
    $result = $this->action->execute();

    expect($result['created'])->toBeGreaterThan(200);
    expect($result['updated'])->toBe(0);
    expect($result['skipped'])->toBe(0);
});

it('is idempotent', function (): void {
    $first = $this->action->execute();
    $second = $this->action->execute();

    expect($second['created'])->toBe(0);
    expect($second['updated'])->toBe(0);
    expect($second['skipped'])->toBe($first['created']);
});

it('includes MY Malaysia with ISO2 MY and ISO3 MYS', function (): void {
    $this->action->execute();

    $my = AddressCountry::where('iso2', 'MY')->first();

    expect($my)->not->toBeNull();
    expect($my->iso3)->toBe('MYS');
    expect($my->name)->toContain('Malaysia');
});

it('enforces unique ISO3 and numeric country codes', function (): void {
    $this->action->execute();

    expect(fn (): AddressCountry => AddressCountry::query()->create([
        'iso2' => 'ZZ',
        'name' => 'Duplicate ISO3',
        'iso3' => 'MYS',
        'numeric_code' => '999',
    ]))->toThrow(QueryException::class);

    expect(fn (): AddressCountry => AddressCountry::query()->create([
        'iso2' => 'ZY',
        'name' => 'Duplicate Numeric Code',
        'iso3' => 'ZZZ',
        'numeric_code' => '458',
    ]))->toThrow(QueryException::class);
});

it('does not populate calling_codes (dropped from bundled data)', function (): void {
    $this->action->execute();

    $my = AddressCountry::where('iso2', 'MY')->first();

    expect($my->calling_codes)->toBeNull();
});

it('stores reference data outside the country row', function (): void {
    $this->action->execute();
    app(SeedCurrenciesAction::class)->execute();
    app(SeedTimezonesAction::class)->execute();
    app(SeedAddressCountryReferencesAction::class)->execute();

    $my = AddressCountry::where('iso2', 'MY')->first();

    expect(Currency::where('code', 'MYR')->exists())->toBeTrue()
        ->and(Timezone::where('name', 'Asia/Kuala_Lumpur')->exists())->toBeTrue()
        ->and($my->currencies->pluck('code')->all())->toContain('MYR')
        ->and($my->timezones->pluck('name')->all())->toContain('Asia/Kuala_Lumpur');
});

it('rebuilds bundled country reference links without retaining stale rows', function (): void {
    $this->action->execute();

    app(SeedAddressCountryReferencesAction::class)->execute();

    $my = AddressCountry::where('iso2', 'MY')->firstOrFail();
    $tokyo = Timezone::where('name', 'Asia/Tokyo')->firstOrFail();

    DB::table(config('addressing.tables.country_timezone_links'))->insert([
        'id' => (string) str()->uuid(),
        'country_id' => $my->id,
        'timezone_id' => $tokyo->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(SeedAddressCountryReferencesAction::class)->execute();

    expect($my->fresh()->timezones->pluck('name')->all())
        ->not->toContain('Asia/Tokyo');
});
