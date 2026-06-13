<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Schemas;

use AIArmada\Contacting\Enums\ContactMethodType;
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
                    $phoneTypes = ['phone', 'mobile', 'whatsapp'];

                    return [
                        Grid::make(2)->schema([
                            Select::make('type')
                                ->label('Type')
                                ->options(ContactMethodType::options(config('contacting.contact_methods.types', [])))
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
                            ->label(fn (Get $get): string => in_array($get('type'), $phoneTypes, true) ? 'Phone Number' : 'Value')
                            ->required()
                            ->maxLength(2048)
                            ->tel(fn (Get $get): bool => in_array($get('type'), $phoneTypes, true))
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
