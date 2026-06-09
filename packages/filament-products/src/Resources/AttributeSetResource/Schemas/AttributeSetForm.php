<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeSetResource\Schemas;

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

class AttributeSetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('filament-products::resources.attribute_sets.sections.basic'))
                    ->schema([
                        TextInput::make('code')
                            ->label(__('filament-products::resources.attribute_sets.fields.code'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->alphaDash()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('code', $state ? Str::slug($state, '_') : '')),

                        TextInput::make('name')
                            ->label(__('filament-products::resources.attribute_sets.fields.name'))
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label(__('filament-products::resources.attribute_sets.fields.description'))
                            ->rows(2)
                            ->maxLength(500),

                        Toggle::make('is_default')
                            ->label(__('filament-products::resources.attribute_sets.fields.is_default'))
                            ->helperText(__('filament-products::resources.attribute_sets.fields.is_default_help'))
                            ->default(false),
                    ])
                    ->columns(2),

                Section::make(__('filament-products::resources.attribute_sets.sections.attributes'))
                    ->schema([
                        Select::make('setAttributes')
                            ->label(__('filament-products::resources.attribute_sets.fields.attributes'))
                            ->multiple()
                            ->relationship(
                                'setAttributes',
                                'name',
                                modifyQueryUsing: function (Builder $query): Builder {
                                    $owner = OwnerContext::resolve();

                                    return OwnerQuery::applyToEloquentBuilder($query, $owner);
                                }
                            )
                            ->preload()
                            ->searchable(),
                    ]),

                Section::make(__('filament-products::resources.attribute_sets.sections.groups'))
                    ->schema([
                        Select::make('groups')
                            ->label(__('filament-products::resources.attribute_sets.fields.groups'))
                            ->multiple()
                            ->relationship(
                                'groups',
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
