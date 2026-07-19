<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\Events\Models\EventTaxonomy;
use AIArmada\FilamentEvents\Resources\EventTaxonomyResource\Pages\CreateEventTaxonomy;
use AIArmada\FilamentEvents\Resources\EventTaxonomyResource\Pages\EditEventTaxonomy;
use AIArmada\FilamentEvents\Resources\EventTaxonomyResource\Pages\ListEventTaxonomies;
use AIArmada\FilamentEvents\Resources\EventTaxonomyResource\Pages\ViewEventTaxonomy;
use AIArmada\FilamentEvents\Resources\EventTaxonomyResource\RelationManagers\TermsRelationManager;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

final class EventTaxonomyResource extends Resource
{
    protected static ?string $model = EventTaxonomy::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-folder';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-events.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-events.resources.navigation_sort.event_taxonomy');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->weight('semibold'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50),
                Tables\Columns\IconColumn::make('is_hierarchical')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('terms_count')
                    ->label('Terms')
                    ->counts('terms')
                    ->numeric()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\Filter::make('is_active')
                    ->query(fn ($query) => $query->where('is_active', true))
                    ->default(true)
                    ->toggle(),
            ])
            ->defaultSort('name');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Taxonomy Details')
                    ->schema([
                        TextEntry::make('code')
                            ->weight('semibold'),
                        TextEntry::make('name'),
                        TextEntry::make('description'),
                        TextEntry::make('is_hierarchical')
                            ->badge()
                            ->state(fn (bool $state): string => $state ? 'Hierarchical' : 'Flat'),
                        TextEntry::make('is_active')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ])->columns(2),
            ]);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Taxonomy Details')
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->helperText('Machine-readable identifier (e.g. "event_type", "topic")'),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(3),
                        Toggle::make('is_hierarchical')
                            ->helperText('Allow parent-child relationships between terms'),
                        Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TermsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEventTaxonomies::route('/'),
            'create' => CreateEventTaxonomy::route('/create'),
            'view' => ViewEventTaxonomy::route('/{record}'),
            'edit' => EditEventTaxonomy::route('/{record}/edit'),
        ];
    }
}
