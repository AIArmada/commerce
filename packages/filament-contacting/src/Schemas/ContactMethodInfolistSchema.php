<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Ysfkaya\FilamentPhoneInput\Infolists\PhoneEntry;
use Ysfkaya\FilamentPhoneInput\PhoneInputNumberType;

final class ContactMethodInfolistSchema
{
    /**
     * @return array<int, mixed>
     */
    public static function make(): array
    {
        return [
            Section::make('Contact Method')
                ->schema([
                    TextEntry::make('type')
                        ->badge(),

                    TextEntry::make('label'),

                    PhoneEntry::make('normalized_value')
                        ->displayFormat(PhoneInputNumberType::INTERNATIONAL)
                        ->countryColumn('country_code')
                        ->label('Phone')
                        ->visible(fn (?string $state): bool => $state !== null && $state !== ''),

                    TextEntry::make('value'),

                    TextEntry::make('country_code'),

                    IconEntry::make('is_primary')
                        ->boolean()
                        ->label('Primary'),

                    IconEntry::make('is_public')
                        ->boolean()
                        ->label('Public'),

                    IconEntry::make('is_verified')
                        ->boolean()
                        ->label('Verified'),

                    TextEntry::make('verified_at')
                        ->dateTime()
                        ->visible(fn (?string $state): bool => $state !== null),

                    TextEntry::make('created_at')
                        ->dateTime(),

                    TextEntry::make('updated_at')
                        ->dateTime(),
                ])->columns(2),

            Section::make('Metadata')
                ->schema([
                    TextEntry::make('metadata')
                        ->json()
                        ->visible(fn (?array $state): bool => ! empty($state)),
                ]),
        ];
    }
}
