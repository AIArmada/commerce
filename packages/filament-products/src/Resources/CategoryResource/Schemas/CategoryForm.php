<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CategoryResource\Schemas;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Category Information')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Category Name')
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

                                Select::make('parent_id')
                                    ->label('Parent Category')
                                    ->relationship(
                                        'parent',
                                        'name',
                                        modifyQueryUsing: function (Builder $query): Builder {
                                            $owner = OwnerContext::resolve();

                                            return OwnerQuery::applyToEloquentBuilder($query, $owner);
                                        }
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('None (Root Category)')
                                    ->helperText('Leave blank to make this a root category'),

                                MarkdownEditor::make('description')
                                    ->label('Description')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('SEO')
                            ->schema([
                                TextInput::make('meta_title')
                                    ->label('Meta Title')
                                    ->maxLength(70)
                                    ->helperText('Leave blank to use category name'),

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
                                    ->default(0)
                                    ->helperText('Lower numbers appear first'),

                                Toggle::make('is_visible')
                                    ->label('Visible')
                                    ->default(true)
                                    ->helperText('Show in navigation and listing'),

                                Toggle::make('is_featured')
                                    ->label('Featured')
                                    ->helperText('Highlight on homepage'),
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

                                SpatieMediaLibraryFileUpload::make('icon')
                                    ->label('Icon Image')
                                    ->collection('icon')
                                    ->image()
                                    ->imageEditor()
                                    ->acceptedFileTypes(config('products.media.collections.icon.mimes', []))
                                    ->maxFiles((int) config('products.media.collections.icon.limit', 1)),

                                SpatieMediaLibraryFileUpload::make('banner')
                                    ->label('Banner Image')
                                    ->collection('banner')
                                    ->image()
                                    ->imageEditor()
                                    ->acceptedFileTypes(config('products.media.collections.banner.mimes', []))
                                    ->maxFiles((int) config('products.media.collections.banner.limit', 1)),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }
}
