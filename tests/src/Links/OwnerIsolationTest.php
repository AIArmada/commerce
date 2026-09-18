<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Links\Actions\CreateLink;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Models\LinkClick;

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
