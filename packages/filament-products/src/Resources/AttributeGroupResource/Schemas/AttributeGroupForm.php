<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeGroupResource\Schemas;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AttributeGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('filament-products::resources.attribute_groups.sections.basic'))
                    ->schema([
                        TextInput::make('code')
                            ->label(__('filament-products::resources.attribute_groups.fields.code'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->alphaDash()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('code', $state ? Str::slug($state, '_') : '')),

                        TextInput::make('name')
                            ->label(__('filament-products::resources.attribute_groups.fields.name'))
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label(__('filament-products::resources.attribute_groups.fields.description'))
                            ->rows(2)
                            ->maxLength(500),

                        TextInput::make('position')
                            ->label(__('filament-products::resources.attribute_groups.fields.position'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Toggle::make('is_visible')
                            ->label(__('filament-products::resources.attribute_groups.fields.is_visible'))
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make(__('filament-products::resources.attribute_groups.sections.attributes'))
                    ->schema([
                        Select::make('groupAttributes')
                            ->label(__('filament-products::resources.attribute_groups.fields.attributes'))
                            ->multiple()
                            ->relationship(
                                'groupAttributes',
                                'name',
                                modifyQueryUsing: function (Builder $query): Builder {
                                    $owner = OwnerContext::resolve();

                                    return OwnerQuery::applyToEloquentBuilder($query, $owner);
                                }
                            )
                            ->preload()
                            ->searchable(),
                    ]),
            ]);
    }
}
