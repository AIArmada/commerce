<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeSetResource\Tables;

use AIArmada\Products\Models\AttributeSet;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class AttributeSetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('filament-products::resources.attribute_sets.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament-products::resources.attribute_sets.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('set_attributes_count')
                    ->label(__('filament-products::resources.attribute_sets.fields.attributes_count'))
                    ->counts('setAttributes')
                    ->badge()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('groups_count')
                    ->label(__('filament-products::resources.attribute_sets.fields.groups_count'))
                    ->counts('groups')
                    ->badge()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label(__('filament-products::resources.attribute_sets.fields.is_default'))
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament-products::resources.attribute_sets.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label(__('filament-products::resources.attribute_sets.fields.is_default')),
            ])
            ->actions([
                EditAction::make(),
                Action::make('setDefault')
                    ->label(__('filament-products::resources.attribute_sets.actions.set_default'))
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (AttributeSet $record) => $record->setAsDefault())
                    ->visible(fn (AttributeSet $record): bool => ! $record->is_default),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
