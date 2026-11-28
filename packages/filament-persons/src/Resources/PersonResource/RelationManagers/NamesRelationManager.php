<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\FilamentPersons\Queries\LanguageOptions;
use AIArmada\Persons\Enums\PersonNameType;
use AIArmada\Persons\Features\Identity\Actions\SavePersonName;
use AIArmada\Persons\Features\Identity\Models\PersonName;
use AIArmada\Persons\Models\Person;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use InvalidArgumentException;

final class NamesRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'names';

    protected static ?string $title = 'Names';

    protected static ?string $recordTitleAttribute = 'full_name';

    /**
     * @return array<string, string>
     */
    public static function getLanguageOptions(): array
    {
        return app(LanguageOptions::class)->get();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('name_type')
                    ->options(PersonNameType::class)
                    ->required(),
                TextInput::make('full_name')
                    ->required()
                    ->maxLength(255),
                Select::make('language_code')
                    ->options(fn (): array => static::getLanguageOptions())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->default('en'),
                Checkbox::make('is_primary'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('language_code')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_primary')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(fn (array $data): PersonName => app(SavePersonName::class)->create($this->person(), $data)),
            ])
            ->actions([
                EditAction::make()
                    ->using(fn (PersonName $record, array $data): PersonName => app(SavePersonName::class)->update($this->person(), $record, $data)),
                DeleteAction::make()
                    ->using(fn (PersonName $record): bool => app(SavePersonName::class)->delete($this->person(), $record)),
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
}
