<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Models\AffiliateCommissionTemplate;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentAffiliates\Resources\AffiliateCommissionTemplateResource\Pages\CreateAffiliateCommissionTemplate;
use AIArmada\FilamentAffiliates\Resources\AffiliateCommissionTemplateResource\Pages\EditAffiliateCommissionTemplate;
use AIArmada\FilamentAffiliates\Resources\AffiliateCommissionTemplateResource\Pages\ListAffiliateCommissionTemplates;
use AIArmada\FilamentAffiliates\Resources\AffiliateCommissionTemplateResource\Pages\ViewAffiliateCommissionTemplate;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class AffiliateCommissionTemplateResource extends Resource
{
    protected static ?string $model = AffiliateCommissionTemplate::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $navigationLabel = 'Commission Templates';

    protected static ?string $modelLabel = 'Commission Template';

    protected static ?string $pluralModelLabel = 'Commission Templates';

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

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<AffiliateCommissionTemplate> $query */
        $query = parent::getEloquentQuery();

        if (! (bool) config('affiliates.owner.enabled', false)) {
            /** @var Builder<Model> $unscopedQuery */
            $unscopedQuery = $query;

            return $unscopedQuery;
        }

        $scopedQuery = $query->forOwner();

        /** @var Builder<Model> $modelQuery */
        $modelQuery = $scopedQuery;

        return $modelQuery;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Template')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    TextInput::make('description')
                        ->maxLength(255),

                    Toggle::make('is_default')
                        ->default(false),

                    Toggle::make('is_active')
                        ->default(true),
                ])
                ->columns(2),

            Section::make('Rules')
                ->schema([
                    KeyValue::make('rules')
                        ->keyLabel('Key')
                        ->valueLabel('Value'),
                ])
                ->collapsed(),

            Section::make('Metadata')
                ->schema([
                    KeyValue::make('metadata')
                        ->keyLabel('Key')
                        ->valueLabel('Value'),
                ])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_default')
                    ->boolean()
                    ->label('Default'),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
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
            Section::make('Template')
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('slug'),
                    TextEntry::make('description')
                        ->placeholder('—'),
                    TextEntry::make('is_default')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                    TextEntry::make('is_active')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                ])
                ->columns(2),

            Section::make('Rules')
                ->schema([
                    KeyValueEntry::make('rules'),
                ])
                ->collapsed(),

            Section::make('Metadata')
                ->schema([
                    KeyValueEntry::make('metadata'),
                ])
                ->collapsed(),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateCommissionTemplates::route('/'),
            'create' => CreateAffiliateCommissionTemplate::route('/create'),
            'view' => ViewAffiliateCommissionTemplate::route('/{record}'),
            'edit' => EditAffiliateCommissionTemplate::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.resources.navigation_sort.affiliate_commission_templates', 64);
    }
}
