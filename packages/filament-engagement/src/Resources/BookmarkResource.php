<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\JsonDisplay;
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Models\Bookmark;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class BookmarkResource extends Resource
{
    protected static ?string $model = Bookmark::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bookmark';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-engagement.navigation.group');
    }

    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false)
            ->latest('bookmarked_at');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bookmarker_type')->label('Bookmarker Type')->badge(),
                Tables\Columns\TextColumn::make('bookmarker_id')->label('Bookmarker ID'),
                Tables\Columns\TextColumn::make('bookmarkable_type')->label('Bookmarkable Type')->badge(),
                Tables\Columns\TextColumn::make('bookmarkable_id')->label('Bookmarkable ID'),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'success' => 'active',
                    'danger' => 'removed',
                    'warning' => 'archived',
                ]),
                Tables\Columns\TextColumn::make('notes')->limit(50),
                Tables\Columns\TextColumn::make('bookmarked_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('removed_at')->dateTime()->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'removed' => 'Removed',
                    'archived' => 'Archived',
                ]),
                Tables\Filters\SelectFilter::make('bookmarker_type')
                    ->options(fn (): array => Bookmark::query()
                        ->select('bookmarker_type')
                        ->distinct()
                        ->orderBy('bookmarker_type')
                        ->pluck('bookmarker_type', 'bookmarker_type')
                        ->all())
                    ->label('Bookmarker Type'),
                Tables\Filters\SelectFilter::make('bookmarkable_type')
                    ->options(fn (): array => Bookmark::query()
                        ->select('bookmarkable_type')
                        ->distinct()
                        ->orderBy('bookmarkable_type')
                        ->pluck('bookmarkable_type', 'bookmarkable_type')
                        ->all())
                    ->label('Bookmarkable Type'),
            ])
            ->actions([
                Action::make('remove')
                    ->action(fn (Bookmark $record) => app(EngagementManager::class)
                        ->removeBookmark($record->bookmarker, $record->bookmarkable))
                    ->requiresConfirmation()
                    ->visible(fn (Bookmark $record) => $record->isActive()),
                Action::make('restore')
                    ->action(fn (Bookmark $record): Bookmark => app(EngagementManager::class)
                        ->bookmark($record->bookmarker, $record->bookmarkable, [
                            'notes' => $record->notes,
                            'source' => $record->source,
                            'metadata' => $record->metadata,
                        ]))
                    ->requiresConfirmation()
                    ->visible(fn (Bookmark $record) => $record->isRemoved()),
            ])
            ->bulkActions([
                BulkAction::make('remove')
                    ->action(function ($records): void {
                        foreach ($records as $record) {
                            if ($record instanceof Bookmark && $record->isActive()) {
                                app(EngagementManager::class)
                                    ->removeBookmark($record->bookmarker, $record->bookmarkable);
                            }
                        }
                    }),
            ])
            ->defaultSort('bookmarked_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                Infolists\Components\TextEntry::make('bookmarker_type'),
                Infolists\Components\TextEntry::make('bookmarker_id'),
                Infolists\Components\TextEntry::make('bookmarkable_type'),
                Infolists\Components\TextEntry::make('bookmarkable_id'),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('notes'),
                Infolists\Components\TextEntry::make('bookmarked_at')->dateTime(),
                Infolists\Components\TextEntry::make('removed_at')->dateTime(),
                Infolists\Components\TextEntry::make('archived_at')->dateTime(),
                Infolists\Components\TextEntry::make('source'),
                Infolists\Components\TextEntry::make('metadata')
                    ->formatStateUsing(fn (mixed $state): string => JsonDisplay::format($state))
                    ->html()
                    ->visible(fn (?array $state): bool => ! empty($state)),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => BookmarkResource\Pages\ListBookmarks::route('/'),
            'view' => BookmarkResource\Pages\ViewBookmark::route('/{record}'),
        ];
    }
}
