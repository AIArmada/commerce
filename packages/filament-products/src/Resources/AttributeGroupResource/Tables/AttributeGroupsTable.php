<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeGroupResource\Tables;

use AIArmada\Products\Enums\Visibility;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
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
                    ->color(fn (string $state): string => Visibility::tryFrom($state)?->color() ?? 'gray')
                    ->formatStateUsing(fn (string $state): string => Visibility::tryFrom($state)?->label() ?? $state)
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
                    ->options(Visibility::class),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('position');
    }
}
