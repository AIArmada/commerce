<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Slovenia\SloveniaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('exposes corrected Slovenian municipality names', function (): void {
    $areas = app(SloveniaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('si:municipality:dobrovapolhov-gradec')->name)->toBe('Dobrova-Polhov Gradec')
        ->and($areas->get('si:municipality:dobrovapolhov-gradec')->code)->toBe('021')
        ->and($areas->get('si:municipality:miklavz-na-dravskem-polju')->name)->toBe('Miklavž na Dravskem polju')
        ->and($areas->get('si:municipality:miklavz-na-dravskem-polju')->code)->toBe('169')
        ->and($areas->get('si:municipality:sveti-jurij-v-slovenskih-goricah')->name)->toBe('Sveti Jurij v Slovenskih goricah')
        ->and($areas->get('si:municipality:sveti-jurij-v-slovenskih-goricah')->type)->toBe('municipality');
});

it('roles urban municipalities with the municipality selector', function (): void {
    $roles = app(SloveniaGeographyProvider::class)->areaRoles(new AddressCountry);

    expect($roles['si:urban_municipality:celje'][0]['role'])->toBe('municipality');
});
