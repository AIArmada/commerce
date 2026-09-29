<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Guam\GuamGeographyProvider;

it('leads renamed villages with the official Chamorro name', function (): void {
    $areas = app(GuamGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas)->toHaveCount(19)
        ->and($areas->get('gu:village:hagat')->name)->toBe('Hågat (Agat)')
        ->and($areas->get('gu:village:inarajan-inalahan')->name)->toBe('Inalåhan (Inarajan)')
        ->and($areas->get('gu:village:merizo-malesso')->name)->toBe('Malesso\' (Merizo)')
        ->and($areas->get('gu:village:santa-rita-santa-rita-sumai')->name)->toBe('Sånta Rita-Sumai (Santa Rita)')
        ->and($areas->get('gu:village:talofofo-talofofo')->name)->toBe('Talo\'fo\'fo (Talofofo)')
        ->and($areas->get('gu:village:umatac-humatak')->name)->toBe('Humåtak (Umatac)');
});
