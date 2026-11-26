<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\GoogleAddressReferenceSource;
use AIArmada\Addressing\Features\ReferenceIntegrity\Actions\DiffGoogleAddressReferenceAction;

function googleTestPayload(array $overrides = []): array
{
    return array_merge([
        'key' => 'MY',
        'name' => 'MALAYSIA',
        'fmt' => '%N%n%O%n%A%n%D%n%Z %C%n%S',
        'require' => 'ACSZ',
        'upper' => 'CS',
        'zip' => '\d{5}',
        'zipex' => '43000,50754',
    ], $overrides);
}

it('reports no drift when the profile matches Google', function (): void {
    $diff = googleReferenceDiff(fn (string $code): array => googleTestPayload());

    $result = $diff->execute('MY');

    expect($result->countryCode)->toBe('MY')
        ->and($result->hasDrift())->toBeFalse()
        ->and($result->format)->toBe('%N%n%O%n%A%n%D%n%Z %C%n%S');
});

it('flags required and upper mismatches', function (): void {
    $diff = googleReferenceDiff(fn (string $code): array => googleTestPayload([
        'require' => 'AZ',
        'upper' => 'C',
    ]));

    $dimensions = array_column($diff->execute('my')->drifts, 'dimension');

    expect($dimensions)->toContain('required', 'upper');
});

it('flags zipex examples our pattern rejects', function (): void {
    $diff = googleReferenceDiff(fn (string $code): array => googleTestPayload([
        'zip' => '\d{4}',
        'zipex' => '1234',
    ]));

    $drifts = $diff->execute('MY')->drifts;

    expect(array_column($drifts, 'dimension'))->toContain('pattern-examples')
        ->and($drifts[0]['theirs'])->toContain('1234');
});

it('flags one-sided zip rules', function (): void {
    $googleOnly = googleReferenceDiff(fn (string $code): array => googleTestPayload(['key' => 'HK']));
    $oursOnly = googleReferenceDiff(fn (string $code): array => googleTestPayload(['zip' => '']));

    expect(array_column($googleOnly->execute('HK')->drifts, 'dimension'))->toContain('pattern')
        ->and(array_column($oursOnly->execute('MY')->drifts, 'dimension'))->toContain('pattern');
});

it('flags unprofiled countries where Google has data', function (): void {
    $diff = googleReferenceDiff(fn (string $code): array => googleTestPayload(['key' => 'AQ']));

    expect(array_column($diff->execute('AQ')->drifts, 'dimension'))->toContain('profile');
});

it('marks Google-silent countries instead of drifting', function (): void {
    $diff = googleReferenceDiff(fn (string $code): array => []);

    $result = $diff->execute('MY');

    expect($result->googleSilent)->toBeTrue()
        ->and($result->hasDrift())->toBeFalse();
});

it('fails loudly when the fetch fails', function (): void {
    $diff = googleReferenceDiff(function (string $code): array {
        throw new RuntimeException('boom');
    });

    $diff->execute('MY');
})->throws(RuntimeException::class, 'boom');

function googleReferenceDiff(Closure $fetcher): DiffGoogleAddressReferenceAction
{
    $source = Mockery::mock(GoogleAddressReferenceSource::class);
    $source->shouldReceive('fetch')->andReturnUsing($fetcher);

    return new DiffGoogleAddressReferenceAction($source);
}

it('rejects malformed country codes before contacting the source', function (): void {
    $source = Mockery::mock(GoogleAddressReferenceSource::class);
    $source->shouldNotReceive('fetch');

    (new DiffGoogleAddressReferenceAction($source))->execute('../MY');
})->throws(InvalidArgumentException::class);
