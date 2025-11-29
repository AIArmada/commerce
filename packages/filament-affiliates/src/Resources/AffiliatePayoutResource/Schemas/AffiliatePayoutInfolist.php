<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class AffiliatePayoutInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payout')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('reference')
                            ->label('Reference')
                            ->copyable()
                            ->icon(Heroicon::OutlinedIdentification),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                        TextEntry::make('conversion_count')
                            ->label('Conversions')
                            ->badge()
                            ->color('info'),
                    ]),
                    Grid::make(2)->schema([
                        TextEntry::make('total_minor')
                            ->label('Total')
                            ->formatStateUsing(fn ($state, $record): string => sprintf(
                                '%s %.2f',
                                $record->currency,
                                $state / 100
                            ))
                            ->badge()
                            ->color('primary'),
                        TextEntry::make('currency')
                            ->label('Currency'),
                    ]),
                    Grid::make(2)->schema([
                        TextEntry::make('scheduled_at')->label('Scheduled')->dateTime(),
                        TextEntry::make('paid_at')->label('Paid')->dateTime(),
                    ]),
                ]),
            Section::make('Metadata')
                ->schema([
                    KeyValueEntry::make('metadata')
                        ->label('Metadata')
                        ->hidden(fn ($state): bool => empty($state ?? [])),
                ])
                ->collapsed(),
        ]);
    }
}
