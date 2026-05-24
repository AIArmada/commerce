<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentPromotions\Models\Promotion;
use AIArmada\FilamentPromotions\Resources\PromotionResource\Pages\ListPromotions;
use AIArmada\FilamentPromotions\Widgets\PromotionStatsWidget;
use AIArmada\FilamentPromotions\Widgets\TopPromotionsUsageChart;
use AIArmada\Orders\Models\Order;
use Filament\Widgets\StatsOverviewWidget\Stat;

function filamentPromotions_invokeProtected(object $instance, string $methodName, array $arguments = []): mixed
{
    $method = new ReflectionMethod($instance, $methodName);

    return $method->invokeArgs($instance, $arguments);
}

function filamentPromotions_createPromotion(string $name, int $usageCount, ?string $code = null, bool $isActive = true): Promotion
{
    $promotion = Promotion::factory()->create([
        'name' => $name,
        'code' => $code,
        'is_active' => $isActive,
    ]);

    $promotion->forceFill(['usage_count' => $usageCount])->save();

    return $promotion->fresh();
}

/**
 * @return array{promotion_id: string, name: string, code: string|null, type: string, discount: int}
 */
function filamentPromotions_payload(Promotion $promotion, int $discount): array
{
    return [
        'promotion_id' => (string) $promotion->id,
        'name' => $promotion->name,
        'code' => $promotion->code,
        'type' => $promotion->type->value,
        'discount' => $discount,
    ];
}

/**
 * @param  array<int, array{promotion_id: string, name: string, code: string|null, type: string, discount: int}>  $promotions
 */
function filamentPromotions_createOrderWithPromotions(array $promotions, int $grandTotal, string $currency = 'MYR'): Order
{
    $discountTotal = collect($promotions)->sum(static fn (array $promotion): int => (int) $promotion['discount']);

    return Order::factory()->create([
        'subtotal' => $grandTotal + $discountTotal,
        'discount_total' => $discountTotal,
        'shipping_total' => 0,
        'tax_total' => 0,
        'grand_total' => $grandTotal,
        'currency' => $currency,
        'metadata' => [
            'discount_data' => [
                'promotions' => $promotions,
            ],
        ],
    ]);
}

it('builds promotion stats widget with performance metrics', function (): void {
    $launchPromotion = filamentPromotions_createPromotion('Launch Code', 7, 'LAUNCH7');
    $alwaysOnPromotion = filamentPromotions_createPromotion('Always On', 5, null);
    filamentPromotions_createPromotion('Expired Code', 2, 'OLD2', false);

    filamentPromotions_createOrderWithPromotions([
        filamentPromotions_payload($launchPromotion, 1200),
    ], 10000);

    filamentPromotions_createOrderWithPromotions([
        filamentPromotions_payload($launchPromotion, 800),
        filamentPromotions_payload($alwaysOnPromotion, 500),
    ], 11000);

    filamentPromotions_createOrderWithPromotions([
        filamentPromotions_payload($launchPromotion, 600),
    ], 9000);

    Order::factory()->create(['currency' => 'MYR']);

    $widget = app(PromotionStatsWidget::class);

    /** @var array<int, Stat> $stats */
    $stats = filamentPromotions_invokeProtected($widget, 'getStats');

    $statsByLabel = collect($stats)->mapWithKeys(
        static fn (Stat $stat): array => [$stat->getLabel() => $stat]
    );

    expect($stats)->toHaveCount(5)
        ->and($statsByLabel['Total Promotions']->getValue())->toBe('3')
        ->and($statsByLabel['Active Promotions']->getValue())->toBe('2')
        ->and($statsByLabel['Orders Influenced']->getValue())->toBe('3')
        ->and($statsByLabel['Influenced Revenue']->getValue())->toBe('300.00 MYR')
        ->and($statsByLabel['Discount Attributed']->getValue())->toBe('31.00 MYR');
});

it('builds top promotions usage chart data and exposes analytics widgets on the listing page', function (): void {
    $topPerformer = filamentPromotions_createPromotion('Top Performer', 12, 'TOP12');
    $autoPerformer = filamentPromotions_createPromotion('Auto Performer', 4);
    filamentPromotions_createPromotion('Unused Promotion', 0, 'ZERO0');

    filamentPromotions_createOrderWithPromotions([
        filamentPromotions_payload($topPerformer, 1200),
    ], 15000);

    filamentPromotions_createOrderWithPromotions([
        filamentPromotions_payload($topPerformer, 900),
        filamentPromotions_payload($autoPerformer, 400),
    ], 12000);

    filamentPromotions_createOrderWithPromotions([
        filamentPromotions_payload($topPerformer, 700),
    ], 9000);

    $widget = app(TopPromotionsUsageChart::class);
    $data = filamentPromotions_invokeProtected($widget, 'getData');

    expect($data['labels'])->toHaveCount(2)
        ->and($data['labels'][0])->toContain('Top Performer')
        ->and($data['labels'][1])->toContain('Auto Performer')
        ->and($data['datasets'][0]['label'])->toBe('Orders Influenced')
        ->and($data['datasets'][0]['data'])->toBe([3, 1])
        ->and(filamentPromotions_invokeProtected($widget, 'getType'))->toBe('bar')
        ->and(filamentPromotions_invokeProtected($widget, 'getOptions'))->toBeArray();

    $page = app(ListPromotions::class);
    $method = new ReflectionMethod(ListPromotions::class, 'getHeaderWidgets');

    expect($method->invoke($page))->toBe([
        PromotionStatsWidget::class,
        TopPromotionsUsageChart::class,
    ]);
});

it('keeps promotion analytics owner scoped', function (): void {
    config()->set('promotions.features.owner.enabled', true);
    config()->set('promotions.features.owner.include_global', false);
    config()->set('orders.owner.enabled', true);
    config()->set('orders.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Promotion Owner A',
        'email' => 'promotion-owner-a-analytics@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Promotion Owner B',
        'email' => 'promotion-owner-b-analytics@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $ownerAPromotion = OwnerContext::withOwner($ownerA, static fn (): Promotion => filamentPromotions_createPromotion('Owner A Code', 7, 'A7'));
    $ownerAAutomaticPromotion = OwnerContext::withOwner($ownerA, static fn (): Promotion => filamentPromotions_createPromotion('Owner A Auto', 3));

    OwnerContext::withOwner($ownerA, static function () use ($ownerAPromotion, $ownerAAutomaticPromotion): void {
        filamentPromotions_createOrderWithPromotions([
            filamentPromotions_payload($ownerAPromotion, 700),
        ], 10000);

        filamentPromotions_createOrderWithPromotions([
            filamentPromotions_payload($ownerAAutomaticPromotion, 300),
        ], 6000);
    });

    $ownerBPromotion = OwnerContext::withOwner($ownerB, static fn (): Promotion => filamentPromotions_createPromotion('Owner B Promo', 99, 'B99'));

    OwnerContext::withOwner($ownerB, static function () use ($ownerBPromotion): void {
        filamentPromotions_createOrderWithPromotions([
            filamentPromotions_payload($ownerBPromotion, 990),
        ], 15000);
    });

    $statsWidget = app(PromotionStatsWidget::class);

    /** @var array<int, Stat> $stats */
    $stats = filamentPromotions_invokeProtected($statsWidget, 'getStats');

    $statsByLabel = collect($stats)->mapWithKeys(
        static fn (Stat $stat): array => [$stat->getLabel() => $stat]
    );

    expect($statsByLabel['Total Promotions']->getValue())->toBe('2')
        ->and($statsByLabel['Orders Influenced']->getValue())->toBe('2')
        ->and($statsByLabel['Influenced Revenue']->getValue())->toBe('160.00 MYR')
        ->and($statsByLabel['Discount Attributed']->getValue())->toBe('10.00 MYR');

    $chartWidget = app(TopPromotionsUsageChart::class);
    $data = filamentPromotions_invokeProtected($chartWidget, 'getData');

    expect($data['labels'])->toHaveCount(2)
        ->and(implode(' ', $data['labels']))->toContain('Owner A')
        ->and(implode(' ', $data['labels']))->not->toContain('Owner B');
});
