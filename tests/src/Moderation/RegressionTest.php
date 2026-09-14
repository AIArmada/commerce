<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Moderation\Actions\BlockEntityAction;
use AIArmada\Moderation\Actions\ExpireModerationBlocksAction;
use AIArmada\Moderation\Actions\RecordModerationAction;
use AIArmada\Moderation\Enums\BlockReason;
use AIArmada\Moderation\Enums\BlockStatus;
use AIArmada\Moderation\Enums\ModerationActionType;
use AIArmada\Moderation\Models\Block;
use AIArmada\Moderation\Models\ModerationAction;
use AIArmada\Moderation\Traits\HasBlocks;
use AIArmada\Moderation\Traits\HasModerationActions;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class RegressionBlockable extends Model
{
    use HasBlocks;
    use HasModerationActions;
    use HasUuids;

    protected $table = 'regression_blockables';

    protected $fillable = ['name'];
}

beforeEach(function (): void {
    Schema::dropIfExists('regression_blockables');

    Schema::create('regression_blockables', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('moderation.owner.enabled', false);
    config()->set('moderation.actors.allowed_types', []);

    $this->entity = RegressionBlockable::create(['name' => 'Regression Entity']);
});

afterEach(function (): void {
    Schema::dropIfExists('regression_blockables');
});

test('accepts a mutable expiry date when blocking', function (): void {
    $expiresAt = Carbon::now()->addDays(3);

    $block = $this->entity->block(
        reason: BlockReason::Spam->value,
        expiresAt: $expiresAt,
    );

    expect($block->expires_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($block->expires_at->toDateTimeString())->toBe($expiresAt->toDateTimeString());
});

test('rejects an unknown reason string when blocking', function (): void {
    expect(fn (): Block => $this->entity->block(reason: 'definitely-not-a-reason'))
        ->toThrow(InvalidArgumentException::class, 'Unknown block reason');
});

test('stores notes through the moderation action chain', function (): void {
    $action = $this->entity->recordModerationAction(
        ModerationActionType::Warn,
        'Needs review',
        notes: 'Escalated by the night shift',
    );

    expect($action)->toBeInstanceOf(ModerationAction::class)
        ->and($action->notes)->toBe('Escalated by the night shift')
        ->and($action->fresh()->notes)->toBe('Escalated by the night shift');
});

test('records which actor lifts a block', function (): void {
    $moderator = User::create([
        'name' => 'Lift Actor',
        'email' => 'lift-actor-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $block = Block::unguarded(fn (): Block => Block::create([
        'blockable_type' => $this->entity->getMorphClass(),
        'blockable_id' => $this->entity->id,
        'reason' => BlockReason::Spam,
        'status' => BlockStatus::Active,
    ]));

    $block->transitionTo(BlockStatus::Lifted, CarbonImmutable::now(), $moderator)->save();

    expect($block->fresh())
        ->lifted_at->not->toBeNull()
        ->lifted_by_type->toBe($moderator->getMorphClass())
        ->lifted_by_id->toBe($moderator->id);

    $block->transitionTo(BlockStatus::Active)->save();

    expect($block->fresh())
        ->lifted_at->toBeNull()
        ->lifted_by_type->toBeNull()
        ->lifted_by_id->toBeNull();
});

test('treats past-due active blocks as expired in queries', function (): void {
    Block::unguarded(fn (): Block => Block::create([
        'blockable_type' => $this->entity->getMorphClass(),
        'blockable_id' => $this->entity->id,
        'reason' => BlockReason::Spam,
        'status' => BlockStatus::Active,
        'expires_at' => CarbonImmutable::now()->subMinute(),
    ]));
    $upcoming = Block::unguarded(fn (): Block => Block::create([
        'blockable_type' => $this->entity->getMorphClass(),
        'blockable_id' => $this->entity->id,
        'reason' => BlockReason::Spam,
        'status' => BlockStatus::Active,
        'expires_at' => CarbonImmutable::now()->addHour(),
    ]));

    $expired = Block::expired()->get();

    expect($expired)->toHaveCount(1)
        ->and($expired->pluck('id'))->not->toContain($upcoming->id);
    expect(Block::active()->get()->pluck('id'))->toContain($upcoming->id);
});

test('keeps block lifecycle fields out of mass assignment', function (): void {
    $block = Block::create([
        'blockable_type' => $this->entity->getMorphClass(),
        'blockable_id' => $this->entity->id,
        'reason' => BlockReason::Spam,
        'status' => BlockStatus::Lifted,
        'lifted_at' => CarbonImmutable::now(),
    ]);

    expect($block->fresh()->status)->toBe(BlockStatus::Active)
        ->and($block->fresh()->lifted_at)->toBeNull();

    $bare = new Block;
    $bare->fill(['status' => BlockStatus::Lifted->value, 'expires_at' => CarbonImmutable::now()]);

    expect($bare->status)->toBeNull()
        ->and($bare->expires_at)->toBeNull();
});

test('re-blocking returns the existing active block', function (): void {
    $action = app(BlockEntityAction::class);

    $first = $action->execute($this->entity, reason: BlockReason::Spam);
    $second = $action->execute($this->entity, reason: BlockReason::Harassment);

    expect($second->is($first))->toBeTrue()
        ->and(Block::query()->where('blockable_id', $this->entity->id)->count())->toBe(1);
});

test('re-blocking extends the expiry only when the new date is later', function (): void {
    $action = app(BlockEntityAction::class);

    $first = $action->execute(
        $this->entity,
        reason: BlockReason::Spam,
        expiresAt: CarbonImmutable::now()->addDays(5),
    );

    $extended = $action->execute(
        $this->entity,
        reason: BlockReason::Spam,
        expiresAt: CarbonImmutable::now()->addDays(10),
    );

    expect($extended->is($first))->toBeTrue()
        ->and($extended->expires_at->toDateString())->toBe(CarbonImmutable::now()->addDays(10)->toDateString());

    $action->execute(
        $this->entity,
        reason: BlockReason::Spam,
        expiresAt: CarbonImmutable::now()->addDays(2),
    );

    expect($first->fresh()->expires_at->toDateString())->toBe(CarbonImmutable::now()->addDays(10)->toDateString());
});

test('re-blocking leaves an indefinite block indefinite', function (): void {
    $action = app(BlockEntityAction::class);

    $indefinite = Block::unguarded(fn (): Block => Block::create([
        'blockable_type' => $this->entity->getMorphClass(),
        'blockable_id' => $this->entity->id,
        'reason' => BlockReason::Spam,
        'status' => BlockStatus::Active,
        'expires_at' => null,
    ]));

    $result = $action->execute($this->entity, reason: BlockReason::Spam);

    expect($result->is($indefinite))->toBeTrue()
        ->and($result->expires_at)->toBeNull()
        ->and(Block::query()->where('blockable_id', $this->entity->id)->count())->toBe(1);
});

test('rejects invalid moderation action input', function (): void {
    $action = app(RecordModerationAction::class);

    expect(fn (): ModerationAction => $action->execute($this->entity, ModerationActionType::Warn, '   '))
        ->toThrow(InvalidArgumentException::class, 'reason is required');

    expect(fn (): ModerationAction => $action->execute($this->entity, ModerationActionType::Warn, str_repeat('r', 256)))
        ->toThrow(InvalidArgumentException::class, 'must not exceed 255 characters');

    expect(fn (): ModerationAction => $action->execute($this->entity, ModerationActionType::Warn, 'Valid.', notes: str_repeat('n', 10001)))
        ->toThrow(InvalidArgumentException::class, 'must not exceed 10000 characters');

    expect(fn (): ModerationAction => $action->execute($this->entity, ModerationActionType::Warn, 'Valid.', metadata: array_fill(0, 51, 'x')))
        ->toThrow(InvalidArgumentException::class, 'must not exceed 50 entries');

    expect(fn (): ModerationAction => $action->execute($this->entity, ModerationActionType::Warn, 'Valid.', metadata: ['blob' => str_repeat('x', 70000)]))
        ->toThrow(InvalidArgumentException::class, 'must not exceed 65535 bytes');
});

test('deleting a blockable expires active blocks from every owner scope', function (): void {
    config()->set('moderation.owner.enabled', true);

    $ownerA = User::query()->create(['name' => 'Hook Owner A', 'email' => 'hook-owner-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Hook Owner B', 'email' => 'hook-owner-b@example.com', 'password' => 'secret']);

    $blockA = OwnerContext::withOwner($ownerA, fn (): Block => app(BlockEntityAction::class)->execute($this->entity, reason: BlockReason::Spam));
    $blockB = OwnerContext::withOwner($ownerB, fn (): Block => app(BlockEntityAction::class)->execute($this->entity, reason: BlockReason::Spam));

    $this->entity->delete();

    expect(Block::withoutOwnerScope()->findOrFail($blockA->getKey())->status)->toBe(BlockStatus::Expired)
        ->and(Block::withoutOwnerScope()->findOrFail($blockB->getKey())->status)->toBe(BlockStatus::Expired);
});

test('expires past-due blocks in bulk without firing model events', function (): void {
    Block::unguarded(fn (): Block => Block::create([
        'blockable_type' => $this->entity->getMorphClass(),
        'blockable_id' => $this->entity->id,
        'reason' => BlockReason::Spam,
        'status' => BlockStatus::Active,
        'expires_at' => CarbonImmutable::now()->subMinute(),
    ]));
    Block::unguarded(fn (): Block => Block::create([
        'blockable_type' => $this->entity->getMorphClass(),
        'blockable_id' => $this->entity->id,
        'reason' => BlockReason::Spam,
        'status' => BlockStatus::Active,
        'expires_at' => CarbonImmutable::now()->addHour(),
    ]));

    $saved = 0;
    Block::saved(function () use (&$saved): void {
        $saved++;
    });

    $processed = app(ExpireModerationBlocksAction::class)->execute(withoutEvents: true);

    expect($processed)->toBe(1)
        ->and($saved)->toBe(0)
        ->and(Block::expired()->count())->toBe(1);
});

test('restricts actor types when an allowlist is configured', function (): void {
    config()->set('moderation.owner.enabled', true);

    $moderator = User::create([
        'name' => 'Listed Actor',
        'email' => 'listed-actor-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    config()->set('moderation.actors.allowed_types', [User::class]);

    $block = $this->entity->block(
        reason: BlockReason::Spam->value,
        blockedById: (string) $moderator->getKey(),
        blockedByType: User::class,
    );

    expect($block->blocked_by_id)->toBe($moderator->id);

    expect(fn (): Block => $this->entity->block(
        reason: BlockReason::Spam->value,
        blockedById: (string) $moderator->getKey(),
        blockedByType: Customer::class,
    ))->toThrow(InvalidArgumentException::class, 'not allowed to moderate');
});
