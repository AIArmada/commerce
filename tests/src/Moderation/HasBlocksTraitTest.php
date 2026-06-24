<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Moderation\Enums\BlockReason;
use AIArmada\Moderation\Enums\BlockStatus;
use AIArmada\Moderation\Models\Block;
use AIArmada\Moderation\Traits\HasBlocks;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class BlockableTestModel extends Model
{
    use HasBlocks;
    use HasUuids;

    protected $table = 'blockable_test_models';

    protected $fillable = ['name'];
}

beforeEach(function (): void {
    Schema::dropIfExists('blockable_test_models');

    Schema::create('blockable_test_models', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('moderation.features.owner.enabled', false);

    $this->entity = BlockableTestModel::create(['name' => 'Test Entity']);

    $this->blockedEntity = BlockableTestModel::create(['name' => 'Blocked Entity']);

    Block::create([
        'blockable_type' => $this->blockedEntity->getMorphClass(),
        'blockable_id' => $this->blockedEntity->id,
        'reason' => BlockReason::Spam,
        'status' => BlockStatus::Active,
        'expires_at' => CarbonImmutable::now()->addDays(30),
        'metadata' => '{}',
    ]);
});

afterEach(function (): void {
    Schema::dropIfExists('blockable_test_models');
});

describe('isBlocked', function (): void {
    it('returns true when active block exists', function (): void {
        expect($this->blockedEntity->isBlocked())->toBeTrue();
    });

    it('returns false when no blocks exist', function (): void {
        expect($this->entity->isBlocked())->toBeFalse();
    });

    it('returns false when block is expired', function (): void {
        $entity = BlockableTestModel::create(['name' => 'Expired Block Entity']);

        Block::create([
            'blockable_type' => $entity->getMorphClass(),
            'blockable_id' => $entity->id,
            'reason' => BlockReason::Other,
            'status' => BlockStatus::Expired,
            'metadata' => '{}',
        ]);

        expect($entity->isBlocked())->toBeFalse();
    });

    it('returns false when block is lifted', function (): void {
        $entity = BlockableTestModel::create(['name' => 'Lifted Block Entity']);

        Block::create([
            'blockable_type' => $entity->getMorphClass(),
            'blockable_id' => $entity->id,
            'reason' => BlockReason::Other,
            'status' => BlockStatus::Lifted,
            'metadata' => '{}',
        ]);

        expect($entity->isBlocked())->toBeFalse();
    });
});

describe('blocks relationship', function (): void {
    it('returns all blocks for the model', function (): void {
        expect($this->blockedEntity->blocks)->toHaveCount(1);
        expect($this->blockedEntity->blocks->first()->reason->value)->toBe('spam');
    });

    it('returns empty collection when no blocks exist', function (): void {
        expect($this->entity->blocks)->toHaveCount(0);
    });
});

describe('activeBlocks relationship', function (): void {
    it('returns only active blocks', function (): void {
        expect($this->blockedEntity->activeBlocks)->toHaveCount(1);

        Block::create([
            'blockable_type' => $this->blockedEntity->getMorphClass(),
            'blockable_id' => $this->blockedEntity->id,
            'reason' => BlockReason::Other,
            'status' => BlockStatus::Expired,
            'metadata' => '{}',
        ]);

        expect($this->blockedEntity->activeBlocks)->toHaveCount(1);
    });
});

describe('scopeWhereNotBlocked', function (): void {
    it('excludes blocked records', function (): void {
        $results = BlockableTestModel::whereNotBlocked()->get();

        expect($results->pluck('id'))->toContain($this->entity->id);
        expect($results->pluck('id'))->not->toContain($this->blockedEntity->id);
    });

    it('includes non-blocked records', function (): void {
        $results = BlockableTestModel::whereNotBlocked()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($this->entity->id);
    });
});

describe('block helper', function (): void {
    it('creates a block for a global model when owner scoping is enabled', function (): void {
        config()->set('moderation.features.owner.enabled', true);

        $block = $this->entity->block(
            reason: BlockReason::Spam->value,
            notes: 'Manual moderation block',
        );

        expect($block)->toBeInstanceOf(Block::class);
        expect($block->blockable_id)->toBe($this->entity->id);
        expect($block->reason)->toBe(BlockReason::Spam);
        expect($block->metadata)->toBeNull();
    });

    it('rejects a cross-owner blockedBy model', function (): void {
        config()->set('moderation.features.owner.enabled', true);

        $otherOwner = User::create([
            'name' => 'Other Owner',
            'email' => 'other-owner-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);

        $blockedBy = OwnerContext::withOwner($otherOwner, function (): Customer {
            return Customer::factory()->create();
        });

        expect(fn (): Block => $this->entity->block(
            reason: BlockReason::Spam->value,
            blockedById: $blockedBy->id,
            blockedByType: Customer::class,
        ))->toThrow(AuthorizationException::class);
    });
});
