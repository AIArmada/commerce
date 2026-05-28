<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\CustomerResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PaymentMethodsRelationManager extends RelationManager
{
    protected static string $relationship = 'storedPaymentMethods';

    protected static ?string $title = 'Payment Methods';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('brand')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state, $record): string => ucfirst($state ?? $record->type ?? 'Unknown'))
                    ->badge()
                    ->color('primary'),

                TextColumn::make('last_four')
                    ->label('Last Four')
                    ->formatStateUsing(fn (?string $state): string => '•••• ' . ($state ?? '****')),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('success'),
            ])
            ->headerActions([
                Action::make('add_payment_method')
                    ->label('Add Payment Method')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->visible(fn (): bool => method_exists($this->getOwnerRecord(), 'hasChipId') && $this->getOwnerRecord()->hasChipId())
                    ->action(function (): void {
                        $record = $this->getOwnerRecord();

                        if (method_exists($record, 'setupPaymentMethodUrl')) {
                            $url = $record->setupPaymentMethodUrl([
                                'success_url' => url()->current(),
                                'cancel_url' => url()->current(),
                            ]);

                            if ($url) {
                                Notification::make()
                                    ->title('Payment Method Setup')
                                    ->body('Checkout URL generated. Redirect customer to complete setup.')
                                    ->info()
                                    ->actions([
                                        Action::make('copy')
                                            ->label('Copy URL')
                                            ->url($url)
                                            ->openUrlInNewTab(),
                                    ])
                                    ->persistent()
                                    ->send();
                            }
                        }
                    }),

                Action::make('refresh')
                    ->label('Refresh from Chip')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (): bool => method_exists($this->getOwnerRecord(), 'hasChipId') && $this->getOwnerRecord()->hasChipId())
                    ->action(function (): void {
                        $record = $this->getOwnerRecord();

                        if (method_exists($record, 'updateDefaultPaymentMethodFromChip')) {
                            $record->updateDefaultPaymentMethodFromChip();

                            Notification::make()
                                ->title('Payment Methods Refreshed')
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $owner = $this->getOwnerRecord();

                        if (method_exists($owner, 'deletePaymentMethod')) {
                            $owner->deletePaymentMethod($record->recurring_token);

                            Notification::make()
                                ->title('Payment Method Deleted')
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No Payment Methods')
            ->emptyStateDescription('This customer has no saved payment methods.')
            ->emptyStateActions([
                Action::make('add_first')
                    ->label('Add Payment Method')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => method_exists($this->getOwnerRecord(), 'hasChipId') && $this->getOwnerRecord()->hasChipId())
                    ->action(function (): void {
                        $record = $this->getOwnerRecord();

                        if (method_exists($record, 'setupPaymentMethodUrl')) {
                            $url = $record->setupPaymentMethodUrl();

                            Notification::make()
                                ->title('Setup URL Generated')
                                ->body($url)
                                ->info()
                                ->send();
                        }
                    }),
            ]);
    }
}
