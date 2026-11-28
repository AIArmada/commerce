<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\Persons\Features\Credentials\Actions\AssignCredentialAction;
use AIArmada\Persons\Features\Credentials\Models\CredentialAssignment;
use AIArmada\Persons\Features\Credentials\Models\CredentialDefinition;
use AIArmada\Persons\Models\Person;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class CredentialAssignmentsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'credentialAssignments';

    protected static ?string $title = 'Credentials';

    protected static ?string $recordTitleAttribute = 'credential_id';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('credential_id')
                    ->label('Credential')
                    ->relationship('credential', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('registration_number')
                    ->maxLength(100),
                DatePicker::make('date_obtained'),
                DatePicker::make('date_expired'),
                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'revoked' => 'Revoked',
                        'expired' => 'Expired',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('credential.name')
                    ->label('Credential')
                    ->searchable(),
                Tables\Columns\TextColumn::make('credential.short_form')
                    ->badge()
                    ->label('Short'),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('date_obtained')
                    ->date()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('registration_number')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): CredentialAssignment {
                        $id = (string) $data['credential_id'];
                        unset($data['credential_id']);

                        return app(AssignCredentialAction::class)->execute($this->person(), $id, $data);
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        CredentialDefinition::query()->findOrFail($data['credential_id']);

                        $this->assertAssignmentData($data);

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->using(fn (CredentialAssignment $record, array $data): CredentialAssignment => app(AssignCredentialAction::class)->update($this->person(), $record, $data))
                    ->mutateFormDataUsing(function (array $data, ?Model $record): array {
                        if (isset($data['credential_id'])) {
                            CredentialDefinition::query()->findOrFail($data['credential_id']);
                        }

                        $this->assertAssignmentData($data, $record instanceof CredentialAssignment ? $record->getKey() : null);

                        return $data;
                    }),
                DeleteAction::make()
                    ->using(fn (CredentialAssignment $record): bool => app(AssignCredentialAction::class)->delete($this->person(), $record)),
            ]);
    }

    private function person(): Person
    {
        $record = $this->getOwnerRecord();

        if (! $record instanceof Person) {
            throw new InvalidArgumentException('Invalid person record.');
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertAssignmentData(array $data, mixed $excludeId = null): void
    {
        $obtained = $data['date_obtained'] ?? null;
        $expired = $data['date_expired'] ?? null;

        if ($obtained !== null && $obtained !== '' && $expired !== null && $expired !== '' && (string) $expired < (string) $obtained) {
            throw ValidationException::withMessages([
                'date_expired' => ['The expiry date must be on or after the obtained date.'],
            ]);
        }

        if (! isset($data['credential_id']) || ! is_scalar($data['credential_id'])) {
            return;
        }

        $owner = $this->getOwnerRecord();

        $exists = CredentialAssignment::query()
            ->where('credentialable_type', $owner->getMorphClass())
            ->where('credentialable_id', $owner->getKey())
            ->where('credential_id', (string) $data['credential_id'])
            ->when($excludeId !== null, static fn ($query) => $query->whereKeyNot($excludeId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'credential_id' => ['This credential is already assigned to the person.'],
            ]);
        }
    }
}
