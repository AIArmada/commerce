<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources;

use AIArmada\FilamentPersons\Resources\TitleResource\Pages\CreateTitle;
use AIArmada\FilamentPersons\Resources\TitleResource\Pages\EditTitle;
use AIArmada\FilamentPersons\Resources\TitleResource\Pages\ListTitles;
use AIArmada\FilamentPersons\Resources\TitleResource\Pages\ViewTitle;
use AIArmada\Persons\Models\Title;
use AIArmada\Persons\Support\ModelResolver;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class TitleResource extends Resource
{
    protected static ?string $model = Title::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-persons.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-persons.resources.navigation_sort.title');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('short_form')
                            ->maxLength(50),
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        Select::make('usage_position')
                            ->options([
                                'before_name' => 'Before Name',
                                'after_name' => 'After Name',
                            ])
                            ->live()
                            ->required(),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->live(onBlur: true)
                            ->default(1)
                            ->helperText(function (Get $get): string {
                                $position = (int) $get('sort_order');

                                if ($position < 1) {
                                    return __('Enter a position of 1 or higher.');
                                }

                                $query = Title::query()
                                    ->where('category_id', $get('category_id'))
                                    ->where('usage_position', $get('usage_position'))
                                    ->where('sort_order', $position);

                                return $query->exists()
                                    ? __('Position :position is already occupied; saving will consolidate the order and shift the other title.', ['position' => $position])
                                    : __('Saving will place this title at position :position and shift later titles in this category.', ['position' => $position]);
                            }),
                        TextInput::make('language_code')
                            ->maxLength(10),
                        Textarea::make('description'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $columns = [
            Tables\Columns\TextColumn::make('name')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('short_form')
                ->badge(),
            Tables\Columns\TextColumn::make('category.name')
                ->sortable(),
        ];

        if (ModelResolver::countryClass() !== null) {
            $columns[] = Tables\Columns\TextColumn::make('country.name')
                ->label('Country')
                ->sortable();
        }

        return $table
            ->columns([
                ...$columns,
                Tables\Columns\TextColumn::make('usage_position')
                    ->badge(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                ...(ModelResolver::countryClass() !== null
                    ? [Tables\Filters\SelectFilter::make('country_id')
                        ->label('Country')
                        ->relationship('country', 'name')
                        ->searchable()
                        ->preload()]
                    : []
                ),
                Tables\Filters\SelectFilter::make('usage_position')
                    ->options([
                        'before_name' => 'Before Name',
                        'after_name' => 'After Name',
                    ]),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->components([
                        TextEntry::make('name'),
                        TextEntry::make('short_form'),
                        TextEntry::make('category.name'),
                        TextEntry::make('usage_position'),
                        TextEntry::make('sort_order'),
                        TextEntry::make('language_code'),
                        TextEntry::make('description'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTitles::route('/'),
            'create' => CreateTitle::route('/create'),
            'view' => ViewTitle::route('/{record}'),
            'edit' => EditTitle::route('/{record}/edit'),
        ];
    }
}
