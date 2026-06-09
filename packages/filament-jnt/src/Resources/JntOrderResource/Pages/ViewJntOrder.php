<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources\JntOrderResource\Pages;

use AIArmada\CommerceSupport\Filament\Pages\ReadOnlyViewRecord;
use AIArmada\FilamentJnt\Actions\CancelOrderAction;
use AIArmada\FilamentJnt\Actions\PrintAwbTableAction;
use AIArmada\FilamentJnt\Actions\SyncTrackingAction;
use AIArmada\FilamentJnt\Resources\JntOrderResource;
use Filament\Support\Icons\Heroicon;
use Override;

final class ViewJntOrder extends ReadOnlyViewRecord
{
    protected static string $resource = JntOrderResource::class;

    #[Override]
    public function getTitle(): string
    {
        $record = $this->getRecord();

        return sprintf('Order %s', $record->order_id ?? $record->getKey());
    }

    public function getHeadingIcon(): Heroicon
    {
        return Heroicon::OutlinedTruck;
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            PrintAwbTableAction::make(),
            SyncTrackingAction::make(),
            CancelOrderAction::make(),
        ];
    }
}
