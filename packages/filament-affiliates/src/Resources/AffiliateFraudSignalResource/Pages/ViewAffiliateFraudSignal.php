<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewAffiliateFraudSignal extends ViewRecord
{
    protected static string $resource = AffiliateFraudSignalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('dismiss')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status->value === 'detected')
                ->action(fn () => $this->record->update([
                    'status' => 'dismissed',
                    'reviewed_at' => now(),
                ])),
            Actions\Action::make('confirm')
                ->icon('heroicon-o-check')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status->value === 'detected')
                ->action(fn () => $this->record->update([
                    'status' => 'confirmed',
                    'reviewed_at' => now(),
                ])),
        ];
    }
}
