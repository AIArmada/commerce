<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Resources\OrderResource\RelationManagers;

use AIArmada\Orders\Enums\PaymentStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class RefundsRelationManager extends RelationManager
{
    protected static string $relationship = 'refunds';

    protected static ?string $title = 'Refunds';

    private function resolveCurrency(): string
    {
        if (! isset($this->ownerRecord)) {
            return (string) config('orders.currency.default', 'MYR');
        }

        return $this->getOwnerRecord()->currency ?? (string) config('orders.currency.default', 'MYR');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('gateway')
                    ->label('Gateway')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->copyable()
                    ->searchable()
                    ->placeholder('Pending'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money(fn (): string => $this->resolveCurrency(), divideBy: 100)
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (PaymentStatus $state) => $state->color()),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('refunded_at')
                    ->label('Refunded At')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Pending')
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}
