<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Actions\ServeSignalsTracker;
use AIArmada\Signals\Jobs\EvaluateSignalAlertsForEvent;
use AIArmada\Signals\Jobs\ReverseGeocodeSessionJob;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

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

it('deduplicates event payloads by idempotency key per tracked property', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Idempotent Owner',
        'email' => 'idempotent-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Idempotent Property',
        'slug' => 'idempotent-property',
        'write_key' => 'idempotent-write-key',
    ]);

    $payload = [
        'write_key' => 'idempotent-write-key',
        'event_name' => 'cart.snapshot.synced',
        'event_category' => 'cart',
        'idempotency_key' => 'snapshot-1',
        'properties' => [
            'cart_total_minor' => 12000,
        ],
    ];

    $first = $this->postJson('/api/signals/collect/event', $payload);
    $second = $this->postJson('/api/signals/collect/event', $payload);

    $first->assertAccepted();
    $second->assertAccepted();

    expect(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(1);
});

it('filters raw PII and non-allowlisted properties from event payloads', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Privacy Owner',
        'email' => 'privacy-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    TrackedProperty::query()->create([
        'name' => 'Privacy Property',
        'slug' => 'privacy-property',
        'write_key' => 'privacy-write-key',
    ]);

    $response = $this->postJson('/api/signals/collect/event', [
        'write_key' => 'privacy-write-key',
        'event_name' => 'cart.high_value.detected',
        'event_category' => 'cart',
        'properties' => [
            'cart_total_minor' => 25000,
            'email' => 'customer@example.test',
            'customer_name' => 'Sensitive Customer',
            'metadata' => ['anything' => 'goes'],
            'unknown_key' => 'not allowed',
        ],
    ]);

    $response->assertAccepted();

    $event = SignalEvent::query()->withoutOwnerScope()->firstOrFail();

    expect($event->properties)->toBe([
        'cart_total_minor' => 25000,
    ]);
});

it('queues alert evaluation when on-ingest evaluation is explicitly enabled', function (): void {
    Queue::fake();

    config()->set('signals.features.alerts.evaluate_on_ingest.enabled', true);
    config()->set('signals.features.alerts.evaluate_on_ingest.queue', true);

    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Queued Alert Owner',
        'email' => 'queued-alert-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    TrackedProperty::query()->create([
        'name' => 'Queued Alert Property',
        'slug' => 'queued-alert-property',
        'write_key' => 'queued-alert-write-key',
    ]);

    $response = $this->postJson('/api/signals/collect/event', [
        'write_key' => 'queued-alert-write-key',
        'event_name' => 'cart.abandoned',
        'event_category' => 'cart',
    ]);

    $response->assertAccepted();

    Queue::assertPushed(EvaluateSignalAlertsForEvent::class, function (EvaluateSignalAlertsForEvent $job): bool {
        expect($job->signalEventId)->not()->toBe('')
            ->and($job->ownerType)->toBeString()
            ->and($job->ownerId)->not()->toBeNull()
            ->and($job->ownerIsGlobal)->toBeFalse();

        return true;
    });
});

it('queues reverse geocoding with explicit owner payload context', function (): void {
    Queue::fake();

    config()->set('signals.features.geolocation.enabled', true);
    config()->set('signals.features.geolocation.reverse_geocode.enabled', true);
    config()->set('signals.features.geolocation.reverse_geocode.async', true);

    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Geo Owner',
        'email' => 'geo-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    TrackedProperty::query()->create([
        'name' => 'Geo Property',
        'slug' => 'geo-property',
        'write_key' => 'geo-write-key',
    ]);

    $this->postJson('/api/signals/collect/event', [
        'write_key' => 'geo-write-key',
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'session_identifier' => 'geo-sess-1',
    ])->assertAccepted();

    $this->postJson('/api/signals/collect/geo', [
        'write_key' => 'geo-write-key',
        'session_identifier' => 'geo-sess-1',
        'latitude' => 3.139,
        'longitude' => 101.6869,
        'accuracy' => 18,
    ])->assertAccepted();

    Queue::assertPushed(ReverseGeocodeSessionJob::class, function (ReverseGeocodeSessionJob $job): bool {
        expect($job->sessionId)->not()->toBe('')
            ->and($job->ownerType)->toBeString()
            ->and($job->ownerId)->not()->toBeNull()
            ->and($job->ownerIsGlobal)->toBeFalse();

        return true;
    });
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

it('adds geolocation columns to the configured session table', function (): void {
    config()->set('signals.database.tables.sessions', 'custom_signal_sessions');
    config()->set('signals.database.json_column_type', 'json');

    Schema::dropIfExists('custom_signal_sessions');
    Schema::dropIfExists('signal_sessions');

    Schema::create('custom_signal_sessions', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('country', 2)->nullable();
        $table->timestamps();
    });

    $migration = require __DIR__ . '/../../../../packages/signals/database/migrations/2001_01_01_000017_add_geolocation_fields_to_signals_sessions_table.php';
    $migration->up();

    expect(Schema::hasColumn('custom_signal_sessions', 'country_source'))->toBeTrue()
        ->and(Schema::hasColumn('custom_signal_sessions', 'latitude'))->toBeTrue()
        ->and(Schema::hasColumn('custom_signal_sessions', 'longitude'))->toBeTrue()
        ->and(Schema::hasColumn('custom_signal_sessions', 'accuracy_meters'))->toBeTrue()
        ->and(Schema::hasColumn('custom_signal_sessions', 'geolocation_source'))->toBeTrue()
        ->and(Schema::hasColumn('custom_signal_sessions', 'geolocation_captured_at'))->toBeTrue()
        ->and(Schema::hasColumn('custom_signal_sessions', 'resolved_country_code'))->toBeTrue()
        ->and(Schema::hasColumn('custom_signal_sessions', 'resolved_country_name'))->toBeTrue()
        ->and(Schema::hasColumn('custom_signal_sessions', 'resolved_state'))->toBeTrue()
        ->and(Schema::hasColumn('custom_signal_sessions', 'resolved_city'))->toBeTrue()
        ->and(Schema::hasColumn('custom_signal_sessions', 'resolved_postcode'))->toBeTrue()
        ->and(Schema::hasColumn('custom_signal_sessions', 'resolved_formatted_address'))->toBeTrue()
        ->and(Schema::hasColumn('custom_signal_sessions', 'reverse_geocode_provider'))->toBeTrue()
        ->and(Schema::hasColumn('custom_signal_sessions', 'reverse_geocoded_at'))->toBeTrue()
        ->and(Schema::hasColumn('custom_signal_sessions', 'raw_reverse_geocode_payload'))->toBeTrue()
        ->and(Schema::hasTable('signal_sessions'))->toBeFalse();
});
