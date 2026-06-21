<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Models\CommunicationBatch;
use AIArmada\Filament\Communications\Resources\CommunicationBatchResource\Pages;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CommunicationBatchResource extends Resource
{
    protected static ?string $model = CommunicationBatch::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rocket-launch';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-communications.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-communications.navigation.sort');
    }

    /**
     * @return Builder<CommunicationBatch>
     */
    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('purpose')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category'),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('requested_count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('failed_count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->options(collect(CommunicationCategory::cases())->pluck('value', 'value')),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('purpose'),
                        TextEntry::make('category'),
                        TextEntry::make('status')->badge(),
                    ])->columns(2),
                Section::make('Counts')
                    ->schema([
                        TextEntry::make('requested_count')->numeric(),
                        TextEntry::make('planned_count')->numeric(),
                        TextEntry::make('queued_count')->numeric(),
                        TextEntry::make('completed_count')->numeric(),
                        TextEntry::make('failed_count')->numeric(),
                    ])->columns(3),
                Section::make('Timeline')
                    ->schema([
                        TextEntry::make('scheduled_at')->dateTime(),
                        TextEntry::make('started_at')->dateTime(),
                        TextEntry::make('completed_at')->dateTime(),
                        TextEntry::make('cancelled_at')->dateTime(),
                        TextEntry::make('failed_at')->dateTime(),
                        TextEntry::make('expires_at')->dateTime(),
                    ])->columns(3),
                Section::make('Metadata')
                    ->schema([
                        TextEntry::make('laravel_batch_id'),
                        TextEntry::make('idempotency_key'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommunicationBatches::route('/'),
            'view' => Pages\ViewCommunicationBatch::route('/{record}'),
        ];
    }
}
