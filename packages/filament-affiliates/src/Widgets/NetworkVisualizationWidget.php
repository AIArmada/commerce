<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Widgets;

use AIArmada\Affiliates\Models\Affiliate;
use Filament\Widgets\Widget;

final class NetworkVisualizationWidget extends Widget
{
    public ?string $affiliateId = null;

    public int $depth = 3;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament-affiliates::widgets.network-visualization';

    public function mount(?string $affiliateId = null): void
    {
        $this->affiliateId = $affiliateId;
    }

    public function getNetworkData(): array
    {
        if (! $this->affiliateId) {
            // Get root affiliates (no parent)
            $roots = Affiliate::query()
                ->whereNull('parent_affiliate_id')
                ->where('status', 'active')
                ->limit(10)
                ->get();

            return $roots->map(fn (Affiliate $a) => $this->buildNode($a, 0))->all();
        }

        $affiliate = Affiliate::find($this->affiliateId);

        if (! $affiliate) {
            return [];
        }

        return [$this->buildNode($affiliate, 0)];
    }

    public function getNetworkStats(): array
    {
        return [
            'total_affiliates' => Affiliate::count(),
            'active_affiliates' => Affiliate::where('status', 'active')->count(),
            'max_depth' => $this->calculateMaxDepth(),
            'avg_children' => $this->calculateAverageChildren(),
        ];
    }

    private function buildNode(Affiliate $affiliate, int $currentDepth): array
    {
        $children = [];

        if ($currentDepth < $this->depth) {
            $children = $affiliate->children()
                ->where('status', 'active')
                ->get()
                ->map(fn (Affiliate $child) => $this->buildNode($child, $currentDepth + 1))
                ->all();
        }

        return [
            'id' => $affiliate->id,
            'name' => $affiliate->name,
            'code' => $affiliate->code,
            'status' => $affiliate->status->value,
            'rank' => $affiliate->rank?->name,
            'conversions' => $affiliate->conversions()->count(),
            'children' => $children,
            'children_count' => $affiliate->children()->count(),
        ];
    }

    private function calculateMaxDepth(): int
    {
        // Simple approximation - count levels from closure table if available
        return 5; // Default max depth
    }

    private function calculateAverageChildren(): float
    {
        $affiliatesWithChildren = Affiliate::query()
            ->whereHas('children')
            ->withCount('children')
            ->get();

        if ($affiliatesWithChildren->isEmpty()) {
            return 0;
        }

        return round($affiliatesWithChildren->avg('children_count'), 1);
    }
}
