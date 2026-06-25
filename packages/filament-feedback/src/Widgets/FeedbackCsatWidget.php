<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Models\FeedbackResponse;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FeedbackCsatWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $query = OwnerUiScope::apply(FeedbackResponse::query(), includeGlobal: false);

        $counts = (clone $query)
            ->where('status', 'submitted')
            ->whereNotNull('score')
            ->selectRaw('
                COUNT(CASE WHEN score >= 4 THEN 1 END) as satisfied,
                COUNT(*) as total
            ')
            ->first();

        $satisfied = (int) ($counts !== null && isset($counts->satisfied) ? $counts->satisfied : 0);
        $total = (int) ($counts !== null && isset($counts->total) ? $counts->total : 0);

        return [
            Stat::make('CSAT', $total > 0
                ? number_format(($satisfied / $total) * 100, 1) . '%'
                : 'N/A'),
        ];
    }
}
