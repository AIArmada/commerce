<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Engagement\Models\BookmarkCollection;
use Illuminate\Auth\Access\AuthorizationException;

it('isolates bookmark collections by owner', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Engagement Owner A',
        'email' => 'engagement-owner-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Engagement Owner B',
        'email' => 'engagement-owner-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $collectionA = OwnerContext::withOwner($ownerA, function (): BookmarkCollection {
        return BookmarkCollection::query()->create([
            'name' => 'Owner A Collection',
            'visibility' => 'private',
            'status' => 'active',
            'sort_order' => 0,
        ]);
    });

    $collectionB = OwnerContext::withOwner($ownerB, function (): BookmarkCollection {
        return BookmarkCollection::query()->create([
            'name' => 'Owner B Collection',
            'visibility' => 'private',
            'status' => 'active',
            'sort_order' => 0,
        ]);
    });

    $ownerACollectionIds = OwnerContext::withOwner($ownerA, function (): array {
        return BookmarkCollection::query()->pluck('id')->all();
    });

    expect($ownerACollectionIds)->toEqual([$collectionA->id]);

    expect(function () use ($ownerA, $collectionB): void {
        OwnerContext::withOwner($ownerA, function () use ($collectionB): void {
            OwnerWriteGuard::findOrFailForOwner(BookmarkCollection::class, $collectionB->id);
        });
    })->toThrow(AuthorizationException::class);
});
