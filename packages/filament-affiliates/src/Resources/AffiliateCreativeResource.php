<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Models\AffiliateProgramCreative;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

final class AffiliateCreativeResource extends Resource
{
    protected static ?string $model = AffiliateProgramCreative::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    protected static ?string $navigationLabel = 'Marketing Materials';

    protected static ?string $slug = 'affiliate-creatives';

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
        /** @var Builder<AffiliateProgramCreative> $query */
        $query = parent::getEloquentQuery();

        return $query->general();
    }

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-photo';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.resources.navigation_sort.affiliate_creatives', 64);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Creative Details')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Select::make('type')
                        ->options([
                            'banner' => 'Banner',
                            'text_link' => 'Text Link',
                            'image' => 'Image',
                            'video' => 'Video',
                            'document' => 'Document',
                            'email' => 'Email Template',
                        ])
                        ->required()
                        ->default('banner'),

                    Textarea::make('description')
                        ->rows(3),
                ])
                ->columns(2),

            Section::make('Asset File')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('asset')
                        ->collection('creative_asset')
                        ->maxSize(51200)
                        ->label('Upload File')
                        ->helperText('Upload an image, video, PDF, or ZIP file. Max 50MB.'),

                    TextInput::make('asset_url')
                        ->label('Asset URL (or leave blank if uploading)')
                        ->url()
                        ->maxLength(2048),

                    TextInput::make('width')
                        ->label('Width (px)')
                        ->numeric()
                        ->nullable(),

                    TextInput::make('height')
                        ->label('Height (px)')
                        ->numeric()
                        ->nullable(),
                ])
                ->columns(2),

            Section::make('Tracking')
                ->schema([
                    TextInput::make('destination_url')
                        ->label('Destination URL')
                        ->url()
                        ->required()
                        ->helperText('Where the affiliate link should point to.')
                        ->maxLength(2048),

                    TextInput::make('tracking_code')
                        ->label('Tracking Code Identifier')
                        ->maxLength(255)
                        ->default(fn (): string => mb_strtoupper(Str::random(8))),
                ])
                ->columns(2),

            Section::make('Metadata')
                ->schema([
                    KeyValue::make('metadata')
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->columnSpanFull(),
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

                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('asset_url')
                    ->label('Asset')
                    ->limit(40)
                    ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true),

                TextColumn::make('destination_url')
                    ->label('Destination')
                    ->limit(40)
                    ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true),

                TextColumn::make('tracking_code')
                    ->label('Tracking Code')
                    ->copyable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'banner' => 'Banner',
                        'text_link' => 'Text Link',
                        'image' => 'Image',
                        'video' => 'Video',
                        'document' => 'Document',
                        'email' => 'Email Template',
                    ]),
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

    public static function getPages(): array
    {
        return [
            'index' => AffiliateCreativeResource\Pages\ListAffiliateCreatives::route('/'),
            'create' => AffiliateCreativeResource\Pages\CreateAffiliateCreative::route('/create'),
            'edit' => AffiliateCreativeResource\Pages\EditAffiliateCreative::route('/{record}/edit'),
        ];
    }
}
