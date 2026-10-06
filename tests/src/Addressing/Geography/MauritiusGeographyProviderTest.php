<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Mauritius\MauritiusGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 9 districts plus 3 dependencies with 142 places', function (): void {
    $areas = app(MauritiusGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($areas->where('level', 1))->toHaveCount(12)
        ->and($l2)->toHaveCount(142)
        // ISO 3166-2:MU district + dependency codes.
        ->and($byId->get('mu:district:black-river')->code)->toBe('BL')
        ->and($byId->get('mu:district:flacq')->code)->toBe('FL')
        ->and($byId->get('mu:district:grand-port')->code)->toBe('GP')
        ->and($byId->get('mu:district:moka')->code)->toBe('MO')
        ->and($byId->get('mu:district:pamplemousses')->code)->toBe('PA')
        ->and($byId->get('mu:district:plaines-wilhems')->code)->toBe('PW')
        ->and($byId->get('mu:district:port-louis')->code)->toBe('PL')
        ->and($byId->get('mu:district:riviere-du-rempart')->code)->toBe('RR')
        ->and($byId->get('mu:district:savanne')->code)->toBe('SA')
        ->and($byId->get('mu:dependency:agalega-islands')->code)->toBe('AG')
        ->and($byId->get('mu:dependency:rodrigues-island')->code)->toBe('RO')
        ->and($byId->get('mu:dependency:saint-brandon-islands')->code)->toBe('CC')
        // Per-district place counts (WP places table, zero-diff).
        ->and($l2->where('parentSourceId', 'mu:district:port-louis'))->toHaveCount(2)
        ->and($l2->where('parentSourceId', 'mu:district:plaines-wilhems'))->toHaveCount(6)
        ->and($l2->where('parentSourceId', 'mu:district:black-river'))->toHaveCount(13)
        ->and($l2->where('parentSourceId', 'mu:district:riviere-du-rempart'))->toHaveCount(20)
        ->and($l2->where('parentSourceId', 'mu:district:pamplemousses'))->toHaveCount(20)
        ->and($l2->where('parentSourceId', 'mu:district:savanne'))->toHaveCount(14)
        ->and($l2->where('parentSourceId', 'mu:district:grand-port'))->toHaveCount(25)
        ->and($l2->where('parentSourceId', 'mu:district:flacq'))->toHaveCount(25)
        ->and($l2->where('parentSourceId', 'mu:district:moka'))->toHaveCount(14)
        ->and($l2->where('parentSourceId', 'mu:dependency:agalega-islands'))->toHaveCount(3)
        // Urban grain + Agalega trio (Agaléga article).
        ->and($byId->get('mu:city:port-louis')->name)->toBe('Port Louis')
        ->and($byId->get('mu:town:curepipe')->parentSourceId)->toBe('mu:district:plaines-wilhems')
        ->and($byId->get('mu:village:vingt-cinq')->parentSourceId)->toBe('mu:dependency:agalega-islands')
        ->and($byId->get('mu:village:belle-vue-haurel')->name)->toBe('Belle Vue Haurel');
});

it('links 1990 postcodes with zero multis and Rodrigues at the dependency', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MU', $dir . '/mauritius-postal-codes.csv', $dir . '/mauritius-postal-code-areas.csv', 'aiarmada.addressing.mauritius');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(1990)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(1990)
        ->and($postcodes->groupBy->code->filter(fn ($legs) => $legs->count() > 1))->toHaveCount(0);

    $byCode = $postcodes->groupBy->code;

    // UPU anchors: Port Louis 11213, Lalmatie 42602.
    expect($byCode->get('11213')->sole()->areaSourceId)->toBe('mu:city:port-louis')
        ->and($byCode->get('42602')->sole()->areaSourceId)->toBe('mu:village:lalmatie')
        // Rodrigues R-codes resolve at the dependency (no village grain bundled).
        ->and($byCode->filter(fn ($legs, $code) => str_starts_with((string) $code, 'R')))->toHaveCount(182)
        ->and($byCode->get('R1301')->sole()->areaSourceId)->toBe('mu:dependency:rodrigues-island')
        // Agalega A-codes: 3 villages + 1 dependency leg.
        ->and($byCode->get('A1101')->sole()->areaSourceId)->toBe('mu:village:la-fourche')
        ->and($byCode->get('A1102')->sole()->areaSourceId)->toBe('mu:dependency:agalega-islands')
        // Cross-block border villages keep their whole-village ranges.
        ->and($byCode->get('30101')->sole()->areaSourceId)->toBe('mu:village:belle-vue-haurel')
        ->and($byCode->get('61401')->sole()->areaSourceId)->toBe('mu:village:l-escalier');
});
