<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Ecuador\EcuadorGeographyProvider;

it('uses the INEC formal canton names', function (): void {
    $areas = app(EcuadorGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ec:canton:distrito-metropolitano-de-quito')->name)->toBe('Distrito Metropolitano de Quito')
        ->and($areas->get('ec:canton:san-pedro-de-pelileo')->name)->toBe('San Pedro de Pelileo')
        ->and($areas->get('ec:canton:santiago-de-pillaro')->name)->toBe('Santiago de Píllaro')
        ->and($areas->get('ec:canton:banos-de-agua-santa')->name)->toBe('Baños de Agua Santa')
        ->and($areas->get('ec:canton:san-jacinto-de-yaguachi')->name)->toBe('San Jacinto de Yaguachi')
        ->and($areas->get('ec:canton:santiago')->name)->toBe('Santiago')
        ->and($areas->get('ec:canton:santo-domingo')->name)->toBe('Santo Domingo')
        ->and($areas->get('ec:canton:la-joya-de-los-sachas')->name)->toBe('La Joya de los Sachas')
        ->and($areas->get('ec:canton:puebloviejo')->name)->toBe('Puebloviejo')
        ->and($areas->get('ec:canton:rioverde')->name)->toBe('Rioverde');
});

it('has no short-form canton slugs left', function (): void {
    $areas = app(EcuadorGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    foreach (['ec:canton:quito', 'ec:canton:pelileo', 'ec:canton:pillaro', 'ec:canton:banos', 'ec:canton:yaguachi', 'ec:canton:santiago-de-mendez', 'ec:canton:santo-domingo-de-los-colorados', 'ec:canton:joya-de-los-sachas', 'ec:canton:pueblo-viejo', 'ec:canton:rio-verde', 'ec:canton:borbon'] as $slug) {
        expect($areas->has($slug))->toBeFalse();
    }
});
