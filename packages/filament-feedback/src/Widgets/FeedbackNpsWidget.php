<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Models\FeedbackResponse;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FeedbackNpsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $query = OwnerUiScope::apply(FeedbackResponse::query(), includeGlobal: false);

        $counts = (clone $query)
            ->where('status', 'submitted')
            ->whereNotNull('score')
            ->selectRaw('
                COUNT(CASE WHEN score >= 9 THEN 1 END) as promoters,
                COUNT(CASE WHEN score BETWEEN 7 AND 8 THEN 1 END) as passives,
                COUNT(CASE WHEN score <= 6 THEN 1 END) as detractors,
                COUNT(*) as total
            ')
            ->first();

        $total = (int) ($counts->total ?? 0);
        $nps = null;

        if ($total > 0) {
            $promoters = (int) ($counts->promoters ?? 0);
            $detractors = (int) ($counts->detractors ?? 0);
            $nps = (int) round(($promoters / $total * 100) - ($detractors / $total * 100));
        }

        return [
            Stat::make('NPS', $nps !== null ? (string) $nps : 'N/A'),
        ];
    }
}
