<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\RelationManagers;

use AIArmada\Events\Enums\EventModerationStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

final class EventReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    protected static ?string $title = 'Reviews';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('event_submission_id')
                    ->label('Submission')
                    ->relationship('submission', 'id')
                    ->searchable()
                    ->nullable(),

                TextInput::make('reviewed_by_type')
                    ->maxLength(255),

                TextInput::make('reviewed_by_id')
                    ->maxLength(36),

                Select::make('decision')
                    ->options(EventModerationStatus::class)
                    ->required(),

                TextInput::make('reason_key')
                    ->label('Reason code')
                    ->maxLength(255),

                DateTimePicker::make('reviewed_at'),

                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),

                KeyValue::make('before_snapshot')
                    ->label('Before')
                    ->columnSpanFull(),

                KeyValue::make('after_snapshot')
                    ->label('After')
                    ->columnSpanFull(),

                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('decision')
            ->columns([
                Tables\Columns\TextColumn::make('decision')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason_key')
                    ->label('Reason')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reviewed_by_type')
                    ->label('Reviewer')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
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
