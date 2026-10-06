<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SierraLeone\SierraLeoneGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('ships 16 districts under the 4 provinces and Western Area', function (): void {
    $areas = app(SierraLeoneGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(16)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'sl:province:eastern'))->toHaveCount(3)
        ->and($l2->where('parentSourceId', 'sl:province:northern'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'sl:province:north-western'))->toHaveCount(3)
        ->and($l2->where('parentSourceId', 'sl:province:southern'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'sl:area:western'))->toHaveCount(2)
        ->and($byId->get('sl:district:falaba')->parentSourceId)->toBe('sl:province:northern')
        ->and($byId->get('sl:district:karene')->parentSourceId)->toBe('sl:province:north-western')
        ->and($byId->get('sl:district:western-urban')->parentSourceId)->toBe('sl:area:western');
});

it('seeds the ISO 3166-2:SL provinces and area', function (): void {
    $country = $this->seedCountry('SL');

    app(SierraLeoneGeographyProvider::class)->seed($country);

    $states = State::query()->where('country_id', $country->id)->orderBy('code')->pluck('name', 'code')->all();

    expect($states)->toHaveCount(5)
        ->and($states['E'])->toBe('Eastern')
        ->and($states['NW'])->toBe('North Western')
        ->and($states['N'])->toBe('Northern')
        ->and($states['S'])->toBe('Southern')
        ->and($states['W'])->toBe('Western');
});

it('pins the verified district names and official Western long forms', function (): void {
    $byId = app(SierraLeoneGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // Short forms match the Districts-table display labels; Stats SL long
    // forms ship as alternative names (see below).
    expect($byId->get('sl:district:port-loko')->name)->toBe('Port Loko')
        ->and($byId->get('sl:district:koinadugu')->name)->toBe('Koinadugu')
        ->and($byId->get('sl:district:pujehun')->name)->toBe('Pujehun')
        ->and($byId->get('sl:district:western-rural')->name)->toBe('Western Rural')
        ->and($byId->get('sl:district:western-urban')->name)->toBe('Western Urban');

    $names = app(SierraLeoneGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['sl:district:western-rural'])->toContain(
        ['name' => 'Western Area Rural', 'name_type' => 'alternative'],
    )->and($names['sl:district:western-urban'])->toContain(
        ['name' => 'Western Area Urban', 'name_type' => 'alternative'],
    )->and($names['sl:province:north-western'])->toContain(
        ['name' => 'North West', 'name_type' => 'alternative'],
    )->and($names['sl:area:western'])->toContain(
        ['name' => 'Western Area', 'name_type' => 'alternative'],
    );
});
