<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CollectionResource\Schemas;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Products\Enums\CatalogStatus;
use AIArmada\Products\Models\Category;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Collection Information')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Collection Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (Set $set, ?string $state) => $set('slug', Str::slug($state))
                                    ),

                                TextInput::make('slug')
                                    ->label('URL Slug')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true),

                                MarkdownEditor::make('description')
                                    ->label('Description')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('Collection Type')
                            ->schema([
                                Radio::make('type')
                                    ->label('Type')
                                    ->options([
                                        'manual' => 'Manual - Add products individually',
                                        'automatic' => 'Automatic - Products match conditions',
                                    ])
                                    ->default('manual')
                                    ->required()
                                    ->live(),

                                Repeater::make('conditions')
                                    ->label('Conditions')
                                    ->schema([
                                        Select::make('field')
                                            ->label('Field')
                                            ->options([
                                                'price_min' => 'Minimum Price',
                                                'price_max' => 'Maximum Price',
                                                'type' => 'Product Type',
                                                'category' => 'Category',
                                                'tag' => 'Tag',
                                                'is_featured' => 'Featured',
                                            ])
                                            ->required()
                                            ->live(),

                                        TextInput::make('value')
                                            ->label('Value')
                                            ->required()
                                            ->visible(fn (Get $get) => in_array($get('field'), ['price_min', 'price_max', 'tag'])),

                                        Select::make('value')
                                            ->label('Value')
                                            ->options([
                                                'simple' => 'Simple',
                                                'configurable' => 'Configurable',
                                                'bundle' => 'Bundle',
                                                'digital' => 'Digital',
                                                'subscription' => 'Subscription',
                                            ])
                                            ->visible(fn (Get $get) => $get('field') === 'type'),

                                        Select::make('value')
                                            ->label('Category')
                                            ->options(function (): array {
                                                $owner = OwnerContext::resolve();

                                                return OwnerQuery::applyToEloquentBuilder(Category::query(), $owner)->pluck('name', 'id')->toArray();
                                            })
                                            ->searchable()
                                            ->visible(fn (Get $get) => $get('field') === 'category'),

                                        Toggle::make('value')
                                            ->label('Is Featured')
                                            ->visible(fn (Get $get) => $get('field') === 'is_featured'),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('Add Condition')
                                    ->visible(fn (Get $get) => $get('type') === 'automatic'),
                            ]),

                        Section::make('Scheduling')
                            ->schema([
                                DateTimePicker::make('published_at')
                                    ->label('Publish At')
                                    ->helperText('Leave blank to publish immediately'),

                                DateTimePicker::make('unpublished_at')
                                    ->label('Unpublish At')
                                    ->helperText('Leave blank to keep published'),
                            ])
                            ->columns(2)
                            ->collapsible(),

                        Section::make('SEO')
                            ->schema([
                                TextInput::make('meta_title')
                                    ->label('Meta Title')
                                    ->maxLength(70),

                                Textarea::make('meta_description')
                                    ->label('Meta Description')
                                    ->rows(3)
                                    ->maxLength(160),
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Display')
                            ->schema([
                                TextInput::make('position')
                                    ->label('Position')
                                    ->numeric()
                                    ->default(0),

                                Select::make('status')
                                    ->label('Status')
                                    ->options(
                                        collect(CatalogStatus::cases())
                                            ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
                                    )
                                    ->default('active'),

                                Toggle::make('is_featured')
                                    ->label('Featured Collection'),
                            ]),

                        Section::make('Media')
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('hero')
                                    ->label('Hero Image')
                                    ->collection('hero')
                                    ->image()
                                    ->imageEditor()
                                    ->acceptedFileTypes(config('products.media.collections.hero.mimes', []))
                                    ->maxFiles((int) config('products.media.collections.hero.limit', 1)),

                                SpatieMediaLibraryFileUpload::make('banner')
                                    ->label('Banner Image')
                                    ->collection('banner')
                                    ->image()
                                    ->imageEditor()
                                    ->acceptedFileTypes(config('products.media.collections.banner.mimes', []))
                                    ->maxFiles((int) config('products.media.collections.banner.limit', 1)),
                            ]),

                        Section::make('Products')
                            ->schema([
                                Select::make('products')
                                    ->label('Products')
                                    ->relationship(
                                        'products',
                                        'name',
                                        modifyQueryUsing: function (Builder $query): Builder {
                                            $owner = OwnerContext::resolve();

                                            return OwnerQuery::applyToEloquentBuilder($query, $owner);
                                        }
                                    )
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->helperText('For manual collections only'),
                            ])
                            ->visible(fn (Get $get) => $get('type') !== 'automatic'),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }
}
