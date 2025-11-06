<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\Pages;

use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Widgets\VoucherCartStatsWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherUsageTimelineWidget;
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
            Actions\EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            VoucherCartStatsWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            VoucherUsageTimelineWidget::class,
        ];
    }
}
