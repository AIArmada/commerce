<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\Events\Models\EventTerm;
use AIArmada\FilamentEvents\Resources\EventTermResource\Pages\CreateEventTerm;
use AIArmada\FilamentEvents\Resources\EventTermResource\Pages\EditEventTerm;
use AIArmada\FilamentEvents\Resources\EventTermResource\Pages\ListEventTerms;
use AIArmada\FilamentEvents\Resources\EventTermResource\Pages\ViewEventTerm;
use BackedEnum;
use Filament\Forms\Components\Select;
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

final class EventTermResource extends Resource
{
    protected static ?string $model = EventTerm::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-events.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-events.resources.navigation_sort.event_term');

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
                Tables\Columns\TextColumn::make('taxonomy.name')
                    ->label('Taxonomy')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->default('—'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_taxonomy_id')
                    ->label('Taxonomy')
                    ->relationship('taxonomy', 'name'),
                Tables\Filters\Filter::make('is_active')
                    ->query(fn ($query) => $query->where('is_active', true))
                    ->default(true)
                    ->toggle(),
            ])
            ->defaultSort('sort_order');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Term Details')
                    ->schema([
                        TextEntry::make('code')
                            ->weight('semibold'),
                        TextEntry::make('name'),
                        TextEntry::make('description'),
                        TextEntry::make('taxonomy.name')
                            ->label('Taxonomy')
                            ->badge()
                            ->color('info'),
                        TextEntry::make('parent.name')
                            ->label('Parent'),
                        TextEntry::make('sort_order')
                            ->numeric(),
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
                Section::make('Term Details')
                    ->schema([
                        Select::make('event_taxonomy_id')
                            ->label('Taxonomy')
                            ->relationship('taxonomy', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        TextInput::make('code')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->helperText('Machine-readable identifier (e.g. "workshop", "beginner")'),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(3),
                        Select::make('parent_id')
                            ->label('Parent Term')
                            ->relationship(
                                'parent',
                                'name',
                                fn ($query, $get) => $query->where('event_taxonomy_id', $get('event_taxonomy_id'))
                            )
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get): bool => (bool) $get('event_taxonomy_id')),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->integer(),
                        Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEventTerms::route('/'),
            'create' => CreateEventTerm::route('/create'),
            'view' => ViewEventTerm::route('/{record}'),
            'edit' => EditEventTerm::route('/{record}/edit'),
        ];
    }
}
