<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class AffiliateFraudSignalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Signal Details')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('affiliate.name')
                            ->label('Affiliate')
                            ->placeholder('—'),

                        TextEntry::make('rule_code')
                            ->label('Rule')
                            ->badge()
                            ->placeholder('—'),

                        TextEntry::make('severity')
                            ->label('Severity')
                            ->badge(),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                    ]),
                ]),

            Section::make('Detection')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('risk_points')
                            ->label('Risk')
                            ->formatStateUsing(fn (int $state): string => $state . '%'),

                        TextEntry::make('detected_at')
                            ->label('Detected At')
                            ->dateTime(),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                ]),

            Section::make('Review Notes')
                ->schema([
                    TextEntry::make('evidence.review_notes')
                        ->label('Review Notes')
                        ->placeholder('—')
                        ->columnSpanFull(),

                    Grid::make(2)->schema([
                        TextEntry::make('reviewed_by')
                            ->label('Reviewed By')
                            ->placeholder('—'),

                        TextEntry::make('reviewed_at')
                            ->label('Reviewed At')
                            ->dateTime()
                            ->placeholder('—'),
                    ]),
                ]),
        ]);
    }
}
