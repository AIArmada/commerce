<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Signals\Actions\IngestSignalEvent;
use AIArmada\Signals\Actions\ResolveSession;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
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

it('normalizes trusted revenue to non-negative minor units', function (): void {
    $property = createSessionTrustProperty();

    $cases = [
        'float rounds' => [1299.6, 1300],
        'negative clamps to zero' => [-50, 0],
        'non-numeric becomes zero' => ['not-money', 0],
        'integer passes through' => [15900, 15900],
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

    expect(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(4);
});
