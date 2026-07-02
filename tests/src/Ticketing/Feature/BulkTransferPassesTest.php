<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Ticketing\Actions\BulkTransferPassesAction;
use AIArmada\Ticketing\Exceptions\BulkTransferSizeExceededException;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('transfers multiple passes', function (): void {
    $passes = Pass::factory()->count(3)->create();
    $newHolder = PassHolder::factory()->make(['name' => 'Bulk Recipient']);

    $results = app(BulkTransferPassesAction::class)->handle(
        $passes->pluck('id')->toArray(),
        $newHolder,
    );

    expect($results)->toHaveCount(3);
});

it('throws exception when exceeding max size', function (): void {
    config()->set('ticketing.transfers.bulk_max_size', 2);

    $passes = Pass::factory()->count(3)->create();

    expect(fn () => app(BulkTransferPassesAction::class)->handle(
        $passes->pluck('id')->all(),
        PassHolder::factory()->make(),
    ))->toThrow(BulkTransferSizeExceededException::class);
});

it('requires at least one pass id', function (): void {
    expect(fn () => app(BulkTransferPassesAction::class)->handle(
        [],
        PassHolder::factory()->make(),
    ))->toThrow(InvalidArgumentException::class);
});

it('fails when any pass id is outside the current owner scope', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Bulk Owner A',
        'email' => 'bulk-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Bulk Owner B',
        'email' => 'bulk-owner-b@example.com',
        'password' => 'secret',
    ]);

    $ownedPass = OwnerContext::withOwner($ownerA, fn (): Pass => Pass::factory()->create());
    $crossTenantPass = OwnerContext::withOwner($ownerB, fn (): Pass => Pass::factory()->create());

    expect(fn () => OwnerContext::withOwner($ownerA, fn () => app(BulkTransferPassesAction::class)->handle(
        [$ownedPass->id, $crossTenantPass->id],
        PassHolder::factory()->make(),
    )))->toThrow(ModelNotFoundException::class);
});
