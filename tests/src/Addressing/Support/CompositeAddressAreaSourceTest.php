<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use AIArmada\Addressing\Support\CompositeAddressAreaSource;

it('streams every source in order under the first source key', function (): void {
    $composite = new CompositeAddressAreaSource([
        new ArrayAddressAreaSource('one', [
            new AddressAreaData(source: 'one', sourceId: 'a', countryCode: 'MY', type: 'state', level: 1, name: 'A'),
        ]),
        new ArrayAddressAreaSource('two', [
            new AddressAreaData(source: 'two', sourceId: 'b', countryCode: 'MY', type: 'state', level: 1, name: 'B'),
        ]),
    ]);

    expect($composite->key())->toBe('one')
        ->and($composite->areas()->map->sourceId->all())->toBe(['a', 'b']);
});

it('rejects an empty source list', function (): void {
    new CompositeAddressAreaSource([]);
})->throws(InvalidArgumentException::class);
