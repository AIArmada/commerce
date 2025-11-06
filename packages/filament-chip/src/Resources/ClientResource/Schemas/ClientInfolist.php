<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\ClientResource\Schemas;

use AIArmada\FilamentChip\Models\ChipClient;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

final class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Client Summary')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('full_name')
                                ->label('Name')
                                ->icon(Heroicon::OutlinedUserCircle)
                                ->weight(FontWeight::SemiBold)
                                ->placeholder('—'),
                            TextEntry::make('email')
                                ->label('Email')
                                ->icon(Heroicon::OutlinedEnvelope)
                                ->copyable()
                                ->placeholder('—'),
                            TextEntry::make('phone')
                                ->label('Phone')
                                ->icon(Heroicon::OutlinedPhone)
                                ->placeholder('—'),
                        ]),
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('personal_code')
                                ->label('Personal Code')
                                ->icon(Heroicon::OutlinedIdentification)
                                ->placeholder('—'),
                            TextEntry::make('created_on')
                                ->label('Created')
                                ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                                ->icon(Heroicon::OutlinedClock)
                                ->placeholder('—'),
                            TextEntry::make('updated_on')
                                ->label('Updated')
                                ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                                ->icon(Heroicon::OutlinedClock)
                                ->placeholder('—'),
                        ]),
                ]),

            Section::make('Addresses')
                ->schema([
                    Fieldset::make('Billing')->inlineLabelled() // @phpstan-ignore method.notFound
                        ->schema([
                            TextEntry::make('street_address')
                                ->label('Street')
                                ->placeholder('—'),
                            TextEntry::make('city')
                                ->label('City')
                                ->placeholder('—'),
                            TextEntry::make('state')
                                ->label('State')
                                ->placeholder('—'),
                            TextEntry::make('zip_code')
                                ->label('Postal Code')
                                ->placeholder('—'),
                            TextEntry::make('country')
                                ->label('Country')
                                ->badge()
                                ->formatStateUsing(fn (?string $state): ?string => $state !== null && $state !== '' && $state !== '0' ? mb_strtoupper($state) : null)
                                ->placeholder('—'),
                        ]),
                    Fieldset::make('Shipping')->inlineLabelled() // @phpstan-ignore method.notFound
                        ->schema([
                            TextEntry::make('shipping_street_address')
                                ->label('Street')
                                ->placeholder('—'),
                            TextEntry::make('shipping_city')
                                ->label('City')
                                ->placeholder('—'),
                            TextEntry::make('shipping_state')
                                ->label('State')
                                ->placeholder('—'),
                            TextEntry::make('shipping_zip_code')
                                ->label('Postal Code')
                                ->placeholder('—'),
                            TextEntry::make('shipping_country')
                                ->label('Country')
                                ->badge()
                                ->formatStateUsing(fn (?string $state): ?string => $state !== null && $state !== '' && $state !== '0' ? mb_strtoupper($state) : null)
                                ->placeholder('—'),
                        ])
                        ->visible(fn (ChipClient $record): bool => filled(array_filter([
                            $record->shipping_street_address,
                            $record->shipping_city,
                            $record->shipping_state,
                            $record->shipping_zip_code,
                            $record->shipping_country,
                        ]))),
                ]),

            Section::make('Company & Banking')
                ->schema([
                    Fieldset::make('Company Profile')->inlineLabelled() // @phpstan-ignore method.notFound
                        ->schema([
                            TextEntry::make('legal_name')
                                ->label('Legal Name')
                                ->placeholder('—'),
                            TextEntry::make('brand_name')
                                ->label('Brand Name')
                                ->placeholder('—'),
                            TextEntry::make('registration_number')
                                ->label('Registration #')
                                ->placeholder('—'),
                            TextEntry::make('tax_number')
                                ->label('Tax #')
                                ->placeholder('—'),
                        ])
                        ->visible(fn (ChipClient $record): bool => filled(array_filter([
                            $record->legal_name,
                            $record->brand_name,
                            $record->registration_number,
                            $record->tax_number,
                        ]))),
                    Fieldset::make('Banking')->inlineLabelled() // @phpstan-ignore method.notFound
                        ->schema([
                            TextEntry::make('bank_account')
                                ->label('Bank Account')
                                ->copyable()
                                ->placeholder('—'),
                            TextEntry::make('bank_code')
                                ->label('Bank Code')
                                ->placeholder('—'),
                        ])
                        ->visible(fn (ChipClient $record): bool => filled(array_filter([
                            $record->bank_account,
                            $record->bank_code,
                        ]))),
                ])
                ->collapsible(),

            Section::make('Notification Emails')
                ->schema([
                    TextEntry::make('cc')
                        ->label('CC')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->formatStateUsing(fn ($state): ?string => self::formatEmails($state))
                        ->placeholder('—'),
                    TextEntry::make('bcc')
                        ->label('BCC')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->formatStateUsing(fn ($state): ?string => self::formatEmails($state))
                        ->placeholder('—'),
                ])
                ->collapsible()
                ->collapsed(fn (ChipClient $record): bool => array_filter([
                    $record->cc,
                    $record->bcc,
                ]) === []),
        ]);
    }

    private static function formatEmails(mixed $emails): ?string
    {
        if ($emails === null || $emails === '') {
            return null;
        }

        if (is_string($emails)) {
            $decoded = json_decode($emails, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $emails = $decoded;
            } else {
                $emails = preg_split('/\s*,\s*/', $emails) ?: [];
            }
        }

        if (! is_array($emails)) {
            return null;
        }

        return Collection::make($emails)
            ->filter(fn (?string $email): bool => filled($email))
            ->map(fn (string $email): string => mb_trim($email))
            ->implode(', ');
    }
}
