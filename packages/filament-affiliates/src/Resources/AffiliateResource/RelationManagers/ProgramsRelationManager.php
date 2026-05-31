<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers;

use AIArmada\Affiliates\Enums\MembershipStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ProgramsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('program_id')
                ->relationship('program', 'name')
                ->searchable()
                ->preload()
                ->required(),

            Select::make('tier_id')
                ->relationship('tier', 'name')
                ->searchable()
                ->preload(),

            Select::make('status')
                ->options(MembershipStatus::class)
                ->required()
                ->default(MembershipStatus::Pending),

            DateTimePicker::make('applied_at')
                ->required()
                ->default(now()),

            DateTimePicker::make('approved_at'),

            DateTimePicker::make('expires_at'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('program.name')
            ->columns([
                TextColumn::make('program.name')
                    ->label('Program')
                    ->searchable(),

                TextColumn::make('tier.name')
                    ->label('Tier')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('applied_at')
                    ->label('Applied')
                    ->dateTime(),

                TextColumn::make('approved_at')
                    ->label('Approved')
                    ->dateTime()
                    ->placeholder('—'),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
