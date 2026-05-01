<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\CustomerResource\RelationManagers;

use AIArmada\CashierChip\Subscription;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    protected static ?string $recordTitleAttribute = 'type';

    protected static ?string $title = 'Subscriptions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->searchable()
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('chip_price')
                    ->label('Price')
                    ->badge()
                    ->color('primary')
                    ->placeholder('Multiple'),

                TextColumn::make('chip_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Subscription $record): string => self::getStatusColor($record->chip_status)),

                IconColumn::make('on_trial')
                    ->label('Trial')
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->getStateUsing(fn (Subscription $record): bool => $record->onTrial()),

                TextColumn::make('next_billing_at')
                    ->label('Next Billing')
                    ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                    ->placeholder('—'),

                TextColumn::make('ends_at')
                    ->label('Ends At')
                    ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                    ->placeholder('—')
                    ->color(fn (Subscription $record): string => $record->onGracePeriod() ? 'warning' : 'danger'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([])
            ->actions([
                ViewAction::make()
                    ->url(fn (Subscription $record): string => SubscriptionResource::getUrl('view', ['record' => $record])),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Subscription $record): bool => ! $record->canceled())
                    ->action(function (Subscription $record): void {
                        $record->cancel();

                        Notification::make()
                            ->title('Subscription Canceled')
                            ->success()
                            ->send();
                    }),

                Action::make('resume')
                    ->label('Resume')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Subscription $record): bool => $record->onGracePeriod())
                    ->action(function (Subscription $record): void {
                        $record->resume();

                        Notification::make()
                            ->title('Subscription Resumed')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No Subscriptions')
            ->emptyStateDescription('This customer has no subscriptions yet.');
    }

    private static function getStatusColor(string $status): string
    {
        return match ($status) {
            Subscription::STATUS_ACTIVE => 'success',
            Subscription::STATUS_TRIALING => 'warning',
            Subscription::STATUS_CANCELED => 'danger',
            Subscription::STATUS_PAST_DUE => 'danger',
            Subscription::STATUS_PAUSED => 'gray',
            Subscription::STATUS_INCOMPLETE => 'warning',
            Subscription::STATUS_UNPAID => 'danger',
            default => 'gray',
        };
    }
}
