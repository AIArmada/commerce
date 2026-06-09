<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CollectionResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

class CollectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Collection')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->slug),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => $state === 'automatic' ? 'info' : 'gray'),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Immediate')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('position')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'manual' => 'Manual',
                        'automatic' => 'Automatic',
                    ]),

                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visible'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('rebuild')
                    ->label('Rebuild')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => $record->isAutomatic())
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->rebuildProductList();

                        Notification::make()
                            ->success()
                            ->title('Collection Rebuilt')
                            ->body('Products have been re-matched based on conditions.')
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
