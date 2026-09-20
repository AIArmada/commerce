<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateVolumeTier;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentAffiliates\Resources\AffiliateVolumeTierResource\Pages\CreateAffiliateVolumeTier;
use AIArmada\FilamentAffiliates\Resources\AffiliateVolumeTierResource\Pages\EditAffiliateVolumeTier;
use AIArmada\FilamentAffiliates\Resources\AffiliateVolumeTierResource\Pages\ListAffiliateVolumeTiers;
use AIArmada\FilamentAffiliates\Resources\AffiliateVolumeTierResource\Pages\ViewAffiliateVolumeTier;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class AffiliateVolumeTierResource extends Resource
{
    protected static ?string $model = AffiliateVolumeTier::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Volume Tiers';

    protected static ?string $modelLabel = 'Volume Tier';

    protected static ?string $pluralModelLabel = 'Volume Tiers';

    public static function canViewAny(): bool
    {
        return FilamentPermission::hasAbility('affiliate.viewAny');
    }

    public static function canView(Model $record): bool
    {
        return FilamentPermission::hasAbility('affiliate.view');
    }

    public static function canCreate(): bool
    {
        return FilamentPermission::hasAbility('affiliate.create');
    }

    public static function canEdit(Model $record): bool
    {
        return FilamentPermission::hasAbility('affiliate.update');
    }

    public static function canDelete(Model $record): bool
    {
        return FilamentPermission::hasAbility('affiliate.delete');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Tier Details')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('program_id')
                        ->label('Program')
                        ->options(fn (): array => AffiliateProgram::query()->pluck('name', 'id')->all())
                        ->searchable()
                        ->nullable()
                        ->helperText('Leave empty for a global tier that applies to every program.'),

                    Forms\Components\Select::make('period')
                        ->options([
                            'monthly' => 'Monthly',
                            'quarterly' => 'Quarterly',
                            'yearly' => 'Yearly',
                            'lifetime' => 'Lifetime',
                        ])
                        ->default('monthly')
                        ->required(),

                    Forms\Components\TextInput::make('commission_rate_basis_points')
                        ->label('Commission Rate (basis points)')
                        ->numeric()
                        ->required()
                        ->minValue(0),
                ])
                ->columns(2),

            Section::make('Volume Thresholds')
                ->description('Volume is measured in the tier currency before comparing.')
                ->schema([
                    Forms\Components\TextInput::make('min_volume_minor')
                        ->label('Min Volume (minor units)')
                        ->numeric()
                        ->required()
                        ->minValue(0),

                    Forms\Components\TextInput::make('max_volume_minor')
                        ->label('Max Volume (minor units)')
                        ->numeric()
                        ->nullable()
                        ->minValue(0),

                    Forms\Components\Select::make('currency')
                        ->label('Tier Currency')
                        ->options([
                            'USD' => 'USD',
                            'MYR' => 'MYR',
                            'SGD' => 'SGD',
                            'IDR' => 'IDR',
                        ])
                        ->default((string) config('affiliates.currency.default', 'MYR'))
                        ->required(),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('program.name')
                    ->label('Program')
                    ->placeholder('Global')
                    ->sortable(),

                Tables\Columns\TextColumn::make('min_volume_minor')
                    ->label('Min Volume')
                    ->money(fn ($record): string => $record->currencyCode())
                    ->sortable(),

                Tables\Columns\TextColumn::make('currency')
                    ->badge(),

                Tables\Columns\TextColumn::make('commission_rate_basis_points')
                    ->label('Rate')
                    ->formatStateUsing(fn ($state): string => ((float) $state / 100) . '%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('period')
                    ->badge(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Volume Tier')
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('program.name')->label('Program')->placeholder('Global'),
                    TextEntry::make('min_volume_minor')->label('Min Volume (minor units)')->numeric(),
                    TextEntry::make('max_volume_minor')->label('Max Volume (minor units)')->numeric(),
                    TextEntry::make('currency')->badge(),
                    TextEntry::make('commission_rate_basis_points')->label('Rate (basis points)')->numeric(),
                    TextEntry::make('period')->badge(),
                ])
                ->columns(2),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateVolumeTiers::route('/'),
            'create' => CreateAffiliateVolumeTier::route('/create'),
            'view' => ViewAffiliateVolumeTier::route('/{record}'),
            'edit' => EditAffiliateVolumeTier::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.resources.navigation_sort.affiliate_volume_tiers', 74);
    }
}
