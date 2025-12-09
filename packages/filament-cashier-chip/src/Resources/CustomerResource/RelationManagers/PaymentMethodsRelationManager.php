<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\CustomerResource\RelationManagers;

use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PaymentMethodsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentMethods';

    protected static ?string $title = 'Payment Methods';

    /**
     * Since payment methods are not Eloquent models (they're from API),
     * we need to handle this differently.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getOwnerRecord()->query()->whereKey($this->getOwnerRecord()->getKey()))
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->getStateUsing(fn (): string => $this->getOwnerRecord()->pm_type ?? 'Unknown')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (?string $state): string => ucfirst($state ?? 'Unknown')),

                TextColumn::make('last_four')
                    ->label('Last Four')
                    ->getStateUsing(fn (): string => '•••• ' . ($this->getOwnerRecord()->pm_last_four ?? '****')),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('success')
                    ->getStateUsing(fn (): bool => true),
            ])
            ->headerActions([
                Action::make('add_payment_method')
                    ->label('Add Payment Method')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->visible(fn (): bool => ! empty($this->getOwnerRecord()->chip_id))
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
                                        \Filament\Notifications\Actions\Action::make('copy')
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
                    ->visible(fn (): bool => ! empty($this->getOwnerRecord()->chip_id))
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
                    ->action(function (): void {
                        $record = $this->getOwnerRecord();

                        if (method_exists($record, 'deletePaymentMethods')) {
                            $record->deletePaymentMethods();

                            Notification::make()
                                ->title('Payment Methods Deleted')
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
                    ->visible(fn (): bool => ! empty($this->getOwnerRecord()->chip_id))
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

    /**
     * Override to indicate this isn't a standard Eloquent relationship.
     */
    public function isReadOnly(): bool
    {
        return true;
    }
}
