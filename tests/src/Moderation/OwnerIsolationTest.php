<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Moderation\Actions\BlockEntityAction;
use AIArmada\Moderation\Actions\RecordModerationAction;
use AIArmada\Moderation\Enums\BlockReason;
use AIArmada\Moderation\Enums\ModerationActionType;
use AIArmada\Moderation\Models\Block;
use AIArmada\Moderation\Models\ModerationAction;

it('isolates blocks and moderation actions by owner', function (): void {
    config()->set('moderation.features.owner.enabled', true);

    $ownerA = User::query()->create([
        'name' => 'Moderation Owner A',
        'email' => 'moderation-owner-a@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Moderation Owner B',
        'email' => 'moderation-owner-b@example.com',
        'password' => 'secret',
    ]);
    $target = User::query()->create([
        'name' => 'Moderation Target',
        'email' => 'moderation-target@example.com',
        'password' => 'secret',
    ]);

    $blockA = OwnerContext::withOwner(
        $ownerA,
        fn (): Block => app(BlockEntityAction::class)->execute($target, reason: BlockReason::Spam),
    );
    $blockB = OwnerContext::withOwner(
        $ownerB,
        fn (): Block => app(BlockEntityAction::class)->execute($target, reason: BlockReason::Harassment),
    );
    $actionA = OwnerContext::withOwner(
        $ownerA,
        fn (): ModerationAction => app(RecordModerationAction::class)->execute(
            $target,
            ModerationActionType::Warn,
            'Owner A warning.',
        ),
    );
    $actionB = OwnerContext::withOwner(
        $ownerB,
        fn (): ModerationAction => app(RecordModerationAction::class)->execute(
            $target,
            ModerationActionType::Approve,
            'Owner B approval.',
        ),
    );

    expect(OwnerContext::withOwner($ownerA, fn (): array => Block::query()->pluck('id')->all()))
        ->toBe([$blockA->id]);
    expect(OwnerContext::withOwner($ownerB, fn (): array => Block::query()->pluck('id')->all()))
        ->toBe([$blockB->id]);
    expect(OwnerContext::withOwner($ownerA, fn (): array => ModerationAction::query()->pluck('id')->all()))
        ->toBe([$actionA->id]);
    expect(OwnerContext::withOwner($ownerB, fn (): array => ModerationAction::query()->pluck('id')->all()))
        ->toBe([$actionB->id]);
});

it('does not allow moderation owner mass assignment', function (): void {
    $block = new Block;
    $block->fill(['owner_type' => User::class, 'owner_id' => 'other-owner']);

    $action = new ModerationAction;
    $action->fill(['owner_type' => User::class, 'owner_id' => 'other-owner']);

    expect($block->owner_type)->toBeNull()
        ->and($block->owner_id)->toBeNull()
        ->and($action->owner_type)->toBeNull()
        ->and($action->owner_id)->toBeNull();
});
