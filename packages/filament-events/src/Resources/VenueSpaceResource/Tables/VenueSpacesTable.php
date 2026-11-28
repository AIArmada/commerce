<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\VenueSpaceResource\Tables;

use AIArmada\Events\Features\Venues\Actions\ManageVenueSpaceAction;
use AIArmada\FilamentEvents\Resources\VenueSpaceResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

class VenueSpacesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('space_type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('capacity')
                    ->numeric()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('visibility')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->using(static function (Collection | LazyCollection $records): void {
                        foreach ($records as $record) {
                            abort_unless(VenueSpaceResource::canDelete($record), 403);
                            app(ManageVenueSpaceAction::class)->delete((string) $record->getKey(), Filament::auth()->user());
                        }
                    }),
                ]),
            ]);
    }
}
