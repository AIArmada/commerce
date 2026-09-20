<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateTrainingModule;
use AIArmada\Affiliates\Models\AffiliateTrainingProgress;
use AIArmada\Affiliates\States\Active;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// AffiliateTrainingProgress Tests
test('AffiliateTrainingProgress can be created', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'TRAIN001',
        'name' => 'Training Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $module = AffiliateTrainingModule::create([
        'title' => 'Module 1',
        'slug' => 'module-1',
        'content' => 'Content here',
        'order' => 1,
    ]);

    $progress = AffiliateTrainingProgress::create([
        'affiliate_id' => $affiliate->id,
        'module_id' => $module->id,
        'completed_at' => now(),
    ]);

    expect($progress)->toBeInstanceOf(AffiliateTrainingProgress::class);
    expect($progress->completed_at)->not->toBeNull();
});

test('AffiliateTrainingProgress has affiliate relationship', function (): void {
    $progress = new AffiliateTrainingProgress;

    expect($progress->affiliate())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateTrainingProgress has module relationship', function (): void {
    $progress = new AffiliateTrainingProgress;

    expect($progress->module())->toBeInstanceOf(BelongsTo::class);
});
