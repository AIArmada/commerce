<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource\Pages;

use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewAffiliateFraudSignal extends ViewRecord
{
    protected static string $resource = AffiliateFraudSignalResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        if (! $record instanceof AffiliateFraudSignal) {
            return [];
        }

        return [
            Actions\Action::make('dismiss')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => $record->status === FraudSignalStatus::Detected)
                ->action(fn (): bool => $record->update([
                    'status' => FraudSignalStatus::Dismissed,
                    'reviewed_at' => now(),
                ])),
            Actions\Action::make('confirm')
                ->icon('heroicon-o-check')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $record->status === FraudSignalStatus::Detected)
                ->action(fn (): bool => $record->update([
                    'status' => FraudSignalStatus::Confirmed,
                    'reviewed_at' => now(),
                ])),
        ];
    }
}
