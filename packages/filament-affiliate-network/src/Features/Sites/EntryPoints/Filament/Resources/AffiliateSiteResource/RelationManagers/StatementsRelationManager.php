<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource\RelationManagers;

use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\IssueMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\MarkMerchantStatementPaid;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\VoidMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources\MerchantStatementResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class StatementsRelationManager extends RelationManager
{
    protected static string $relationship = 'statements';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutGlobalScope(OwnerScope::class))
            ->recordTitleAttribute('statement_number')
            ->columns([
                TextColumn::make('statement_number')
                    ->label('Statement')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('period_start')
                    ->label('Period start')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('period_end')
                    ->label('Period end')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('leg_count')
                    ->label('Legs')
                    ->numeric(),

                TextColumn::make('commission_minor')
                    ->label('Commission')
                    ->formatStateUsing(fn (MerchantStatement $record): string => MoneyFormatter::formatMinor($record->commission_minor, $record->currency)),

                TextColumn::make('fee_minor')
                    ->label('Fee')
                    ->formatStateUsing(fn (MerchantStatement $record): string => MoneyFormatter::formatMinor($record->fee_minor, $record->currency))
                    ->toggleable(),

                TextColumn::make('payout_minor')
                    ->label('Payout')
                    ->formatStateUsing(fn (MerchantStatement $record): string => MoneyFormatter::formatMinor($record->payout_minor, $record->currency))
                    ->badge()
                    ->color('success'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (SettlementStatus | string $state): string => $state instanceof SettlementStatus ? $state->label() : (SettlementStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn (SettlementStatus | string $state): string => $state instanceof SettlementStatus ? $state->color() : (SettlementStatus::tryFrom((string) $state)?->color() ?? 'gray')),

                TextColumn::make('due_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions([])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (MerchantStatement $record): string => MerchantStatementResource::getUrl('view', ['record' => $record])),

                Action::make('issue')
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

                Action::make('mark_paid')
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

                Action::make('void')
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
}
