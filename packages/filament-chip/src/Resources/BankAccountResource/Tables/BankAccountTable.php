<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\BankAccountResource\Tables;

use AIArmada\Chip\Models\BankAccount;
use AIArmada\Chip\Services\ChipSendService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

final class BankAccountTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('name')
                    ->label('Account Holder')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('account_number')
                    ->label('Account Number')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('bank_code')
                    ->label('Bank')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (BankAccount $record): string => $record->statusLabel())
                    ->color(fn (BankAccount $record): string => $record->statusColor())
                    ->sortable(),

                IconColumn::make('is_debiting_account')
                    ->label('Debit')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedXCircle)
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_crediting_account')
                    ->label('Credit')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedXCircle)
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('group_id')
                    ->label('Group')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'verified' => 'Verified',
                        'pending' => 'Pending',
                        'rejected' => 'Rejected',
                    ])
                    ->label('Status'),

                SelectFilter::make('is_debiting_account')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ])
                    ->label('Debiting Account'),

                SelectFilter::make('is_crediting_account')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ])
                    ->label('Crediting Account'),
            ])
            ->actions([
                ViewAction::make()
                    ->iconButton(),

                ActionGroup::make([
                    Action::make('delete')
                        ->label('Delete Account')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete Bank Account')
                        ->modalDescription('This will delete the bank account from CHIP Send. This cannot be undone.')
                        ->action(function (BankAccount $record): void {
                            $service = app(ChipSendService::class);
                            $scopedRecord = self::resolveScopedBankAccount($record);

                            if ($scopedRecord === null) {
                                Notification::make()
                                    ->title('Bank account is outside your owner scope')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                $service->deleteBankAccount((int) $scopedRecord->getKey());
                                Notification::make()
                                    ->title('Bank account deleted')
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                Notification::make()
                                    ->title('Failed to delete account')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->visible(fn (BankAccount $record): bool => $record->getAttribute('deleted_at') === null),
                ])
                    ->iconButton()
                    ->icon(Heroicon::OutlinedEllipsisVertical),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No bank accounts')
            ->emptyStateDescription('Register bank accounts to send payouts via CHIP Send.')
            ->emptyStateIcon(Heroicon::OutlinedBuildingLibrary)
            ->poll('30s');
    }

    private static function resolveScopedBankAccount(BankAccount $record): ?BankAccount
    {
        return BankAccount::query()
            ->forOwner()
            ->whereKey($record->getKey())
            ->first();
    }
}
