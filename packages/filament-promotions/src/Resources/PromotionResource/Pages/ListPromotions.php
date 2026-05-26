<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Resources\PromotionResource\Pages;

use AIArmada\FilamentPromotions\Actions\IssuePromotionVouchersFromListAction;
use AIArmada\FilamentPromotions\Models\Promotion;
use AIArmada\FilamentPromotions\Resources\PromotionResource;
use AIArmada\FilamentPromotions\Widgets\PromotionStatsWidget;
use AIArmada\FilamentPromotions\Widgets\TopPromotionsUsageChart;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPromotions extends ListRecords
{
    protected static string $resource = PromotionResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            CreateAction::make(),
        ];

        if (Promotion::supportsIssuedVoucherTracking()) {
            $actions[] = IssuePromotionVouchersFromListAction::make();
        }

        return $actions;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PromotionStatsWidget::class,
            TopPromotionsUsageChart::class,
        ];
    }
}
