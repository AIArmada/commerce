<?php

declare(strict_types=1);

use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('yields linked postcodes with area references and primary flags', function (): void {
    $dir = sys_get_temp_dir() . '/csv-postal-' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/codes.csv', "country_code,code\nSM,47890\nSM,47899\n");
    file_put_contents($dir . '/links.csv', "postcode,area_source_id,relationship_type,is_primary\n47890,sm:municipality:san-marino,served_by,true\n");

    $source = new CsvPostalCodeSource('SM', $dir . '/codes.csv', $dir . '/links.csv', 'aiarmada_addressing_sanmarino_v1');
    $items = $source->postalCodes()->all();

    expect($source->key())->toBe('sm_postal_v1')
        ->and($items)->toHaveCount(2)
        ->and($items[0]->code)->toBe('47890')
        ->and($items[0]->areaSourceId)->toBe('sm:municipality:san-marino')
        ->and($items[0]->isPrimary)->toBeTrue()
        ->and($items[0]->sourceId)->toBe('47890:sm:municipality:san-marino')
        ->and($items[1]->code)->toBe('47899')
        ->and($items[1]->areaSourceId)->toBeNull();

    unlink($dir . '/codes.csv');
    unlink($dir . '/links.csv');
    rmdir($dir);
});

it('skips blank rows and defaults the relationship type', function (): void {
    $dir = sys_get_temp_dir() . '/csv-postal-' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/codes.csv', "country_code,code\n");
    file_put_contents($dir . '/links.csv', "postcode,area_source_id,relationship_type,is_primary\n97133,bl:overseas_collectivity:saint-barthelemy,,true\n,\n");

    $items = (new CsvPostalCodeSource('BL', $dir . '/codes.csv', $dir . '/links.csv', 'aiarmada_addressing_saintbarthelemy_v1'))->postalCodes()->all();

    expect($items)->toHaveCount(1)
        ->and($items[0]->relationshipType)->toBe('served_by');

    unlink($dir . '/codes.csv');
    unlink($dir . '/links.csv');
    rmdir($dir);
});
