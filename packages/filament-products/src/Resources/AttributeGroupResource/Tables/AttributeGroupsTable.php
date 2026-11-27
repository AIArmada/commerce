<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeGroupResource\Tables;

use AIArmada\FilamentProducts\Actions\DeleteCatalogBulkAction;
use AIArmada\Products\Actions\DeleteCatalogRecord;
use AIArmada\Products\Models\AttributeGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class AttributeGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('filament-products::resources.attribute_groups.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament-products::resources.attribute_groups.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('group_attributes_count')
                    ->label(__('filament-products::resources.attribute_groups.fields.attributes_count'))
                    ->counts('groupAttributes')
                    ->badge()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('visibility')
                    ->label(__('filament-products::resources.attribute_groups.fields.visibility'))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'hidden' ? 'gray' : 'success')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('position')
                    ->label(__('filament-products::resources.attribute_groups.fields.position'))
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament-products::resources.attribute_groups.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('position')
            ->filters([
                Tables\Filters\SelectFilter::make('visibility')
                    ->label(__('filament-products::resources.attribute_groups.fields.visibility'))
                    ->options(['visible' => 'Visible', 'hidden' => 'Hidden']),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()->using(fn (AttributeGroup $record): bool => app(DeleteCatalogRecord::class)->execute($record)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteCatalogBulkAction::make(),
                ]),
            ])
            ->reorderable('position');
    }
}
