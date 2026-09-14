<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Support;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Promotions\Support\PromotionPerformanceInsights;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Owner-scoped, short-TTL cache in front of the promotion analytics engine.
 *
 * The underlying insights aggregate orders on every call, which is too
 * expensive to run on each widget render. Reads stay owner-isolated via
 * OwnerCache keys; the engine itself remains the source of truth.
 */
final class CachedPromotionInsights
{
    public function __construct(
        private readonly PromotionPerformanceInsights $insights,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        /** @var array<string, mixed> $overview */
        $overview = OwnerCache::remember(
            OwnerUiScope::resolveOwner(Promotion::class),
            'filament-promotions.insights.overview',
            CarbonImmutable::now()->addSeconds(60),
            fn (): array => $this->insights->overview(),
        );

        return $overview;
    }

    /**
     * @return Collection<int, array{label: string, order_count: int, influenced_revenue_minor: int, attributed_discount_minor: int}>
     */
    public function topPromotionsByOrders(int $limit = 5): Collection
    {
        /** @var Collection<int, array{label: string, order_count: int, influenced_revenue_minor: int, attributed_discount_minor: int}> $top */
        $top = OwnerCache::remember(
            OwnerUiScope::resolveOwner(Promotion::class),
            "filament-promotions.insights.top-orders.{$limit}",
            CarbonImmutable::now()->addSeconds(60),
            fn (): Collection => $this->insights->topPromotionsByOrders($limit),
        );

        return $top;
    }

    /**
     * @return Collection<int, array{label: string, usage_count: int}>
     */
    public function topPromotionsByUsage(int $limit = 5): Collection
    {
        /** @var Collection<int, array{label: string, usage_count: int}> $top */
        $top = OwnerCache::remember(
            OwnerUiScope::resolveOwner(Promotion::class),
            "filament-promotions.insights.top-usage.{$limit}",
            CarbonImmutable::now()->addSeconds(60),
            fn (): Collection => $this->insights->topPromotionsByUsage($limit),
        );

        return $top;
    }
}
