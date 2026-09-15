<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\RelationManagers;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentSeating\Schemas\SeatMapFormSchema;
use AIArmada\FilamentSeating\Tables\SeatMapTable;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Manage seat maps on any owner with a `seatMaps` relationship.
 *
 * Register it from the host resource's `getRelations()` (for example through
 * the `filament-events` relation-manager config seam). The form and table
 * reuse the seat-map resource definitions.
 */
class SeatMapsRelationManager extends RelationManager
{
    protected static string $relationship = 'seatMaps';

    protected static ?string $title = 'Seat Maps';

    public function getRelationship(): Relation | Builder
    {
        return parent::getRelationship()->withCount('sections');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema(SeatMapFormSchema::make());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns(SeatMapTable::columns())
            ->filters(SeatMapTable::filters())
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (?Model $record): bool => $record === null || OwnerUiScope::canMutateRecord($record))
                    ->before(function (?Model $record): void {
                        abort_unless($record === null || OwnerUiScope::canMutateRecord($record), 403);
                    }),
                DeleteAction::make()
                    ->visible(fn (?Model $record): bool => $record === null || OwnerUiScope::canMutateRecord($record))
                    ->before(function (?Model $record): void {
                        abort_unless($record === null || OwnerUiScope::canMutateRecord($record), 403);
                    }),
            ]);
    }
}
