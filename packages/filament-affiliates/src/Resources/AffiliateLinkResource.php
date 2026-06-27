<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource\Pages\CreateAffiliateLink;
use AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource\Pages\EditAffiliateLink;
use AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource\Pages\ListAffiliateLinks;
use AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource\Pages\ViewAffiliateLink;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class AffiliateLinkResource extends Resource
{
    protected static ?string $model = AffiliateLink::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Affiliate Links';

    protected static ?string $modelLabel = 'Affiliate Link';

    protected static ?string $pluralModelLabel = 'Affiliate Links';

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
        /** @var Builder<AffiliateLink> $query */
        $query = parent::getEloquentQuery()->with(['affiliate', 'program']);

        /** @var Builder<Model> $modelQuery */
        $modelQuery = $query;

        return OwnerUiScope::apply($modelQuery, includeGlobal: false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Link Details')
                ->schema([
                    Forms\Components\Select::make('affiliate_id')
                        ->relationship('affiliate', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('program_id')
                        ->relationship('program', 'name')
                        ->searchable()
                        ->preload(),

                    Forms\Components\TextInput::make('destination_url')
                        ->url()
                        ->required()
                        ->maxLength(2048),

                    Forms\Components\TextInput::make('tracking_url')
                        ->url()
                        ->required()
                        ->maxLength(2048),

                    Forms\Components\TextInput::make('short_url')
                        ->url()
                        ->maxLength(2048),

                    Forms\Components\TextInput::make('custom_slug')
                        ->maxLength(255),
                ])
                ->columns(2),

            Section::make('Campaign')
                ->schema([
                    Forms\Components\TextInput::make('campaign')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('sub_id')
                        ->label('Sub ID')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('sub_id_2')
                        ->label('Sub ID 2')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('sub_id_3')
                        ->label('Sub ID 3')
                        ->maxLength(255),

                    Forms\Components\DateTimePicker::make('deactivated_at')
                        ->label('Deactivated At')
                        ->helperText('Leave empty for active links, set a date to deactivate')
                        ->native(false),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('affiliate.code')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('program.name')
                    ->label('Program')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('campaign')
                    ->searchable(),

                Tables\Columns\TextColumn::make('destination_url')
                    ->label('Destination')
                    ->limit(40)
                    ->tooltip(fn (AffiliateLink $record): string => $record->destination_url),

                Tables\Columns\TextColumn::make('tracking_url')
                    ->label('Tracking')
                    ->limit(40)
                    ->tooltip(fn (AffiliateLink $record): string => $record->tracking_url),

                Tables\Columns\TextColumn::make('clicks')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('conversions')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('deactivated_at')
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? 'Active' : 'Inactive')
                    ->badge()
                    ->color(fn (?string $state): string => $state === null ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'active') {
                            return $query->whereNull('deactivated_at');
                        }
                        if ($data['value'] === 'inactive') {
                            return $query->whereNotNull('deactivated_at');
                        }

                        return $query;
                    }),
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
            Section::make('Link Details')
                ->schema([
                    TextEntry::make('destination_url')
                        ->label('Destination URL'),
                    TextEntry::make('tracking_url')
                        ->label('Tracking URL'),
                    TextEntry::make('short_url')
                        ->label('Short URL')
                        ->placeholder('—'),
                    TextEntry::make('campaign')
                        ->label('Campaign')
                        ->placeholder('—'),
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
            'index' => ListAffiliateLinks::route('/'),
            'create' => CreateAffiliateLink::route('/create'),
            'view' => ViewAffiliateLink::route('/{record}'),
            'edit' => EditAffiliateLink::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.resources.navigation_sort.affiliate_links', 65);
    }
}
