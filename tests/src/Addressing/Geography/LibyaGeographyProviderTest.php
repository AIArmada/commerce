<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Libya\LibyaGeographyProvider;

it('pins the verified Libya tree of 22 popularates and 100 baladiyas', function (): void {
    $areas = app(LibyaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'popularate'))->toHaveCount(22)
        ->and($areas->where('type', 'baladiya'))->toHaveCount(100)
        ->and($areas->where('parentSourceId', 'ly:popularate:jabal-al-gharbi'))->toHaveCount(14)
        ->and($areas->where('parentSourceId', 'ly:popularate:jafara'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'ly:popularate:nalut'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'ly:popularate:tripoli'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'ly:popularate:al-wahat'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'ly:popularate:nuqat-al-khams'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'ly:popularate:derna'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'ly:popularate:ghat'))->toHaveCount(1);

    // ISO 3166-2:LY codes; IOM DTM / OCHA COD operational 100-set:
    // Tajoura p-code anchor, Bani Waleed under Misrata, and the
    // four renamed mantika mappings (Tobruk->Al Butnan,
    // Ejdabia->Al Wahat, Zwara->Nuqat al Khams, Ubari->Wadi al
    // Hayaa).
    expect($byId->get('ly:popularate:al-wahat')->code)->toBe('WA')
        ->and($byId->get('ly:popularate:tripoli')->code)->toBe('TB')
        ->and($byId->get('ly:popularate:wadi-al-shatii')->code)->toBe('WS')
        ->and($byId->get('ly:baladiya:tajoura')->code)->toBe('LY021102')
        ->and($byId->get('ly:baladiya:tajoura')->parentSourceId)->toBe('ly:popularate:tripoli')
        ->and($byId->get('ly:baladiya:bani-waleed')->parentSourceId)->toBe('ly:popularate:misrata')
        ->and($byId->get('ly:baladiya:tobruk')->parentSourceId)->toBe('ly:popularate:al-butnan')
        ->and($byId->get('ly:baladiya:ejdabia')->parentSourceId)->toBe('ly:popularate:al-wahat')
        ->and($byId->get('ly:baladiya:zwara')->parentSourceId)->toBe('ly:popularate:nuqat-al-khams')
        ->and($byId->get('ly:baladiya:ubari')->parentSourceId)->toBe('ly:popularate:wadi-al-hayaa');
});
