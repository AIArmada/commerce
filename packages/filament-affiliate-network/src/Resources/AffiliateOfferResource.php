<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages\CreateAffiliateOffer;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages\EditAffiliateOffer;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages\ListAffiliateOffers;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
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

final class AffiliateOfferResource extends Resource
{
    protected static ?string $model = AffiliateOffer::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $navigationLabel = 'Offers';

    protected static ?string $modelLabel = 'Offer';

    protected static ?string $pluralModelLabel = 'Offers';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliate-network.navigation.group', 'Affiliate Network');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliate-network.navigation.sort', 50) + 1;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Offer Details')
                    ->schema([
                        Select::make('site_id')
                            ->label('Site')
                            ->options(fn () => AffiliateSite::query()
                                ->where('status', AffiliateSite::STATUS_VERIFIED)
                                ->pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Select::make('category_id')
                            ->label('Category')
                            ->options(fn () => AffiliateOfferCategory::query()
                                ->where('is_active', true)
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
                            ->maxLength(255),

                        Textarea::make('description')
                            ->maxLength(2000)
                            ->columnSpanFull(),

                        Textarea::make('terms')
                            ->label('Terms & Conditions')
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Commission')
                    ->schema([
                        Select::make('commission_type')
                            ->options([
                                'percentage' => 'Percentage',
                                'fixed' => 'Fixed Amount',
                            ])
                            ->required()
                            ->default('percentage'),

                        TextInput::make('commission_rate')
                            ->numeric()
                            ->required()
                            ->default(1000)
                            ->helperText('In basis points (1000 = 10%) or minor units for fixed'),

                        TextInput::make('currency')
                            ->maxLength(3)
                            ->placeholder('USD'),

                        TextInput::make('cookie_days')
                            ->label('Cookie Duration (days)')
                            ->numeric()
                            ->nullable()
                            ->placeholder('30'),
                    ])
                    ->columns(4),

                Section::make('Settings')
                    ->schema([
                        Select::make('status')
                            ->options([
                                AffiliateOffer::STATUS_DRAFT => 'Draft',
                                AffiliateOffer::STATUS_PENDING => 'Pending Review',
                                AffiliateOffer::STATUS_ACTIVE => 'Active',
                                AffiliateOffer::STATUS_PAUSED => 'Paused',
                                AffiliateOffer::STATUS_EXPIRED => 'Expired',
                                AffiliateOffer::STATUS_REJECTED => 'Rejected',
                            ])
                            ->required()
                            ->default(AffiliateOffer::STATUS_DRAFT),

                        Toggle::make('is_featured')
                            ->label('Featured')
                            ->default(false),

                        Toggle::make('is_public')
                            ->label('Public')
                            ->default(true)
                            ->helperText('Visible in marketplace'),

                        Toggle::make('requires_approval')
                            ->label('Requires Approval')
                            ->default(true)
                            ->helperText('Affiliates must apply to promote'),

                        TextInput::make('landing_url')
                            ->label('Landing Page URL')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),

                        DateTimePicker::make('starts_at')
                            ->nullable(),

                        DateTimePicker::make('ends_at')
                            ->nullable(),
                    ])
                    ->columns(4),

                Section::make('Advanced')
                    ->schema([
                        KeyValue::make('restrictions')
                            ->nullable()
                            ->columnSpanFull(),

                        KeyValue::make('metadata')
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('site.name')
                    ->label('Site')
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => AffiliateOffer::STATUS_DRAFT,
                        'warning' => AffiliateOffer::STATUS_PENDING,
                        'success' => AffiliateOffer::STATUS_ACTIVE,
                        'info' => AffiliateOffer::STATUS_PAUSED,
                        'danger' => fn (string $state): bool => in_array($state, [AffiliateOffer::STATUS_EXPIRED, AffiliateOffer::STATUS_REJECTED]),
                    ]),

                Tables\Columns\TextColumn::make('commission_rate')
                    ->label('Commission')
                    ->formatStateUsing(function (AffiliateOffer $record): string {
                        if ($record->commission_type === 'percentage') {
                            return number_format($record->commission_rate / 100, 2) . '%';
                        }

                        return '$' . number_format($record->commission_rate / 100, 2);
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('applications_count')
                    ->label('Applications')
                    ->counts('applications')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        AffiliateOffer::STATUS_DRAFT => 'Draft',
                        AffiliateOffer::STATUS_PENDING => 'Pending',
                        AffiliateOffer::STATUS_ACTIVE => 'Active',
                        AffiliateOffer::STATUS_PAUSED => 'Paused',
                        AffiliateOffer::STATUS_EXPIRED => 'Expired',
                        AffiliateOffer::STATUS_REJECTED => 'Rejected',
                    ]),

                Tables\Filters\SelectFilter::make('site_id')
                    ->label('Site')
                    ->relationship('site', 'name'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),

                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Public'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('activate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateOffer $record): bool => $record->status !== AffiliateOffer::STATUS_ACTIVE)
                    ->action(function (AffiliateOffer $record): void {
                        $scopedRecord = AffiliateOffer::query()
                            ->whereKey($record->getKey())
                            ->firstOrFail();

                        $scopedRecord->update(['status' => AffiliateOffer::STATUS_ACTIVE]);
                    }),
                Actions\Action::make('pause')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateOffer $record): bool => $record->status === AffiliateOffer::STATUS_ACTIVE)
                    ->action(function (AffiliateOffer $record): void {
                        $scopedRecord = AffiliateOffer::query()
                            ->whereKey($record->getKey())
                            ->firstOrFail();

                        $scopedRecord->update(['status' => AffiliateOffer::STATUS_PAUSED]);
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateOffers::route('/'),
            'create' => CreateAffiliateOffer::route('/create'),
            'edit' => EditAffiliateOffer::route('/{record}/edit'),
        ];
    }
}
