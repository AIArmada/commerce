<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\JsonDisplay;
use AIArmada\Engagement\Models\BookmarkCollection;
use BackedEnum;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class BookmarkCollectionResource extends Resource
{
    protected static ?string $model = BookmarkCollection::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-folder';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-engagement.navigation.group');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return OwnerUiScope::apply($query, includeGlobal: false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->rows(3),
                Forms\Components\Select::make('visibility')
                    ->required()
                    ->options([
                        'private' => 'Private',
                        'unlisted' => 'Unlisted',
                        'public' => 'Public',
                    ]),
                Forms\Components\Select::make('status')
                    ->required()
                    ->options([
                        'active' => 'Active',
                        'archived' => 'Archived',
                    ]),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('owner_type')->badge(),
                Tables\Columns\TextColumn::make('owner_id'),
                Tables\Columns\TextColumn::make('visibility')->badge()->colors([
                    'gray' => 'private',
                    'warning' => 'unlisted',
                    'success' => 'public',
                ]),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'success' => 'active',
                    'danger' => 'archived',
                ]),
                Tables\Columns\TextColumn::make('sort_order')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('visibility')->options([
                    'private' => 'Private',
                    'unlisted' => 'Unlisted',
                    'public' => 'Public',
                ]),
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'archived' => 'Archived',
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                Infolists\Components\TextEntry::make('name'),
                Infolists\Components\TextEntry::make('slug'),
                Infolists\Components\TextEntry::make('description'),
                Infolists\Components\TextEntry::make('owner_type'),
                Infolists\Components\TextEntry::make('owner_id'),
                Infolists\Components\TextEntry::make('visibility')->badge(),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('sort_order')->numeric(),
                Infolists\Components\IconEntry::make('is_default')->boolean(),
                Infolists\Components\IconEntry::make('is_system')->boolean(),
                Infolists\Components\TextEntry::make('created_at')->dateTime(),
                Infolists\Components\TextEntry::make('updated_at')->dateTime(),
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
            'index' => BookmarkCollectionResource\Pages\ListBookmarkCollections::route('/'),
            'create' => BookmarkCollectionResource\Pages\CreateBookmarkCollection::route('/create'),
            'edit' => BookmarkCollectionResource\Pages\EditBookmarkCollection::route('/{record}/edit'),
        ];
    }
}
