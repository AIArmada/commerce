<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\DiffCldrSubdivisionsAction;

function cldrTestCheckout(array $subdivisions, string $version = '48.0.0'): string
{
    $root = sys_get_temp_dir() . '/cldr-diff-test-' . uniqid();
    mkdir($root . '/cldr-core', 0777, true);
    mkdir($root . '/cldr-subdivisions-full/subdivisions/en', 0777, true);

    file_put_contents($root . '/cldr-core/package.json', json_encode(['version' => $version]));
    file_put_contents(
        $root . '/cldr-subdivisions-full/subdivisions/en/en.json',
        json_encode(['subdivisions' => ['localeDisplayNames' => ['subdivisions' => $subdivisions]]]),
    );

    return $root;
}

function cldrMySubdivisions(): array
{
    return [
        'my01' => 'Johor',
        'my02' => 'Kedah',
        'my03' => 'Kelantan',
        'my14' => 'Kuala Lumpur',
        'my04' => 'Melaka',
        'my05' => 'Negeri Sembilan',
        'my06' => 'Pahang',
        'my07' => 'Penang',
        'my08' => 'Perak',
        'my09' => 'Perlis',
        'my16' => 'Putrajaya',
        'my12' => 'Sabah',
        'my13' => 'Sarawak',
        'my10' => 'Selangor',
        'my11' => 'Terengganu',
        'my99' => 'Fictional',
    ];
}

it('reports missing, stale, and renamed subdivision codes', function (): void {
    $result = app(DiffCldrSubdivisionsAction::class)->execute('MY', cldrTestCheckout(cldrMySubdivisions()));

    $byCode = [];

    foreach ($result->drifts as $drift) {
        $byCode[$drift['code']] = $drift;
    }

    expect($result->countryCode)->toBe('MY')
        ->and($result->cldrVersion)->toBe('48.0.0')
        ->and($result->hasDrift())->toBeTrue()
        ->and($byCode['my04']['dimension'])->toBe('renamed')
        ->and($byCode['my04']['ours'])->toBe('Malacca')
        ->and($byCode['my15']['dimension'])->toBe('stale-code')
        ->and($byCode['my99']['dimension'])->toBe('missing-code')
        ->and($byCode)->not->toHaveKey('my01');
});

it('refuses checkouts that disagree with the pinned version', function (): void {
    config()->set('addressing.reference.cldr_version', '49.0.0');

    app(DiffCldrSubdivisionsAction::class)->execute('MY', cldrTestCheckout(cldrMySubdivisions()));
})->throws(RuntimeException::class, 'pins [49.0.0]');

it('accepts checkouts that match the pinned version', function (): void {
    config()->set('addressing.reference.cldr_version', '48.0.0');

    $result = app(DiffCldrSubdivisionsAction::class)->execute('MY', cldrTestCheckout(cldrMySubdivisions()));

    expect($result->cldrVersion)->toBe('48.0.0');
});

it('requires a checkout path', function (): void {
    app(DiffCldrSubdivisionsAction::class)->execute('MY');
})->throws(InvalidArgumentException::class, 'addressing.reference.cldr_path');

it('rejects blank country codes instead of reporting clean', function (): void {
    app(DiffCldrSubdivisionsAction::class)->execute('  ', cldrTestCheckout(cldrMySubdivisions()));
})->throws(InvalidArgumentException::class, 'must not be blank');

it('rejects directories without the CLDR layout', function (): void {
    $root = sys_get_temp_dir() . '/cldr-diff-empty-' . uniqid();
    mkdir($root, 0777, true);

    app(DiffCldrSubdivisionsAction::class)->execute('MY', $root);
})->throws(InvalidArgumentException::class, 'cldr-core/package.json');
