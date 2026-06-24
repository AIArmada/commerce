<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Moderation\Enums\BlockReason;
use AIArmada\Moderation\Enums\BlockStatus;
use AIArmada\Moderation\Models\Block;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->blockable = User::create([
        'name' => 'Blockable User',
        'email' => 'blockable-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $this->blockedBy = User::create([
        'name' => 'Moderator',
        'email' => 'moderator-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $this->block = Block::create([
        'blockable_type' => $this->blockable->getMorphClass(),
        'blockable_id' => $this->blockable->id,
        'blocked_by_type' => $this->blockedBy->getMorphClass(),
        'blocked_by_id' => $this->blockedBy->id,
        'reason' => BlockReason::PolicyViolation,
        'status' => BlockStatus::Active,
        'notes' => 'Repeated policy violations',
        'expires_at' => CarbonImmutable::now()->addDays(30),
        'metadata' => '{}',
    ]);
});

test('creates a block with minimal attributes', function (): void {
    $minimal = Block::create([
        'blockable_type' => $this->blockable->getMorphClass(),
        'blockable_id' => $this->blockable->id,
        'reason' => BlockReason::Spam,
        'status' => BlockStatus::Active,
        'metadata' => '{}',
    ]);

    expect($minimal->id)->toBeUuid();
    expect($minimal->reason->value)->toBe('spam');
    expect($minimal->status->value)->toBe('active');
    expect($minimal->blockable_type)->toBe($this->blockable->getMorphClass());
    expect($minimal->blockable_id)->toBe($this->blockable->id);
});

test('uses UUID primary key', function (): void {
    expect($this->block->id)->toBeUuid();
    expect(Str::isUuid($this->block->id))->toBeTrue();
});

test('casts enum attributes correctly', function (): void {
    $fresh = Block::find($this->block->id);

    expect($fresh->reason)->toBeInstanceOf(BlockReason::class);
    expect($fresh->reason->value)->toBe('policy_violation');
    expect($fresh->status)->toBeInstanceOf(BlockStatus::class);
    expect($fresh->status->value)->toBe('active');
});

test('casts datetime attributes as CarbonImmutable', function (): void {
    expect($this->block->expires_at)->toBeInstanceOf(CarbonImmutable::class);
});

test('casts metadata as array', function (): void {
    $this->block->update(['metadata' => ['source' => 'report', 'severity' => 'high']]);
    $fresh = Block::find($this->block->id);

    expect($fresh->metadata)->toBeArray();
    expect($fresh->metadata['source'])->toBe('report');
});

test('has morphTo blockable relationship', function (): void {
    expect($this->block->blockable)->toBeInstanceOf(User::class);
    expect($this->block->blockable->id)->toBe($this->blockable->id);
});

test('has morphTo blockedBy relationship', function (): void {
    expect($this->block->blockedBy)->toBeInstanceOf(User::class);
    expect($this->block->blockedBy->id)->toBe($this->blockedBy->id);
});

test('scopeActive returns only active blocks', function (): void {
    $expired = Block::create([
        'blockable_type' => $this->blockable->getMorphClass(),
        'blockable_id' => $this->blockable->id,
        'reason' => BlockReason::Spam,
        'status' => BlockStatus::Expired,
        'metadata' => '{}',
    ]);

    $activeBlocks = Block::active()->get();

    expect($activeBlocks)->toHaveCount(1);
    expect($activeBlocks->first()->id)->toBe($this->block->id);
    expect($activeBlocks->pluck('id'))->not->toContain($expired->id);
});

test('scopeExpired returns only expired blocks', function (): void {
    $expired = Block::create([
        'blockable_type' => $this->blockable->getMorphClass(),
        'blockable_id' => $this->blockable->id,
        'reason' => BlockReason::Spam,
        'status' => BlockStatus::Expired,
        'metadata' => '{}',
    ]);

    $expiredBlocks = Block::expired()->get();

    expect($expiredBlocks)->toHaveCount(1);
    expect($expiredBlocks->first()->id)->toBe($expired->id);
});

test('uses config-driven table name', function (): void {
    $originalPrefix = config('moderation.database.table_prefix');

    config()->set('moderation.database.table_prefix', 'custom_');
    config()->set('moderation.database.tables.blocks', 'custom_blocks');

    expect((new Block)->getTable())->toBe('custom_blocks');

    config()->set('moderation.database.table_prefix', $originalPrefix);
    config()->set('moderation.database.tables.blocks', $originalPrefix . 'blocks');
});
