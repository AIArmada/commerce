<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;
use Illuminate\Auth\Access\AuthorizationException;

it('isolates event reads and writes by owner', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Event Owner A',
        'email' => 'event-owner-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Event Owner B',
        'email' => 'event-owner-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $eventA = OwnerContext::withOwner($ownerA, function (): Event {
        return Event::factory()->create();
    });

    $eventB = OwnerContext::withOwner($ownerB, function (): Event {
        return Event::factory()->create();
    });

    $ownerAEventIds = OwnerContext::withOwner($ownerA, function () use ($eventA): array {
        return Event::query()->pluck('id')->all();
    });

    expect($ownerAEventIds)->toEqual([$eventA->id]);

    expect(function () use ($ownerA, $eventB): void {
        OwnerContext::withOwner($ownerA, function () use ($eventB): void {
            OwnerWriteGuard::findOrFailForOwner(Event::class, $eventB->id);
        });
    })->toThrow(AuthorizationException::class);
});
