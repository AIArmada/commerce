<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers;

use AIArmada\Persons\Enums\AssignmentStatus;
use AIArmada\Persons\Models\Title;
use AIArmada\Persons\Models\TitleAssignment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class TitleAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'titleAssignments';

    protected static ?string $title = 'Titles';

    protected static ?string $recordTitleAttribute = 'title_id';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('title_id')
                    ->label('Title')
                    ->relationship('title', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('date_awarded'),
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
                Tables\Columns\TextColumn::make('title.name')
                    ->label('Title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title.sort_order')
                    ->label('Order')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title.usage_position')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (AssignmentStatus $state): string => match ($state) {
                        AssignmentStatus::Active => 'success',
                        AssignmentStatus::Revoked => 'danger',
                        AssignmentStatus::Expired => 'warning',
                    }),
                Tables\Columns\TextColumn::make('date_awarded')
                    ->date()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('date_expired')
                    ->date()
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        Title::query()->findOrFail($data['title_id']);

                        $this->assertAssignmentData($data);

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data, ?Model $record): array {
                        if (isset($data['title_id'])) {
                            Title::query()->findOrFail($data['title_id']);
                        }

                        $this->assertAssignmentData($data, $record instanceof TitleAssignment ? $record->getKey() : null);

                        return $data;
                    }),
                DeleteAction::make(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertAssignmentData(array $data, mixed $excludeId = null): void
    {
        $awarded = $data['date_awarded'] ?? null;
        $expired = $data['date_expired'] ?? null;

        if ($awarded !== null && $awarded !== '' && $expired !== null && $expired !== '' && (string) $expired < (string) $awarded) {
            throw ValidationException::withMessages([
                'date_expired' => ['The expiry date must be on or after the awarded date.'],
            ]);
        }

        if (! isset($data['title_id']) || ! is_scalar($data['title_id'])) {
            return;
        }

        $owner = $this->getOwnerRecord();

        $exists = TitleAssignment::query()
            ->where('titleable_type', $owner->getMorphClass())
            ->where('titleable_id', $owner->getKey())
            ->where('title_id', (string) $data['title_id'])
            ->when($excludeId !== null, static fn ($query) => $query->whereKeyNot($excludeId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'title_id' => ['This title is already assigned to the person.'],
            ]);
        }
    }
}
