<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeResource\Schemas;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Products\Enums\AttributeType;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AttributeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('filament-products::resources.attributes.sections.basic'))
                    ->schema([
                        TextInput::make('code')
                            ->label(__('filament-products::resources.attributes.fields.code'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->alphaDash()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('code', $state ? Str::slug($state, '_') : '')),

                        TextInput::make('name')
                            ->label(__('filament-products::resources.attributes.fields.name'))
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label(__('filament-products::resources.attributes.fields.description'))
                            ->rows(2)
                            ->maxLength(500),

                        Select::make('type')
                            ->label(__('filament-products::resources.attributes.fields.type'))
                            ->options(
                                collect(AttributeType::cases())
                                    ->mapWithKeys(fn (AttributeType $type) => [$type->value => $type->label()])
                            )
                            ->required()
                            ->live()
                            ->native(false),

                        Select::make('groups')
                            ->label(__('filament-products::resources.attributes.fields.groups'))
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
                            ->searchable()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('code')
                                    ->required()
                                    ->maxLength(100)
                                    ->alphaDash(),
                            ]),

                        TextInput::make('position')
                            ->label(__('filament-products::resources.attributes.fields.position'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ])
                    ->columns(2),

                Section::make(__('filament-products::resources.attributes.sections.options'))
                    ->schema([
                        Repeater::make('options')
                            ->label(__('filament-products::resources.attributes.fields.options'))
                            ->schema([
                                TextInput::make('value')
                                    ->label(__('filament-products::resources.attributes.fields.option_value'))
                                    ->required(),
                                TextInput::make('label')
                                    ->label(__('filament-products::resources.attributes.fields.option_label'))
                                    ->required(),
                                TextInput::make('position')
                                    ->label(__('filament-products::resources.attributes.fields.position'))
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(3)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
                    ])
                    ->visible(fn (Get $get): bool => in_array($get('type'), ['select', 'multiselect'], true)),

                Section::make(__('filament-products::resources.attributes.sections.validation'))
                    ->schema([
                        Toggle::make('is_required')
                            ->label(__('filament-products::resources.attributes.fields.is_required'))
                            ->default(false),

                        KeyValue::make('validation_rules')
                            ->label(__('filament-products::resources.attributes.fields.validation_rules'))
                            ->keyLabel(__('filament-products::resources.attributes.fields.rule'))
                            ->valueLabel(__('filament-products::resources.attributes.fields.value'))
                            ->addActionLabel(__('filament-products::resources.attributes.fields.add_rule'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make(__('filament-products::resources.attributes.sections.visibility'))
                    ->schema([
                        Toggle::make('is_filterable')
                            ->label(__('filament-products::resources.attributes.fields.is_filterable'))
                            ->helperText(__('filament-products::resources.attributes.fields.is_filterable_help'))
                            ->default(false),

                        Toggle::make('is_searchable')
                            ->label(__('filament-products::resources.attributes.fields.is_searchable'))
                            ->helperText(__('filament-products::resources.attributes.fields.is_searchable_help'))
                            ->default(false),

                        Toggle::make('is_comparable')
                            ->label(__('filament-products::resources.attributes.fields.is_comparable'))
                            ->helperText(__('filament-products::resources.attributes.fields.is_comparable_help'))
                            ->default(false),

                        Toggle::make('is_visible_on_front')
                            ->label(__('filament-products::resources.attributes.fields.is_visible_on_front'))
                            ->helperText(__('filament-products::resources.attributes.fields.is_visible_on_front_help'))
                            ->default(true),

                        Toggle::make('is_visible_in_admin')
                            ->label(__('filament-products::resources.attributes.fields.is_visible_in_admin'))
                            ->helperText(__('filament-products::resources.attributes.fields.is_visible_in_admin_help'))
                            ->default(true),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ]);
    }
}
