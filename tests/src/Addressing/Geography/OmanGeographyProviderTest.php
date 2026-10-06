<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Oman\OmanGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 63 wilayats under governorates with parent links', function (): void {
    $areas = app(OmanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'wilayat');

    expect($l2)->toHaveCount(63)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('om:wilayat:adam')->name)->toBe('Adam')
        ->and($byId->get('om:wilayat:al-hamra')->parentSourceId)->toBe('om:governorate:ad-dakhiliyah');
});

it('pins the verified Oman tree of 11 governorates and 63 wilayats', function (): void {
    $areas = app(OmanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'governorate'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'om:governorate:ad-dakhiliyah'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'om:governorate:ad-dhahirah'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'om:governorate:al-batinah-north'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'om:governorate:al-batinah-south'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'om:governorate:al-buraimi'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'om:governorate:al-wusta'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'om:governorate:ash-sharqiyah-north'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'om:governorate:ash-sharqiyah-south'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'om:governorate:dhofar'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'om:governorate:muscat'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'om:governorate:musandam'))->toHaveCount(4);

    // ISO 3166-2:OM codes; post-2022 wilayats under their current parents;
    // Buraimi holds the 2006-split trio while Dhahirah keeps Ibri/Yanqul/Dhank.
    expect($byId->get('om:governorate:muscat')->code)->toBe('MA')
        ->and($byId->get('om:wilayat:jebel-akhdar')->parentSourceId)->toBe('om:governorate:ad-dakhiliyah')
        ->and($byId->get('om:wilayat:sinaw')->parentSourceId)->toBe('om:governorate:ash-sharqiyah-north')
        ->and($byId->get('om:wilayat:as-sunaynah')->parentSourceId)->toBe('om:governorate:al-buraimi')
        ->and($byId->get('om:wilayat:mahdah')->parentSourceId)->toBe('om:governorate:al-buraimi')
        ->and($byId->get('om:wilayat:dhank')->parentSourceId)->toBe('om:governorate:ad-dhahirah');
});

it('bundles the verified 99-code overlay with exact wilayat primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('OM', $dir . '/oman-postal-codes.csv', $dir . '/oman-postal-code-areas.csv', 'aiarmada.addressing.oman');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(99)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(99)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(99)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{3}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // UPU 01/2026 anchors: Ruwi 112, Al Khuwair 133, Sohar 311.
    expect($primary('112'))->toBe('om:wilayat:muttrah')
        ->and($primary('133'))->toBe('om:wilayat:bawshar')
        ->and($primary('311'))->toBe('om:wilayat:sohar');

    // Muscat locality resolutions: Central PO sits at Airport Heights (Seeb),
    // Hay Al Mina at Port Sultan Qaboos (Muttrah); Wattayah adjudicated
    // Muttrah over the OSM Bawshar polygon (see gate carry-forward).
    expect($primary('111'))->toBe('om:wilayat:seeb')
        ->and($primary('114'))->toBe('om:wilayat:muttrah')
        ->and($primary('115'))->toBe('om:wilayat:bawshar')
        ->and($primary('116'))->toBe('om:wilayat:bawshar')
        ->and($primary('117'))->toBe('om:wilayat:muttrah')
        ->and($primary('118'))->toBe('om:wilayat:bawshar')
        ->and($primary('127'))->toBe('om:wilayat:muttrah')
        ->and($primary('131'))->toBe('om:wilayat:muttrah');

    // Village resolutions: census locality + OSM agree; 423/424 split beats
    // the YBK 423-dupe; 517/518 follow current Buraimi admin over the stale
    // Parcelforce region label; Lizugh sits in Samail, not Nizwa.
    expect($primary('219'))->toBe('om:wilayat:taqah')
        ->and($primary('221'))->toBe('om:wilayat:mirbat')
        ->and($primary('316'))->toBe('om:wilayat:suwayq')
        ->and($primary('329'))->toBe('om:wilayat:saham')
        ->and($primary('419'))->toBe('om:wilayat:al-qabil')
        ->and($primary('423'))->toBe('om:wilayat:al-mudhaibi')
        ->and($primary('424'))->toBe('om:wilayat:dema-wa-thaieen')
        ->and($primary('517'))->toBe('om:wilayat:as-sunaynah')
        ->and($primary('615'))->toBe('om:wilayat:samail')
        ->and($primary('621'))->toBe('om:wilayat:jebel-akhdar');

    // Five wilayats are codeless in every directory: no Duqm/Mahout office
    // rows, no Mazyona/Shalim/Wadi-Al-Maawil allocation anywhere.
    expect($postcodes->where('areaSourceId', 'om:wilayat:duqm'))->toHaveCount(0)
        ->and($postcodes->where('areaSourceId', 'om:wilayat:mahout'))->toHaveCount(0)
        ->and($postcodes->where('areaSourceId', 'om:wilayat:al-mazyona'))->toHaveCount(0)
        ->and($postcodes->where('areaSourceId', 'om:wilayat:shalim-and-the-hallaniyat-islands'))->toHaveCount(0)
        ->and($postcodes->where('areaSourceId', 'om:wilayat:wadi-al-maawil'))->toHaveCount(0);
});
