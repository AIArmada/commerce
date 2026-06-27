<?php

declare(strict_types=1);

use AIArmada\Moderation\Actions\BlockEntityAction;
use AIArmada\Moderation\Actions\RecordModerationAction;
use AIArmada\Moderation\Contracts\BlocksEntity;
use AIArmada\Moderation\Contracts\RecordsModerationAction;
use AIArmada\Moderation\Models\Block;
use AIArmada\Moderation\Models\ModerationAction;
use AIArmada\Moderation\ModerationServiceProvider;
use Illuminate\Support\Facades\Schema;

test('service provider registers', function (): void {
    $providers = app()->getLoadedProviders();

    expect(isset($providers[ModerationServiceProvider::class]))->toBeTrue();
});

test('config publishes and reads correctly', function (): void {
    expect(config('moderation.database.table_prefix'))->toBeString()->toBe('moderation_');
    expect(config('moderation.database.tables.blocks'))->toBe('moderation_blocks');
    expect(config('moderation.database.tables.moderation_actions'))->toBe('moderation_actions');
    expect(config('moderation.features.owner.enabled'))->toBeTrue();
    expect(config('moderation.defaults.block_duration_days'))->toBe(30);
});

test('both tables exist after migration', function (): void {
    expect(Schema::hasTable('moderation_blocks'))->toBeTrue('Expected moderation_blocks table to exist');
    expect(Schema::hasTable('moderation_actions'))->toBeTrue('Expected moderation_actions table to exist');
    expect(Schema::hasColumns('moderation_blocks', ['owner_type', 'owner_id']))->toBeTrue();
    expect(Schema::hasColumns('moderation_actions', ['owner_type', 'owner_id']))->toBeTrue();
});

test('model classes instantiate with correct table names', function (): void {
    expect((new Block)->getTable())->toBe('moderation_blocks');
    expect((new ModerationAction)->getTable())->toBe('moderation_actions');
});

test('models have UUID primary keys', function (): void {
    $block = new Block;
    expect($block->getKeyType())->toBe('string');
    expect($block->getIncrementing())->toBeFalse();

    $action = new ModerationAction;
    expect($action->getKeyType())->toBe('string');
    expect($action->getIncrementing())->toBeFalse();
});

test('service container registers actions as singletons', function (): void {
    expect(app(BlockEntityAction::class))->toBeInstanceOf(BlockEntityAction::class);
    expect(app(RecordModerationAction::class))->toBeInstanceOf(RecordModerationAction::class);
});

test('contracts resolve to their concrete implementations', function (): void {
    expect(app(BlocksEntity::class))->toBeInstanceOf(BlockEntityAction::class);
    expect(app(RecordsModerationAction::class))->toBeInstanceOf(RecordModerationAction::class);
});

test('contract and concrete resolve the same singleton', function (): void {
    $contract = app(BlocksEntity::class);
    $concrete = app(BlockEntityAction::class);

    expect($contract)->toBe($concrete);
});

test('helper functions config sources resolve correctly', function (): void {
    expect(config('moderation.database.tables.blocks'))->toBe('moderation_blocks');
    expect(config('moderation.database.tables.moderation_actions'))->toBe('moderation_actions');
    expect(config('moderation.database.json_column_type'))->toBeString()->not->toBeEmpty();
});

test('blockable morph uses a UUID column', function (): void {
    $column = collect(Schema::getColumns('moderation_blocks'))
        ->firstWhere('name', 'blockable_id');

    expect($column)->not->toBeNull()
        ->and((string) $column['type_name'])->toContain('varchar');
});
