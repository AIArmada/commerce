<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Resources\PromotionResource\Pages;

use AIArmada\FilamentPromotions\Actions\IssuePromotionVouchersAction;
use AIArmada\FilamentPromotions\Resources\PromotionResource;
use AIArmada\Promotions\Models\Promotion;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewPromotion extends ViewRecord
{
    protected static string $resource = PromotionResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (Promotion::supportsIssuedVoucherTracking()) {
            $actions[] = IssuePromotionVouchersAction::make();
        }

        $actions[] = EditAction::make();
        $actions[] = DeleteAction::make();

        return $actions;
    }
}
