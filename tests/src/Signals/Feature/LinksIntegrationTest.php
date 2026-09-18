<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Links\Events\LinkClicked;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Models\LinkClick;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

uses(SignalsTestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('tracked_link_clicks');
    Schema::dropIfExists('tracked_links');

    Schema::create('tracked_links', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->nullableUuidMorphs('owner');
        $table->string('name');
        $table->string('slug')->unique();
        $table->text('destination_url');
        $table->json('utm_defaults')->nullable();
        $table->unsignedInteger('max_clicks')->nullable();
        $table->unsignedBigInteger('total_clicks')->default(0);
        $table->unsignedBigInteger('human_clicks')->default(0);
        $table->timestampTz('expires_at')->nullable();
        $table->timestampTz('deactivated_at')->nullable();
        $table->timestampTz('first_clicked_at')->nullable();
        $table->timestampTz('last_clicked_at')->nullable();
        $table->timestampsTz();
    });

    Schema::create('tracked_link_clicks', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignUuid('link_id');
        $table->nullableUuidMorphs('owner');
        $table->timestampTz('occurred_at');
        $table->string('ip_address')->nullable();
        $table->text('user_agent')->nullable();
        $table->string('device_type')->nullable();
        $table->string('device_brand')->nullable();
        $table->string('device_model')->nullable();
        $table->string('browser')->nullable();
        $table->string('browser_version', 50)->nullable();
        $table->string('os')->nullable();
        $table->string('os_version', 50)->nullable();
        $table->boolean('is_bot')->default(false);
        $table->text('referrer')->nullable();
        $table->string('utm_source')->nullable();
        $table->string('utm_medium')->nullable();
        $table->string('utm_campaign')->nullable();
        $table->string('utm_content')->nullable();
        $table->string('utm_term')->nullable();
        $table->json('properties')->nullable();
        $table->timestampsTz();
    });
});

function createLinksIntegrationProperty(): TrackedProperty
{
    return TrackedProperty::query()->create([
        'name' => 'Links Property',
        'slug' => 'links-property-' . uniqid(),
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);
}

function createLinksIntegrationClick(array $overrides = []): array
{
    $owner = User::query()->firstOrFail();

    $link = Link::query()->create(array_merge([
        'name' => 'Camera deal',
        'slug' => 'camera-' . uniqid(),
        'destination_url' => 'https://merchant.example/camera?aff=you',
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ], $overrides['link'] ?? []));

    $click = LinkClick::query()->create(array_merge([
        'link_id' => $link->getKey(),
        'occurred_at' => CarbonImmutable::now(),
        'browser' => 'Chrome',
        'os' => 'Mac',
        'device_type' => 'desktop',
        'is_bot' => false,
        'referrer' => 'https://example.com/blog',
        'utm_source' => 'newsletter',
        'utm_medium' => 'email',
        'utm_campaign' => 'may-launch',
        'owner_type' => $link->owner_type,
        'owner_id' => $link->owner_id,
    ], $overrides['click'] ?? []));

    return [$link, $click];
}

it('records a link click as a signal event on the owner property', function (): void {
    $property = createLinksIntegrationProperty();
    [$link, $click] = createLinksIntegrationClick();

    Event::dispatch(new LinkClicked($link, $click));

    $event = SignalEvent::query()->withoutOwnerScope()->sole();

    expect($event->tracked_property_id)->toBe($property->id)
        ->and($event->event_name)->toBe('link.clicked')
        ->and($event->event_category)->toBe('engagement')
        ->and($event->path)->toBe('/go/' . $link->slug)
        ->and($event->referrer)->toBe('https://example.com/blog')
        ->and($event->source)->toBe('newsletter')
        ->and($event->medium)->toBe('email')
        ->and($event->campaign)->toBe('may-launch')
        ->and($event->idempotency_key)->toBe('link-click:' . $click->id)
        ->and($event->source_event_id)->toBe($click->id)
        ->and($event->properties)->toMatchArray([
            'link_id' => $link->id,
            'link_slug' => $link->slug,
            'link_name' => 'Camera deal',
            'destination_host' => 'merchant.example',
            'is_bot' => false,
            'browser' => 'Chrome',
        ]);
});

it('auto-creates a links tracked property when none exists', function (): void {
    [$link, $click] = createLinksIntegrationClick();

    Event::dispatch(new LinkClicked($link, $click));

    $property = TrackedProperty::query()->withoutOwnerScope()->sole();

    expect($property->slug)->toBe('commerce-links');
    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(1);
});

it('is idempotent when the same click is dispatched twice', function (): void {
    createLinksIntegrationProperty();
    [$link, $click] = createLinksIntegrationClick();

    Event::dispatch(new LinkClicked($link, $click));
    Event::dispatch(new LinkClicked($link, $click));

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(1);
});

it('records nothing when the owner has ambiguous properties', function (): void {
    createLinksIntegrationProperty();
    createLinksIntegrationProperty();
    [$link, $click] = createLinksIntegrationClick();

    Event::dispatch(new LinkClicked($link, $click));

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});
