<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Signals\Features\Ingestion\Actions\IngestSignalEvent;
use AIArmada\Signals\Features\Ingestion\Actions\ResolveSession;
use AIArmada\Signals\Features\Ingestion\Models\SignalEvent;
use AIArmada\Signals\Features\Ingestion\Models\SignalSession;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Illuminate\Http\Request;

uses(SignalsTestCase::class);

function createSessionTrustProperty(): TrackedProperty
{
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Session Trust Owner',
        'email' => 'session-trust-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Session Trust Property',
        'slug' => 'session-trust-property',
        'write_key' => 'session-trust-key',
    ]);
    $property->assignOwner($owner)->save();

    return $property;
}

it('ignores proxy geo headers from untrusted clients', function (): void {
    createSessionTrustProperty();

    $request = Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']);
    $request->headers->set('CF-Connecting-IP', '9.9.9.9');
    $request->headers->set('CF-IPCountry', 'DE');

    $resolver = app(ResolveSession::class);
    $clientIp = new ReflectionMethod(ResolveSession::class, 'resolveClientIp');
    $clientIp->setAccessible(true);
    $countryCode = new ReflectionMethod(ResolveSession::class, 'resolveCountryCode');
    $countryCode->setAccessible(true);
    $countrySource = new ReflectionMethod(ResolveSession::class, 'resolveCountrySource');
    $countrySource->setAccessible(true);

    expect($clientIp->invoke($resolver, $request))->toBe('203.0.113.10')
        ->and($countryCode->invoke($resolver, $request, []))->toBeNull()
        ->and($countrySource->invoke($resolver, $request, []))->toBeNull();
});

it('honors proxy geo headers behind trusted proxies', function (): void {
    createSessionTrustProperty();

    Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_FOR);

    try {
        $request = Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $request->headers->set('CF-Connecting-IP', '9.9.9.9');
        $request->headers->set('CF-IPCountry', 'DE');

        $resolver = app(ResolveSession::class);
        $clientIp = new ReflectionMethod(ResolveSession::class, 'resolveClientIp');
        $clientIp->setAccessible(true);
        $countryCode = new ReflectionMethod(ResolveSession::class, 'resolveCountryCode');
        $countryCode->setAccessible(true);
        $countrySource = new ReflectionMethod(ResolveSession::class, 'resolveCountrySource');
        $countrySource->setAccessible(true);

        expect($clientIp->invoke($resolver, $request))->toBe('9.9.9.9')
            ->and($countryCode->invoke($resolver, $request, []))->toBe('DE')
            ->and($countrySource->invoke($resolver, $request, []))->toBe('cloudflare');
    } finally {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
    }
});

it('requires exact country codes and keeps the reported source aligned', function (): void {
    $resolver = app(ResolveSession::class);
    $countryCode = new ReflectionMethod(ResolveSession::class, 'resolveCountryCode');
    $countryCode->setAccessible(true);
    $countrySource = new ReflectionMethod(ResolveSession::class, 'resolveCountrySource');
    $countrySource->setAccessible(true);

    Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_FOR);

    try {
        $request = Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $request->headers->set('CF-IPCountry', "DE\n");

        expect($countryCode->invoke($resolver, $request, []))->toBeNull()
            ->and($countrySource->invoke($resolver, $request, []))->toBeNull();

        $request->headers->set('CF-IPCountry', 'de');

        expect($countryCode->invoke($resolver, $request, []))->toBe('DE')
            ->and($countrySource->invoke($resolver, $request, []))->toBe('cloudflare');
    } finally {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
    }

    $payloadRequest = Request::create('/', 'POST');

    expect($countryCode->invoke($resolver, $payloadRequest, ['country_code' => "MY\n"]))->toBeNull()
        ->and($countrySource->invoke($resolver, $payloadRequest, ['country_code' => "MY\n"]))->toBeNull()
        ->and($countryCode->invoke($resolver, $payloadRequest, ['country_code' => 'my']))->toBe('MY')
        ->and($countrySource->invoke($resolver, $payloadRequest, ['country_code' => 'my']))->toBe('payload');
});

it('rejects control bytes in persisted session identifiers while preserving valid UTF-8', function (): void {
    $property = createSessionTrustProperty();
    $ingest = app(IngestSignalEvent::class);

    $invalid = $ingest->handle($property, [
        'event_name' => 'custom.invalid_session',
        'external_id' => 'invalid-session-customer',
        'session_identifier' => "session\nid",
    ], trusted: true);

    expect($invalid->signal_session_id)->toBeNull()
        ->and(SignalSession::query()->withoutOwnerScope()->count())->toBe(0);

    $valid = $ingest->handle($property, [
        'event_name' => 'custom.valid_session',
        'external_id' => 'valid-session-customer',
        'session_identifier' => 'sessió-✓',
    ], trusted: true);

    expect($valid->signal_session_id)->not->toBeNull()
        ->and(SignalSession::query()->withoutOwnerScope()->value('session_identifier'))->toBe('sessió-✓');
});

it('normalizes trusted revenue to non-negative integer minor units', function (): void {
    $property = createSessionTrustProperty();

    $cases = [
        'negative integer clamps to zero' => [-50, 0],
        'negative string clamps to zero' => ['-50', 0],
        'huge negative string clamps before casting' => ['-9223372036854775809', 0],
        'integer passes through' => [15900, 15900],
        'integer string passes through' => ['15900', 15900],
        'signed integer string passes through' => ['+0015900', 15900],
        'php int max' => [PHP_INT_MAX, PHP_INT_MAX],
        'php int max string' => [(string) PHP_INT_MAX, PHP_INT_MAX],
    ];

    foreach ($cases as [$input, $expected]) {
        $event = app(IngestSignalEvent::class)->handle($property, [
            'event_name' => 'order.paid',
            'event_category' => 'conversion',
            'external_id' => 'revenue-customer',
            'revenue_minor' => $input,
            'currency' => 'MYR',
        ], trusted: true);

        expect($event->revenue_minor)->toBe($expected);
    }

    expect(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(8);
});

it('rejects non-integer and overflowing trusted revenue before writing', function (): void {
    $property = createSessionTrustProperty();

    foreach ([1299.6, 1299.0, '1299.6', '1.299e3', 'not-money', '9223372036854775808', true] as $input) {
        expect(fn (): SignalEvent => app(IngestSignalEvent::class)->handle($property, [
            'event_name' => 'order.paid',
            'event_category' => 'conversion',
            'external_id' => 'revenue-invalid-customer',
            'revenue_minor' => $input,
            'currency' => 'MYR',
        ], trusted: true))->toThrow(InvalidArgumentException::class);
    }

    expect(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(0);
});

it('anonymizes IPv6 addresses using byte boundaries', function (): void {
    $resolver = app(ResolveSession::class);
    $anonymize = new ReflectionMethod(ResolveSession::class, 'anonymizeIpAddress');

    expect($anonymize->invoke($resolver, 'c3a9:c3a9:c3a9:c3a9:1234:5678:9abc:def0'))->toBe('c3a9:c3a9:c3a9::')
        ->and($anonymize->invoke($resolver, '203.0.113.42'))->toBe('203.0.113.0');
});

it('does not erase the session identity on a later event without identity fields', function (): void {
    $property = createSessionTrustProperty();
    $ingest = app(IngestSignalEvent::class);
    $first = $ingest->handle($property, [
        'event_name' => 'custom.first',
        'external_id' => 'known-customer',
        'session_identifier' => 'known-session',
    ], trusted: true);

    $ingest->handle($property, [
        'event_name' => 'custom.next',
        'session_identifier' => 'known-session',
    ], trusted: true);

    expect($first->signal_identity_id)->not->toBeNull()
        ->and($property->sessions()->firstOrFail()->signal_identity_id)->toBe($first->signal_identity_id);
});

it('preserves integer minor-unit revenue above the exact floating-point range', function (): void {
    $property = createSessionTrustProperty();
    $event = app(IngestSignalEvent::class)->handle($property, [
        'event_name' => 'order.paid',
        'revenue_minor' => 9007199254740993,
    ], trusted: true);

    expect($event->revenue_minor)->toBe(9007199254740993);
});
