<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Moderation\Actions\BlockEntityAction;
use AIArmada\Moderation\Enums\BlockReason;
use AIArmada\Moderation\Enums\BlockStatus;
use AIArmada\Moderation\Models\Block;

beforeEach(function (): void {
    config()->set('moderation.features.owner.enabled', false);

    $this->blockable = User::create([
        'name' => 'Blockable User',
        'email' => 'blockable-action-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $this->moderator = User::create([
        'name' => 'Moderator',
        'email' => 'moderator-action-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $this->action = app(BlockEntityAction::class);
});

test('creates a block record via the action', function (): void {
    $block = $this->action->execute(
        blockable: $this->blockable,
        blockedBy: $this->moderator,
        reason: BlockReason::Harassment,
        notes: 'Repeated harassment in comments',
        metadata: [],
    );

    expect($block)->toBeInstanceOf(Block::class);
    expect($block->blockable_id)->toBe($this->blockable->id);
    expect($block->blocked_by_id)->toBe($this->moderator->id);
    expect($block->reason->value)->toBe('harassment');
    expect($block->status->value)->toBe('active');
    expect($block->notes)->toBe('Repeated harassment in comments');
});

test('defaults reason to other when not specified', function (): void {
    $block = $this->action->execute(
        blockable: $this->blockable,
        metadata: [],
    );

    expect($block->reason)->toBeInstanceOf(BlockReason::class);
    expect($block->reason->value)->toBe('other');
});

test('defaults blockedBy to null when not provided', function (): void {
    $block = $this->action->execute(
        blockable: $this->blockable,
        reason: BlockReason::Spam,
        metadata: [],
    );

    expect($block->blocked_by_type)->toBeNull();
    expect($block->blocked_by_id)->toBeNull();
});

test('sets default expiration date based on config', function (): void {
    $block = $this->action->execute(
        blockable: $this->blockable,
        reason: BlockReason::PolicyViolation,
        metadata: [],
    );

    expect($block->expires_at)->not->toBeNull();
});

test('creates block with active status', function (): void {
    $block = $this->action->execute(
        blockable: $this->blockable,
        reason: BlockReason::Spam,
        metadata: [],
    );

    expect($block->status->value)->toBe('active');
    expect($block->status)->toBe(BlockStatus::Active);
});

test('works within a transaction', function (): void {
    $block = $this->action->execute(
        blockable: $this->blockable,
        reason: BlockReason::CopyrightViolation,
        metadata: [],
    );

    expect(Block::find($block->id))->not->toBeNull();
});

test('stores metadata when provided', function (): void {
    $metadata = ['source' => 'automated', 'confidence' => 0.95];

    $block = $this->action->execute(
        blockable: $this->blockable,
        reason: BlockReason::Spam,
        metadata: $metadata,
    );

    expect($block->metadata)->toBe($metadata);
    expect($block->metadata['source'])->toBe('automated');
});
