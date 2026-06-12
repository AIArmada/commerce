<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Schemas\Components\Section;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Ysfkaya\FilamentPhoneInput\PhoneInputNumberType;

final class ContactMethodFormSchema
{
    /**
     * @return array<int, mixed>
     */
    public static function make(?bool $includeCountryCode = true): array
    {
        return [
            Section::make('Contact Method')
                ->schema(function () use ($includeCountryCode) {
                    $phoneTypes = ['phone', 'mobile', 'whatsapp'];

                    return [
                        Grid::make(2)->schema([
                            Select::make('type')
                                ->label('Type')
                                ->options(self::typeOptions())
                                ->required()
                                ->searchable()
                                ->native(false)
                                ->live(),

                            TextInput::make('label')
                                ->label('Label')
                                ->maxLength(255)
                                ->placeholder('Admin, Office, Support, PIC'),
                        ]),

                        TextInput::make('value')
                            ->label(fn (Get $get): string => in_array($get('type'), $phoneTypes) ? 'Phone Number' : 'Value')
                            ->required()
                            ->maxLength(2048)
                            ->placeholder(fn (Get $get): string => match ($get('type')) {
                                'email' => 'hello@example.com',
                                'phone', 'mobile', 'whatsapp' => '+60123456789',
                                'website' => 'https://example.com',
                                default => 'Enter value',
                            })
                            ->visible(fn (Get $get): bool => ! in_array($get('type'), $phoneTypes)),

                        PhoneInput::make('value')
                            ->label('Phone Number')
                            ->required()
                            ->inputNumberFormat(PhoneInputNumberType::E164)
                            ->displayNumberFormat(PhoneInputNumberType::INTERNATIONAL)
                            ->defaultCountry(config('contacting.defaults.country_code', 'MY'))
                            ->visible(fn (Get $get): bool => in_array($get('type'), $phoneTypes)),

                        Grid::make($includeCountryCode ? 3 : 2)->schema([
                            TextInput::make('country_code')
                                ->label('Country Code')
                                ->maxLength(2)
                                ->placeholder('MY')
                                ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : mb_strtoupper($state))
                                ->visible($includeCountryCode),

                            Toggle::make('is_primary')
                                ->label('Primary'),

                            Toggle::make('is_public')
                                ->label('Public')
                                ->default(true),
                        ]),
                    ];
                }),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function typeOptions(): array
    {
        return config('contacting.contact_methods.types', [
            'email' => 'Email',
            'phone' => 'Phone',
            'mobile' => 'Mobile',
            'whatsapp' => 'WhatsApp',
            'website' => 'Website',
            'telegram' => 'Telegram',
            'fax' => 'Fax',
            'other' => 'Other',
        ]);
    }
}
