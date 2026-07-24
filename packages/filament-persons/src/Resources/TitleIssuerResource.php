<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources;

use AIArmada\FilamentPersons\Resources\TitleIssuerResource\Pages\CreateTitleIssuer;
use AIArmada\FilamentPersons\Resources\TitleIssuerResource\Pages\EditTitleIssuer;
use AIArmada\FilamentPersons\Resources\TitleIssuerResource\Pages\ListTitleIssuers;
use AIArmada\FilamentPersons\Resources\TitleIssuerResource\Pages\ViewTitleIssuer;
use AIArmada\Persons\Models\TitleIssuer;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class TitleIssuerResource extends Resource
{
    protected static ?string $model = TitleIssuer::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $recordTitleAttribute = 'issuer_name';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-persons.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-persons.resources.navigation_sort.title_issuer');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->components([
                        TextInput::make('issuer_name')
                            ->required()
                            ->maxLength(255),
                        Select::make('issuer_type')
                            ->options([
                                'government' => 'Government',
                                'royal' => 'Royal',
                                'religious_body' => 'Religious Body',
                                'university' => 'University',
                                'professional_board' => 'Professional Board',
                                'organization' => 'Organization',
                            ])
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('issuer_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('issuer_type')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('issuer_type')
                    ->options([
                        'government' => 'Government',
                        'royal' => 'Royal',
                        'religious_body' => 'Religious Body',
                        'university' => 'University',
                        'professional_board' => 'Professional Board',
                        'organization' => 'Organization',
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
                        TextEntry::make('issuer_name'),
                        TextEntry::make('issuer_type'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTitleIssuers::route('/'),
            'create' => CreateTitleIssuer::route('/create'),
            'view' => ViewTitleIssuer::route('/{record}'),
            'edit' => EditTitleIssuer::route('/{record}/edit'),
        ];
    }
}
