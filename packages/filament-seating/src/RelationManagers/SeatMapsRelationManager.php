<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\FilamentSeating\Resources\SeatMapResource;
use AIArmada\Seating\Features\Maps\Actions\SaveSeatMap;
use AIArmada\Seating\Features\Maps\Actions\DeleteSeatMap;
use AIArmada\Seating\Models\SeatMap;
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
    use VerifiesRelationManagerOwnerContext;

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
                CreateAction::make()
                    ->visible(fn (): bool => SeatMapResource::canCreate())
                    ->using(function (array $data): Model {
                        abort_unless(SeatMapResource::canCreate(), 403);

                        return app(SaveSeatMap::class)->handle($data, host: $this->getOwnerRecord());
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (SeatMap $record): bool => SeatMapResource::canEdit($record))
                    ->using(function (SeatMap $record, array $data): Model {
                        abort_unless(SeatMapResource::canEdit($record), 403);

                        return app(SaveSeatMap::class)->handle($data, $record, $this->getOwnerRecord());
                    }),
                DeleteAction::make()
                    ->visible(fn (SeatMap $record): bool => SeatMapResource::canDelete($record))
                    ->using(function (SeatMap $record): bool {
                        abort_unless(SeatMapResource::canDelete($record), 403);

                        return app(DeleteSeatMap::class)->handle($record, $this->getOwnerRecord());
                    }),
            ]);
    }
}
