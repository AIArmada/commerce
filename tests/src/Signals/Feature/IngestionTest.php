<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Actions\ServeSignalsTracker;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Http\Request;

uses(SignalsTestCase::class);

it('accepts identify payloads using a property write key', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Signals Owner',
        'email' => 'identify-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Identify Property',
        'slug' => 'identify-property',
        'write_key' => 'identify-write-key',
    ]);
    $property->assignOwner($owner)->save();

    $response = $this->postJson('/api/signals/collect/identify', [
        'write_key' => 'identify-write-key',
        'external_id' => 'customer-123',
        'email' => 'customer@example.com',
        'traits' => ['plan' => 'pro'],
    ]);

    $response->assertAccepted()->assertJsonPath('data.tracked_property_id', $property->id);

    $identity = SignalIdentity::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->first();

    expect($identity)->not()->toBeNull()
        ->and($identity?->external_id)->toBe('customer-123')
        ->and($identity?->owner_type)->toBe($owner->getMorphClass())
        ->and($identity?->owner_id)->toBe($owner->getKey());
});

it('accepts event payloads and creates identity and session records', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Event Owner',
        'email' => 'event-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Event Property',
        'slug' => 'event-property',
        'write_key' => 'event-write-key',
    ]);
    $property->assignOwner($owner)->save();

    $response = $this->postJson('/api/signals/collect/event', [
        'write_key' => 'event-write-key',
        'event_name' => 'purchase_completed',
        'event_category' => 'conversion',
        'external_id' => 'customer-456',
        'email' => 'buyer@example.com',
        'session_identifier' => 'sess-abc',
        'path' => '/checkout/complete',
        'referrer' => 'https://google.com',
        'utm_source' => 'newsletter',
        'utm_medium' => 'email',
        'utm_campaign' => 'spring-sale',
        'utm_content' => 'hero-banner',
        'utm_term' => 'spring deals',
        'revenue_minor' => 9900,
        'properties' => [
            'order_number' => 'ORD-1001',
            'items_count' => 3,
            'first_order' => true,
            'checkout' => [
                'completed_at' => '2026-03-10T10:30:00+00:00',
            ],
        ],
    ]);

    $response->assertAccepted()->assertJsonPath('data.tracked_property_id', $property->id);

    $followUpResponse = $this->postJson('/api/signals/collect/event', [
        'write_key' => 'event-write-key',
        'event_name' => 'checkout_progressed',
        'event_category' => 'conversion',
        'external_id' => 'customer-456',
        'session_identifier' => 'sess-abc',
        'path' => '/checkout/payment',
    ]);

    $followUpResponse->assertAccepted();

    $events = SignalEvent::query()
        ->withoutOwnerScope()
        ->where('tracked_property_id', $property->id)
        ->orderBy('created_at')
        ->get();
    $event = $events->first();
    $followUpEvent = $events->last();
    $identity = SignalIdentity::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->first();
    $session = SignalSession::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->first();

    expect($event)->not()->toBeNull()
        ->and($followUpEvent)->not()->toBeNull()
        ->and($identity)->not()->toBeNull()
        ->and($session)->not()->toBeNull()
        ->and($event?->signal_identity_id)->toBe($identity?->id)
        ->and($event?->signal_session_id)->toBe($session?->id)
        ->and($followUpEvent?->signal_session_id)->toBe($session?->id)
        ->and($session?->session_identifier)->toBe('sess-abc')
        ->and($session?->referrer)->toBe('https://google.com')
        ->and($session?->utm_content)->toBe('hero-banner')
        ->and($session?->utm_term)->toBe('spring deals')
        ->and($followUpEvent?->source)->toBe('newsletter')
        ->and($followUpEvent?->medium)->toBe('email')
        ->and($followUpEvent?->campaign)->toBe('spring-sale')
        ->and($followUpEvent?->content)->toBe('hero-banner')
        ->and($followUpEvent?->term)->toBe('spring deals')
        ->and($followUpEvent?->referrer)->toBe('https://google.com')
        ->and($session?->is_bounce)->toBeFalse()
        ->and($event?->revenue_minor)->toBe(9900)
        ->and($event?->property_types)->toMatchArray([
            'order_number' => 'string',
            'items_count' => 'number',
            'first_order' => 'boolean',
            'checkout' => [
                'completed_at' => 'date',
            ],
        ]);
});

it('accepts pageview payloads and records a page_view event', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Pageview Owner',
        'email' => 'pageview-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Pageview Property',
        'slug' => 'pageview-property',
        'write_key' => 'pageview-write-key',
    ]);
    $property->assignOwner($owner)->save();

    $response = $this->postJson('/api/signals/collect/pageview', [
        'write_key' => 'pageview-write-key',
        'anonymous_id' => 'anon-1',
        'session_identifier' => 'page-sess-1',
        'path' => '/pricing',
        'url' => 'https://example.test/pricing',
        'title' => 'Pricing',
        'referrer' => 'https://google.com',
    ]);

    $response->assertAccepted()->assertJsonPath('data.tracked_property_id', $property->id);

    $event = SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->first();
    $session = SignalSession::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->first();

    expect($event)->not()->toBeNull()
        ->and($session)->not()->toBeNull()
        ->and($event?->event_name)->toBe('page_view')
        ->and($event?->event_category)->toBe('page_view')
        ->and($event?->path)->toBe('/pricing')
        ->and($event?->url)->toBe('https://example.test/pricing')
        ->and($event?->properties['title'] ?? null)->toBe('Pricing')
        ->and($session?->session_identifier)->toBe('page-sess-1')
        ->and($session?->is_bounce)->toBeTrue();
});

it('rejects public ingestion when the tracked property domain does not match the request', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Domain Guard Owner',
        'email' => 'domain-guard-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Domain Guard Property',
        'slug' => 'domain-guard-property',
        'write_key' => 'domain-guard-key',
        'domain' => 'example.test',
    ]);
    $property->assignOwner($owner)->save();

    $identifyResponse = $this
        ->withHeaders([
            'Origin' => 'https://evil.test',
            'Referer' => 'https://evil.test/account',
        ])
        ->postJson('/api/signals/collect/identify', [
            'write_key' => 'domain-guard-key',
            'external_id' => 'customer-789',
        ]);

    $identifyResponse
        ->assertStatus(422)
        ->assertJsonValidationErrors(['write_key']);

    $pageViewResponse = $this->postJson('/api/signals/collect/pageview', [
        'write_key' => 'domain-guard-key',
        'anonymous_id' => 'anon-domain-guard',
        'session_identifier' => 'page-sess-domain-guard',
        'path' => '/pricing',
        'url' => 'https://evil.test/pricing',
    ]);

    $pageViewResponse
        ->assertStatus(422)
        ->assertJsonValidationErrors(['write_key']);

    expect(SignalIdentity::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(SignalSession::query()->withoutOwnerScope()->count())->toBe(0);
});

it('serves a lightweight tracker script', function (): void {
    $response = $this->get('/api/signals/tracker.js');

    $response->assertOk();

    expect((string) $response->headers->get('content-type'))->toContain('application/javascript')
        ->and($response->getContent())->toContain('navigator.sendBeacon')
        ->and($response->getContent())->toContain('/collect/pageview')
        ->and($response->getContent())->toContain('URLSearchParams')
        ->and($response->getContent())->toContain("utm_source: params.get('utm_source')")
        ->and($response->getContent())->toContain('data-write-key');
});

it('uses the configured tracker filename when deriving the pageview endpoint', function (): void {
    config()->set('signals.http.tracker_script', 'pulse.min.js');

    $response = app(ServeSignalsTracker::class)->asController(Request::create('/api/signals/pulse.min.js', 'GET'));

    expect($response->getContent())->toContain('/pulse\\.min\\.js$')
        ->and($response->getContent())->not()->toContain('/tracker\\.js$')
        ->and($response->getContent())->toContain('/collect/pageview');
});
