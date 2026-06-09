<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeResource\Tables;

use AIArmada\Products\Enums\AttributeType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class AttributesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('filament-products::resources.attributes.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament-products::resources.attributes.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('filament-products::resources.attributes.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (AttributeType $state): string => $state->label())
                    ->color(fn (AttributeType $state): string => $state->color())
                    ->icon(fn (AttributeType $state): string => $state->icon()),

                Tables\Columns\TextColumn::make('groups.name')
                    ->label(__('filament-products::resources.attributes.fields.groups'))
                    ->badge()
                    ->separator(',')
                    ->limitList(2)
                    ->expandableLimitedList(),

                Tables\Columns\IconColumn::make('is_required')
                    ->label(__('filament-products::resources.attributes.fields.is_required'))
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_filterable')
                    ->label(__('filament-products::resources.attributes.fields.is_filterable'))
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_searchable')
                    ->label(__('filament-products::resources.attributes.fields.is_searchable'))
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('position')
                    ->label(__('filament-products::resources.attributes.fields.position'))
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament-products::resources.attributes.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('position')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('filament-products::resources.attributes.fields.type'))
                    ->options(
                        collect(AttributeType::cases())
                            ->mapWithKeys(fn (AttributeType $type) => [$type->value => $type->label()])
                    ),

                Tables\Filters\SelectFilter::make('groups')
                    ->label(__('filament-products::resources.attributes.fields.groups'))
                    ->relationship(
                        'groups',
                        'name',
                        modifyQueryUsing: fn ($query) => $query->forOwner()
                    )
                    ->multiple()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_required')
                    ->label(__('filament-products::resources.attributes.fields.is_required')),

                Tables\Filters\TernaryFilter::make('is_filterable')
                    ->label(__('filament-products::resources.attributes.fields.is_filterable')),

                Tables\Filters\TernaryFilter::make('is_searchable')
                    ->label(__('filament-products::resources.attributes.fields.is_searchable')),
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
