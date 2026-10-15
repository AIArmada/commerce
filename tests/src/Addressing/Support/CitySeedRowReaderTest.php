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

it('streams the complete dataset in bounded chunks across large utf8 rows', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'cities') . '.json';
    $json = "[\n";

    for ($index = 0; $index < 15_001; $index++) {
        $name = $index === 10_000 ? 'Saint-Barthélemy' : str_repeat('x', 150) . $index;
        $separator = $index === 15_000 ? '' : ',';
        $json .= json_encode(['name' => $name, 'country_code' => 'MY'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . $separator . "\n";
    }

    file_put_contents($path, $json . "]\n");
    $reader = new CitySeedRowReader($path);

    try {
        $chunks = iterator_to_array($reader->readAllChunks(5000), false);
        $rows = array_merge(...$chunks);

        expect($chunks)->toHaveCount(4)
            ->and(max(array_map('count', $chunks)))->toBe(5000)
            ->and($rows)->toHaveCount(15_001)
            ->and(array_column($rows, 'name'))->toContain('Saint-Barthélemy');
    } finally {
        unlink($path);
    }
});

it('rejects incomplete or malformed top-level city arrays', function (string $json): void {
    [$path, $reader] = makeCitySeedReader($json);

    try {
        expect(fn () => iterator_to_array($reader->readAllChunks(), false))
            ->toThrow(RuntimeException::class);
    } finally {
        unlink($path);
    }
})->with([
    'truncated object' => '[{"name":"Kuala Lumpur","country_code":"MY"',
    'missing array close' => '[{"name":"Kuala Lumpur","country_code":"MY"}',
    'trailing comma' => '[{"name":"Kuala Lumpur","country_code":"MY"},]',
    'trailing content' => '[{"name":"Kuala Lumpur","country_code":"MY"}] garbage',
]);
