<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Widgets;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class UplineVisualizationWidget extends Widget
{
    public const int MAX_DEPTH = 5;

    public const int MAX_BREADTH = 25;

    public ?string $affiliateId = null;

    public int $depth = 3;

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    /** @var view-string */
    protected string $view = 'filament-affiliates::widgets.upline-visualization';

    public function mount(?string $affiliateId = null): void
    {
        $this->affiliateId = $affiliateId;
        $this->depth = $this->clampedDepth($this->depth);
    }

    public function updatedDepth(mixed $value): void
    {
        $this->depth = $this->clampedDepth(is_numeric($value) ? (int) $value : 3);
    }

    private function clampedDepth(int $depth): int
    {
        return max(1, min(self::MAX_DEPTH, $depth));
    }

    public function getUplineData(): array
    {
        /** @var Model|null $owner */
        $owner = (bool) config('affiliates.owner.enabled', false)
            ? OwnerContext::resolve()
            : null;

        if (! $this->affiliateId) {
            // Get root affiliates (no parent)
            $roots = Affiliate::query()
                ->when(
                    (bool) config('affiliates.owner.enabled', false),
                    fn ($query) => $query->forOwner($owner),
                )
                ->whereNull('parent_affiliate_id')
                ->where('status', AffiliateStatus::fromString(Active::class)->getValue())
                ->with(['rank'])
                ->withCount(['children', 'conversions'])
                ->limit(10)
                ->get();

            return $roots->map(fn (Affiliate $a) => $this->buildNode($a, 0))->all();
        }

        $affiliate = Affiliate::query()
            ->when(
                (bool) config('affiliates.owner.enabled', false),
                fn ($query) => $query->forOwner($owner),
            )
            ->whereKey($this->affiliateId)
            ->with(['rank'])
            ->withCount(['children', 'conversions'])
            ->first();

        if (! $affiliate) {
            return [];
        }

        return [$this->buildNode($affiliate, 0)];
    }

    public function getUplineStats(): array
    {
        /** @var Model|null $owner */
        $owner = (bool) config('affiliates.owner.enabled', false)
            ? OwnerContext::resolve()
            : null;

        return [
            'total_affiliates' => Affiliate::query()
                ->when(
                    (bool) config('affiliates.owner.enabled', false),
                    fn ($query) => $query->forOwner($owner),
                )
                ->count(),
            'active_affiliates' => Affiliate::query()
                ->when(
                    (bool) config('affiliates.owner.enabled', false),
                    fn ($query) => $query->forOwner($owner),
                )
                ->where('status', AffiliateStatus::fromString(Active::class)->getValue())
                ->count(),
            'max_depth' => $this->calculateMaxDepth(),
            'avg_children' => $this->calculateAverageChildren(),
        ];
    }

    private function buildNode(Affiliate $affiliate, int $currentDepth): array
    {
        $children = [];

        if ($currentDepth < $this->clampedDepth($this->depth)) {
            $children = $affiliate->children()
                ->where('status', AffiliateStatus::fromString(Active::class)->getValue())
                ->with(['rank'])
                ->withCount(['children', 'conversions'])
                ->limit(self::MAX_BREADTH)
                ->get()
                ->map(fn (Affiliate $child) => $this->buildNode($child, $currentDepth + 1))
                ->all();
        }

        $conversionsCount = is_int($affiliate->getAttribute('conversions_count'))
            ? (int) $affiliate->getAttribute('conversions_count')
            : $affiliate->conversions()->count();

        $childrenCount = is_int($affiliate->getAttribute('children_count'))
            ? (int) $affiliate->getAttribute('children_count')
            : $affiliate->children()->count();

        return [
            'id' => $affiliate->id,
            'name' => $affiliate->name,
            'code' => $affiliate->code,
            'status' => AffiliateStatus::normalize($affiliate->status),
            'rank' => $affiliate->rank?->name,
            'conversions' => $conversionsCount,
            'children' => $children,
            'children_count' => $childrenCount,
        ];
    }

    private function calculateMaxDepth(): int
    {
        // Simple approximation - count levels from closure table if available
        return 5; // Default max depth
    }

    private function calculateAverageChildren(): float
    {
        /** @var Model|null $owner */
        $owner = (bool) config('affiliates.owner.enabled', false)
            ? OwnerContext::resolve()
            : null;

        $counted = Affiliate::query()
            ->when(
                (bool) config('affiliates.owner.enabled', false),
                fn ($query) => $query->forOwner($owner),
            )
            ->whereHas('children')
            ->withCount('children');

        $average = DB::query()->fromSub($counted->getQuery(), 'counted')->avg('children_count');

        return round((float) $average, 1);
    }
}
