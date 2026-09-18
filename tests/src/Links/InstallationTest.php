<?php

declare(strict_types=1);

use AIArmada\Links\LinksServiceProvider;
use Illuminate\Support\Facades\Schema;

test('service provider registers', function (): void {
    $providers = app()->getLoadedProviders();

    expect(isset($providers[LinksServiceProvider::class]))->toBeTrue();
});

test('config publishes and reads correctly', function (): void {
    expect(config('links.database.tables.links'))->toBe('tracked_links');
    expect(config('links.database.tables.clicks'))->toBe('tracked_link_clicks');
    expect(config('links.defaults.slug_length'))->toBe(7);
    expect(config('links.defaults.redirect_status'))->toBe(302);
    expect(config('links.routing.prefix'))->toBe('go');
    expect(config('links.owner.enabled'))->toBeTrue();
    expect(config('links.owner.include_global'))->toBeFalse();
});

test('links tables exist after migration', function (): void {
    expect(Schema::hasTable('tracked_links'))->toBeTrue();
    expect(Schema::hasColumn('tracked_links', 'slug'))->toBeTrue();
    expect(Schema::hasColumn('tracked_links', 'destination_url'))->toBeTrue();
    expect(Schema::hasColumn('tracked_links', 'owner_type'))->toBeTrue();
    expect(Schema::hasColumn('tracked_links', 'owner_id'))->toBeTrue();

    expect(Schema::hasTable('tracked_link_clicks'))->toBeTrue();
    expect(Schema::hasColumn('tracked_link_clicks', 'link_id'))->toBeTrue();
    expect(Schema::hasColumn('tracked_link_clicks', 'is_bot'))->toBeTrue();
});

test('links declares its commerce support dependency', function (): void {
    $manifest = json_decode(
        (string) file_get_contents(__DIR__ . '/../../../packages/links/composer.json'),
        true,
    );

    expect($manifest['require']['aiarmada/commerce-support'] ?? null)->not->toBeNull();
});
