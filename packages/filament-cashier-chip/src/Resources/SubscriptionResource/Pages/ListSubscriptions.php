<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Pages;

use AIArmada\CashierChip\Subscription;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource;
use AIArmada\FilamentCashierChip\Support\CashierChipOwnerScope;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'Subscriptions';
    }

    #[Override]
    public function getSubheading(): string
    {
        return 'Manage subscription billing and lifecycle across all customers.';
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulk_pause')
                ->label('Bulk Pause')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Pause All Active Subscriptions')
                ->modalDescription('Are you sure you want to pause all active subscriptions? This action will prevent billing for all subscribers.')
                ->action(function (): void {
                    $count = CashierChipOwnerScope::apply(Subscription::query())
                        ->where('chip_status', Subscription::STATUS_ACTIVE)
                        ->update(['chip_status' => Subscription::STATUS_PAUSED]);

                    Notification::make()
                        ->title('Subscriptions Paused')
                        ->body("Successfully paused {$count} subscriptions.")
                        ->success()
                        ->send();
                }),

            Action::make('bulk_resume')
                ->label('Bulk Resume')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Resume All Paused Subscriptions')
                ->modalDescription('Are you sure you want to resume all paused subscriptions? This will re-enable billing for all paused subscribers.')
                ->action(function (): void {
                    $count = CashierChipOwnerScope::apply(Subscription::query())
                        ->where('chip_status', Subscription::STATUS_PAUSED)
                        ->update(['chip_status' => Subscription::STATUS_ACTIVE]);

                    Notification::make()
                        ->title('Subscriptions Resumed')
                        ->body("Successfully resumed {$count} subscriptions.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
