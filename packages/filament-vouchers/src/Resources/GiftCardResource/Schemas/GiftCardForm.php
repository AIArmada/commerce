<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\GiftCardResource\Schemas;

use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

final class GiftCardForm
{
    public static function configure(Schema $schema): Schema
    {
        $currencyOptions = [
            'MYR' => 'MYR',
            'USD' => 'USD',
            'SGD' => 'SGD',
            'IDR' => 'IDR',
        ];

        $defaultCurrency = mb_strtoupper((string) config('filament-vouchers.default_currency', 'MYR'));
        $currencyOptions[$defaultCurrency] = $defaultCurrency;

        return $schema->schema([
            Section::make('Gift Card Details')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('code')
                                ->label('Code')
                                ->maxLength(64)
                                ->unique(ignoreRecord: true)
                                ->helperText('Leave blank to auto-generate a unique code')
                                ->afterStateUpdated(static function (?string $state, Set $set): void {
                                    if ($state !== null) {
                                        $set('code', mb_strtoupper($state));
                                    }
                                }),

                            Select::make('type')
                                ->label('Type')
                                ->required()
                                ->options(
                                    static fn (): array => collect(GiftCardType::cases())
                                        ->mapWithKeys(static fn (GiftCardType $type): array => [$type->value => $type->label()])
                                        ->toArray()
                                )
                                ->default(GiftCardType::Standard->value),
                        ]),

                    Grid::make(3)
                        ->schema([
                            TextInput::make('initial_balance')
                                ->label('Initial Balance')
                                ->numeric()
                                ->minValue(0.01)
                                ->required()
                                ->suffix(fn (Get $get): string => $get('currency') ?? $defaultCurrency)
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

                            TextInput::make('current_balance')
                                ->label('Current Balance')
                                ->numeric()
                                ->suffix(fn (Get $get): string => $get('currency') ?? $defaultCurrency)
                                ->formatStateUsing(
                                    fn (?int $state): ?string => $state !== null
                                    ? number_format($state / 100, 2, '.', '')
                                    : null
                                )
                                ->dehydrateStateUsing(
                                    fn (?string $state): ?int => $state !== null && $state !== ''
                                    ? (int) round((float) $state * 100)
                                    : null
                                )
                                ->disabled()
                                ->dehydrated(false)
                                ->visibleOn(['view', 'edit']),

                            Select::make('currency')
                                ->label('Currency')
                                ->required()
                                ->options($currencyOptions)
                                ->default($defaultCurrency),
                        ]),

                    TextInput::make('pin')
                        ->label('PIN')
                        ->password()
                        ->revealable()
                        ->maxLength(20)
                        ->helperText('Optional PIN for additional security'),
                ]),

            Section::make('Status & Scheduling')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Select::make('status')
                                ->label('Status')
                                ->options(
                                    static fn (): array => collect(GiftCardStatus::cases())
                                        ->mapWithKeys(static fn (GiftCardStatus $status): array => [$status->value => $status->label()])
                                        ->toArray()
                                )
                                ->default(GiftCardStatus::Inactive->value)
                                ->required(),

                            DateTimePicker::make('activated_at')
                                ->label('Activated At')
                                ->seconds(false)
                                ->disabled()
                                ->visibleOn(['view', 'edit']),

                            DateTimePicker::make('expires_at')
                                ->label('Expires At')
                                ->seconds(false),
                        ]),
                ]),

            Section::make('Metadata')
                ->schema([
                    KeyValue::make('metadata')
                        ->label('Metadata')
                        ->helperText('Attach arbitrary key-value pairs')
                        ->keyLabel('Key')
                        ->valueLabel('Value'),
                ])
                ->collapsed(),
        ]);
    }
}
