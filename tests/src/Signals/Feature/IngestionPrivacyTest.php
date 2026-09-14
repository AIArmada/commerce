<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Signals\Actions\IdentifySignalIdentity;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\TrackedProperty;

uses(SignalsTestCase::class);

function createPrivacyProperty(string $writeKey): TrackedProperty
{
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Privacy Probe Owner',
        'email' => 'privacy-probe-' . $writeKey . '@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Privacy Probe Property',
        'slug' => 'privacy-probe-' . $writeKey,
        'write_key' => $writeKey,
    ]);
    $property->assignOwner($owner)->save();

    return $property;
}

it('ignores forged auth linkage on the public identify endpoint', function (): void {
    $property = createPrivacyProperty('forged-auth-key');

    $response = $this->postJson('/api/signals/collect/identify', [
        'write_key' => 'forged-auth-key',
        'external_id' => 'customer-forged',
        'auth_user_type' => 'App\\Models\\Admin',
        'auth_user_id' => 'admin-1',
    ]);

    $response->assertAccepted();

    $identity = SignalIdentity::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->firstOrFail();

    expect($identity->auth_user_type)->toBeNull()
        ->and($identity->auth_user_id)->toBeNull();
});

it('derives auth linkage from the authenticated user only when tracking is enabled', function (): void {
    $property = createPrivacyProperty('server-auth-key');

    /** @var User $user */
    $user = User::query()->create([
        'name' => 'Server Auth User',
        'email' => 'server-auth-user@signals.test',
        'password' => 'secret',
    ]);

    $this->actingAs($user);

    $identity = app(IdentifySignalIdentity::class)->handle($property, [
        'external_id' => 'customer-server-auth',
    ]);

    expect($identity->auth_user_type)->toBeNull()
        ->and($identity->auth_user_id)->toBeNull();

    config()->set('signals.features.auth_tracking.enabled', true);

    $linked = app(IdentifySignalIdentity::class)->handle($property, [
        'external_id' => 'customer-server-auth-linked',
    ]);

    expect($linked->auth_user_type)->toBe($user->getMorphClass())
        ->and($linked->auth_user_id)->toBe((string) $user->getAuthIdentifier());
});

it('strips PII and non-allowlisted keys from identity traits', function (): void {
    $property = createPrivacyProperty('traits-filter-key');

    $response = $this->postJson('/api/signals/collect/identify', [
        'write_key' => 'traits-filter-key',
        'external_id' => 'customer-traits',
        'traits' => [
            'email' => 'private@example.com',
            'phone' => '+60123456789',
            'plan' => 'pro',
            'cart_id' => 'cart-1',
        ],
    ]);

    $response->assertAccepted();

    $identity = SignalIdentity::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->firstOrFail();

    expect($identity->traits)->toBe(['cart_id' => 'cart-1']);
});

it('still strips blocked keys when the property allowlist is fully open', function (): void {
    createPrivacyProperty('open-allowlist-key');
    config()->set('signals.features.privacy.property_allowlist', ['*']);

    $response = $this->postJson('/api/signals/collect/browser-event', [
        'write_key' => 'open-allowlist-key',
        'event_name' => 'custom.clicked',
        'properties' => [
            'email' => 'private@example.com',
            'cart_id' => 'cart-9',
        ],
    ]);

    $response->assertAccepted();

    $event = SignalEvent::query()->withoutOwnerScope()->firstOrFail();

    expect($event->properties)->toBe(['cart_id' => 'cart-9']);
});

it('drops list-shaped properties instead of failing', function (): void {
    createPrivacyProperty('list-props-key');

    $response = $this->postJson('/api/signals/collect/browser-event', [
        'write_key' => 'list-props-key',
        'event_name' => 'custom.clicked',
        'properties' => ['unexpected', 'list', 'shape'],
    ]);

    $response->assertAccepted();

    $event = SignalEvent::query()->withoutOwnerScope()->firstOrFail();

    expect($event->properties)->toBeNull();
});

it('rejects raw cookie values from browser event properties', function (): void {
    createPrivacyProperty('cookie-props-key');

    $response = $this->postJson('/api/signals/collect/browser-event', [
        'write_key' => 'cookie-props-key',
        'event_name' => 'custom.clicked',
        'properties' => [
            'cookie_value' => 'session-material-abc',
            'cart_id' => 'cart-7',
        ],
    ]);

    $response->assertAccepted();

    $event = SignalEvent::query()->withoutOwnerScope()->firstOrFail();

    expect($event->properties)->toBe(['cart_id' => 'cart-7']);
});

it('deduplicates browser retries that share an idempotency key', function (): void {
    $property = createPrivacyProperty('browser-idempotency-key');

    $payload = [
        'write_key' => 'browser-idempotency-key',
        'event_name' => 'cart.snapshot.synced',
        'event_category' => 'cart',
        'idempotency_key' => 'browser-retry-1',
        'properties' => ['cart_id' => 'cart-3'],
    ];

    $this->postJson('/api/signals/collect/browser-event', $payload)->assertAccepted();
    $second = $this->postJson('/api/signals/collect/browser-event', $payload)->assertAccepted();

    expect(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(1)
        ->and($second->json('data.event_id'))->toBe(
            SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->firstOrFail()->id
        );
});

it('rejects write keys sent through the query string', function (): void {
    createPrivacyProperty('query-key-property');

    $this->postJson('/api/signals/collect/browser-event?write_key=query-key-property', [
        'write_key' => 'query-key-property',
        'event_name' => 'custom.clicked',
    ])->assertUnprocessable();

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('measures payload byte caps in bytes for multibyte content', function (): void {
    createPrivacyProperty('multibyte-cap-key');
    config()->set('signals.ingestion.browser.max_bytes', 1024);
    config()->set('signals.ingestion.browser.max_string_bytes', 100000);

    $this->postJson('/api/signals/collect/browser-event', [
        'write_key' => 'multibyte-cap-key',
        'event_name' => 'custom.large',
        'properties' => ['title' => str_repeat('é', 600)],
    ])->assertUnprocessable();

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('falls back to now for invalid seen_at values instead of failing', function (): void {
    $property = createPrivacyProperty('seen-at-key');

    $identity = app(IdentifySignalIdentity::class)->handle($property, [
        'external_id' => 'customer-bad-seen-at',
        'seen_at' => 'not-a-date',
    ]);

    expect($identity->exists)->toBeTrue()
        ->and($identity->last_seen_at)->not->toBeNull();
});
