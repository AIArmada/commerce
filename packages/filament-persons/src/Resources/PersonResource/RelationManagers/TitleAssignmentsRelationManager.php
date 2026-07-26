<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers;

use AIArmada\Persons\Enums\AssignmentStatus;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class TitleAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'titleAssignments';

    protected static ?string $title = 'Titles';

    protected static ?string $recordTitleAttribute = 'title_id';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title.name')
                    ->label('Title')
                    ->searchable(),
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
                    ->form([
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
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
