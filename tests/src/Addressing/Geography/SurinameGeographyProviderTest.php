<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Suriname\SurinameGeographyProvider;

it('pins the verified Suriname tree of 10 districts and 63 resorts', function (): void {
    $areas = app(SurinameGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(10)
        ->and($areas->where('type', 'resort'))->toHaveCount(63)
        ->and($areas->where('parentSourceId', 'sr:district:paramaribo'))->toHaveCount(12)
        ->and($areas->where('parentSourceId', 'sr:district:sipaliwini'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'sr:district:wanica'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'sr:district:brokopondo'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'sr:district:para'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'sr:district:coronie'))->toHaveCount(3);

    // ISO 3166-2:SR codes; Resorts-of-Suriname oracle: two Centrums
    // (Brokopondo + Paramaribo) and the comma-carrying Para, Zuid.
    expect($byId->get('sr:district:nickerie')->code)->toBe('NI')
        ->and($byId->get('sr:district:sipaliwini')->code)->toBe('SI')
        ->and($byId->get('sr:resort:centrum')->parentSourceId)->toBe('sr:district:brokopondo')
        ->and($byId->get('sr:resort:paramaribo:centrum')->parentSourceId)->toBe('sr:district:paramaribo')
        ->and($byId->get('sr:resort:para-zuid')->name)->toBe('Para, Zuid');
});
