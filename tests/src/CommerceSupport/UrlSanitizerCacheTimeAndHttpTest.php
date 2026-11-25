<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Drivers\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Persistence\CacheVersionStore;
use AIArmada\CommerceSupport\Persistence\VersionedCache;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use AIArmada\CommerceSupport\Support\UrlSanitizer;
use AIArmada\CommerceSupport\Support\UserDateTimeFormatter;
use AIArmada\CommerceSupport\Support\UserTimezoneResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

test('url sanitizer blocks dangerous schemes and obfuscation while keeping safe http and relative urls', function (): void {
    expect(UrlSanitizer::sanitize(null))->toBeNull()
        ->and(UrlSanitizer::sanitize('   '))->toBeNull()
        ->and(UrlSanitizer::sanitize('javascript:alert(1)'))->toBeNull()
        ->and(UrlSanitizer::sanitize('&#x6a;avascript:alert(1)'))->toBeNull()
        ->and(UrlSanitizer::sanitize('java%0ascript:alert(1)'))->toBeNull()
        ->and(UrlSanitizer::sanitize('data:text/html,<script>alert(1)</script>'))->toBeNull()
        ->and(UrlSanitizer::sanitize('//evil.example/path'))->toBeNull()
        ->and(UrlSanitizer::sanitize('/events/kajian'))->toBe('/events/kajian')
        ->and(UrlSanitizer::sanitize('https://ilmu360.example/path?a=1'))->toBe('https://ilmu360.example/path?a=1')
        ->and(UrlSanitizer::sanitize('HTTP://example.com'))->toBe('HTTP://example.com');
});

test('versioned cache invalidates derived keys cleanly when version bumps and coerces ints', function (): void {
    $versions = new CacheVersionStore;
    $cache = new VersionedCache($versions);

    $calls = 0;
    $first = $cache->remember('test:ver', 'item:a', 60, function () use (&$calls): string {
        $calls++;

        return 'v1';
    });

    $cached = $cache->remember('test:ver', 'item:a', 60, function () use (&$calls): string {
        $calls++;

        return 'v1-again';
    });

    expect($first)->toBe('v1')
        ->and($cached)->toBe('v1')
        ->and($calls)->toBe(1);

    $versions->bump('test:ver');

    $afterBump = $cache->remember('test:ver', 'item:a', 60, function () use (&$calls): string {
        $calls++;

        return 'v2';
    });

    expect($afterBump)->toBe('v2')
        ->and($calls)->toBe(2)
        ->and($cache->rememberInt('test:ver', 'int:a', 60, static fn (): int => 42))->toBe(42);
});

test('user timezone resolver and datetime formatter honor precedence and parse utc ranges', function (): void {
    config()->set('app.default_user_timezone', 'Asia/Kuala_Lumpur');

    $request = Request::create('/events', 'GET', ['user_timezone' => 'Asia/Singapore']);
    expect(UserTimezoneResolver::resolve($request, 'Asia/Tokyo'))->toBe('Asia/Tokyo')
        ->and(UserTimezoneResolver::resolveWithSource($request)['source'])->toBe('request_input');

    $headerRequest = Request::create('/events', 'GET');
    $headerRequest->headers->set('X-Timezone', 'Europe/London');
    expect(UserTimezoneResolver::resolveWithSource($headerRequest))->toBe([
        'timezone' => 'Europe/London',
        'source' => 'header',
    ]);

    $utcTime = CarbonImmutable::parse('2026-03-10 04:30:00', 'UTC');
    expect(UserDateTimeFormatter::format($utcTime, 'Y-m-d H:i', null, 'Asia/Kuala_Lumpur'))
        ->toBe('2026-03-10 12:30')
        ->and(UserDateTimeFormatter::format(null))->toBe('');

    $rangeStart = UserDateTimeFormatter::parseUserDateToUtc('2026-03-10', false, $headerRequest);
    expect($rangeStart?->toIso8601String())->toBe('2026-03-10T00:00:00+00:00')
        ->and(UserDateTimeFormatter::parseUserDateToUtc('invalid-date', false, $headerRequest))->toBeNull();

    $fallbackRequest = Request::create('/events', 'GET');
    Auth::shouldReceive('check')->once()->andReturn(true);
    $user = new class extends Authenticatable
    {
        public string $timezone = 'Asia/Jakarta';
    };
    Auth::shouldReceive('user')->once()->andReturn($user);
    expect(UserTimezoneResolver::resolveWithSource($fallbackRequest))->toBe([
        'timezone' => 'Asia/Jakarta',
        'source' => 'authenticated_user',
    ]);
});

test('public http url guard rejects ipv4-mapped ipv6 private destinations and resolves relative urls', function (): void {
    expect(PublicHttpUrlGuard::isPublicIpAddress('93.184.216.34'))->toBeTrue()
        ->and(PublicHttpUrlGuard::isPublicIpAddress('::ffff:127.0.0.1'))->toBeFalse()
        ->and(PublicHttpUrlGuard::isPublicIpAddress('::ffff:10.0.0.1'))->toBeFalse()
        ->and(PublicHttpUrlGuard::isPublicIpAddress('100.64.0.1'))->toBeFalse()
        ->and(PublicHttpUrlGuard::isPublicIpAddress('0.0.0.1'))->toBeFalse();

    expect(PinnedHttpClient::resolveRelativeUrl('https://example.com/a/b', '/next?x=1'))
        ->toBe('https://example.com/next?x=1')
        ->and(PinnedHttpClient::resolveRelativeUrl('https://example.com/a/b', '//cdn.example.com/file'))
        ->toBe('https://cdn.example.com/file')
        ->and(PinnedHttpClient::resolveRelativeUrl('https://example.com/dir/page.html', 'sub.html'))
        ->toBe('https://example.com/dir/sub.html');
});

test('pinned http client follows validated public redirects and blocks private redirect hops', function (): void {
    Http::fake([
        'https://example.com/start' => Http::response('', 302, ['Location' => '/final']),
        'https://example.com/final' => Http::response('hello world', 200, ['Content-Type' => 'text/html']),
    ]);

    $guard = new PublicHttpUrlGuard(static fn (string $host): array => ['93.184.216.34']);
    $client = new PinnedHttpClient;

    $response = $client->sendFollowingRedirects($guard, 'GET', 'https://example.com/start');
    expect($response->body())->toBe('hello world')
        ->and($client->readBoundedBody($response, 5))->toBeNull()
        ->and($client->readBoundedBody($response, 100))->toBe('hello world');

    Http::fake([
        'https://example.com/ssrf' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secret']),
    ]);

    expect(fn () => $client->sendFollowingRedirects($guard, 'GET', 'https://example.com/ssrf'))
        ->toThrow(InvalidArgumentException::class);
});
