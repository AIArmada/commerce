<?php

declare(strict_types=1);

use AIArmada\Links\Actions\CreateLink;
use AIArmada\Links\Actions\DeactivateLink;
use AIArmada\Links\Actions\ReactivateLink;
use AIArmada\Links\Actions\RecordLinkClick;
use AIArmada\Links\Events\LinkClickLimitReached;
use AIArmada\Links\Events\LinkExpired;
use AIArmada\Links\Models\LinkClick;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

const LINKS_LIFECYCLE_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

test('expired links return gone and fire an event', function (): void {
    Event::fake([LinkExpired::class]);

    CreateLink::run([
        'name' => 'Expired',
        'slug' => 'expired-link',
        'destination_url' => 'https://merchant.example/expired',
        'expires_at' => CarbonImmutable::now()->subDay()->toDateTimeString(),
    ]);

    $this->get('/go/expired-link')->assertGone();

    Event::assertDispatched(LinkExpired::class);
});

test('links with exhausted click limits return gone and fire an event', function (): void {
    Event::fake([LinkClickLimitReached::class]);

    $link = CreateLink::run([
        'name' => 'Limited',
        'slug' => 'limited-link',
        'destination_url' => 'https://merchant.example/limited',
        'max_clicks' => 1,
    ]);

    RecordLinkClick::run($link, ['user_agent' => LINKS_LIFECYCLE_UA]);

    expect($link->refresh()->human_clicks)->toBe(1);

    $this->get('/go/limited-link')->assertGone();

    Event::assertDispatched(LinkClickLimitReached::class);
});

test('deactivated links return gone until reactivated', function (): void {
    $link = CreateLink::run([
        'name' => 'Toggled',
        'slug' => 'toggled-link',
        'destination_url' => 'https://merchant.example/toggled',
    ]);

    DeactivateLink::run($link);

    $this->get('/go/toggled-link')->assertGone();

    ReactivateLink::run($link);

    $this->get('/go/toggled-link')->assertRedirect('https://merchant.example/toggled');
});

test('prune command deletes only old clicks', function (): void {
    $link = CreateLink::run([
        'name' => 'Prunable',
        'slug' => 'prunable-link',
        'destination_url' => 'https://merchant.example/prunable',
    ]);

    LinkClick::query()->create([
        'link_id' => $link->id,
        'occurred_at' => CarbonImmutable::now()->subDays(400),
        'owner_type' => $link->owner_type,
        'owner_id' => $link->owner_id,
    ]);

    LinkClick::query()->create([
        'link_id' => $link->id,
        'occurred_at' => CarbonImmutable::now(),
        'owner_type' => $link->owner_type,
        'owner_id' => $link->owner_id,
    ]);

    $this->artisan('links:prune-clicks')->assertExitCode(0);

    expect(LinkClick::query()->count())->toBe(1);
});
