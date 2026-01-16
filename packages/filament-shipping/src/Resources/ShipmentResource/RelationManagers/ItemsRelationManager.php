<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShipmentResource\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'name';

    private function resolveCurrency(): string
    {
        if (! isset($this->ownerRecord)) {
            return (string) config('shipping.defaults.currency', 'MYR');
        }

        return $this->getOwnerRecord()->currency ?? (string) config('shipping.defaults.currency', 'MYR');
    }

    public function table(Table $table): Table
    {
        $weightUnit = (string) config('shipping.defaults.weight_unit', 'g');

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric(),

                Tables\Columns\TextColumn::make('weight')
                    ->formatStateUsing(fn ($state): string => $state === null
                        ? '-'
                        : ($weightUnit === 'kg'
                            ? number_format($state / 1000, 2) . ' kg'
                            : number_format($state) . ' g')),

                Tables\Columns\TextColumn::make('declared_value')
                    ->money(fn (): string => $this->resolveCurrency(), divideBy: 100),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }
}
