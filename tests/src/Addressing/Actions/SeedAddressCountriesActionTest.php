<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedAddressCountryReferencesAction;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\AddressingTableResolver;
use AIArmada\CommerceSupport\Actions\SeedCurrenciesAction;
use AIArmada\CommerceSupport\Actions\SeedTimezonesAction;
use AIArmada\CommerceSupport\Models\Currency;
use AIArmada\CommerceSupport\Models\Timezone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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

it('preloads countries once and preserves country identity and creation time on reseed', function (): void {
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $first = $this->action->execute();
        $countrySelects = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(static fn (string $query): bool => str_starts_with(mb_strtolower(mb_ltrim($query)), 'select')
                && str_contains($query, AddressCountry::query()->getModel()->getTable()))
            ->count();
    } finally {
        DB::disableQueryLog();
    }

    $my = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $id = $my->getKey();
    $createdAt = $my->created_at;

    $second = $this->action->execute();
    $reseeded = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    expect($first['created'])->toBeGreaterThan(200)
        ->and($countrySelects)->toBe(1)
        ->and($second['created'])->toBe(0)
        ->and($reseeded->getKey())->toBe($id)
        ->and($reseeded->created_at->equalTo($createdAt))->toBeTrue();
});

it('runs country creating observers during country seeding', function (): void {
    $event = 'eloquent.creating: ' . AddressCountry::class;
    Event::listen($event, static function (AddressCountry $country): void {
        if ($country->iso2 === 'AD') {
            $country->name = 'Observer Andorra';
        }
    });

    try {
        $this->action->execute();
    } finally {
        Event::getFacadeRoot()->forget($event);
    }

    expect(AddressCountry::query()->where('iso2', 'AD')->value('name'))->toBe('Observer Andorra');
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

    DB::table(AddressingTableResolver::resolve('country_timezone_links'))->insert([
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

it('batches country currency and timezone reference inserts', function (): void {
    $this->action->execute();
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        app(SeedAddressCountryReferencesAction::class)->execute();
        app(SeedAddressCountryReferencesAction::class)->execute();
        $queries = collect(DB::getQueryLog())->pluck('query');
        $currencyTable = AddressingTableResolver::resolve('country_currency_links');
        $timezoneTable = AddressingTableResolver::resolve('country_timezone_links');
        $linkInserts = $queries->filter(static fn (string $query): bool => str_starts_with(mb_strtolower(mb_ltrim($query)), 'insert')
            && (str_contains($query, $currencyTable) || str_contains($query, $timezoneTable)));
    } finally {
        DB::disableQueryLog();
    }

    expect($linkInserts)->toHaveCount(4);
});
