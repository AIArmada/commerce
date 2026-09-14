<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Schemas;

use AIArmada\Contacting\Enums\ContactMethodType;
use AIArmada\Contacting\Enums\ContactPurpose;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

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
                    $configuredTypes = array_keys(ContactMethodType::options(config('contacting.contact_methods.types', [])));
                    $phoneTypes = array_values(array_intersect(['phone', 'mobile', 'whatsapp'], $configuredTypes));

                    return [
                        Grid::make(3)->schema([
                            Select::make('type')
                                ->label('Type')
                                ->options(ContactMethodType::options(config('contacting.contact_methods.types', [])))
                                ->required()
                                ->searchable()
                                ->native(false)
                                ->live(),

                            Select::make('purpose')
                                ->label('Purpose')
                                ->options(ContactPurpose::options())
                                ->default(ContactPurpose::General->value)
                                ->searchable()
                                ->native(false),

                            TextInput::make('label')
                                ->label('Label')
                                ->maxLength(255)
                                ->placeholder('Admin, Office, Support, PIC'),
                        ]),

                        TextInput::make('value')
                            ->label(fn (Get $get): string => in_array($get('type'), $phoneTypes, true) ? 'Phone Number' : 'Value')
                            ->required()
                            ->maxLength(2048)
                            ->tel(fn (Get $get): bool => in_array($get('type'), $phoneTypes, true))
                            ->email(fn (Get $get): bool => $get('type') === 'email')
                            ->rules([
                                fn (Get $get): callable => $get('type') === 'website'
                                    ? function (string $attribute, mixed $value, callable $fail): void {
                                        if (! is_string($value) || (! filter_var($value, FILTER_VALIDATE_URL) && ! filter_var('https://' . $value, FILTER_VALIDATE_URL))) {
                                            $fail('The :attribute must be a valid URL.');
                                        }
                                    }
                                    : function (string $attribute, mixed $value, callable $fail): void {},
                            ])
                            ->placeholder(fn (Get $get): string => match ($get('type')) {
                                'email' => 'hello@example.com',
                                'phone', 'mobile', 'whatsapp' => '+60123456789',
                                'website' => 'https://example.com',
                                default => 'Enter value',
                            }),

                        Grid::make($includeCountryCode ? 3 : 2)->schema([
                            TextInput::make('country_code')
                                ->label('Country Code')
                                ->maxLength(2)
                                ->rule('nullable')
                                ->rule('alpha')
                                ->placeholder('MY')
                                ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : mb_strtoupper($state))
                                ->visible($includeCountryCode),

                            Toggle::make('is_primary')
                                ->label('Primary'),

                            Toggle::make('is_public')
                                ->label('Public')
                                ->default((bool) config('contacting.defaults.public_by_default', true)),
                        ]),
                    ];
                }),
        ];
    }
}
