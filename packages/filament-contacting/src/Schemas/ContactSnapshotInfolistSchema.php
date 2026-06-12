<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

final class ContactSnapshotInfolistSchema
{
    /**
     * @return array<int, mixed>
     */
    public static function make(): array
    {
        return [
            Section::make('Snapshot Info')
                ->schema([
                    TextEntry::make('snapshot_type')
                        ->badge()
                        ->label('Type'),

                    TextEntry::make('reason'),

                    TextEntry::make('snapshotable_type')
                        ->label('Source Entity Type'),

                    TextEntry::make('snapshotable_id')
                        ->label('Source Entity ID'),

                    TextEntry::make('channel'),

                    TextEntry::make('value'),

                    TextEntry::make('display_value'),

                    TextEntry::make('created_at')
                        ->dateTime(),
                ])->columns(2),

            Section::make('Stored Payload')
                ->schema([
                    TextEntry::make('payload')
                        ->json()
                        ->visible(fn (?array $state): bool => ! empty($state)),
                ]),

            Section::make('Metadata')
                ->schema([
                    TextEntry::make('metadata')
                        ->json()
                        ->visible(fn (?array $state): bool => ! empty($state)),
                ]),
        ];
    }
}
