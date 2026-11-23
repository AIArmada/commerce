<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\MerchantStatementResource\Tables;

use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\IssueMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\MarkMerchantStatementPaid;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\VoidMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class MerchantStatementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('statement_number')
                    ->label('Statement')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                TextColumn::make('site.name')
                    ->label('Site')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (SettlementStatus | string $state): string => $state instanceof SettlementStatus ? $state->color() : (SettlementStatus::tryFrom((string) $state)?->color() ?? 'gray')),

                TextColumn::make('currency')
                    ->badge(),

                TextColumn::make('leg_count')
                    ->label('Legs')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('commission_minor')
                    ->label('Commission')
                    ->formatStateUsing(fn (MerchantStatement $record): string => MoneyFormatter::formatMinor($record->commission_minor, $record->currency))
                    ->sortable(),

                TextColumn::make('fee_minor')
                    ->label('Fee')
                    ->formatStateUsing(fn (MerchantStatement $record): string => MoneyFormatter::formatMinor($record->fee_minor, $record->currency))
                    ->toggleable(),

                TextColumn::make('payout_minor')
                    ->label('Payout')
                    ->formatStateUsing(fn (MerchantStatement $record): string => MoneyFormatter::formatMinor($record->payout_minor, $record->currency))
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('period_start')
                    ->label('Period start')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('period_end')
                    ->label('Period end')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('issued_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('due_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(self::statusOptions()),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\Action::make('issue')
                    ->label('Issue')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (MerchantStatement $record): bool => $record->status === SettlementStatus::Draft)
                    ->action(function (MerchantStatement $record): void {
                        app(IssueMerchantStatement::class)->execute((string) $record->getKey());

                        Notification::make()
                            ->title('Statement issued')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('mark_paid')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (MerchantStatement $record): bool => in_array($record->status, [SettlementStatus::Draft, SettlementStatus::Issued], true))
                    ->form([
                        TextInput::make('payment_reference')
                            ->label('Payment reference')
                            ->maxLength(255)
                            ->nullable(),
                    ])
                    ->action(function (MerchantStatement $record, array $data): void {
                        $reference = is_string($data['payment_reference'] ?? null) && $data['payment_reference'] !== ''
                            ? $data['payment_reference']
                            : null;

                        app(MarkMerchantStatementPaid::class)->execute((string) $record->getKey(), $reference);

                        Notification::make()
                            ->title('Statement marked paid')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('void')
                    ->label('Void')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (MerchantStatement $record): bool => in_array($record->status, [SettlementStatus::Draft, SettlementStatus::Issued], true))
                    ->action(function (MerchantStatement $record): void {
                        app(VoidMerchantStatement::class)->execute((string) $record->getKey());

                        Notification::make()
                            ->title('Statement voided')
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [];

        foreach (SettlementStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
