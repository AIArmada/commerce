<?php

declare(strict_types=1);

use AIArmada\Promotions\Models\Promotion;
use AIArmada\Promotions\PromotionsServiceProvider;
use AIArmada\Promotions\Support\IssuedVoucherTrackingState;
use Illuminate\Support\Facades\Schema;

it('memoizes voucher tracking detection per scope instead of per process', function (): void {
    $this->app->register(PromotionsServiceProvider::class);

    $first = app(IssuedVoucherTrackingState::class);
    $second = app(IssuedVoucherTrackingState::class);

    expect($first)->toBe($second);

    $detected = Promotion::supportsIssuedVoucherTracking();

    expect($first->supported)->toBe($detected);

    app()->forgetScopedInstances();

    expect(app(IssuedVoucherTrackingState::class))->not->toBe($first)
        ->and(app(IssuedVoucherTrackingState::class)->supported)->toBeNull();
});

it('indexes promotionables for reverse lookups', function (): void {
    $indexes = collect(Schema::getIndexes('promotionables'));

    expect($indexes->pluck('name'))->toContain('promotionables_reverse_index');
});

it('constrains promotion codes per owner instead of globally', function (): void {
    $indexes = collect(Schema::getIndexes('promotions'));

    expect($indexes->pluck('name'))->toContain('promotions_owner_code_unique')
        ->and($indexes->where('name', 'promotions_owner_code_unique')->first()['columns'])
        ->toBe(['owner_type', 'owner_id', 'code']);
});
