<?php

declare(strict_types=1);

use AIArmada\Promotions\Actions\DeactivatePromotion;
use AIArmada\Promotions\Events\PromotionDeactivated;
use AIArmada\Promotions\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

it('stamps the deactivation time and returns a usable promotion', function (): void {
    Event::fake([PromotionDeactivated::class]);

    $promotion = Promotion::factory()->active()->create(['discount_value' => 10]);

    $resolved = app(DeactivatePromotion::class)->handle($promotion);

    expect($resolved->is_active)->toBeFalse()
        ->and($resolved->deactivated_at)->not->toBeNull()
        ->and($promotion->fresh()->deactivated_at)->not->toBeNull();

    Event::assertDispatched(PromotionDeactivated::class);
});

it('reports an ended promotion as not currently active without a sweep', function (): void {
    $ended = Promotion::factory()->create([
        'is_active' => true,
        'starts_at' => null,
        'ends_at' => CarbonImmutable::now()->subDay(),
    ]);
    $live = Promotion::factory()->active()->create();

    // The stored flag is untouched: nothing sweeps it.
    expect($ended->is_active)->toBeTrue()
        ->and($ended->is_currently_active)->toBeFalse()
        ->and($live->fresh()->is_currently_active)->toBeTrue()
        // The canonical scope already refused it before any deactivation.
        ->and($ended->isActive())->toBeFalse()
        // Filament resolves column state through data_get, which will not call
        // a bare method: the attribute must be readable that way too.
        ->and(data_get($ended, 'is_currently_active'))->toBeFalse()
        ->and(data_get($live->fresh(), 'is_currently_active'))->toBeTrue();
});

it('excludes ended promotions from the currently-active scope', function (): void {
    $ended = Promotion::factory()->create([
        'is_active' => true,
        'starts_at' => null,
        'ends_at' => CarbonImmutable::now()->subDay(),
    ]);
    $live = Promotion::factory()->active()->create();
    $disabled = Promotion::factory()->create(['is_active' => false]);

    $ids = Promotion::query()->currentlyActive()->pluck('id');

    expect($ids)->toContain($live->id)
        ->and($ids)->not->toContain($ended->id)
        ->and($ids)->not->toContain($disabled->id);
});

it('keeps a promotion at its usage limit out of the canonical scope but inside currently-active', function (): void {
    $capped = Promotion::factory()->create([
        'is_active' => true,
        'starts_at' => null,
        'ends_at' => null,
        'usage_limit' => 1,
        'usage_count' => 1,
    ]);

    // Usage cap is a redemption constraint, not a lifecycle end, so reporting
    // still counts it while the discount engine refuses it.
    expect($capped->isActive())->toBeFalse()
        ->and($capped->is_currently_active)->toBeTrue()
        ->and(Promotion::query()->currentlyActive()->pluck('id'))->toContain($capped->id);
});
