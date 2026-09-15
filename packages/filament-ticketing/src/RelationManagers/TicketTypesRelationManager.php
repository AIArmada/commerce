<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\RelationManagers;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentTicketing\Schemas\TicketTypeFormSchema;
use AIArmada\FilamentTicketing\Tables\TicketTypeTable;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Manage ticket types on any owner with a `ticketTypes` relationship.
 *
 * Register it from the host resource's `getRelations()` (for example through
 * the `filament-events` relation-manager config seam). The form and table
 * reuse the ticket-type resource definitions, scoped to the owner record.
 */
class TicketTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'ticketTypes';

    protected static ?string $title = 'Ticket Types';

    public function form(Schema $schema): Schema
    {
        return $schema->schema(TicketTypeFormSchema::make($this->getOwnerRecord()));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns(TicketTypeTable::columns())
            ->filters(TicketTypeTable::filters())
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
