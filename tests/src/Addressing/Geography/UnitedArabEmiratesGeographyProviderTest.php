<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\UnitedArabEmirates\UnitedArabEmiratesGeographyProvider;
use AIArmada\Addressing\Models\State;

it('pins the verified UAE tree of 7 emirates', function (): void {
    $areas = app(UnitedArabEmiratesGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('type', 'emirate'))->toHaveCount(7)
        ->and($areas->where('level', 1))->toHaveCount(7)
        ->and($areas->pluck('parentSourceId')->filter()->all())->toBe([]);

    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:AE codes.
    expect($byId->get('ae:emirate:ajman')->code)->toBe('AJ')
        ->and($byId->get('ae:emirate:abu-dhabi')->code)->toBe('AZ')
        ->and($byId->get('ae:emirate:dubai')->code)->toBe('DU')
        ->and($byId->get('ae:emirate:fujairah')->code)->toBe('FU')
        ->and($byId->get('ae:emirate:ras-al-khaimah')->code)->toBe('RK')
        ->and($byId->get('ae:emirate:sharjah')->code)->toBe('SH')
        ->and($byId->get('ae:emirate:umm-al-quwain')->code)->toBe('UQ');
});

it('seeds the 7 ISO 3166-2:AE emirates', function (): void {
    $country = $this->seedCountry('AE');

    app(UnitedArabEmiratesGeographyProvider::class)->seed($country);

    $states = State::query()->where('country_id', $country->id)->orderBy('code')->pluck('name', 'code')->all();

    expect($states)->toHaveCount(7)
        ->and($states['AJ'])->toBe('Ajman')
        ->and($states['AZ'])->toBe('Abu Dhabi')
        ->and($states['DU'])->toBe('Dubai')
        ->and($states['FU'])->toBe('Fujairah')
        ->and($states['RK'])->toBe('Ras Al Khaimah')
        ->and($states['SH'])->toBe('Sharjah')
        ->and($states['UQ'])->toBe('Umm Al Quwain');
});
