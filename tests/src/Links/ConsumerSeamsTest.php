<?php

declare(strict_types=1);

use AIArmada\Links\Actions\CreateLink;
use AIArmada\Links\Actions\GenerateLinkUrl;
use AIArmada\Links\Actions\RecordLinkClick;
use AIArmada\Links\Contracts\LinkGateInterface;
use AIArmada\Links\Events\LinkBlocked;
use AIArmada\Links\Events\LinkExpired;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Models\LinkClick;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

test('link parameters merge into the destination and win over incoming query', function (): void {
    CreateLink::run([
        'name' => 'Attributed',
        'slug' => 'attributed-link',
        'destination_url' => 'https://merchant.example/item?aff=0',
        'parameters' => ['aff' => '9', 'sub1' => 'newsletter'],
    ]);

    $this->get('/go/attributed-link?aff=spoofed&utm_source=x')
        ->assertRedirect('https://merchant.example/item?aff=9&utm_source=x&sub1=newsletter');

    expect(LinkClick::query()->count())->toBe(1);
});

test('clicks inherit the link subject and resolve through forSubject scopes', function (): void {
    $link = CreateLink::run([
        'name' => 'Subject link',
        'slug' => 'subject-link',
        'destination_url' => 'https://merchant.example/subject',
        'subject_type' => 'offer-link',
        'subject_id' => (string) Str::uuid(),
    ]);

    $this->get('/go/subject-link')->assertRedirect();

    $click = LinkClick::query()->first();

    expect($click->subject_type)->toBe('offer-link')
        ->and($click->subject_id)->toBe($link->subject_id)
        ->and(LinkClick::query()->where('subject_type', 'offer-link')->where('subject_id', $link->subject_id)->count())->toBe(1);
});

test('forSubject scopes match link and click rows by model', function (): void {
    $link = CreateLink::run([
        'name' => 'Scoped',
        'slug' => 'scoped-link',
        'destination_url' => 'https://merchant.example/scoped',
    ]);

    $link->forceFill([
        'subject_type' => $link->getMorphClass(),
        'subject_id' => (string) $link->getKey(),
    ])->save();

    $this->get('/go/scoped-link')->assertRedirect();

    expect(Link::query()->forSubject($link)->count())->toBe(1)
        ->and(LinkClick::query()->forSubject($link)->count())->toBe(1);
});

test('links without a signature requirement keep plain public urls', function (): void {
    $link = CreateLink::run([
        'name' => 'Plain',
        'slug' => 'plain-link',
        'destination_url' => 'https://merchant.example/plain',
    ]);

    expect(GenerateLinkUrl::run($link))->toBe($link->cloakedUrl());

    $this->get('/go/plain-link')->assertRedirect('https://merchant.example/plain');
});

test('signed links reject unsigned, tampered and expired urls', function (): void {
    $link = CreateLink::run([
        'name' => 'Signed',
        'slug' => 'signed-link',
        'destination_url' => 'https://merchant.example/signed',
        'require_signature' => true,
    ]);

    $url = GenerateLinkUrl::run($link);

    expect($url)->toContain('signature=');

    $this->get('/go/signed-link')->assertForbidden();
    $this->get($url . 'tampered')->assertForbidden();

    $expired = URL::temporarySignedRoute(
        'links.redirect',
        now()->subMinute(),
        ['slug' => $link->slug],
    );

    $this->get($expired)->assertForbidden();
    $this->get($url)->assertRedirect('https://merchant.example/signed');

    expect(LinkClick::query()->count())->toBe(1);
});

test('ad click ids pass through to the destination', function (): void {
    CreateLink::run([
        'name' => 'Ad traffic',
        'slug' => 'ad-traffic',
        'destination_url' => 'https://merchant.example/landing',
    ]);

    $this->get('/go/ad-traffic?gclid=abc123&fbclid=fb456&utm_source=google')
        ->assertRedirect('https://merchant.example/landing?utm_source=google&gclid=abc123&fbclid=fb456');

    expect(LinkClick::query()->first()->properties)->toBe([
        'click_ids' => ['gclid' => 'abc123', 'fbclid' => 'fb456'],
    ]);
});

test('unknown query parameters are neither forwarded nor stored', function (): void {
    CreateLink::run([
        'name' => 'Clean',
        'slug' => 'clean-link',
        'destination_url' => 'https://merchant.example/clean',
    ]);

    $this->get('/go/clean-link?made_up_param=1')
        ->assertRedirect('https://merchant.example/clean');

    expect(LinkClick::query()->first()->properties)->toBeNull();
});

test('explicit click id context wins over the request', function (): void {
    $link = CreateLink::run([
        'name' => 'Manual',
        'slug' => 'manual-link',
        'destination_url' => 'https://merchant.example/manual',
    ]);

    RecordLinkClick::run($link, [
        'gclid' => 'explicit-gclid',
        'click_ids' => ['fbclid' => 'map-fbclid', 'not_a_click_id' => 'ignored'],
        'properties' => ['source' => 'email'],
    ]);

    expect(LinkClick::query()->first()->properties)->toBe([
        'source' => 'email',
        'click_ids' => ['fbclid' => 'map-fbclid', 'gclid' => 'explicit-gclid'],
    ]);
});

test('blocked links return gone and dispatch reason events', function (): void {
    Event::fake([LinkExpired::class, LinkBlocked::class]);

    CreateLink::run([
        'name' => 'Expired',
        'slug' => 'expired-link',
        'destination_url' => 'https://merchant.example/expired',
        'expires_at' => now()->subDay()->toIso8601String(),
    ]);

    $this->get('/go/expired-link')->assertGone();

    Event::assertDispatched(LinkExpired::class);
    Event::assertNotDispatched(LinkBlocked::class);
    expect(LinkClick::query()->count())->toBe(0);
});

test('a bound gate can block redirects with a custom reason', function (): void {
    Event::fake([LinkBlocked::class]);

    app()->instance(LinkGateInterface::class, new class implements LinkGateInterface
    {
        public function blockedReason(Link $link): ?string
        {
            return $link->slug === 'gated-link' ? 'offer_inactive' : null;
        }
    });

    CreateLink::run([
        'name' => 'Gated',
        'slug' => 'gated-link',
        'destination_url' => 'https://merchant.example/gated',
    ]);

    CreateLink::run([
        'name' => 'Open',
        'slug' => 'open-link',
        'destination_url' => 'https://merchant.example/open',
    ]);

    $this->get('/go/gated-link')->assertGone();
    $this->get('/go/open-link')->assertRedirect('https://merchant.example/open');

    Event::assertDispatched(LinkBlocked::class, fn (LinkBlocked $event): bool => $event->reason === 'offer_inactive'
        && $event->link->slug === 'gated-link');
    expect(LinkClick::query()->count())->toBe(1);
});
