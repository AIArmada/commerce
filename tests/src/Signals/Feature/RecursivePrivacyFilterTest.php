<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Actions\IdentifySignalIdentity;
use AIArmada\Signals\Actions\IngestSignalEvent;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\SignalPropertyFilter;

uses(SignalsTestCase::class);

function sig01_filter(): SignalPropertyFilter
{
    return app(SignalPropertyFilter::class);
}

function sig01_property(string $slug): TrackedProperty
{
    return TrackedProperty::query()->create([
        'name' => 'Recursive Privacy ' . $slug,
        'slug' => 'sig01-' . $slug,
        'write_key' => 'sig01-' . $slug,
    ]);
}

/**
 * @return array<string, mixed>
 */
function sig01_wrap_depth(int $depth, mixed $value): array
{
    for ($level = 0; $level < $depth; $level++) {
        $value = ['level_' . $level => $value];
    }

    /** @var array<string, mixed> $value */
    return $value;
}

it('filters blocked keys at every nested map depth while keeping safe siblings', function (): void {
    config()->set('signals.features.privacy.property_allowlist', ['*']);

    $filtered = sig01_filter()->filter([
        'context' => [
            'email' => 'nested@example.test',
            'phone' => '+60123456789',
            'plan' => 'pro',
            'billing' => [
                'name' => 'Nested Name',
                'metadata' => ['source' => 'audit'],
                'tier' => 'gold',
            ],
        ],
        'cart_id' => 'cart-1',
    ]);

    expect($filtered)->toBe([
        'context' => [
            'plan' => 'pro',
            'billing' => [
                'tier' => 'gold',
            ],
        ],
        'cart_id' => 'cart-1',
    ]);
});

it('filters blocked keys inside lists, preserves list shape, and drops emptied containers', function (): void {
    config()->set('signals.features.privacy.property_allowlist', ['*']);

    $filtered = sig01_filter()->filter([
        'items' => [
            ['email' => 'first@example.test'],
            ['sku' => 'sku-2', 'name' => 'Second'],
            ['sku' => 'sku-3'],
        ],
        'tags' => ['alpha', 7, null],
        'emptied' => ['email' => 'only@example.test'],
    ]);

    expect($filtered)->toBe([
        'items' => [
            ['sku' => 'sku-2'],
            ['sku' => 'sku-3'],
        ],
        'tags' => ['alpha', 7, null],
    ]);
});

it('gates only top-level keys with the allowlist while filtering descendants recursively', function (): void {
    config()->set('signals.features.privacy.property_allowlist', ['context', 'cart_id']);

    $filtered = sig01_filter()->filter([
        'context' => [
            'email' => 'nested@example.test',
            'plan' => 'pro',
            'external' => 'kept-when-nested',
        ],
        'cart_id' => 'cart-2',
        'untracked_top_level' => 'dropped',
    ]);

    expect($filtered)->toBe([
        'context' => [
            'plan' => 'pro',
            'external' => 'kept-when-nested',
        ],
        'cart_id' => 'cart-2',
    ]);
});

it('drops subtrees beyond the traversal depth bound without throwing', function (): void {
    config()->set('signals.features.privacy.property_allowlist', ['*']);

    $withinBounds = sig01_filter()->filter(sig01_wrap_depth(6, [
        'email' => 'within@example.test',
        'marker' => 'within-marker',
    ]));

    $encodedWithin = json_encode($withinBounds, JSON_THROW_ON_ERROR);

    expect($encodedWithin)->toContain('within-marker')
        ->and($encodedWithin)->not->toContain('within@example.test');

    $beyondBounds = sig01_filter()->filter(['keep' => 'kept'] + sig01_wrap_depth(15, [
        'email' => 'beyond@example.test',
        'marker' => 'beyond-marker',
    ]));

    $encodedBeyond = json_encode($beyondBounds, JSON_THROW_ON_ERROR);

    expect($beyondBounds['keep'])->toBe('kept')
        ->and($encodedBeyond)->not->toContain('beyond@example.test')
        ->and($encodedBeyond)->not->toContain('beyond-marker');
});

it('bounds node consumption for very wide payloads without throwing', function (): void {
    config()->set('signals.features.privacy.property_allowlist', ['*']);

    $entries = [];

    for ($index = 0; $index < 1200; $index++) {
        $entries[] = ['email' => 'node-' . $index . '@example.test', 'sku' => 'sku-' . $index];
    }

    $filtered = sig01_filter()->filter(['keep' => 'kept', 'entries' => $entries]);

    $encoded = json_encode($filtered, JSON_THROW_ON_ERROR);

    expect($filtered['keep'])->toBe('kept')
        ->and($encoded)->not->toContain('@example.test')
        ->and(mb_substr_count($encoded, 'sku-'))->toBeLessThan(1200);
});

it('never persists nested personal information from public identify traits', function (): void {
    sig01_property('identify-traits');
    config()->set('signals.features.privacy.property_allowlist', ['*']);

    $this->postJson('/api/signals/collect/identify', [
        'write_key' => 'sig01-identify-traits',
        'external_id' => 'customer-nested-traits',
        'traits' => [
            'context' => ['email' => 'nested@example.test', 'plan' => 'pro'],
            'billing' => ['name' => 'Nested Name', 'country' => 'MY'],
            'items' => [['email' => 'row@example.test', 'sku' => 'sku-1']],
            'cart_metadata' => ['cart_id' => 'cart-hidden'],
        ],
    ])->assertAccepted();

    $identity = SignalIdentity::query()->withoutOwnerScope()
        ->where('external_id', 'customer-nested-traits')
        ->firstOrFail();

    expect($identity->traits)->toBe([
        'context' => ['plan' => 'pro'],
        'billing' => ['country' => 'MY'],
        'items' => [['sku' => 'sku-1']],
    ]);
});

it('never persists nested personal information from public browser events', function (): void {
    sig01_property('browser-props');
    config()->set('signals.features.privacy.property_allowlist', ['*']);

    $this->postJson('/api/signals/collect/browser-event', [
        'write_key' => 'sig01-browser-props',
        'event_name' => 'custom.recursive',
        'properties' => [
            'context' => ['email' => 'nested@example.test', 'plan' => 'pro'],
            'items' => [['phone' => '+60123456789', 'sku' => 'sku-9']],
            'cart_id' => 'cart-4',
        ],
    ])->assertAccepted();

    $event = SignalEvent::query()->withoutOwnerScope()
        ->where('tracked_property_id', TrackedProperty::query()->withoutOwnerScope()->where('slug', 'sig01-browser-props')->firstOrFail()->id)
        ->where('event_name', 'custom.recursive')
        ->firstOrFail();

    expect($event->properties)->toBe([
        'context' => ['plan' => 'pro'],
        'items' => [['sku' => 'sku-9']],
        'cart_id' => 'cart-4',
    ]);
});

it('filters nested traits on the direct untrusted identify action', function (): void {
    $property = sig01_property('direct-identify');
    config()->set('signals.features.privacy.property_allowlist', ['*']);

    $identity = app(IdentifySignalIdentity::class)->handle($property, [
        'external_id' => 'customer-direct-nested',
        'traits' => [
            'customer' => ['customer_email' => 'direct@example.test', 'tier' => 'gold'],
            'tags' => [['last_name' => 'Hidden', 'label' => 'vip']],
        ],
    ]);

    expect($identity->fresh()->traits)->toBe([
        'customer' => ['tier' => 'gold'],
        'tags' => [['label' => 'vip']],
    ]);
});

it('keeps trusted recorder properties unfiltered by the recursive privacy filter', function (): void {
    $property = sig01_property('trusted-recorder');

    $event = app(IngestSignalEvent::class)->handle($property, [
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'properties' => ['context' => ['email' => 'trusted@example.test', 'plan' => 'pro']],
    ], trusted: true);

    expect($event->fresh()->properties)->toBe([
        'context' => ['email' => 'trusted@example.test', 'plan' => 'pro'],
    ]);
});
