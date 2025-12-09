<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\CampaignResource\Schemas;

use AIArmada\FilamentVouchers\Support\OwnerTypeRegistry;
use AIArmada\Vouchers\Campaigns\Enums\CampaignObjective;
use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Enums\CampaignType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Relations\Relation;

final class CampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        $defaultCurrency = mb_strtoupper((string) config('filament-vouchers.default_currency', 'MYR'));
        $ownerRegistry = app(OwnerTypeRegistry::class);

        return $schema->schema([
            Wizard::make([
                Wizard\Step::make('Campaign Details')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Campaign Name')
                                ->required()
                                ->maxLength(255)
                                ->helperText('A descriptive name for this campaign')
                                ->live(onBlur: true)
                                ->afterStateUpdated(static function (?string $state, Set $set, Get $get): void {
                                    if ($state !== null && $get('slug') === null) {
                                        $set('slug', \Illuminate\Support\Str::slug($state));
                                    }
                                }),

                            TextInput::make('slug')
                                ->label('Slug')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true)
                                ->helperText('URL-friendly identifier'),
                        ]),

                        RichEditor::make('description')
                            ->label('Description')
                            ->helperText('Optional campaign description for internal use')
                            ->columnSpanFull(),

                        Grid::make(2)->schema([
                            Select::make('type')
                                ->label('Campaign Type')
                                ->required()
                                ->options(
                                    static fn (): array => collect(CampaignType::cases())
                                        ->mapWithKeys(static fn (CampaignType $type): array => [$type->value => $type->label()])
                                        ->toArray()
                                )
                                ->helperText('The category of this campaign'),

                            Select::make('objective')
                                ->label('Campaign Objective')
                                ->required()
                                ->options(
                                    static fn (): array => collect(CampaignObjective::cases())
                                        ->mapWithKeys(static fn (CampaignObjective $obj): array => [$obj->value => $obj->label()])
                                        ->toArray()
                                )
                                ->helperText('Primary goal of this campaign'),
                        ]),

                        Select::make('status')
                            ->label('Status')
                            ->options(
                                static fn (): array => collect(CampaignStatus::cases())
                                    ->mapWithKeys(static fn (CampaignStatus $status): array => [$status->value => $status->label()])
                                    ->toArray()
                            )
                            ->default(CampaignStatus::Draft->value)
                            ->required()
                            ->columnSpan(1),
                    ]),

                Wizard\Step::make('Schedule')
                    ->icon('heroicon-o-calendar')
                    ->schema([
                        Grid::make(3)->schema([
                            DateTimePicker::make('starts_at')
                                ->label('Start Date')
                                ->seconds(false)
                                ->helperText('When the campaign becomes active'),

                            DateTimePicker::make('ends_at')
                                ->label('End Date')
                                ->seconds(false)
                                ->after('starts_at')
                                ->helperText('When the campaign ends'),

                            Select::make('timezone')
                                ->label('Timezone')
                                ->options(
                                    static fn (): array => collect(timezone_identifiers_list())
                                        ->mapWithKeys(fn (string $tz): array => [$tz => $tz])
                                        ->toArray()
                                )
                                ->default('Asia/Kuala_Lumpur')
                                ->searchable()
                                ->helperText('Campaign schedule timezone'),
                        ]),
                    ]),

                Wizard\Step::make('Budget & Limits')
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('budget_cents')
                                ->label('Campaign Budget')
                                ->numeric()
                                ->suffix($defaultCurrency)
                                ->helperText('Maximum total spend for this campaign (leave empty for unlimited)')
                                ->formatStateUsing(
                                    fn (?int $state): ?string => $state !== null
                                    ? number_format($state / 100, 2, '.', '')
                                    : null
                                )
                                ->dehydrateStateUsing(
                                    fn (?string $state): ?int => $state !== null && $state !== ''
                                    ? (int) round((float) $state * 100)
                                    : null
                                ),

                            TextInput::make('max_redemptions')
                                ->label('Maximum Redemptions')
                                ->numeric()
                                ->minValue(1)
                                ->helperText('Total redemptions allowed (leave empty for unlimited)'),
                        ]),

                        Section::make('Current Usage')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('spent_cents')
                                        ->label('Amount Spent')
                                        ->suffix($defaultCurrency)
                                        ->disabled()
                                        ->formatStateUsing(
                                            fn (?int $state): string => $state !== null
                                            ? number_format($state / 100, 2, '.', '')
                                            : '0.00'
                                        )
                                        ->dehydrated(false),

                                    TextInput::make('current_redemptions')
                                        ->label('Current Redemptions')
                                        ->disabled()
                                        ->dehydrated(false),
                                ]),
                            ])
                            ->collapsed()
                            ->visible(fn ($record): bool => $record !== null),
                    ]),

                Wizard\Step::make('A/B Testing')
                    ->icon('heroicon-o-beaker')
                    ->schema([
                        Toggle::make('ab_testing_enabled')
                            ->label('Enable A/B Testing')
                            ->helperText('Split traffic between different voucher variants')
                            ->live()
                            ->columnSpanFull(),

                        Section::make('Variants')
                            ->description('Define test variants with different vouchers or configurations')
                            ->schema([
                                Repeater::make('variants_config')
                                    ->label('Test Variants')
                                    ->schema([
                                        Grid::make(4)->schema([
                                            TextInput::make('name')
                                                ->label('Variant Name')
                                                ->required()
                                                ->maxLength(100),

                                            TextInput::make('variant_code')
                                                ->label('Code')
                                                ->required()
                                                ->maxLength(20)
                                                ->helperText('A, B, C, etc.'),

                                            TextInput::make('traffic_percentage')
                                                ->label('Traffic %')
                                                ->numeric()
                                                ->minValue(0)
                                                ->maxValue(100)
                                                ->default(50)
                                                ->suffix('%'),

                                            Toggle::make('is_control')
                                                ->label('Control Group')
                                                ->inline(false),
                                        ]),
                                    ])
                                    ->columns(1)
                                    ->defaultItems(2)
                                    ->minItems(2)
                                    ->maxItems(5)
                                    ->reorderable(false)
                                    ->addActionLabel('Add Variant')
                                    ->helperText('Ensure traffic percentages sum to 100%'),
                            ])
                            ->visible(fn (Get $get): bool => (bool) $get('ab_testing_enabled')),
                    ]),

                Wizard\Step::make('Ownership')
                    ->icon('heroicon-o-user-group')
                    ->schema(static function () use ($ownerRegistry): array {
                        if (! $ownerRegistry->hasDefinitions()) {
                            return [
                                Textarea::make('no_owners')
                                    ->label('')
                                    ->disabled()
                                    ->default('No owner types configured. This campaign will be global.')
                                    ->columnSpanFull()
                                    ->dehydrated(false),
                            ];
                        }

                        return [
                            Grid::make(2)->schema([
                                Select::make('owner_type')
                                    ->label('Owner Type')
                                    ->options($ownerRegistry->options())
                                    ->placeholder('Global campaign (no owner)')
                                    ->live()
                                    ->helperText('Assign to a specific vendor or store')
                                    ->dehydrateStateUsing(
                                        static fn (?string $state): ?string => $state !== null && $state !== ''
                                        ? Relation::getMorphAlias($state)
                                        : null
                                    )
                                    ->afterStateHydrated(static function (?string $state, Set $set): void {
                                        if ($state !== null && $state !== '') {
                                            $set('owner_type', Relation::getMorphedModel($state));
                                        }
                                    }),

                                Select::make('owner_id')
                                    ->label('Owner')
                                    ->searchable()
                                    ->placeholder('Select owner')
                                    ->getSearchResultsUsing(static function (Get $get, ?string $search) use ($ownerRegistry): array {
                                        $ownerType = $get('owner_type');

                                        if (! is_string($ownerType) || $ownerType === '') {
                                            return [];
                                        }

                                        return $ownerRegistry->search($ownerType, $search);
                                    })
                                    ->getOptionLabelUsing(static function (Get $get, $value) use ($ownerRegistry): ?string {
                                        $ownerType = $get('owner_type');

                                        if (! is_string($ownerType) || $ownerType === '' || $value === null || $value === '') {
                                            return null;
                                        }

                                        return $ownerRegistry->resolveLabelForKey($ownerType, $value);
                                    })
                                    ->hidden(fn (Get $get): bool => ! is_string($get('owner_type')) || $get('owner_type') === '')
                                    ->dehydrated(fn (Get $get): bool => is_string($get('owner_type')) && $get('owner_type') !== ''),
                            ]),
                        ];
                    }),
            ])
                ->columnSpanFull()
                ->skippable(),
        ]);
    }
}
