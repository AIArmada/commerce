<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $recordTitleAttribute = 'content';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Textarea::make('content')
                    ->label('Note')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_internal')
                    ->label('Internal Note')
                    ->helperText('Internal notes are not visible to the customer')
                    ->default(true),

                Forms\Components\Toggle::make('is_pinned')
                    ->label('Pin Note')
                    ->helperText('Pinned notes appear at the top'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('content')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\IconColumn::make('is_pinned')
                    ->label('')
                    ->icon(fn ($state) => $state ? 'heroicon-s-star' : null)
                    ->color('warning')
                    ->width('40px'),

                Tables\Columns\TextColumn::make('content')
                    ->label('Note')
                    ->limit(100)
                    ->searchable()
                    ->wrap(),

                Tables\Columns\IconColumn::make('is_internal')
                    ->label('Internal')
                    ->boolean(),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('By')
                    ->placeholder('System'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_internal')
                    ->label('Note Type')
                    ->trueLabel('Internal Only')
                    ->falseLabel('Customer Visible'),

                Tables\Filters\TernaryFilter::make('is_pinned')
                    ->label('Pinned'),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                \Filament\Actions\Action::make('pin')
                    ->icon('heroicon-o-star')
                    ->action(fn ($record) => $record->pin())
                    ->visible(fn ($record) => ! $record->is_pinned),
                \Filament\Actions\Action::make('unpin')
                    ->icon('heroicon-s-star')
                    ->color('warning')
                    ->action(fn ($record) => $record->unpin())
                    ->visible(fn ($record) => $record->is_pinned),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
