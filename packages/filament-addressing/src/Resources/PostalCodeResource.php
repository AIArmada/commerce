<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources;

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\PostalCode;
use AIArmada\Addressing\Rules\ValidPostalCode;
use AIArmada\Addressing\Support\ModelResolver;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages\CreatePostalCode;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages\EditPostalCode;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages\ListPostalCodes;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages\ViewPostalCode;
use AIArmada\FilamentAddressing\Tables\PostalCodeTable;
use BackedEnum;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Validation\ValidationRule;
use JsonException;

final class PostalCodeResource extends Resource
{
    protected static ?string $slug = 'postal-codes';

    protected static ?string $model = PostalCode::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-envelope';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-addressing.navigation.group');
    }

    public static function getNavigationIcon(): BackedEnum | string | null
    {
        return config('filament-addressing.navigation.icons.postal_codes', parent::getNavigationIcon());
    }

    public static function getNavigationLabel(): string
    {
        return 'Postcodes';
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-addressing.navigation.sort', 80) + 3;
    }

    public static function getModel(): string
    {
        return config('filament-addressing.resources.postal_codes.model', PostalCode::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-addressing.navigation.enabled', true);
    }

    public static function table(Table $table): Table
    {
        return PostalCodeTable::make($table);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Postcode')
                ->schema([
                    Select::make('country_code')
                        ->label('Country')
                        ->options(ModelResolver::countryClass()::query()->orderBy('name')->pluck('name', 'iso2')->toArray())
                        ->searchable()
                        ->required()
                        ->live()
                        ->disabled(static fn (?PostalCode $record): bool => $record !== null),
                    TextInput::make('code')->label('Postcode')->required()->maxLength(20)
                        ->rules(static fn (Get $get, ?PostalCode $record): array => $record === null
                            ? [new ValidPostalCode((string) $get('country_code'))]
                            : [])
                        ->disabled(static fn (?PostalCode $record): bool => $record !== null),
                    Toggle::make('is_active')->default(true),
                ])->columns(3),
            Section::make('Metadata')
                ->schema([
                    Textarea::make('metadata')
                        ->label('Metadata (JSON)')
                        ->json()
                        ->nullable()
                        ->rows(12)
                        ->helperText('Enter a JSON object, array, or null. Use null to clear metadata.')
                        ->formatStateUsing(static fn (?array $state): string => $state === null
                            ? 'null'
                            : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))
                        ->dehydrateStateUsing(static function (?string $state): mixed {
                            if ($state === null || mb_trim($state) === '') {
                                return null;
                            }

                            return json_decode($state, true, 512, JSON_THROW_ON_ERROR);
                        })
                        ->rules([
                            new class implements ValidationRule
                            {
                                public function validate(string $attribute, mixed $value, Closure $fail): void
                                {
                                    if ($value === null || $value === '') {
                                        return;
                                    }

                                    if (! is_string($value)) {
                                        $fail('Metadata must be a JSON object, array, or null.');

                                        return;
                                    }

                                    try {
                                        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                                    } catch (JsonException) {
                                        return;
                                    }

                                    if ($decoded !== null && ! is_array($decoded)) {
                                        $fail('Metadata must be a JSON object, array, or null.');
                                    }
                                }
                            },
                        ])
                        ->columnSpanFull(),
                ]),
            Section::make('Manual Coverage')
                ->description('Provider coverage is preserved. Changes here manage only manual links.')
                ->schema([
                    Repeater::make('manual_coverage')
                        ->schema([
                            Select::make('area_id')
                                ->label('Area')
                                ->searchable()
                                ->getSearchResultsUsing(static function (Get $get, ?string $search): array {
                                    $countryCode = $get('../../country_code');

                                    if (! is_string($countryCode) || mb_trim($countryCode) === '' || ! is_string($search) || mb_trim($search) === '') {
                                        return [];
                                    }

                                    return ModelResolver::areaClass()::query()
                                        ->where('country_code', mb_strtoupper(mb_trim($countryCode)))
                                        ->where('is_active', true)
                                        ->where('name', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_trim($search)) . '%')
                                        ->orderBy('name')
                                        ->limit(50)
                                        ->pluck('name', 'id')
                                        ->all();
                                })
                                ->getOptionLabelUsing(static function (Get $get, string | int | null $value): ?string {
                                    $countryCode = $get('../../country_code');

                                    if (! is_string($value) || ! is_string($countryCode)) {
                                        return null;
                                    }

                                    return ModelResolver::areaClass()::query()
                                        ->whereKey($value)
                                        ->where('country_code', mb_strtoupper(mb_trim($countryCode)))
                                        ->value('name');
                                })
                                ->required(),
                            TextInput::make('relationship_type')->default('served_by')->required()->maxLength(50),
                            Toggle::make('is_primary')->default(false),
                        ])
                        ->columns(3)
                        ->defaultItems(0),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Postcode')
                ->schema([
                    TextEntry::make('country_code')->badge(),
                    TextEntry::make('code'),
                    TextEntry::make('is_active')->badge(),
                ])->columns(3),
            Section::make('Coverage')
                ->schema([
                    RepeatableEntry::make('areas')
                        ->label('Served areas')
                        ->schema([
                            TextEntry::make('name')
                                ->label('Area')
                                ->url(static function (AddressArea $record): ?string {
                                    if (! config('filament-addressing.resources.areas.enabled', true)
                                        || ! AddressAreaResource::canView($record)) {
                                        return null;
                                    }

                                    return AddressAreaResource::getUrl('view', ['record' => $record]);
                                }),
                            TextEntry::make('pivot.source')->label('Source')->badge(),
                            TextEntry::make('pivot.source_id')->label('Source ID'),
                            TextEntry::make('pivot.relationship_type')->label('Relationship'),
                            TextEntry::make('pivot.is_primary')->label('Primary')->badge()
                                ->formatStateUsing(static fn (mixed $state): string => $state ? 'Primary' : 'Secondary'),
                            TextEntry::make('is_active')->label('Area Status')->badge()
                                ->color(static fn (mixed $state): string => $state ? 'success' : 'warning')
                                ->formatStateUsing(static fn (mixed $state): string => $state ? 'Active' : 'Inactive'),
                        ])
                        ->columns(6),
                    TextEntry::make('metadata')
                        ->label('Metadata')
                        ->formatStateUsing(static fn (?array $state): string => $state === null
                            ? '—'
                            : (json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}')),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        $pages = [
            'index' => ListPostalCodes::route('/'),
        ];

        if (! self::isReadOnly()) {
            $pages['create'] = CreatePostalCode::route('/create');
        }

        $pages['view'] = ViewPostalCode::route('/{record}');

        if (! self::isReadOnly()) {
            $pages['edit'] = EditPostalCode::route('/{record}/edit');
        }

        return $pages;
    }

    public static function isReadOnly(): bool
    {
        return (bool) config('filament-addressing.resources.postal_codes.read_only', false);
    }
}
