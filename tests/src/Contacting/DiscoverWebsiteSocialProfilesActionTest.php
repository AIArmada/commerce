<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use AIArmada\Contacting\Actions\DiscoverWebsiteSocialProfilesAction;
use Illuminate\Support\Facades\Http;

test('discover website social profiles extracts direct social urls and html anchor links', function (): void {
    $action = app(DiscoverWebsiteSocialProfilesAction::class);

    $direct = $action->handle('https://www.instagram.com/ilmu360official');
    expect($direct)->toHaveCount(1)
        ->and($direct[0]['platform'])->toBe('instagram')
        ->and($direct[0]['handle'])->toBe('ilmu360official');

    $html = '<a href="https://youtube.com/@ilmu360">YouTube</a><a href="https://t.me/ilmu360channel">Telegram</a>';
    $extracted = $action->extractSocialProfiles($html);
    expect($extracted)->toHaveCount(2);

    app()->instance(PublicHttpUrlGuard::class, new PublicHttpUrlGuard(static fn (): array => ['93.184.216.34']));
    Http::fake([
        'https://ilmu360.example*' => Http::response($html, 200, ['Content-Type' => 'text/html']),
    ]);

    $fetched = app(DiscoverWebsiteSocialProfilesAction::class)->handle('https://ilmu360.example');
    expect($fetched)->toHaveCount(2);
});
