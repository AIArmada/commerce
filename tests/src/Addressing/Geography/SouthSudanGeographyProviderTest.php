<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SouthSudan\SouthSudanGeographyProvider;

it('exposes the corrected Jonglei state slug and name', function (): void {
    $areas = app(SouthSudanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ss:state:jonglei')->name)->toBe('Jonglei')
        ->and($areas->get('ss:state:jonglei')->code)->toBe('JG')
        ->and($areas->get('ss:state:jonglei')->type)->toBe('state')
        ->and($areas->has('ss:state:jonglei-state'))->toBeFalse();
});
