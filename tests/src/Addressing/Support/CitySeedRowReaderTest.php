<?php

declare(strict_types=1);

use AIArmada\Addressing\Support\CitySeedRowReader;

function makeCitySeedReader(string $json): array
{
    $path = tempnam(sys_get_temp_dir(), 'cities') . '.json';

    file_put_contents($path, $json);

    return [$path, new CitySeedRowReader($path)];
}

it('streams only the requested countries', function (): void {
    [$path, $reader] = makeCitySeedReader(json_encode([
        ['name' => 'Kuala Lumpur', 'country_code' => 'MY'],
        ['name' => 'Singapore', 'country_code' => 'SG'],
        ['name' => 'Saint-Barthélemy', 'country_code' => 'BL'],
        ['name' => 'George Town', 'country_code' => 'MY', 'nested' => ['brace' => '{', 'quote' => '"']],
    ]));

    $rows = $reader->readForCountries(['my', 'BL']);

    unlink($path);

    expect($rows)->toHaveCount(3)
        ->and(array_column($rows, 'name'))->toContain('Saint-Barthélemy');
});

it('returns no rows when no country is requested', function (): void {
    $reader = new CitySeedRowReader('/missing/cities.json');

    expect($reader->readForCountries([]))->toBe([])
        ->and($reader->readForCountries(['  ']))->toBe([]);
});

it('prefers the gzip sidecar when present', function (): void {
    [$path, $reader] = makeCitySeedReader(json_encode([['name' => 'Plain', 'country_code' => 'MY']]));

    file_put_contents($path . '.gz', gzencode(json_encode([['name' => 'Zipped', 'country_code' => 'MY']])));

    $rows = $reader->readForCountries(['MY']);

    unlink($path);
    unlink($path . '.gz');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('Zipped');
});

it('throws when the dataset is unreadable', function (): void {
    (new CitySeedRowReader('/missing/cities.json'))->readForCountries(['MY']);
})->throws(RuntimeException::class);

it('returns null for a seed run covering the full dataset', function (): void {
    [$path, $reader] = makeCitySeedReader(json_encode([['name' => 'Kuala Lumpur', 'country_code' => 'MY']]));

    try {
        expect($reader->rowsForSeed([], false))->toBeNull()
            ->and($reader->rowsForSeed(null, false))->toBeNull()
            ->and($reader->rowsForSeed('MY', false))->toBeNull()
            ->and($reader->rowsForSeed(['MY'], true))->toBeNull();
    } finally {
        unlink($path);
    }
});

it('streams the configured countries for a filtered seed run', function (): void {
    [$path, $reader] = makeCitySeedReader(json_encode([
        ['name' => 'Kuala Lumpur', 'country_code' => 'MY'],
        ['name' => 'Singapore', 'country_code' => 'SG'],
    ]));

    $rows = $reader->rowsForSeed([' my ', 'MY', ''], false);

    unlink($path);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('Kuala Lumpur');
});
