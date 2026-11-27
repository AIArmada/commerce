<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Links\Actions\CreateLink;
use AIArmada\Links\Actions\DeactivateLink;
use AIArmada\Links\Actions\DeleteLink;
use AIArmada\Links\Actions\UpdateLink;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Models\LinkClick;
use Illuminate\Database\Eloquent\ModelNotFoundException;

test('link reads are isolated between owners', function (): void {
    $ownerA = User::create(['name' => 'Owner A', 'email' => 'owner-a-' . uniqid() . '@example.com', 'password' => 'secret']);
    $ownerB = User::create(['name' => 'Owner B', 'email' => 'owner-b-' . uniqid() . '@example.com', 'password' => 'secret']);

    OwnerContext::withOwner($ownerA, fn () => CreateLink::run([
        'name' => 'Owner A link',
        'slug' => 'owner-a-link',
        'destination_url' => 'https://merchant.example/a',
    ]));

    OwnerContext::withOwner($ownerB, function (): void {
        expect(Link::query()->count())->toBe(0);
        expect(LinkClick::query()->count())->toBe(0);
    });

    OwnerContext::withOwner($ownerA, function (): void {
        expect(Link::query()->count())->toBe(1);
    });
});

test('public redirects work without owner context and keep the link owner on clicks', function (): void {
    $owner = User::create(['name' => 'Link Owner', 'email' => 'link-owner-' . uniqid() . '@example.com', 'password' => 'secret']);

    $link = OwnerContext::withOwner($owner, fn () => CreateLink::run([
        'name' => 'Public link',
        'slug' => 'public-link',
        'destination_url' => 'https://merchant.example/public',
    ]));

    OwnerContext::withOwner(null, function (): void {
        $this->get('/go/public-link')->assertRedirect('https://merchant.example/public');
    });

    $click = LinkClick::query()->withoutOwnerScope()->first();

    expect($click)->not->toBeNull();
    expect($click->owner_type)->toBe($link->owner_type);
    expect($click->owner_id)->toBe($link->owner_id);
});

test('link management re-resolves supplied models under the current owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $link = OwnerContext::withOwner($ownerA, fn () => CreateLink::run([
        'name' => 'Owner A',
        'slug' => 'owner-a-mutation',
        'destination_url' => 'https://merchant.example/a',
    ]));

    OwnerContext::withOwner($ownerB, function () use ($link): void {
        expect(fn () => UpdateLink::run($link, ['name' => 'Forged']))
            ->toThrow(ModelNotFoundException::class)
            ->and(fn () => DeactivateLink::run($link))->toThrow(ModelNotFoundException::class)
            ->and(fn () => app(DeleteLink::class)->handle($link))->toThrow(ModelNotFoundException::class);
    });

    OwnerContext::withOwner($ownerA, function () use ($link): void {
        expect($link->fresh()->name)->toBe('Owner A')
            ->and($link->fresh()->deactivated_at)->toBeNull();
    });
});
