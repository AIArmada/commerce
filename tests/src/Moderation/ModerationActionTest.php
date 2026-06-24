<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Moderation\Enums\ModerationActionType;
use AIArmada\Moderation\Models\ModerationAction;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->actionable = User::create([
        'name' => 'Actionable User',
        'email' => 'actionable-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $this->moderator = User::create([
        'name' => 'Moderator',
        'email' => 'moderator-action-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $this->action = ModerationAction::create([
        'actionable_type' => $this->actionable->getMorphClass(),
        'actionable_id' => $this->actionable->id,
        'actioned_by_type' => $this->moderator->getMorphClass(),
        'actioned_by_id' => $this->moderator->id,
        'type' => ModerationActionType::Warn,
        'reason' => 'Inappropriate behavior',
        'notes' => 'First warning',
        'metadata' => '{}',
    ]);
});

test('creates with minimal attributes', function (): void {
    $minimal = ModerationAction::create([
        'actionable_type' => $this->actionable->getMorphClass(),
        'actionable_id' => $this->actionable->id,
        'type' => ModerationActionType::Ban,
        'reason' => 'Permanent ban',
        'metadata' => '{}',
    ]);

    expect($minimal->id)->toBeUuid();
    expect($minimal->type->value)->toBe('ban');
    expect($minimal->reason)->toBe('Permanent ban');
    expect($minimal->actionable_type)->toBe($this->actionable->getMorphClass());
    expect($minimal->actionable_id)->toBe($this->actionable->id);
});

test('uses UUID primary key', function (): void {
    expect($this->action->id)->toBeUuid();
    expect(Str::isUuid($this->action->id))->toBeTrue();
});

test('casts type enum correctly', function (): void {
    $fresh = ModerationAction::find($this->action->id);

    expect($fresh->type)->toBeInstanceOf(ModerationActionType::class);
    expect($fresh->type->value)->toBe('warn');
});

test('casts metadata as array', function (): void {
    $this->action->update(['metadata' => ['ip' => '192.168.1.1', 'automated' => false]]);
    $fresh = ModerationAction::find($this->action->id);

    expect($fresh->metadata)->toBeArray();
    expect($fresh->metadata['ip'])->toBe('192.168.1.1');
});

test('has morphTo actionable relationship', function (): void {
    expect($this->action->actionable)->toBeInstanceOf(User::class);
    expect($this->action->actionable->id)->toBe($this->actionable->id);
});

test('has morphTo actionedBy relationship', function (): void {
    expect($this->action->actionedBy)->toBeInstanceOf(User::class);
    expect($this->action->actionedBy->id)->toBe($this->moderator->id);
});

test('actionedBy is nullable', function (): void {
    $action = ModerationAction::create([
        'actionable_type' => $this->actionable->getMorphClass(),
        'actionable_id' => $this->actionable->id,
        'type' => ModerationActionType::Approve,
        'reason' => 'Content approved',
        'metadata' => '{}',
    ]);

    expect($action->actionedBy)->toBeNull();
});

test('uses config-driven table name', function (): void {
    $originalPrefix = config('moderation.database.table_prefix');

    config()->set('moderation.database.table_prefix', 'custom_');
    config()->set('moderation.database.tables.moderation_actions', 'custom_actions');

    expect((new ModerationAction)->getTable())->toBe('custom_actions');

    config()->set('moderation.database.table_prefix', $originalPrefix);
    config()->set('moderation.database.tables.moderation_actions', $originalPrefix . 'actions');
});
