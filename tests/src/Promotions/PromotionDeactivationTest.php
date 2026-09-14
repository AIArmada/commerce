<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Promotions\Actions\DeactivatePromotion;
use AIArmada\Promotions\Console\Commands\DeactivateExpiredPromotionsCommand;
use AIArmada\Promotions\Events\PromotionDeactivated;
use AIArmada\Promotions\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->app->make(Kernel::class)->registerCommand(new DeactivateExpiredPromotionsCommand);
});

it('stamps the deactivation time and returns a usable promotion', function (): void {
    Event::fake([PromotionDeactivated::class]);

    $promotion = Promotion::factory()->active()->create(['discount_value' => 10]);

    $resolved = app(DeactivatePromotion::class)->handle($promotion);

    expect($resolved->is_active)->toBeFalse()
        ->and($resolved->deactivated_at)->not->toBeNull()
        ->and($promotion->fresh()->deactivated_at)->not->toBeNull();

    Event::assertDispatched(PromotionDeactivated::class);
});

it('deactivates expired promotions per owner without tripping the write guard', function (): void {
    config()->set('promotions.features.owner.enabled', true);
    config()->set('promotions.features.owner.include_global', false);
    config()->set('promotions.features.owner.auto_assign_on_create', true);

    // Console runs have no ambient owner; drop the default test resolver so
    // the command iterates discovered owners like production.
    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $expiredA = OwnerContext::withOwner($ownerA, fn (): Promotion => Promotion::factory()->create([
        'is_active' => true,
        'starts_at' => null,
        'ends_at' => CarbonImmutable::now()->subDay(),
    ]));
    $expiredB = OwnerContext::withOwner($ownerB, fn (): Promotion => Promotion::factory()->create([
        'is_active' => true,
        'starts_at' => null,
        'ends_at' => CarbonImmutable::now()->subDay(),
    ]));
    $live = OwnerContext::withOwner($ownerA, fn (): Promotion => Promotion::factory()->active()->create());

    $this->artisan('promotions:deactivate-expired')->assertSuccessful();

    expect($expiredA->fresh()->is_active)->toBeFalse()
        ->and($expiredA->fresh()->deactivated_at)->not->toBeNull()
        ->and($expiredB->fresh()->is_active)->toBeFalse()
        ->and($live->fresh()->is_active)->toBeTrue();
});

it('leaves expired promotions untouched on dry runs', function (): void {
    $expired = Promotion::factory()->create([
        'is_active' => true,
        'starts_at' => null,
        'ends_at' => CarbonImmutable::now()->subDay(),
    ]);

    $this->artisan('promotions:deactivate-expired', ['--dry-run' => true])->assertSuccessful();

    expect($expired->fresh()->is_active)->toBeTrue()
        ->and($expired->fresh()->deactivated_at)->toBeNull();
});
