<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\OccurrenceResource\RelationManagers;

use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\FilamentEvents\Resources\RegistrationResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use RuntimeException;

final class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'Registrations';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema(RegistrationResource::formSchema(includeOccurrenceField: false));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Participant')
                    ->searchable(['first_name', 'last_name'])
                    ->description(fn (Registration $record): string => RegistrationResource::registrationContactLabel($record)),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())
                    ->color(fn (RegistrationStatus $state): string => $state->color()),

                Tables\Columns\TextColumn::make('checked_in_at')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Not checked in'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(RegistrationStatus::options()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        return RegistrationResource::normalizeCreateData($data, $this->ownerOccurrence());
                    }),
            ])
            ->actions([
                EditAction::make(),
                RegistrationResource::approveAction(),
                RegistrationResource::rejectAction(),
                RegistrationResource::checkInAction(),
                RegistrationResource::cancelAction(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private function ownerOccurrence(): Occurrence
    {
        $ownerRecord = $this->getOwnerRecord();

        if ($ownerRecord instanceof Occurrence) {
            return $ownerRecord;
        }

        throw new RuntimeException(sprintf(
            'Expected occurrence owner record, got %s.',
            $ownerRecord::class,
        ));
    }
}
