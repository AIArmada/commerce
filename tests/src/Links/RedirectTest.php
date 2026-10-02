<?php

declare(strict_types=1);

use AIArmada\Links\Actions\CreateLink;
use AIArmada\Links\Actions\RedirectToLink;
use AIArmada\Links\Events\LinkClicked;
use AIArmada\Links\Models\LinkClick;
use Illuminate\Http\Request;
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

test('redirect forwards custom UTM keys and preserves their spelling', function (): void {
    CreateLink::run([
        'name' => 'Custom campaign', 'slug' => 'custom-campaign',
        'destination_url' => 'https://merchant.example/offer',
    ]);

    $this->get('/go/custom-campaign?utm_creative=hero-video&utm_audience=lookalike&UtM_Placement2=bio')
        ->assertRedirect('https://merchant.example/offer?utm_creative=hero-video&utm_audience=lookalike&UtM_Placement2=bio');
});

test('baked custom UTM parameters win over incoming and destination values', function (): void {
    CreateLink::run([
        'name' => 'Baked campaign', 'slug' => 'baked-campaign',
        'destination_url' => 'https://merchant.example/offer?utm_creative=destination&utm_audience=destination',
        'parameters' => ['utm_creative' => 'baked'],
    ]);

    $this->get('/go/baked-campaign?utm_creative=spoofed&utm_audience=incoming')
        ->assertRedirect('https://merchant.example/offer?utm_creative=baked&utm_audience=incoming');
});

test('redirect ignores malformed UTM keys and values', function (array $query): void {
    $link = CreateLink::run([
        'name' => 'Malformed campaign', 'slug' => 'malformed-campaign',
        'destination_url' => 'https://merchant.example/offer?utm_creative=destination',
    ]);
    $request = Request::create('/go/malformed-campaign', 'GET', $query);

    $response = app(RedirectToLink::class)->asController($request, $link->slug);
    expect($response->getTargetUrl())->toBe('https://merchant.example/offer?utm_creative=destination');
})->with([
    'empty key' => [['' => 'value']],
    'numeric key' => [[0 => 'value']],
    'empty suffix' => [['utm_' => 'value']],
    'invalid suffix' => [['utm_creative-extra' => 'value']],
    'non ASCII suffix' => [['utm_créative' => 'value']],
    'key too long' => [['utm_' . str_repeat('a', 97) => 'value']],
    'empty value' => [['utm_creative' => '']],
    'array value' => [['utm_creative' => ['nested' => 'value']]],
    'integer value' => [['utm_creative' => 42]],
    'null value' => [['utm_creative' => null]],
    'value too long' => [['utm_creative' => str_repeat('a', 501)]],
]);

test('redirect accepts UTM keys and values at the length limits', function (): void {
    CreateLink::run([
        'name' => 'Length boundary', 'slug' => 'utm-boundary',
        'destination_url' => 'https://merchant.example/offer',
    ]);
    $query = ['utm_' . str_repeat('a', 96) => str_repeat('é', 500)];

    $this->get('/go/utm-boundary?' . http_build_query($query))
        ->assertRedirect('https://merchant.example/offer?' . http_build_query($query));
});

test('UTM forwarding preserves defaults destination incoming and baked precedence', function (): void {
    CreateLink::run([
        'name' => 'Precedence', 'slug' => 'utm-precedence',
        'destination_url' => 'https://merchant.example/offer?utm_source=destination&utm_campaign=destination&utm_term=destination&gclid=destination&merchant=keep#details',
        'utm_defaults' => [
            'utm_source' => 'default', 'utm_medium' => 'default', 'utm_campaign' => 'default',
            'utm_term' => 'default', 'utm_content' => 'default',
        ],
        'parameters' => ['utm_term' => 'baked', 'utm_creative' => 'baked', 'fbclid' => 'baked-click'],
    ]);

    $response = $this->get('/go/utm-precedence?utm_campaign=incoming&utm_term=incoming&utm_content=incoming&utm_creative=incoming&gclid=incoming&fbclid=incoming');
    $response->assertStatus(302);
    $url = $response->headers->get('Location');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect($query)->toBe([
        'utm_source' => 'destination', 'utm_campaign' => 'incoming', 'utm_term' => 'baked',
        'gclid' => 'incoming', 'merchant' => 'keep', 'utm_medium' => 'default',
        'utm_content' => 'incoming', 'utm_creative' => 'baked', 'fbclid' => 'baked-click',
    ])->and(parse_url($url, PHP_URL_FRAGMENT))->toBe('details');
});
