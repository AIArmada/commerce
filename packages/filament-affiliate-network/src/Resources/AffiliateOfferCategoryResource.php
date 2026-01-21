<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

final class AffiliateOfferCategoryResource extends Resource
{
    protected static ?string $model = AffiliateOfferCategory::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'Category';

    protected static ?string $pluralModelLabel = 'Categories';

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliate-network.navigation.group', 'Affiliate Network');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliate-network.navigation.sort', 50) + 2;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Details')
                    ->schema([
                        Select::make('parent_id')
                            ->label('Parent Category')
                            ->options(fn (?AffiliateOfferCategory $record) => AffiliateOfferCategory::query()
                                ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('icon')
                            ->maxLength(100)
                            ->placeholder('heroicon-o-tag'),

                        Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('offers_count')
                    ->label('Offers')
                    ->counts('offers')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent')
                    ->relationship('parent', 'name'),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => \AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource\Pages\ListAffiliateOfferCategories::route('/'),
            'create' => \AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource\Pages\CreateAffiliateOfferCategory::route('/create'),
            'edit' => \AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource\Pages\EditAffiliateOfferCategory::route('/{record}/edit'),
        ];
    }
}
