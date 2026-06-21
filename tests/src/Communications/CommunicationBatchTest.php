<?php

declare(strict_types=1);

use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationBatch;

beforeEach(function (): void {
    $this->batch = CommunicationBatch::create([
        'name' => 'Campaign Batch',
        'purpose' => 'marketing-campaign',
        'category' => 'marketing',
        'status' => 'pending',
        'requested_count' => 100,
        'planned_count' => 0,
        'queued_count' => 0,
        'completed_count' => 0,
        'failed_count' => 0,
    ]);
});

test('creates a batch with required attributes', function (): void {
    expect($this->batch->id)->toBeUuid();
    expect($this->batch->name)->toBe('Campaign Batch');
    expect($this->batch->purpose)->toBe('marketing-campaign');
    expect($this->batch->category)->toBe('marketing');
    expect($this->batch->status)->toBe('pending');
    expect($this->batch->requested_count)->toBe(100);
    expect($this->batch->planned_count)->toBe(0);
    expect($this->batch->queued_count)->toBe(0);
    expect($this->batch->completed_count)->toBe(0);
    expect($this->batch->failed_count)->toBe(0);
});

test('batch has communications', function (): void {
    Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'batch-test',
        'status' => CommunicationStatus::Draft,
        'batch_id' => $this->batch->id,
    ]);

    expect($this->batch->communications)->toHaveCount(1);
});

test('cascade delete removes communications', function (): void {
    Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'cascade-test',
        'status' => CommunicationStatus::Draft,
        'batch_id' => $this->batch->id,
    ]);

    expect(Communication::query()->count())->toBe(1);

    $this->batch->delete();

    expect(Communication::query()->count())->toBe(0);
});

test('casts counter fields as integers', function (): void {
    expect($this->batch->requested_count)->toBeInt();
    expect($this->batch->planned_count)->toBeInt();
    expect($this->batch->completed_count)->toBeInt();
});

test('stores metadata as array', function (): void {
    $this->batch->update(['metadata' => ['campaign_id' => 'CAM-123', 'segment' => 'new-users']]);
    $fresh = CommunicationBatch::find($this->batch->id);

    expect($fresh->metadata)->toBe(['campaign_id' => 'CAM-123', 'segment' => 'new-users']);
});

test('lifecycle timestamps are nullable', function (): void {
    expect($this->batch->scheduled_at)->toBeNull();
    expect($this->batch->started_at)->toBeNull();
    expect($this->batch->completed_at)->toBeNull();
    expect($this->batch->cancelled_at)->toBeNull();
    expect($this->batch->failed_at)->toBeNull();
    expect($this->batch->expires_at)->toBeNull();
});
