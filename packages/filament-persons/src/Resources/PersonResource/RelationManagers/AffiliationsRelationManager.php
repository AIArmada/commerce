<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AffiliationsRelationManager extends RelationManager
{
    protected static string $relationship = 'affiliations';

    protected static ?string $title = 'Affiliations';

    protected static ?string $recordTitleAttribute = 'institution_id';

    /**
     * @return array<string, string>
     */
    public static function getInstitutionOptions(): array
    {
        return [];
    }

    public static function getInstitutionLabel(string $id): ?string
    {
        return static::getInstitutionOptions()[$id] ?? null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('affiliation_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('institution_id')
                    ->label('Institution')
                    ->formatStateUsing(fn (string $state): string => static::getInstitutionLabel($state) ?? $state)
                    ->placeholder('-'),
                Tables\Columns\IconColumn::make('is_primary')
                    ->boolean(),
                Tables\Columns\TextColumn::make('joined_at')
                    ->date()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('left_at')
                    ->date()
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->form([
                        Select::make('affiliation_type')
                            ->options([
                                'member' => 'Member',
                                'employee' => 'Employee',
                                'advisor' => 'Advisor',
                                'partner' => 'Partner',
                            ])
                            ->required(),
                        Select::make('institution_id')
                            ->label('Institution')
                            ->options(static::getInstitutionOptions())
                            ->searchable()
                            ->preload(),
                        DatePicker::make('joined_at'),
                        DatePicker::make('left_at'),
                        Checkbox::make('is_primary'),
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
