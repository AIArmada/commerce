<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Pages\CreateAffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Pages\EditAffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Pages\ListAffiliateSites;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

final class AffiliateSiteResource extends Resource
{
    protected static ?string $model = AffiliateSite::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Sites';

    protected static ?string $modelLabel = 'Site';

    protected static ?string $pluralModelLabel = 'Sites';

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliate-network.navigation.group', 'Affiliate Network');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliate-network.navigation.sort', 50);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Site Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('domain')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Enter the domain without http:// or https://'),

                        Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Status')
                    ->schema([
                        Select::make('status')
                            ->options([
                                AffiliateSite::STATUS_PENDING => 'Pending',
                                AffiliateSite::STATUS_VERIFIED => 'Verified',
                                AffiliateSite::STATUS_SUSPENDED => 'Suspended',
                                AffiliateSite::STATUS_REJECTED => 'Rejected',
                            ])
                            ->required()
                            ->default(AffiliateSite::STATUS_PENDING),

                        Select::make('verification_method')
                            ->options([
                                'dns' => 'DNS TXT Record',
                                'meta_tag' => 'HTML Meta Tag',
                                'file' => 'Verification File',
                            ])
                            ->nullable(),

                        DateTimePicker::make('verified_at')
                            ->nullable()
                            ->disabled(),
                    ])
                    ->columns(3),

                Section::make('Settings')
                    ->schema([
                        KeyValue::make('settings')
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

                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => AffiliateSite::STATUS_PENDING,
                        'success' => AffiliateSite::STATUS_VERIFIED,
                        'danger' => fn (string $state): bool => in_array($state, [AffiliateSite::STATUS_SUSPENDED, AffiliateSite::STATUS_REJECTED]),
                    ]),

                Tables\Columns\TextColumn::make('offers_count')
                    ->label('Offers')
                    ->counts('offers')
                    ->sortable(),

                Tables\Columns\TextColumn::make('verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        AffiliateSite::STATUS_PENDING => 'Pending',
                        AffiliateSite::STATUS_VERIFIED => 'Verified',
                        AffiliateSite::STATUS_SUSPENDED => 'Suspended',
                        AffiliateSite::STATUS_REJECTED => 'Rejected',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateSite $record): bool => $record->isPending())
                    ->action(function (AffiliateSite $record): void {
                        $record->update([
                            'status' => AffiliateSite::STATUS_VERIFIED,
                            'verified_at' => now(),
                        ]);
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
            'index' => ListAffiliateSites::route('/'),
            'create' => CreateAffiliateSite::route('/create'),
            'edit' => EditAffiliateSite::route('/{record}/edit'),
        ];
    }
}
