<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\Pages;

use AIArmada\FilamentVouchers\Actions\AddToMyWalletAction;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Widgets\VoucherCartStatsWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherUsageTimelineWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherWalletStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

final class ViewVoucher extends ViewRecord
{
    protected static string $resource = VoucherResource::class;

    public function infolist(Schema $schema): Schema
    {
        return self::getResource()::infolist($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            AddToMyWalletAction::make(),
            Actions\EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            VoucherCartStatsWidget::class,
            VoucherWalletStatsWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            VoucherUsageTimelineWidget::class,
        ];
    }
}
