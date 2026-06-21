<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationBatch;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CommunicationsTestOwner extends Model
{
    use HasUuids;

    protected $table = 'communications_test_owners';

    protected $fillable = ['name'];
}

beforeEach(function (): void {
    Schema::dropIfExists('communications_test_owners');

    Schema::create('communications_test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('communications.features.owner.enabled', true);
    config()->set('communications.features.owner.include_global', false);
    config()->set('communications.features.owner.auto_assign_on_create', true);
});

it('isolates communications across owners', function (): void {
    $ownerA = CommunicationsTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = CommunicationsTestOwner::query()->create(['name' => 'Owner B']);

    $commA = OwnerContext::withOwner($ownerA, function (): Communication {
        return Communication::create([
            'direction' => CommunicationDirection::Outbound,
            'category' => CommunicationCategory::Transactional,
            'priority' => CommunicationPriority::Normal,
            'purpose' => 'owner-a',
            'status' => CommunicationStatus::Draft,
        ]);
    });

    $commB = OwnerContext::withOwner($ownerB, function (): Communication {
        return Communication::create([
            'direction' => CommunicationDirection::Outbound,
            'category' => CommunicationCategory::Transactional,
            'priority' => CommunicationPriority::Normal,
            'purpose' => 'owner-b',
            'status' => CommunicationStatus::Draft,
        ]);
    });

    expect(OwnerContext::withOwner($ownerA, function (): array {
        return Communication::query()->pluck('id')->all();
    }))->toEqual([$commA->id]);

    expect(OwnerContext::withOwner($ownerB, function (): array {
        return Communication::query()->pluck('id')->all();
    }))->toEqual([$commB->id]);
});

it('isolates batches across owners', function (): void {
    $ownerA = CommunicationsTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = CommunicationsTestOwner::query()->create(['name' => 'Owner B']);

    $batchA = OwnerContext::withOwner($ownerA, function (): CommunicationBatch {
        return CommunicationBatch::create([
            'name' => 'Batch A',
            'purpose' => 'marketing',
            'category' => 'marketing',
            'status' => 'pending',
            'requested_count' => 10,
            'planned_count' => 0,
            'queued_count' => 0,
            'completed_count' => 0,
            'failed_count' => 0,
        ]);
    });

    $batchB = OwnerContext::withOwner($ownerB, function (): CommunicationBatch {
        return CommunicationBatch::create([
            'name' => 'Batch B',
            'purpose' => 'marketing',
            'category' => 'marketing',
            'status' => 'pending',
            'requested_count' => 20,
            'planned_count' => 0,
            'queued_count' => 0,
            'completed_count' => 0,
            'failed_count' => 0,
        ]);
    });

    expect(OwnerContext::withOwner($ownerA, function (): array {
        return CommunicationBatch::query()->pluck('id')->all();
    }))->toEqual([$batchA->id]);

    expect(OwnerContext::withOwner($ownerB, function (): array {
        return CommunicationBatch::query()->pluck('id')->all();
    }))->toEqual([$batchB->id]);
});

it('blocks cross-owner writes', function (): void {
    $ownerA = CommunicationsTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = CommunicationsTestOwner::query()->create(['name' => 'Owner B']);

    $commA = OwnerContext::withOwner($ownerA, function (): Communication {
        return Communication::create([
            'direction' => CommunicationDirection::Outbound,
            'category' => CommunicationCategory::Transactional,
            'priority' => CommunicationPriority::Normal,
            'purpose' => 'cross-write-a',
            'status' => CommunicationStatus::Draft,
        ]);
    });

    expect(fn () => OwnerContext::withOwner($ownerB, function () use ($commA): void {
        $commA->update(['purpose' => 'hacked-by-b']);
    }))->toThrow(AuthorizationException::class);
});
