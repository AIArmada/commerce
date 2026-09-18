<?php

declare(strict_types=1);

use AIArmada\Links\Actions\CreateLink;
use AIArmada\Links\Actions\DeactivateLink;
use AIArmada\Links\Actions\ReactivateLink;
use AIArmada\Links\Actions\UpdateLink;
use AIArmada\Links\Events\LinkCreated;
use AIArmada\Links\Events\LinkDeactivated;
use AIArmada\Links\Events\LinkReactivated;
use AIArmada\Links\Events\LinkUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

test('creates a link with an auto-generated slug', function (): void {
    Event::fake([LinkCreated::class]);

    $link = CreateLink::run([
        'name' => 'Camera deal',
        'destination_url' => 'https://merchant.example/camera',
    ]);

    expect($link->slug)->toBeString()->toHaveLength(7);
    expect($link->total_clicks)->toBe(0);
    expect($link->cloakedUrl())->toContain('/go/' . $link->slug);
    Event::assertDispatched(LinkCreated::class);
});

test('creates a link with a custom slug', function (): void {
    $link = CreateLink::run([
        'name' => 'Camera deal',
        'slug' => 'camera-deal',
        'destination_url' => 'https://merchant.example/camera',
        'utm_defaults' => ['utm_source' => 'newsletter'],
    ]);

    expect($link->slug)->toBe('camera-deal');
    expect($link->utm_defaults)->toBe(['utm_source' => 'newsletter']);
});

test('rejects duplicate slugs', function (): void {
    CreateLink::run([
        'name' => 'First',
        'slug' => 'dupe-slug',
        'destination_url' => 'https://merchant.example/a',
    ]);

    CreateLink::run([
        'name' => 'Second',
        'slug' => 'dupe-slug',
        'destination_url' => 'https://merchant.example/b',
    ]);
})->throws(ValidationException::class);

test('rejects reserved slugs', function (): void {
    CreateLink::run([
        'name' => 'Admin',
        'slug' => 'admin',
        'destination_url' => 'https://merchant.example/admin',
    ]);
})->throws(ValidationException::class);

test('rejects non-https destinations by default', function (): void {
    CreateLink::run([
        'name' => 'Insecure',
        'destination_url' => 'http://merchant.example/insecure',
    ]);
})->throws(ValidationException::class);

test('rejects destinations with credentials and enforces allowed hosts', function (): void {
    expect(fn () => CreateLink::run([
        'name' => 'Creds',
        'destination_url' => 'https://user:pass@merchant.example/',
    ]))->toThrow(ValidationException::class);

    config()->set('links.features.security.allowed_hosts', ['merchant.example']);

    expect(fn () => CreateLink::run([
        'name' => 'Other host',
        'destination_url' => 'https://other.example/',
    ]))->toThrow(ValidationException::class);

    $link = CreateLink::run([
        'name' => 'Allowed host',
        'destination_url' => 'https://merchant.example/ok',
    ]);

    expect($link->destination_url)->toBe('https://merchant.example/ok');
});

test('rejects unknown utm default keys', function (): void {
    CreateLink::run([
        'name' => 'Bad UTM',
        'destination_url' => 'https://merchant.example/',
        'utm_defaults' => ['utm_source' => 'news', 'made_up' => 'x'],
    ]);
})->throws(ValidationException::class);

test('updates a link and fires an event', function (): void {
    Event::fake([LinkUpdated::class]);

    $link = CreateLink::run([
        'name' => 'Old name',
        'slug' => 'updatable-link',
        'destination_url' => 'https://merchant.example/old',
    ]);

    $updated = UpdateLink::run($link, [
        'name' => 'New name',
        'destination_url' => 'https://merchant.example/new',
    ]);

    expect($updated->name)->toBe('New name');
    expect($updated->slug)->toBe('updatable-link');
    Event::assertDispatched(LinkUpdated::class);
});

test('clearing the slug on update keeps the current slug', function (): void {
    $link = CreateLink::run([
        'name' => 'Keep slug',
        'slug' => 'keep-my-slug',
        'destination_url' => 'https://merchant.example/keep',
    ]);

    $updated = UpdateLink::run($link, ['slug' => null]);

    expect($updated->slug)->toBe('keep-my-slug');

    $updated = UpdateLink::run($updated, ['slug' => 'new-slug-value']);

    expect($updated->slug)->toBe('new-slug-value');
});

test('deactivation and reactivation are idempotent', function (): void {
    $link = CreateLink::run([
        'name' => 'Toggle',
        'destination_url' => 'https://merchant.example/toggle',
    ]);

    Event::fake([LinkDeactivated::class, LinkReactivated::class]);

    DeactivateLink::run($link);
    DeactivateLink::run($link->refresh());

    Event::assertDispatched(LinkDeactivated::class, 1);
    expect($link->refresh()->deactivated_at)->not->toBeNull();

    ReactivateLink::run($link);
    ReactivateLink::run($link->refresh());

    Event::assertDispatched(LinkReactivated::class, 1);
    expect($link->refresh()->deactivated_at)->toBeNull();
});
