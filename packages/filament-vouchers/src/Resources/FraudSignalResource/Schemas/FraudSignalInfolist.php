<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\FraudSignalResource\Schemas;

use AIArmada\Vouchers\Fraud\Enums\FraudRiskLevel;
use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;
use AIArmada\Vouchers\Fraud\Models\VoucherFraudSignal;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class FraudSignalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Signal Details')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('voucher_code')
                                ->label('Voucher Code')
                                ->copyable()
                                ->weight('bold'),

                            TextEntry::make('signal_type')
                                ->label('Signal Type')
                                ->badge()
                                ->color('info')
                                ->formatStateUsing(static fn (FraudSignalType|string $state): string => $state instanceof FraudSignalType ? $state->getLabel() : FraudSignalType::from($state)->getLabel()),

                            TextEntry::make('risk_level')
                                ->label('Risk Level')
                                ->badge()
                                ->color(static fn (FraudRiskLevel|string $state): string => match ($state instanceof FraudRiskLevel ? $state : FraudRiskLevel::from($state)) {
                                    FraudRiskLevel::Low => 'success',
                                    FraudRiskLevel::Medium => 'warning',
                                    FraudRiskLevel::High => 'danger',
                                    FraudRiskLevel::Critical => 'danger',
                                })
                                ->formatStateUsing(static fn (FraudRiskLevel|string $state): string => $state instanceof FraudRiskLevel ? $state->getLabel() : FraudRiskLevel::from($state)->getLabel()),
                        ]),

                    Grid::make(3)
                        ->schema([
                            TextEntry::make('score')
                                ->label('Fraud Score')
                                ->formatStateUsing(static fn (float $state): string => number_format($state * 100, 1).'%'),

                            TextEntry::make('detector')
                                ->label('Detector')
                                ->badge()
                                ->color('gray'),

                            IconEntry::make('was_blocked')
                                ->label('Blocked')
                                ->boolean(),
                        ]),

                    TextEntry::make('message')
                        ->label('Message')
                        ->columnSpanFull(),
                ]),

            Section::make('Context')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('ip_address')
                                ->label('IP Address')
                                ->copyable(),

                            TextEntry::make('user_id')
                                ->label('User ID')
                                ->placeholder('Unknown'),

                            TextEntry::make('device_fingerprint')
                                ->label('Device Fingerprint')
                                ->placeholder('None')
                                ->limit(20),
                        ]),

                    KeyValueEntry::make('context')
                        ->label('Additional Context')
                        ->columnSpanFull(),
                ])
                ->collapsible(),

            Section::make('Review Status')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            IconEntry::make('reviewed')
                                ->label('Reviewed')
                                ->boolean(),

                            TextEntry::make('reviewed_by')
                                ->label('Reviewed By')
                                ->placeholder('Not reviewed'),

                            TextEntry::make('reviewed_at')
                                ->label('Reviewed At')
                                ->dateTime()
                                ->placeholder('Not reviewed'),
                        ]),

                    TextEntry::make('review_notes')
                        ->label('Review Notes')
                        ->columnSpanFull()
                        ->placeholder('No notes'),
                ])
                ->hidden(fn (VoucherFraudSignal $record): bool => ! $record->reviewed),

            Section::make('Metadata')
                ->schema([
                    KeyValueEntry::make('metadata')
                        ->label('Metadata'),
                ])
                ->collapsed()
                ->hidden(fn (VoucherFraudSignal $record): bool => empty($record->metadata)),
        ]);
    }
}
