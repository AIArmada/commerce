<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

function cldrCommandCheckout(): string
{
    $root = sys_get_temp_dir() . '/cldr-diff-cmd-' . uniqid();
    mkdir($root . '/cldr-core', 0777, true);
    mkdir($root . '/cldr-subdivisions-full/subdivisions/en', 0777, true);

    file_put_contents($root . '/cldr-core/package.json', json_encode(['version' => '48.0.0']));
    file_put_contents(
        $root . '/cldr-subdivisions-full/subdivisions/en/en.json',
        json_encode(['subdivisions' => ['localeDisplayNames' => ['subdivisions' => [
            'my01' => 'Johor',
            'my04' => 'Melaka',
        ]]]]),
    );

    return $root;
}

it('fails with a drift table when subdivisions disagree', function (): void {
    $exit = Artisan::call('address:reference:cldr', ['country' => 'MY', '--cldr' => cldrCommandCheckout()]);

    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('renamed');
});

it('succeeds when nothing drifts', function (): void {
    $exit = Artisan::call('address:reference:cldr', ['country' => 'XX', '--cldr' => cldrCommandCheckout()]);

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('no drift');
});

it('requires a country or --all', function (): void {
    $exit = Artisan::call('address:reference:cldr');

    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('Pass a country code');
});
