<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\GiftCardResource\Pages;

use AIArmada\FilamentVouchers\Actions\ActivateGiftCardAction;
use AIArmada\FilamentVouchers\Actions\SuspendGiftCardAction;
use AIArmada\FilamentVouchers\Actions\TopUpGiftCardAction;
use AIArmada\FilamentVouchers\Resources\GiftCardResource;
use AIArmada\FilamentVouchers\Widgets\GiftCardStatsWidget;
use AIArmada\FilamentVouchers\Widgets\GiftCardTransactionTimelineWidget;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewGiftCard extends ViewRecord
{
    protected static string $resource = GiftCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActivateGiftCardAction::make(),
            TopUpGiftCardAction::make(),
            SuspendGiftCardAction::make(),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            GiftCardStatsWidget::class,
            GiftCardTransactionTimelineWidget::class,
        ];
    }
}
