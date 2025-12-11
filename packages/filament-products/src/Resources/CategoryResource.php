<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use AIArmada\FilamentProducts\Resources\CategoryResource\Pages;
use AIArmada\Products\Models\Category;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count() ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Category Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Category Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (Forms\Set $set, ?string $state) => $set('slug', \Illuminate\Support\Str::slug($state))
                                    ),

                                Forms\Components\TextInput::make('slug')
                                    ->label('URL Slug')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\Select::make('parent_id')
                                    ->label('Parent Category')
                                    ->relationship('parent', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('None (Root Category)')
                                    ->helperText('Leave blank to make this a root category'),

                                Forms\Components\MarkdownEditor::make('description')
                                    ->label('Description')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make('SEO')
                            ->schema([
                                Forms\Components\TextInput::make('meta_title')
                                    ->label('Meta Title')
                                    ->maxLength(70)
                                    ->helperText('Leave blank to use category name'),

                                Forms\Components\Textarea::make('meta_description')
                                    ->label('Meta Description')
                                    ->rows(3)
                                    ->maxLength(160),
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Display')
                            ->schema([
                                Forms\Components\TextInput::make('position')
                                    ->label('Position')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Lower numbers appear first'),

                                Forms\Components\Toggle::make('is_visible')
                                    ->label('Visible')
                                    ->default(true)
                                    ->helperText('Show in navigation and listing'),

                                Forms\Components\Toggle::make('is_featured')
                                    ->label('Featured')
                                    ->helperText('Highlight on homepage'),
                            ]),

                        Forms\Components\Section::make('Media')
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('hero')
                                    ->collection('hero')
                                    ->label('Hero Image')
                                    ->image()
                                    ->imageEditor()
                                    ->responsiveImages(),

                                SpatieMediaLibraryFileUpload::make('icon')
                                    ->collection('icon')
                                    ->label('Icon Image')
                                    ->image()
                                    ->imageEditor(),

                                SpatieMediaLibraryFileUpload::make('banner')
                                    ->collection('banner')
                                    ->label('Banner Image')
                                    ->image()
                                    ->imageEditor()
                                    ->responsiveImages(),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        $depth = $record->getDepth();
                        $prefix = str_repeat('— ', $depth);

                        return $prefix . $record->name;
                    })
                    ->description(fn ($record) => $record->slug),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('Root')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\TextColumn::make('position')
                    ->label('Position')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('position')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visible'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent')
                    ->relationship('parent', 'name')
                    ->placeholder('All')
                    ->options(fn () => ['0' => 'Root Categories'] + Category::whereNull('parent_id')->pluck('name', 'id')->toArray()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('add_child')
                    ->label('Add Child')
                    ->icon('heroicon-o-plus')
                    ->url(fn ($record) => static::getUrl('create', ['parent' => $record->id])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('show')
                        ->label('Make Visible')
                        ->icon('heroicon-o-eye')
                        ->action(
                            fn (\Illuminate\Support\Collection $records) => $records->each->update(['is_visible' => true])
                        ),
                    Tables\Actions\BulkAction::make('hide')
                        ->label('Make Hidden')
                        ->icon('heroicon-o-eye-slash')
                        ->action(
                            fn (\Illuminate\Support\Collection $records) => $records->each->update(['is_visible' => false])
                        ),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Category Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Name'),
                        Infolists\Components\TextEntry::make('slug')
                            ->label('Slug')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('parent.name')
                            ->label('Parent')
                            ->placeholder('Root Category'),
                        Infolists\Components\TextEntry::make('full_path')
                            ->label('Full Path')
                            ->getStateUsing(fn ($record) => $record->getFullPath()),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('products_count')
                            ->label('Direct Products')
                            ->getStateUsing(fn ($record) => $record->products()->count()),
                        Infolists\Components\TextEntry::make('all_products_count')
                            ->label('All Products (including children)')
                            ->getStateUsing(fn ($record) => $record->getProductCount(true)),
                        Infolists\Components\TextEntry::make('children_count')
                            ->label('Child Categories')
                            ->getStateUsing(fn ($record) => $record->children()->count()),
                    ])
                    ->columns(3),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'view' => Pages\ViewCategory::route('/{record}'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'description'];
    }
}
