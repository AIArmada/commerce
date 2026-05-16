<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\FilamentGrowth\Pages\ExperimentResultsPage;
use AIArmada\FilamentGrowth\Resources\ExperimentResource\Pages;
use AIArmada\Growth\Actions\ResolveExperimentPreset;
use AIArmada\Growth\Enums\ExperimentModuleType;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Models\TrackedProperty;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

final class ExperimentResource extends Resource
{
    protected static ?string $model = Experiment::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-beaker';

    protected static string | UnitEnum | null $navigationGroup = 'Growth';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<Experiment>
     */
    public static function getEloquentQuery(): Builder
    {
        return Experiment::query()->forOwner();
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-growth.navigation_group', 'Growth');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-growth.resources.navigation_sort.experiments', 20);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Experiment')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', $state ? Str::slug($state) : '')),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->alphaDash()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('owner_scope', static::ownerScopeKey())),

                    Forms\Components\Select::make('tracked_property_id')
                        ->label('Tracked Property')
                        ->relationship(
                            name: 'trackedProperty',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn(
                                $query->getModel()->qualifyColumn('id'),
                                TrackedProperty::query()->forOwner()->select('id'),
                            ),
                        )
                        ->required()
                        ->preload()
                        ->searchable(),

                    Forms\Components\Select::make('module_type')
                        ->required()
                        ->options(ExperimentModuleType::options())
                        ->default(config('growth.defaults.module_type', 'ab_test'))
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                            if (! config('growth.features.preset_modules.enabled', true)) {
                                return;
                            }

                            $preset = app(ResolveExperimentPreset::class)->handle($state);
                            $existingSettings = $get('settings');

                            $set('goal_event_name', $preset['goal_event_name']);
                            $set('goal_event_category', $preset['goal_event_category']);
                            $set('winner_metric', $preset['winner_metric']);
                            $set('settings', array_replace_recursive(
                                $preset['settings'],
                                is_array($existingSettings) ? $existingSettings : [],
                            ));
                        }),

                    Forms\Components\Select::make('status')
                        ->options(collect(ExperimentStatus::cases())->mapWithKeys(fn (ExperimentStatus $status): array => [$status->value => $status->label()]))
                        ->required()
                        ->default(ExperimentStatus::Draft->value),
                ])
                ->columns(2),

            Section::make('Outcome')
                ->schema([
                    Forms\Components\TextInput::make('goal_event_name')
                        ->required()
                        ->default(config('growth.integrations.signals.purchase_event_name', 'order.paid')),

                    Forms\Components\TextInput::make('goal_event_category')
                        ->required()
                        ->default('conversion')
                        ->maxLength(100),

                    Forms\Components\Select::make('winner_metric')
                        ->options([
                            'revenue_per_visitor' => 'Revenue Per Visitor',
                            'revenue_minor' => 'Revenue',
                            'conversion_rate' => 'Conversion Rate',
                            'checkout_starts' => 'Checkout Starts',
                            'purchases' => 'Purchases',
                        ])
                        ->required()
                        ->default(config('growth.defaults.winner_metric', 'revenue_per_visitor')),

                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Module Settings')
                ->schema([
                    Forms\Components\TagsInput::make('settings.entry_paths')
                        ->label('Entry Paths')
                        ->placeholder('/offers/course-v1')
                        ->visible(fn (Get $get): bool => $get('module_type') === ExperimentModuleType::SalesPageTest->value),

                    Forms\Components\TagsInput::make('settings.destination_urls')
                        ->label('Destination URLs')
                        ->placeholder('/checkout/course')
                        ->visible(fn (Get $get): bool => $get('module_type') === ExperimentModuleType::SalesPageTest->value),

                    Forms\Components\TextInput::make('settings.cta_event_name')
                        ->label('CTA Event Name')
                        ->default('cta_click')
                        ->visible(fn (Get $get): bool => $get('module_type') === ExperimentModuleType::SalesPageTest->value),

                    Forms\Components\Repeater::make('settings.funnel_steps')
                        ->label('Funnel Steps')
                        ->schema([
                            Forms\Components\TextInput::make('label')
                                ->required(),
                            Forms\Components\TextInput::make('event_name')
                                ->required(),
                            Forms\Components\TextInput::make('event_category')
                                ->required(),
                        ])
                        ->columns(3)
                        ->visible(fn (Get $get): bool => $get('module_type') === ExperimentModuleType::FunnelTest->value),

                    Forms\Components\TextInput::make('settings.checkout_event_name')
                        ->label('Checkout Event Name')
                        ->default('checkout.started')
                        ->visible(fn (Get $get): bool => $get('module_type') === ExperimentModuleType::PricingTest->value),

                    Forms\Components\TagsInput::make('settings.price_labels')
                        ->label('Price Labels')
                        ->placeholder('Starter')
                        ->visible(fn (Get $get): bool => $get('module_type') === ExperimentModuleType::PricingTest->value),
                ])
                ->columns(2)
                ->visible(fn (): bool => config('growth.features.preset_modules.enabled', true)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Experiment $record): string => $record->slug),
                Tables\Columns\TextColumn::make('trackedProperty.name')
                    ->label('Tracked Property')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('module_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ExperimentModuleType::labelFor($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ExperimentStatus $state): string => $state->label())
                    ->color(fn (ExperimentStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('goal_event_name')
                    ->label('Goal')
                    ->badge(),
                Tables\Columns\TextColumn::make('variants_count')
                    ->counts('variants')
                    ->label('Variants')
                    ->sortable(),
                Tables\Columns\TextColumn::make('assignments_count')
                    ->counts('assignments')
                    ->label('Assignments')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ExperimentStatus::cases())->mapWithKeys(fn (ExperimentStatus $status): array => [$status->value => $status->label()])),
            ])
            ->actions([
                Tables\Actions\Action::make('results')
                    ->label('Results')
                    ->icon('heroicon-o-chart-bar')
                    ->url(fn (Experiment $record): string => ExperimentResultsPage::getUrl(['experiment' => $record->getKey()])),
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
            'index' => Pages\ListExperiments::route('/'),
            'create' => Pages\CreateExperiment::route('/create'),
            'edit' => Pages\EditExperiment::route('/{record}/edit'),
        ];
    }

    private static function ownerScopeKey(): string
    {
        return OwnerScopeKey::forOwner(OwnerContext::resolve());
    }
}