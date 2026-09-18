<?php

declare(strict_types=1);

use AIArmada\Links\Actions\CreateLink;
use AIArmada\Links\Events\LinkClicked;
use AIArmada\Links\Models\LinkClick;
use Illuminate\Support\Facades\Event;

const LINKS_CHROME_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
const LINKS_BOT_UA = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

test('redirects and records a click with merged UTM values', function (): void {
    Event::fake([LinkClicked::class]);

    $link = CreateLink::run([
        'name' => 'Camera deal',
        'slug' => 'camera-redirect',
        'destination_url' => 'https://merchant.example/item?aff=1',
        'utm_defaults' => ['utm_source' => 'newsletter', 'utm_medium' => 'email'],
    ]);

    $response = $this->withHeaders([
        'User-Agent' => LINKS_CHROME_UA,
        'Referer' => 'https://example.com/blog',
    ])->get('/go/camera-redirect?utm_medium=social');

    $response->assertRedirect('https://merchant.example/item?aff=1&utm_source=newsletter&utm_medium=social');

    $click = LinkClick::query()->first();

    expect($click)->not->toBeNull();
    expect($click->link_id)->toBe($link->id);
    expect($click->is_bot)->toBeFalse();
    expect($click->browser)->toBe('Chrome');
    expect($click->referrer)->toBe('https://example.com/blog');
    expect($click->utm_source)->toBeNull();
    expect($click->utm_medium)->toBe('social');

    $link->refresh();
    expect($link->total_clicks)->toBe(1);
    expect($link->human_clicks)->toBe(1);
    expect($link->first_clicked_at)->not->toBeNull();
    expect($link->last_clicked_at)->not->toBeNull();

    Event::assertDispatched(LinkClicked::class);
});

test('unknown slugs return 404', function (): void {
    $this->get('/go/no-such-slug')->assertNotFound();
    expect(LinkClick::query()->count())->toBe(0);
});

test('bot clicks are flagged and excluded from human counts', function (): void {
    $link = CreateLink::run([
        'name' => 'Bot target',
        'slug' => 'bot-target',
        'destination_url' => 'https://merchant.example/bot',
    ]);

    $this->withHeaders(['User-Agent' => LINKS_BOT_UA])
        ->get('/go/bot-target')
        ->assertRedirect('https://merchant.example/bot');

    expect(LinkClick::query()->where('is_bot', true)->count())->toBe(1);

    $link->refresh();
    expect($link->total_clicks)->toBe(1);
    expect($link->human_clicks)->toBe(0);
});

test('bot recording can be disabled entirely', function (): void {
    config()->set('links.features.tracking.bots.record', false);

    $link = CreateLink::run([
        'name' => 'No bots',
        'slug' => 'no-bots',
        'destination_url' => 'https://merchant.example/nobots',
    ]);

    $this->withHeaders(['User-Agent' => LINKS_BOT_UA])
        ->get('/go/no-bots')
        ->assertRedirect('https://merchant.example/nobots');

    expect(LinkClick::query()->count())->toBe(0);

    $link->refresh();
    expect($link->total_clicks)->toBe(0);
});
