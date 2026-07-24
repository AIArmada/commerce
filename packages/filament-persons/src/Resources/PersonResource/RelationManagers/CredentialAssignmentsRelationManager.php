<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class CredentialAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'credentialAssignments';

    protected static ?string $title = 'Credentials';

    protected static ?string $recordTitleAttribute = 'credential_id';

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
                    ->form([
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
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
