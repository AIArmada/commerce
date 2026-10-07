<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\DiffGoogleAddressReferenceAction;

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
    $diff = new DiffGoogleAddressReferenceAction(fn (string $code): array => googleTestPayload());

    $result = $diff->execute('MY');

    expect($result->countryCode)->toBe('MY')
        ->and($result->hasDrift())->toBeFalse()
        ->and($result->format)->toBe('%N%n%O%n%A%n%D%n%Z %C%n%S');
});

it('flags required and upper mismatches', function (): void {
    $diff = new DiffGoogleAddressReferenceAction(fn (string $code): array => googleTestPayload([
        'require' => 'AZ',
        'upper' => 'C',
    ]));

    $dimensions = array_column($diff->execute('my')->drifts, 'dimension');

    expect($dimensions)->toContain('required', 'upper');
});

it('flags zipex examples our pattern rejects', function (): void {
    $diff = new DiffGoogleAddressReferenceAction(fn (string $code): array => googleTestPayload([
        'zip' => '\d{4}',
        'zipex' => '1234',
    ]));

    $drifts = $diff->execute('MY')->drifts;

    expect(array_column($drifts, 'dimension'))->toContain('pattern-examples')
        ->and($drifts[0]['theirs'])->toContain('1234');
});

it('flags one-sided zip rules', function (): void {
    $googleOnly = new DiffGoogleAddressReferenceAction(fn (string $code): array => googleTestPayload(['key' => 'HK']));
    $oursOnly = new DiffGoogleAddressReferenceAction(fn (string $code): array => googleTestPayload(['zip' => '']));

    expect(array_column($googleOnly->execute('HK')->drifts, 'dimension'))->toContain('pattern')
        ->and(array_column($oursOnly->execute('MY')->drifts, 'dimension'))->toContain('pattern');
});

it('flags unprofiled countries where Google has data', function (): void {
    $diff = new DiffGoogleAddressReferenceAction(fn (string $code): array => googleTestPayload(['key' => 'AQ']));

    expect(array_column($diff->execute('AQ')->drifts, 'dimension'))->toContain('profile');
});

it('marks Google-silent countries instead of drifting', function (): void {
    $diff = new DiffGoogleAddressReferenceAction(fn (string $code): array => []);

    $result = $diff->execute('MY');

    expect($result->googleSilent)->toBeTrue()
        ->and($result->hasDrift())->toBeFalse();
});

it('fails loudly when the fetch fails', function (): void {
    $diff = new DiffGoogleAddressReferenceAction(function (string $code): array {
        throw new RuntimeException('boom');
    });

    $diff->execute('MY');
})->throws(RuntimeException::class, 'boom');
