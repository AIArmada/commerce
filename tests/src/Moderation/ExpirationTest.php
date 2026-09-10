<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Moderation\Actions\ExpireModerationBlocksAction;
use AIArmada\Moderation\Enums\BlockReason;
use AIArmada\Moderation\Enums\BlockStatus;
use AIArmada\Moderation\Models\Block;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;

test('the expiry sweep transitions expired active blocks for every owner', function (): void {
    config()->set('moderation.owner.enabled', true);

    $ownerA = User::query()->create([
        'name' => 'Expiry Owner A',
        'email' => 'expiry-owner-a-' . uniqid() . '@example.test',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Expiry Owner B',
        'email' => 'expiry-owner-b-' . uniqid() . '@example.test',
        'password' => 'secret',
    ]);
    $now = CarbonImmutable::parse('2026-09-11 12:00:00');

    $expired = OwnerContext::withOwner($ownerA, fn (): Block => Block::create([
        'reason' => BlockReason::Spam,
        'status' => BlockStatus::Active,
        'expires_at' => $now->subMinute(),
    ]));
    $active = OwnerContext::withOwner($ownerB, fn (): Block => Block::create([
        'reason' => BlockReason::Spam,
        'status' => BlockStatus::Active,
        'expires_at' => $now->addMinute(),
    ]));

    $processed = OwnerContext::withOwner(null, fn (): int => app(ExpireModerationBlocksAction::class)->execute($now));

    expect($processed)->toBe(1)
        ->and(OwnerContext::withOwner($ownerA, fn (): Block => $expired->fresh()))->status->toBe(BlockStatus::Expired)
        ->and(OwnerContext::withOwner($ownerB, fn (): Block => $active->fresh()))->status->toBe(BlockStatus::Active);
});

test('the expiry command invokes the owner-safe sweep', function (): void {
    config()->set('moderation.owner.enabled', true);

    $block = Block::create([
        'reason' => BlockReason::Other,
        'status' => BlockStatus::Active,
        'expires_at' => CarbonImmutable::now()->subMinute(),
    ]);

    OwnerContext::withOwner(null, function () use (&$exitCode): void {
        $exitCode = Artisan::call('moderation:expire-blocks');
    });

    expect($exitCode)->toBe(0)
        ->and($block->fresh()->status)->toBe(BlockStatus::Expired)
        ->and(Artisan::output())->toContain('Expired 1 moderation block(s).');
});
