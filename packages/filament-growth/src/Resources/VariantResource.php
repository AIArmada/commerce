<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources;

use AIArmada\FilamentGrowth\Resources\VariantResource\Pages;
use AIArmada\Growth\Enums\ExperimentModuleType;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class VariantResource extends Resource
{
    protected static ?string $model = Variant::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string | UnitEnum | null $navigationGroup = 'Growth';

    protected static ?int $navigationSort = 21;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<Variant>
     */
    public static function getEloquentQuery(): Builder
    {
        return Variant::query()->forOwner();
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-growth.navigation_group', 'Growth');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-growth.resources.navigation_sort.variants', 21);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Variant')
                ->schema([
                    Forms\Components\Select::make('experiment_id')
                        ->label('Experiment')
                        ->relationship(
                            name: 'experiment',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn(
                                $query->getModel()->qualifyColumn('id'),
                                Experiment::query()->forOwner()->select('id'),
                            ),
                        )
                        ->required()
                        ->preload()
                        ->searchable(),

                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->maxLength(50),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('traffic_percentage')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(100)
                        ->default(50),

                    Forms\Components\TextInput::make('position')
                        ->numeric()
                        ->required()
                        ->default(0),

                    Forms\Components\Toggle::make('is_control')
                        ->default(false),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),

                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Variant Settings')
                ->schema([
                    Forms\Components\TextInput::make('settings.destination_url')
                        ->label('Destination URL')
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::SalesPageTest->value),

                    Forms\Components\TextInput::make('settings.headline')
                        ->label('Headline')
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::SalesPageTest->value),

                    Forms\Components\TextInput::make('settings.cta_copy')
                        ->label('CTA Copy')
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::SalesPageTest->value),

                    Forms\Components\TextInput::make('settings.entry_path')
                        ->label('Entry Path')
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::FunnelTest->value),

                    Forms\Components\TextInput::make('settings.step_key')
                        ->label('Step Key')
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::FunnelTest->value),

                    Forms\Components\TextInput::make('settings.offer_label')
                        ->label('Offer Label')
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::FunnelTest->value),

                    Forms\Components\TextInput::make('settings.price_label')
                        ->label('Price Label')
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::PricingTest->value),

                    Forms\Components\TextInput::make('settings.price_minor')
                        ->label('Price (Minor Units)')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::PricingTest->value),

                    Forms\Components\TextInput::make('settings.currency')
                        ->label('Currency')
                        ->default(config('signals.defaults.currency', 'MYR'))
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::PricingTest->value),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('experiment.name')
                    ->label('Experiment')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('traffic_percentage')
                    ->label('Traffic %')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_control')
                    ->label('Control')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('position')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('position')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVariants::route('/'),
            'create' => Pages\CreateVariant::route('/create'),
            'edit' => Pages\EditVariant::route('/{record}/edit'),
        ];
    }

    private static function selectedExperimentModuleType(mixed $experimentId): ?string
    {
        if (! is_scalar($experimentId) || $experimentId === '') {
            return null;
        }

        $moduleType = Experiment::query()
            ->forOwner()
            ->whereKey((string) $experimentId)
            ->value('module_type');

        return is_string($moduleType) ? $moduleType : null;
    }
}