<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

final class SocialProfileInfolistSchema
{
    /**
     * @return array<int, mixed>
     */
    public static function make(): array
    {
        return [
            Section::make('Social Profile')
                ->schema([
                    TextEntry::make('platform')
                        ->badge(),

                    TextEntry::make('label'),

                    TextEntry::make('handle'),

                    TextEntry::make('url')
                        ->url(fn (?string $state): ?string => $state)
                        ->visible(fn (?string $state): bool => $state !== null && $state !== ''),

                    TextEntry::make('display_name'),

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
                        ->formatStateUsing(function (?array $state): ?string {
                            if (empty($state)) {
                                return null;
                            }

                            return json_encode(
                                $state,
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                            ) ?: null;
                        })
                        ->visible(fn (?array $state): bool => ! empty($state)),
                ]),
        ];
    }
}
