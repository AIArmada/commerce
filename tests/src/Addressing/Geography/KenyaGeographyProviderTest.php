<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Kenya\KenyaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 47 ISO counties with 290 constituencies under county parents', function (): void {
    $areas = app(KenyaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(337)
        ->and($l1)->toHaveCount(47)
        ->and($l2)->toHaveCount(290)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    // ISO 3166-2:KE spot checks (B18: 47/47 codes+names exact).
    expect($byId->get('ke:county:nairobi-city')->code)->toBe('30')
        ->and($byId->get('ke:county:nairobi-city')->name)->toBe('Nairobi City')
        ->and($byId->get('ke:county:mombasa')->code)->toBe('28')
        ->and($byId->get('ke:county:murang-a')->code)->toBe('29')
        ->and($byId->get('ke:county:murang-a')->name)->toBe("Murang'a")
        ->and($byId->get('ke:county:elgeyo-marakwet')->code)->toBe('05')
        ->and($byId->get('ke:county:west-pokot')->code)->toBe('47');

    // Constituency parents (B18: 290/290 vs the numbered 1-290 roster).
    expect($byId->get('ke:constituency:changamwe')->parentSourceId)->toBe('ke:county:mombasa')
        ->and($byId->get('ke:constituency:kiambaa')->parentSourceId)->toBe('ke:county:kiambu')
        ->and($byId->get('ke:constituency:gatundu-south')->parentSourceId)->toBe('ke:county:kiambu');
});

it('pins the B18-verified 28 fills, 26 moves, holds, and anchors', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('KE', $dir . '/kenya-postal-codes.csv', $dir . '/kenya-postal-code-areas.csv', 'aiarmada.addressing.kenya');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(977);

    $byCode = $postcodes->groupBy->code;

    // Nairobi office fills.
    foreach (['00514', '00617', '00624'] as $code) {
        expect($byCode->get($code)->first()->areaSourceId)->toBe('ke:county:nairobi-city');
    }

    // Up-country fills (Murang'a, Nyeri run, Siaya run, Kakamega run, Kitui).
    expect($byCode->get('01026')->first()->areaSourceId)->toBe('ke:county:murang-a')
        ->and($byCode->get('10110')->first()->areaSourceId)->toBe('ke:county:murang-a')
        ->and($byCode->get('10225')->first()->areaSourceId)->toBe('ke:county:murang-a')
        ->and($byCode->get('20155')->first()->areaSourceId)->toBe('ke:county:nyandarua')
        ->and($byCode->get('30127')->first()->areaSourceId)->toBe('ke:county:uasin-gishu')
        ->and($byCode->get('30220')->first()->areaSourceId)->toBe('ke:county:bungoma')
        ->and($byCode->get('40130')->first()->areaSourceId)->toBe('ke:county:kisumu')
        ->and($byCode->get('50427')->first()->areaSourceId)->toBe('ke:county:busia')
        ->and($byCode->get('90201')->first()->areaSourceId)->toBe('ke:county:kitui')
        ->and($byCode->get('90409')->first()->areaSourceId)->toBe('ke:county:kitui');
    foreach (['10134', '10135', '10137', '10138'] as $code) {
        expect($byCode->get($code)->first()->areaSourceId)->toBe('ke:county:nyeri');
    }
    foreach (['40226', '40324', '40327'] as $code) {
        expect($byCode->get($code)->first()->areaSourceId)->toBe('ke:county:homa-bay');
    }
    foreach (['40636', '40637', '40638', '40642', '40643'] as $code) {
        expect($byCode->get($code)->first()->areaSourceId)->toBe('ke:county:siaya');
    }
    foreach (['50129', '50130', '50131'] as $code) {
        expect($byCode->get($code)->first()->areaSourceId)->toBe('ke:county:kakamega');
    }

    // Odd-leg moves off wrong counties.
    expect($byCode->get('01102')->first()->areaSourceId)->toBe('ke:county:kajiado')
        ->and($byCode->get('10311')->first()->areaSourceId)->toBe('ke:county:kirinyaga')
        ->and($byCode->get('20420')->first()->areaSourceId)->toBe('ke:county:bomet')
        ->and($byCode->get('30711')->first()->areaSourceId)->toBe('ke:county:elgeyo-marakwet')
        ->and($byCode->get('90148')->first()->areaSourceId)->toBe('ke:county:machakos');

    // Nairobi-fallback moves (town sits up-country).
    expect($byCode->get('10224')->first()->areaSourceId)->toBe('ke:county:murang-a')
        ->and($byCode->get('30703')->first()->areaSourceId)->toBe('ke:county:elgeyo-marakwet')
        ->and($byCode->get('40312')->first()->areaSourceId)->toBe('ke:county:homa-bay')
        ->and($byCode->get('50301')->first()->areaSourceId)->toBe('ke:county:vihiga')
        ->and($byCode->get('60217')->first()->areaSourceId)->toBe('ke:county:meru')
        ->and($byCode->get('80312')->first()->areaSourceId)->toBe('ke:county:taita-taveta')
        ->and($byCode->get('90216')->first()->areaSourceId)->toBe('ke:county:kitui');

    // Holds: ghost/unattributable codes stay out; unattributable Nairobi keeps stay.
    expect($byCode->has('60210'))->toBeFalse()
        ->and($byCode->has('80204'))->toBeFalse()
        ->and($byCode->has('00127'))->toBeFalse()
        ->and($byCode->get('01029')->first()->areaSourceId)->toBe('ke:county:nairobi-city')
        ->and($byCode->get('30216')->first()->areaSourceId)->toBe('ke:county:nairobi-city')
        ->and($byCode->get('90149')->first()->areaSourceId)->toBe('ke:county:nairobi-city');

    // Anchors.
    expect($byCode->get('00100')->first()->areaSourceId)->toBe('ke:county:nairobi-city')
        ->and($byCode->get('80100')->first()->areaSourceId)->toBe('ke:county:mombasa')
        ->and($byCode->get('20100')->first()->areaSourceId)->toBe('ke:county:nakuru');
});
