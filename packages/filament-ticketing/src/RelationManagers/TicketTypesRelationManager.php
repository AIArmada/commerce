<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\FilamentTicketing\Resources\TicketTypeResource;
use AIArmada\FilamentTicketing\Schemas\TicketTypeFormSchema;
use AIArmada\FilamentTicketing\Tables\TicketTypeTable;
use AIArmada\Ticketing\Features\TicketTypes\Actions\DeleteTicketType;
use AIArmada\Ticketing\Features\TicketTypes\Actions\SaveTicketType;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;
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
    use VerifiesRelationManagerOwnerContext;

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
                CreateAction::make()
                    ->visible(fn (): bool => TicketTypeResource::canCreate())
                    ->using(function (array $data): Model {
                        abort_unless(TicketTypeResource::canCreate(), 403);

                        return app(SaveTicketType::class)->handle($this->getOwnerRecord(), $data);
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (TicketType $record): bool => TicketTypeResource::canEdit($record))
                    ->using(function (TicketType $record, array $data): Model {
                        abort_unless(TicketTypeResource::canEdit($record), 403);

                        return app(SaveTicketType::class)->handle($this->getOwnerRecord(), $data, $record);
                    }),
                DeleteAction::make()
                    ->visible(fn (TicketType $record): bool => TicketTypeResource::canDelete($record))
                    ->using(function (TicketType $record): bool {
                        abort_unless(TicketTypeResource::canDelete($record), 403);

                        return app(DeleteTicketType::class)->handle($record, $this->getOwnerRecord());
                    }),
            ]);
    }
}
