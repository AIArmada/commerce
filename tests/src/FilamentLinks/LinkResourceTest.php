<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentLinks\Resources\LinkResource;
use AIArmada\Links\Actions\CreateLink;

test('link resource reads navigation from config', function (): void {
    expect(LinkResource::getNavigationGroup())->toBe('Links');
    expect(LinkResource::getNavigationSort())->toBe(10);
    expect(array_keys(LinkResource::getPages()))->toBe(['index', 'create', 'edit']);
});

test('link resource queries are owner-scoped', function (): void {
    $ownerA = User::create(['name' => 'Res A', 'email' => 'res-a-' . uniqid() . '@example.com', 'password' => 'secret']);
    $ownerB = User::create(['name' => 'Res B', 'email' => 'res-b-' . uniqid() . '@example.com', 'password' => 'secret']);

    OwnerContext::withOwner($ownerA, fn () => CreateLink::run([
        'name' => 'Scoped link',
        'slug' => 'scoped-link',
        'destination_url' => 'https://merchant.example/scoped',
    ]));

    OwnerContext::withOwner($ownerB, function (): void {
        expect(LinkResource::getEloquentQuery()->count())->toBe(0);
    });

    OwnerContext::withOwner($ownerA, function (): void {
        expect(LinkResource::getEloquentQuery()->count())->toBe(1);
    });
});
