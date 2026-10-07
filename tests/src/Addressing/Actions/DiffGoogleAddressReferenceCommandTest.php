<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\DiffGoogleAddressReferenceAction;
use Illuminate\Support\Facades\Artisan;

it('reports clean countries with success', function (): void {
    app()->instance(DiffGoogleAddressReferenceAction::class, new DiffGoogleAddressReferenceAction(
        fn (string $code): array => [
            'key' => 'MY',
            'fmt' => '%N%n%O%n%A%n%Z %C%n%S',
            'require' => 'ACSZ',
            'upper' => 'CS',
            'zip' => '\d{5}',
            'zipex' => '43000,50754',
        ],
    ));

    $exit = Artisan::call('address:reference:google', ['country' => 'MY']);

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('no drift');
});

it('fails with a drift table when the profile disagrees', function (): void {
    app()->instance(DiffGoogleAddressReferenceAction::class, new DiffGoogleAddressReferenceAction(
        fn (string $code): array => [
            'key' => 'MY',
            'fmt' => '%N%n%O%n%A%n%Z %C%n%S',
            'require' => 'AZ',
            'upper' => 'C',
            'zip' => '\d{5}',
            'zipex' => '43000',
        ],
    ));

    $exit = Artisan::call('address:reference:google', ['country' => 'MY']);

    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('required');
});

it('requires a country or --all', function (): void {
    $exit = Artisan::call('address:reference:google');

    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('Pass a country code');
});
