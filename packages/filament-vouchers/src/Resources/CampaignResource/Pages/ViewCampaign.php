<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\CampaignResource\Pages;

use AIArmada\FilamentVouchers\Actions\ActivateCampaignAction;
use AIArmada\FilamentVouchers\Actions\DeclareABWinnerAction;
use AIArmada\FilamentVouchers\Actions\PauseCampaignAction;
use AIArmada\FilamentVouchers\Resources\CampaignResource;
use AIArmada\FilamentVouchers\Widgets\CampaignStatsWidget;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActivateCampaignAction::make(),
            PauseCampaignAction::make(),
            DeclareABWinnerAction::make(),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CampaignStatsWidget::class,
        ];
    }
}
