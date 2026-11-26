<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\GoogleAddressReferenceSource;
use Illuminate\Support\Facades\Artisan;

it('reports clean countries with success', function (): void {
    $source = Mockery::mock(GoogleAddressReferenceSource::class);
    $source->shouldReceive('fetch')->andReturnUsing(
        fn (string $code): array => [
            'key' => 'MY',
            'fmt' => '%N%n%O%n%A%n%Z %C%n%S',
            'require' => 'ACSZ',
            'upper' => 'CS',
            'zip' => '\d{5}',
            'zipex' => '43000,50754',
        ],
    );
    app()->instance(GoogleAddressReferenceSource::class, $source);

    $exit = Artisan::call('address:reference:google', ['country' => 'MY']);

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('no drift');
});

it('fails with a drift table when the profile disagrees', function (): void {
    $source = Mockery::mock(GoogleAddressReferenceSource::class);
    $source->shouldReceive('fetch')->andReturnUsing(
        fn (string $code): array => [
            'key' => 'MY',
            'fmt' => '%N%n%O%n%A%n%Z %C%n%S',
            'require' => 'AZ',
            'upper' => 'C',
            'zip' => '\d{5}',
            'zipex' => '43000',
        ],
    );
    app()->instance(GoogleAddressReferenceSource::class, $source);

    $exit = Artisan::call('address:reference:google', ['country' => 'MY']);

    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('required');
});

it('requires a country or --all', function (): void {
    $exit = Artisan::call('address:reference:google');

    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('Pass a country code');
});
