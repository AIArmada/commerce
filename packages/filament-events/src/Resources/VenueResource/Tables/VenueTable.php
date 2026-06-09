<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\VenueResource\Tables;

use AIArmada\Events\Models\Venue;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

final class VenueTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Venue $record): string => $record->slug),

                TextColumn::make('city')
                    ->searchable()
                    ->placeholder('No city'),

                TextColumn::make('location_type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Str::headline($state ?? 'physical'))
                    ->sortable(),

                TextColumn::make('country')
                    ->badge()
                    ->sortable(),

                TextColumn::make('timezone')
                    ->placeholder('Not set')
                    ->toggleable(),

                TextColumn::make('occurrences_count')
                    ->label('Runs')
                    ->counts('occurrences')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
