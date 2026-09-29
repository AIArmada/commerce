<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Senegal\SenegalGeographyProvider;

it('exposes corrected Senegalese region slugs and names', function (): void {
    $areas = app(SenegalGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('sn:region:diourbel')->name)->toBe('Diourbel')
        ->and($areas->get('sn:region:diourbel')->code)->toBe('DB')
        ->and($areas->get('sn:region:tambacounda')->name)->toBe('Tambacounda')
        ->and($areas->get('sn:region:tambacounda')->code)->toBe('TC')
        ->and($areas->get('sn:region:thies')->name)->toBe('Thiès')
        ->and($areas->get('sn:region:thies')->type)->toBe('region')
        ->and($areas->has('sn:region:diourbel-region'))->toBeFalse()
        ->and($areas->has('sn:region:tambacounda-region'))->toBeFalse()
        ->and($areas->has('sn:region:thies-region'))->toBeFalse();
});
