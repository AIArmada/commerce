<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;

it('accepts public http destinations that resolve only to public addresses', function (): void {
    $guard = new PublicHttpUrlGuard(static fn (string $host): array => $host === 'example.com'
        ? ['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946']
        : []);

    expect($guard->isAllowed('https://example.com/hooks'))->toBeTrue();
});

it('rejects private internal and malformed outbound destinations', function (string $url): void {
    $guard = new PublicHttpUrlGuard(static fn (string $host): array => match ($host) {
        'private.example' => ['10.0.0.5'],
        default => ['93.184.216.34'],
    });

    expect(fn () => $guard->assertAllowed($url))->toThrow(InvalidArgumentException::class);
})->with([
    'private literal' => 'http://127.0.0.1/admin',
    'private dns result' => 'https://private.example/hooks',
    'carrier grade nat literal' => 'http://100.64.0.1/internal',
    'benchmark network literal' => 'http://198.18.0.1/internal',
    'ipv4 multicast literal' => 'http://224.0.0.1/internal',
    'ipv6 multicast literal' => 'http://[ff02::1]/internal',
    'nat64 private destination' => 'http://[64:ff9b::a00:1]/internal',
    'localhost' => 'http://localhost/status',
    'internal hostname' => 'https://service.internal/hooks',
    'credentials' => 'https://user:secret@example.com/hooks',
    'non standard port' => 'https://example.com:8443/hooks',
    'non http scheme' => 'file:///etc/passwd',
]);

it('fails closed when DNS resolution throws', function (): void {
    $guard = new PublicHttpUrlGuard(static function (): array {
        throw new RuntimeException('Resolver unavailable.');
    });

    expect($guard->isAllowed('https://example.com/hooks'))->toBeFalse();
    expect(fn () => $guard->assertAllowed('https://example.com/hooks'))
        ->toThrow(InvalidArgumentException::class, 'could not be resolved safely');
});


it('rejects a hostname when any DNS answer is non-public', function (): void {
    $guard = new PublicHttpUrlGuard(static fn (string $host): array => ['93.184.216.34', '172.16.0.10']);

    expect(fn () => $guard->validate('https://mixed.example/hooks'))
        ->toThrow(InvalidArgumentException::class, 'resolve exclusively to public IP addresses');
});

it('returns a normalized target pinned to a validated public address', function (): void {
    $target = (new PublicHttpUrlGuard(static fn (string $host): array => ['93.184.216.34']))
        ->validate(' HTTPS://Example.COM/hooks?x=1 ');

    expect($target->url)->toBe('https://example.com/hooks?x=1')
        ->and($target->addresses)->toBe(['93.184.216.34'])
        ->and($target->curlResolveEntry())->toBe('example.com:443:93.184.216.34');
});

test('formats IPv6 pinned addresses for CURLOPT_RESOLVE', function (): void {
    $target = (new PublicHttpUrlGuard(static fn (string $host): array => ['2606:2800:220:1:248:1893:25c8:1946']))
        ->validate('https://example.com/webhook');

    expect($target->selectedIp)->toBe('2606:2800:220:1:248:1893:25c8:1946')
        ->and($target->curlResolveEntry())->toBe('example.com:443:[2606:2800:220:1:248:1893:25c8:1946]');
});
